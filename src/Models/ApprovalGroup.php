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

namespace Menma\Approval\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Menma\Approval\Abstracts\ApprovalCoreAbstract;
use Menma\Approval\Interfaces\ApprovalContributorInterface;

/**
 * @property string $id
 * @property int|null $company_id
 * @property string $name
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read \Menma\Approval\Models\ApprovalGroupContributor[] $contributors
 * @property-read int|null $contributors_count
 * @property-read \Illuminate\Database\Eloquent\Model|null $createdBy
 * @property-read \Illuminate\Database\Eloquent\Model|null $deletedBy
 * @property-read \Illuminate\Database\Eloquent\Model|null $updatedBy
 *
 * @method static Builder<static>|ApprovalGroup newModelQuery()
 * @method static Builder<static>|ApprovalGroup newQuery()
 * @method static Builder<static>|ApprovalGroup onlyTrashed()
 * @method static Builder<static>|ApprovalGroup query()
 * @method static Builder<static>|ApprovalGroup whereCompanyId($value)
 * @method static Builder<static>|ApprovalGroup whereCreatedAt($value)
 * @method static Builder<static>|ApprovalGroup whereCreatedBy($value)
 * @method static Builder<static>|ApprovalGroup whereDeletedAt($value)
 * @method static Builder<static>|ApprovalGroup whereDeletedBy($value)
 * @method static Builder<static>|ApprovalGroup whereId($value)
 * @method static Builder<static>|ApprovalGroup whereName($value)
 * @method static Builder<static>|ApprovalGroup whereUpdatedAt($value)
 * @method static Builder<static>|ApprovalGroup whereUpdatedBy($value)
 * @method static Builder<static>|ApprovalGroup withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|ApprovalGroup withUsers()
 * @method static Builder<static>|ApprovalGroup withoutTrashed()
 */
class ApprovalGroup extends ApprovalCoreAbstract implements ApprovalContributorInterface
{
	/**
	 * The attributes that are mass-assignable.
	 */
	protected $fillable = [
		'company_id',
		'name',
		'created_by',
		'updated_by',
		'deleted_by',
	];

	/**
	 * Get the contributors associated with this group.
	 *
	 * @return HasMany<ApprovalGroupContributor, $this>
	 */
	public function contributors(): HasMany
	{
		return $this->hasMany(ApprovalGroupContributor::class);
	}

	/**
	 * Get the list of user IDs that should be added as contributors.
	 *
	 * @return array<int> Array of user IDs
	 */
	public function getApproverIds(): array
	{
		return $this->contributors()->pluck('user_id')->toArray();
	}
}
