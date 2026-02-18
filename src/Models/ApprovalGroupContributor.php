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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Menma\Approval\Abstracts\ApprovalCoreAbstract;

/**
 * @property string $id
 * @property int|null $company_id
 * @property string $approval_group_id
 * @property int $user_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Model|null $createdBy
 * @property-read \Illuminate\Database\Eloquent\Model|null $deletedBy
 * @property-read \Menma\Approval\Models\ApprovalGroup $group
 * @property-read \Illuminate\Database\Eloquent\Model|null $updatedBy
 * @property-read \Illuminate\Database\Eloquent\Model $user
 *
 * @method static Builder<static>|ApprovalGroupContributor newModelQuery()
 * @method static Builder<static>|ApprovalGroupContributor newQuery()
 * @method static Builder<static>|ApprovalGroupContributor onlyTrashed()
 * @method static Builder<static>|ApprovalGroupContributor query()
 * @method static Builder<static>|ApprovalGroupContributor whereApprovalGroupId($value)
 * @method static Builder<static>|ApprovalGroupContributor whereCompanyId($value)
 * @method static Builder<static>|ApprovalGroupContributor whereCreatedAt($value)
 * @method static Builder<static>|ApprovalGroupContributor whereCreatedBy($value)
 * @method static Builder<static>|ApprovalGroupContributor whereDeletedAt($value)
 * @method static Builder<static>|ApprovalGroupContributor whereDeletedBy($value)
 * @method static Builder<static>|ApprovalGroupContributor whereId($value)
 * @method static Builder<static>|ApprovalGroupContributor whereUpdatedAt($value)
 * @method static Builder<static>|ApprovalGroupContributor whereUpdatedBy($value)
 * @method static Builder<static>|ApprovalGroupContributor whereUserId($value)
 * @method static Builder<static>|ApprovalGroupContributor withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|ApprovalGroupContributor withUsers()
 * @method static Builder<static>|ApprovalGroupContributor withoutTrashed()
 */
class ApprovalGroupContributor extends ApprovalCoreAbstract
{
	/**
	 * The attributes that are mass-assignable.
	 */
	protected $fillable = [
		'company_id',
		'approval_group_id',
		'user_id',
		'created_by',
		'updated_by',
		'deleted_by',
	];

	/**
	 * Get the user associated with this group contributor.
	 *
	 * @return BelongsTo<\Illuminate\Database\Eloquent\Model, $this>
	 */
	public function user(): BelongsTo
	{
		return $this->belongsTo(config('approval.user'));
	}

	/**
	 * Get the group associated with this contributor.
	 *
	 * @return BelongsTo<ApprovalGroup, $this>
	 */
	public function group(): BelongsTo
	{
		return $this->belongsTo(ApprovalGroup::class, 'approval_group_id');
	}
}
