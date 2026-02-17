# Approval Binary

A Laravel plugin for **binary bitmask-based approval workflows**. Define multi-level approval chains where each step is represented as a bit position, enabling efficient state tracking, flexible
workflow automation, and high-performance querying.

## Requirements

- PHP 8.1+
- Laravel 10.x / 11.x

## Installation

### 1. Install via Composer

```bash
composer require menma/approval
```

### 2. Publish Configuration

```bash
php artisan vendor:publish --tag=approval-config
```

### 3. Run Migrations

```bash
php artisan migrate
```

## Core Concepts

### Binary Bitmask System

Each approval step is assigned a unique bit position. The `target` integer stores the require set of approvals (mask), and `step` integer tracks the completed approvals.

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
- **ConditionResolverService**: Evals dynamic conditions (e.g., "Amount > 1000") to filter required steps at runtime (Dynamic Masking).

## Usage

### 1. Prepare Your Model

Extend `ApprovalAbstract` and optionally implement `DynamicMaskingInterface` for conditional logic.

```php
use Menma\Approval\Abstracts\ApprovalAbstract;

class PurchaseOrder extends ApprovalAbstract
{
    // Return users who can approve this specific record (if applicable)
    public function getApproverIds(): array
    {
        return $this->user_id ? [$this->user->manager_id] : [];
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

## API Reference

### Triggering Actions

```php
// Initialize
$foo->initEvent($user);

// Approve (Smart detection of step)
$foo->approve($user);

// Reject
$foo->reject($user);

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
