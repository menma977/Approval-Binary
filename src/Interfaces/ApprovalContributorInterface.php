<?php
/*******************************************************************************
 * Approval-Binary - Binary bitmask-based approval workflows for Laravel
 * Copyright (C) 2026 menma977 <https://github.com/menma977/Approval-Binary>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 ******************************************************************************/

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
