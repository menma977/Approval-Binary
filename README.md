# Approval Binary

A Laravel plugin for **binary bitmask-based approval workflows**. Define multi-level approval chains where each step is represented as a bit position, enabling efficient state tracking, flexible
workflow automation, and high-performance querying.

## Requirements

- PHP 8.1+
- Laravel 10.x / 11.x / 12.x

## Installation

### 1. Install via Composer

```bash
composer require menma/approval-binary
```

### 2. Publish

#### 2.1 migration

```bash
php artisan vendor:publish --tag=approval-migration
```

#### 2.2 config

```bash
php artisan vendor:publish --tag=approval-config
```

#### 2.3 lang

```bash
php artisan vendor:publish --tag=approval-lang
```

### 3. Run Migrations

```bash
php artisan migrate
```

## Core Concepts

### Binary Bitmask System

Each approval step is assigned a unique bit position. The `target` integer stores the required set of approvals (mask), and `step` integer tracks the completed approvals.

| Component | Step | Bit Position | Mask Value       |
|-----------|------|--------------|------------------|
| HR        | 0    | Bit 0        | `1 << 0` = **1** |
| Manager   | 1    | Bit 1        | `1 << 1` = **2** |
| Director  | 2    | Bit 2        | `1 << 2` = **4** |

- **Target = 7** (binary `111`) → HR + Manager + Director required.
- **Target = 3** (binary `011`) → HR + Manager required.

### Architecture

The plugin is architected around specialized services for robust workflow management:

- **EventStoreService**: Handles the creation, retrieval, and initialization of `ApprovalEvent`s. Determines the correct flow and assigns contributors.
- **EventActionService**: Orchestrates state changes (Approve, Reject, Cancel). Handles complex logic like Parallel user detection and Sequential order enforcement.
- **ConditionResolverService**: Evaluates dynamic conditions (e.g., "Amount > 1000") to filter required steps at runtime (Dynamic Masking).

## Usage

### 1. Prepare Your Model

Extend `ApprovalAbstract`. This provides the necessary relationships and default implementations. You can override `getApprovalConditions` to enable Dynamic Masking.

```php
use Menma\Approval\Abstracts\ApprovalAbstract;

class PurchaseOrder extends ApprovalAbstract
{
    // Return users who can approve this specific record (if applicable)
    public function getApproverIds(): array
    {
        return $this->user_id ? [$this->position->user_id] : [];
    }

    // Expose data for Conditional Logic
    public function getApprovalConditions(): array
    {
        return [
            'amount' => $this->amount,
            'dept' => $this->department,
        ];
    }

    // Lifecycle Hooks
    protected function onApprove(ApprovalEvent $event): void { /* ... */ }
    protected function onReject(ApprovalEvent $event): void { /* ... */ }
    protected function onCancel(ApprovalEvent $event): void { /* ... */ }
    protected function onRollback(ApprovalEvent $event): void { /* ... */ }
}
```

### 2. Workflow Scenarios

This plugin supports complex enterprise workflows. Here are the core scenarios:

#### Scenario 1: Single User Approval

A simple one-step workflow.

#### Scenario 2: Parallel Multi-User (OR)

Multiple approvers (e.g., HR, Finance) can approve in **any order**.

- Configuration: `Approval Type = PARALLEL (0)`
- `EventActionService` intelligently detects if the current user is a contributor to _any_ pending step.

#### Scenario 3: Sequential Multi-User

Strict ordering. Step 2 cannot be approved until Step 1 is complete.

- Configuration: `Approval Type = SEQUENTIAL (1)`

#### Scenario 4: Shared Step (OR Logic)

A single step (e.g., "Manager Approval") assigned to multiple users. **Any one** of them can approve to complete the step.

- Component Type: `OR (1)`

#### Scenario 5: Shared Step (AND Logic)

A single step assigned to a committee. **All assigned users** must approve for the step to complete.

- Component Type: `AND (0)`

#### Scenario 6: Conditional Approval (Dynamic Masking)

Dynamically skip steps based on model data.
_Example: "Director approval is only needed if Amount > 1000"._

1. **Define Condition**:
   ```php
   ApprovalCondition::create([
       'approval_id' => $approval->id,
       'field' => 'amount',
       'operator' => '<=',
       'threshold' => '1000',
       'max_step' => 0 // If Amount <= 1000, limit workflow to Step 0 (Manager only)
   ]);
   ```
