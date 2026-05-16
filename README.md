# Approval Binary

Approval Binary is a Laravel package for building approval workflow engines with binary/bitmask state tracking.

It is not a CRUD approval scaffold and it does not ship a UI builder. The package focuses on reusable workflow orchestration: define approval blueprints in database records, resolve contributors at runtime, clone the selected blueprint into an execution event, and track approval progress with bitwise masks.

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Why Binary Approval](#why-binary-approval)
- [Architecture](#architecture)
- [Runtime Lifecycle](#runtime-lifecycle)
- [Dynamic Contributors](#dynamic-contributors)
- [Condition Routing](#condition-routing)
- [Using the Package](#using-the-package)
- [Workflow Behavior](#workflow-behavior)
- [Use Cases](#use-cases)
- [Technical Philosophy](#technical-philosophy)

## Requirements

- PHP 8.1+
- Laravel 10.x / 11.x / 12.x / 13.x

## Installation

```bash
composer require menma/approval-binary
```

Publish package assets:

```bash
php artisan vendor:publish --tag=approval-migrations
php artisan vendor:publish --tag=approval-config
php artisan vendor:publish --tag=approval-lang
```

Run migrations:

```bash
php artisan migrate
```

## Why Binary Approval

Approval Binary uses bitwise state representation, inspired by the same idea behind Linux permission masks. Instead of storing approval progress only as rows or a single status string, each workflow component contributes one bit to a bigint approval mask.

Example:

| Approval component | Component position | Runtime mask value |
|--------------------|--------------------|--------------------|
| HR                 | `0`                | `1 << 0` = `1`     |
| Manager            | `1`                | `1 << 1` = `2`     |
| Finance            | `2`                | `1 << 2` = `4`     |
| Director           | `3`                | `1 << 3` = `8`     |

The runtime event stores two masks:

- `target`: all approval bits required for this event.
- `step`: approval bits already completed.

If HR and Manager are required, `target = 3` (`0011`). If only HR has approved, `step = 1` (`0001`). When `(step & target) === target`, the event is approved.

This gives compact partial-state representation:

```text
target = 15  // 1111, HR + Manager + Finance + Director required
step   =  5  // 0101, HR + Finance already approved
```

Because masks are stored as bigint values, a workflow can support deeper approval layers than a small integer column. The practical maximum is still bounded by PHP integer and database signed-bigint limits, so component positions should stay within that safe numeric range.

## Architecture

The package separates blueprint configuration from runtime execution.

### Blueprint Structure

```text
ApprovalDictionary
└── ApprovalFlowComponent (bridge)
    ├── key = model morph class
    ├── approval_dictionary_id
    └── approval_flow_id

ApprovalFlow
└── Approval
    ├── ApprovalComponent
    │   └── ApprovalContributor
    └── ApprovalCondition
        └── ApprovalConditionComponent
            └── ApprovalComponent
```

Core blueprint concepts:

- `ApprovalDictionary` registers an approvable model class or morph key.
- `ApprovalFlowComponent` bridges the registered model key to a flow.
- `ApprovalFlow` groups one or more approval definitions.
- `Approval` is the workflow blueprint/template used to generate runtime events.
- `ApprovalComponent` is a logical approval unit, such as HR approval, manager approval, or finance approval.
- `ApprovalContributor` defines who can approve a component, either as a direct user or a dynamic contributor source.
- `ApprovalCondition` groups contextual routing rules.
- `ApprovalConditionComponent` links a condition to the components selected when its expression matches.
- `ApprovalComponent.step` is a bit position (`0, 1, 2...`), not the runtime mask value. The runtime mask is calculated with `1 << step`.

### Runtime Structure

```text
ApprovalEvent
└── ApprovalEventComponent
    └── ApprovalEventContributor
```

Runtime concepts:

- `ApprovalEvent` is the execution instance for one approvable model record.
- `ApprovalEventComponent` is a snapshot of a selected `ApprovalComponent`.
- `ApprovalEventContributor` is the resolved runtime user allowed to approve/reject/cancel that event component.

When a workflow starts, the package does not execute the blueprint directly. It clones the selected approval structure into event tables. That makes an event stable: later blueprint or condition changes do not mutate a running approval. `rollback()` intentionally re-runs condition routing and contributor resolution from the current blueprint and current model condition data.

## Runtime Lifecycle

Typical runtime path:

```text
$model->initEvent($user)
└── BinaryService::store()
    └── EventStoreService::store()
        ├── find model flow by morph key
        ├── find the first approval blueprint for that flow
        ├── resolve conditions
        ├── clone selected components into event components
        ├── resolve contributors into event contributors
        └── calculate target mask
```

Action path:

```text
$model->approve($user)
└── BinaryService::approve()
    └── EventActionService::approve()
        ├── find or create event
        ├── find target event component
        ├── validate contributor
        ├── mark contributor/component approved
        └── update event step/status mask
```

Supported event statuses:

- `DRAFT`
- `APPROVED`
- `REJECTED`
- `CANCELED`
- `ROLLBACK`

Runtime hooks are available on the approvable model:

- `onApprove(ApprovalEvent $event)`
- `onReject(ApprovalEvent $event)`
- `onCancel(ApprovalEvent $event)`
- `onRollback(ApprovalEvent $event)`
- `onForce(ApprovalEvent $event)`

These hooks are the intended integration point for application-side listeners, notifications, domain events, or status synchronization. The package itself uses runtime event records and audit observers; it does not ship Laravel event/listener classes for approval actions. If an application needs Laravel events, dispatch them from these hooks.

## Dynamic Contributors

Contributors are not hardcoded as users only.

An `ApprovalContributor` stores:

- `approval_component_id`
- `approvable_type`
- `approvable_id`

At runtime, contributor resolution works like this:

```text
ApprovalContributor
├── approvable_type is registered in config('approval.group')
│   └── load that model and call getApproverIds()
└── approvable_type is not registered
    └── treat approvable_id as direct user id
```

For direct users, store the configured user model class in `approvable_type` and the user id in `approvable_id`. Because the user model class is normally not registered in `config('approval.group')`, the runtime resolver treats `approvable_id` as the concrete user id.

This allows multiple contributor sources:

- direct user id
- role model
- department model
- position model
- approval group
- custom resolver model
- any configurable source implementing `ApprovalContributorInterface`

Example dynamic source:

```php
use Illuminate\Database\Eloquent\Model;
use Menma\Approval\Interfaces\ApprovalContributorInterface;

class Department extends Model implements ApprovalContributorInterface
{
    public function getApproverIds(): array
    {
        return $this->managers()->pluck('users.id')->all();
    }
}
```

Register it in `config/approval.php`:

```php
'group' => [
    Menma\Approval\Models\ApprovalGroup::class,
    App\Models\Department::class,
    App\Models\Position::class,
],
```

Then use the model as an approval contributor:

```php
ApprovalContributor::create([
    'approval_component_id' => $component->id,
    'approvable_type' => App\Models\Department::class,
    'approvable_id' => $department->id,
]);
```

When the event is initialized, the department is resolved into concrete `ApprovalEventContributor` users.

## Condition Routing

The condition system controls which components are copied into the runtime event. This is how the engine supports jump/skip flows and contextual routing.

Structure:

```text
Approval
└── ApprovalCondition
    └── ApprovalConditionComponent
        └── ApprovalComponent
```

Rules:

- Conditions are evaluated by highest `priority` first.
- `priority = 0` is the default fallback condition and is created automatically when an approval is created.
- A condition can contain multiple condition components.
- Each condition component points to one approval component.
- `expression = null` means the component always matches for that condition.
- `expression` supports `all` (AND) and `any` (OR) routing logic.
- Expression paths use Laravel `data_get` dot notation against `getApprovalConditions()`.
- The first condition that produces at least one matched component wins.
- Invalid expression structure or unsupported operators fail loudly with validation errors.

Condition `all`/`any` is only for routing expressions. Component contributor approval logic is separate and uses `ContributorTypeEnum::AND` or `ContributorTypeEnum::OR`.

Expression example:

```php
use Menma\Approval\Support\ApprovalExpression;

ApprovalExpression::all()
    ->where('requester.division', '==', 'HR')
    ->where('amount', '>', 10000)
    ->toArray();
```

This produces JSON-like data:

```php
[
    'all' => [
        ['path' => 'requester.division', 'operator' => '==', 'value' => 'HR'],
        ['path' => 'amount', 'operator' => '>', 'value' => 10000],
    ],
]
```

Supported operators are configured in `config('approval.operators')`:

```php
['<', '>', '<=', '>=', '==', '!=']
```

### Contextual Routing Example

Requirement: if requester division is HR, skip directly to manager approval. Otherwise use the default approval path.

```text
Approval: Operational Request
├── Condition priority 1: requester.division == HR
│   └── Manager Approval
└── Condition priority 0: default fallback
    ├── HR Approval
    ├── Manager Approval
    └── Finance Approval
```

Implementation shape:

```php
use Menma\Approval\Models\ApprovalCondition;
use Menma\Approval\Models\ApprovalConditionComponent;
use Menma\Approval\Support\ApprovalExpression;

$hrShortcut = ApprovalCondition::create([
    'approval_id' => $approval->id,
    'priority' => 1,
]);

ApprovalConditionComponent::create([
    'approval_condition_id' => $hrShortcut->id,
    'approval_component_id' => $managerComponent->id,
    'expression' => ApprovalExpression::all()
        ->where('requester.division', '==', 'HR')
        ->toArray(),
]);
```

The default condition (`priority = 0`) already links new approval components automatically with `expression = null`. If no higher-priority condition selects components, the resolver falls back to the default path.

## Using the Package

### Prepare an App Model

Extend `ApprovalAbstract` on models that need approval behavior.

```php
namespace App\Models;

use Menma\Approval\Abstracts\ApprovalAbstract;
use Menma\Approval\Models\ApprovalEvent;

class PurchaseOrder extends ApprovalAbstract
{
    protected $guarded = [];

    public function getApprovalConditions(): array
    {
        return [
            'amount' => $this->amount,
            'requester' => [
                'division' => $this->requester?->division?->name,
                'position' => $this->requester?->position?->name,
            ],
        ];
    }

    protected function onApprove(ApprovalEvent $event): void
    {
        $this->update(['approval_status' => $event->status->value]);
    }

    protected function onReject(ApprovalEvent $event): void
    {
        $this->update(['approval_status' => $event->status->value]);
    }
}
```

### Define a Workflow Blueprint

```php
use App\Models\PurchaseOrder;
use App\Models\User;
use Menma\Approval\Enums\ApprovalTypeEnum;
use Menma\Approval\Enums\ContributorTypeEnum;
use Menma\Approval\Models\Approval;
use Menma\Approval\Models\ApprovalComponent;
use Menma\Approval\Models\ApprovalContributor;
use Menma\Approval\Models\ApprovalDictionary;
use Menma\Approval\Models\ApprovalFlow;
use Menma\Approval\Models\ApprovalFlowComponent;

$dictionary = ApprovalDictionary::create([
    'key' => PurchaseOrder::class,
    'name' => 'Purchase Order',
]);

$flow = ApprovalFlow::create([
    'name' => 'Purchase Order Flow',
]);

ApprovalFlowComponent::create([
    'approval_flow_id' => $flow->id,
    'approval_dictionary_id' => $dictionary->id,
    'key' => PurchaseOrder::class,
]);

$approval = Approval::create([
    'approval_flow_id' => $flow->id,
    'name' => 'Purchase Order Approval v1',
    'type' => ApprovalTypeEnum::SEQUENTIAL,
]);

// step is the bit position. Runtime mask becomes 1 << step.
$managerComponent = ApprovalComponent::create([
    'approval_id' => $approval->id,
    'name' => 'Manager Approval',
    'step' => 0,
    'type' => ContributorTypeEnum::OR,
]);

$financeComponent = ApprovalComponent::create([
    'approval_id' => $approval->id,
    'name' => 'Finance Approval',
    'step' => 1,
    'type' => ContributorTypeEnum::AND,
]);

ApprovalContributor::create([
    'approval_component_id' => $managerComponent->id,
    'approvable_type' => User::class,
    'approvable_id' => $managerUser->id,
]);

ApprovalContributor::create([
    'approval_component_id' => $financeComponent->id,
    'approvable_type' => App\Models\Department::class,
    'approvable_id' => $financeDepartment->id,
]);
```

### Execute Runtime Actions

```php
$purchaseOrder->initEvent($requester);

$purchaseOrder->approve($manager);
$purchaseOrder->reject($financeUser);
$purchaseOrder->cancel($manager);
$purchaseOrder->rollback($admin);

// Force reset to draft, binary 0.
$purchaseOrder->force($admin);

// Force specific binary state and status.
$purchaseOrder->force($admin, 3, \Menma\Approval\Enums\ApprovalStatusEnum::APPROVED->value);
```

Manual service usage is also available:

```php
$event = app(\Menma\Approval\Services\ApprovalService::class)
    ->forBinary($purchaseOrder)
    ->user($manager->id)
    ->approve();
```

## Workflow Behavior

### Sequential Approval

`ApprovalTypeEnum::SEQUENTIAL` selects the first pending event component by step order when no explicit binary target is provided. The lower-level service API also accepts a binary target; use that only when the caller intentionally wants to address a specific event component.

### Parallel Approval

`ApprovalTypeEnum::PARALLEL` can select a pending component that belongs to the current user. This allows different branches or components to move without strict global ordering.

### Contributor Logic

Each component has `ContributorTypeEnum`:

- `OR`: any contributor approval completes the component.
- `AND`: all contributors must approve before the component is complete.

Rejection behavior follows component logic. For OR components, one rejection rejects the component/event. For AND components, rejection is evaluated against approvals.

### Partial Approval State

The event can be partially approved because `step` and `target` are masks. A model can be in `DRAFT` while some components are already completed.

### Jump and Skip

Conditions can select a subset of components. This changes the generated `target` mask for that event. It is not a visual BPMN jump; it is runtime component selection before the event snapshot is created.

### No-Contributor Components

If a selected component has no resolved contributors, the component is automatically marked approved. If no selected component has any contributor, the entire event is approved.

### Rollback

`rollback()` resets the runtime event to draft state, clears contributor/component timestamps for the active event, re-resolves contributors, re-runs condition routing, and rebuilds the target mask from the current blueprint. It updates or creates selected event components; it is not documented as a destructive cleanup of every stale historical component row.

### Force

`force()` bypasses normal contributor validation and sets the event state directly. Calling `force($user)` with no binary resets the event to draft (`0`). Passing a non-zero binary with an approved status can mark matching component bits as approved.

## Event Runtime Queries

`ApprovalEvent` exposes useful accessors:

- `is_approved`
- `is_rejected`
- `is_cancelled`
- `is_rollback`
- `can_approve`
- `component`
- `current_component`

`can_approve` uses the current authenticated user (`Auth::id()`) and the next pending event component. If no user is authenticated, or the authenticated user is not a pending contributor, it returns false.

The package isolates bitwise SQL in `BitmaskQueryService` so mask queries can be implemented per database driver instead of scattering raw bit operations across the codebase.

## Practical Use Cases

- HR request workflow: route HR-originated requests directly to manager approval, while other divisions pass through HR first.
- Recruitment approval: recruiter, HR manager, department manager, and finance can be selected based on position, department, and salary range.
- Leave request: short leave can require manager only; long leave can add HR or director approval.
- Procurement approval: approval depth can change based on amount, category, department, or budget owner.
- Operational request flow: field requests can skip office-only components and route to the relevant operations role.
- Finance approval: invoice or reimbursement approval can combine department, finance, and executive layers.

## Technical Philosophy

Approval Binary is designed around:

- config-driven workflow records instead of hardcoded approval branches
- runtime event snapshots instead of mutable blueprint execution
- reusable orchestration services
- dynamic contributor resolution
- condition-based component routing
- bitmask state for compact partial approval tracking
- abstraction-first integration through model hooks and resolver interfaces

The package is meant to be extended by application code: define your own contributor resolver models, expose your own condition data, and use lifecycle hooks to connect approval results back into your domain.

## Testing

```bash
./vendor/bin/pest
```

## License

GNU AGPLv3
