<?php

use Menma\Approval\Models\ApprovalGroup;

return [
	/*
	|--------------------------------------------------------------------------
	| User Model
	|--------------------------------------------------------------------------
	|
	| The User model class that will be used for approval contributors.
	|
	*/
	"user" => App\Models\User::class,

	/*
	|--------------------------------------------------------------------------
	| Auditable
	|--------------------------------------------------------------------------
	|
	| Enable or disable auditing for approval events.
	|
	*/
	"is_auditable" => true,

	/*
	|--------------------------------------------------------------------------
	| Approval Groups
	|--------------------------------------------------------------------------
	|
	| Register models that implement ApprovalContributorInterface.
	| These models will be dynamically resolved to get approver user IDs.
	|
	| How it works:
	| 1. Admin creates ApprovalComponent (approval step)
	| 2. Admin adds ApprovalContributor records with:
	|    - Direct User: approvable_type = null, approvable_id = user_id
	|    - Role/Group/etc: approvable_type = Role::class, approvable_id = role_id
	| 3. When approval is initiated:
	|    - System checks if approvable_type is in this 'group' array
	|    - If yes: Finds the model instance and calls getApproverIds()
	|    - If no: Uses approvable_id directly as user_id
	|
	| Example: If Role with id=5 has users [10, 20, 30]:
	|
	| ApprovalContributor::create([
	|     'approval_component_id' => $componentId,
	|     'approvable_type' => Role::class,
	|     'approvable_id' => 5,  // Role ID
	| ]);
	|
	| Result: System will create ApprovalEventContributor for users 10, 20, 30
	|
	| Custom Model Example:
	|
	| class Role extends Model implements ApprovalContributorInterface
	| {
	|     public function users()
	|     {
	|         return $this->hasMany(User::class);
	|     }
	|
	|     public function getApproverIds(): array
	|     {
	|         // Get all user IDs that belong to this role
	|         return $this->users()->pluck('id')->toArray();
	|     }
	| }
	|
	| Then register it here:
	| 'group' => [
	|     ApprovalGroup::class,
	|     App\Models\Role::class,
	|     App\Models\Department::class,
	| ]
	|
	*/
	"group" => [
		ApprovalGroup::class,
		// Add your custom models here that implement ApprovalContributorInterface
		// App\Models\Role::class,
		// App\Models\Department::class,
		// App\Models\Position::class,
	],

	/*
	|--------------------------------------------------------------------------
	| Conditional Dynamic Masking Operators
	|--------------------------------------------------------------------------
	|
	| Allowed operators for approval conditions.
	| These are used to compare model properties against thresholds
	| in the approval_conditions table.
	|
	| Models that implement DynamicMaskingInterface can have their
	| approval target mask dynamically determined based on these conditions.
	|
	*/
	"operators" => ["<", ">", "<=", ">=", "==", "!="],
];