2. **Runtime**:
    - If `PO Amount = 500`: Target Mask = `1` (Manager). Event approved after Manager action.
    - If `PO Amount = 1500`: Target Mask = `3` (Manager + Director). Both required.

## Configuration (Database Seeding)

Since this plugin operates without a UI, approval workflows are defined programmatically via database records. This setup links your Models to specific Approval Flows and defines the logic (steps) and
actors (contributors).

### Entity Relationships

- **ApprovalDictionary**: Registry of models (e.g., "Purchase Order").
- **ApprovalFlow**: Container for the workflow.
- **ApprovalFlowComponent**: The bridge connecting a Model (Dictionary) to a Flow.
- **ApprovalGroup**: A collection of users (e.g., "Board Committee").

### Seeder Example

```php
use Menma\Approval\Models\ApprovalDictionary;
use Menma\Approval\Models\ApprovalFlow;
use Menma\Approval\Models\ApprovalFlowComponent;
use Menma\Approval\Models\Approval;
use Menma\Approval\Models\ApprovalComponent;
use Menma\Approval\Models\ApprovalContributor;
use Menma\Approval\Models\ApprovalGroup;
use Menma\Approval\Models\ApprovalGroupContributor;
use Menma\Approval\Enums\ApprovalTypeEnum;
use Menma\Approval\Enums\ContributorTypeEnum;

// 1. Register the Model (Dictionary)
$dictionary = ApprovalDictionary::create([
    'key' => 'App\Models\PurchaseOrder',
    'name' => 'Purchase Order',
]);

// 2. Create the Flow
$flow = ApprovalFlow::create([
    'name' => 'Standard PO Workflow',
]);

// 3. Link Model to Flow
// The 'key' MUST match your Model's morph class (getMorphClass())
ApprovalFlowComponent::create([
    'approval_flow_id' => $flow->id,
    'approval_dictionary_id' => $dictionary->id,
    'key' => 'App\Models\PurchaseOrder',
]);

// 4. Define Logic Container
$approval = Approval::create([
    'approval_flow_id' => $flow->id,
    'name' => 'PO Logic v1',
    'type' => ApprovalTypeEnum::SEQUENTIAL, // Enforces strict sequential order
]);

// 5. Create Steps
// Step 0: Manager (Bit 0)
$stepManager = ApprovalComponent::create([
    'approval_id' => $approval->id,
    'name' => 'Manager Approval',
    'step' => 0,
    'type' => ContributorTypeEnum::OR,
]);

// Step 1: Board (Bit 1)
$stepBoard = ApprovalComponent::create([
    'approval_id' => $approval->id,
    'name' => 'Board Approval',
    'step' => 1,
    'type' => ContributorTypeEnum::AND, // All members must approve
]);

// 6. Assign Contributors

// 6a. Direct User (Manager)
ApprovalContributor::create([
    'approval_component_id' => $stepManager->id,
    'approvable_type' => null, // Direct User
    'approvable_id' => 1,      // User ID
]);

// 6b. Group (Board)
$boardGroup = ApprovalGroup::create(['name' => 'Board Members']);
ApprovalGroupContributor::create(['approval_group_id' => $boardGroup->id, 'user_id' => 2]);
ApprovalGroupContributor::create(['approval_group_id' => $boardGroup->id, 'user_id' => 3]);

// Assign Group to Step
ApprovalContributor::create([
    'approval_component_id' => $stepBoard->id,
    'approvable_type' => ApprovalGroup::class,
    'approvable_id' => $boardGroup->id,
]);
```

## API Reference

### Triggering Actions

```php
// Initialize
$foo->initEvent($user);

// Approve (Smart detection of step)
$foo->approve($user);

// Reject
$foo->reject($user);

// Cancel
$foo->cancel($user);

// Rollback
$foo->rollback($user);

// Force Approve (Skip Smart Detection)
$foo->force($user, 5, ApprovalStatusEnum::DRAFT->value);

// Check Status
if ($foo->isApproved()) { ... }
```

### Manual Service Usage

For custom implementations avoiding the Model trait:

```php
$service = app(EventActionService::class);
$service->approve($model, $user);
```

## License

GNU AGPLv3
