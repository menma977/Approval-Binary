<?php

namespace Menma\Approval\Interfaces;

/**
 * Interface for models that can provide approval contributors.
 *
 * Implement this interface on models that should be usable as approval contributors.
 * The system will automatically resolve the model to get actual user IDs.
 *
 * Example Usage in ApprovalContributor:
 * - approvable_type = Role::class, approvable_id = 5 (Role with ID 5)
 * - approvable_type = Department::class, approvable_id = 10 (Department with ID 10)
 * - approvable_type = ApprovalGroup::class, approvable_id = 3 (Group with ID 3)
 *
 * Two implementation approaches:
 *
 * 1. Return array of user IDs directly:
 *    public function getApproverIds(): array
 *    {
 *        return [1, 2, 3, 5, 8];
 *    }
 *
 * 2. Query from relationship (recommended):
 *    public function getApproverIds(): array
 *    {
 *        return $this->users()->pluck('id')->toArray();
 *    }
 *
 * Example implementations:
 *
 * // Role model
 * class Role extends Model implements ApprovalContributorInterface
 * {
 *     public function users()
 *     {
 *         return $this->hasMany(User::class);
 *     }
 *
 *     public function getApproverIds(): array
 *     {
 *         return $this->users()->pluck('id')->toArray();
 *     }
 * }
 *
 * // Department model
 * class Department extends Model implements ApprovalContributorInterface
 * {
 *     public function managers()
 *     {
 *         return $this->hasMany(User::class)->where('is_manager', true);
 *     }
 *
 *     public function getApproverIds(): array
 *     {
 *         return $this->managers()->pluck('id')->toArray();
 *     }
 * }
 *
 * // Position model with pivot table
 * class Position extends Model implements ApprovalContributorInterface
 * {
 *     public function users()
 *     {
 *         return $this->belongsToMany(User::class, 'position_user');
 *     }
 *
 *     public function getApproverIds(): array
 *     {
 *         return $this->users()->pluck('users.id')->toArray();
 *     }
 * }
 */
interface ApprovalContributorInterface
{
	/**
	 * Get the list of user IDs that should be added as contributors.
	 *
	 * This method should return an array of user IDs that will be added as
	 * approval contributors. The implementation can query relationships,
	 * filter users, or return a static list.
	 *
	 * @return array<int> Array of user IDs
	 */
	public function getApproverIds(): array;
}
