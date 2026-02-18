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
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Menma\Approval\Abstracts\ApprovalCoreAbstract;

/**
 * @property string $id
 * @property int|null $company_id
 * @property string $approval_event_component_id
 * @property int $user_id
 * @property Carbon|null $approved_at
 * @property Carbon|null $rejected_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $rollback_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read \Menma\Approval\Models\ApprovalEventComponent|null $component
 * @property-read \Illuminate\Database\Eloquent\Model|null $createdBy
 * @property-read \Illuminate\Database\Eloquent\Model|null $deletedBy
 * @property-read bool $is_approved
 * @property-read bool $is_cancelled
 * @property-read bool $is_rejected
 * @property-read bool $is_rollback
 * @property-read \Illuminate\Database\Eloquent\Model|null $updatedBy
 * @property-read \Illuminate\Database\Eloquent\Model $user
 *
 * @method static Builder<static>|ApprovalEventContributor newModelQuery()
 * @method static Builder<static>|ApprovalEventContributor newQuery()
 * @method static Builder<static>|ApprovalEventContributor onlyTrashed()
 * @method static Builder<static>|ApprovalEventContributor query()
 * @method static Builder<static>|ApprovalEventContributor whereApprovalEventComponentId($value)
 * @method static Builder<static>|ApprovalEventContributor whereApprovedAt($value)
 * @method static Builder<static>|ApprovalEventContributor whereCancelledAt($value)
 * @method static Builder<static>|ApprovalEventContributor whereCompanyId($value)
 * @method static Builder<static>|ApprovalEventContributor whereCreatedAt($value)
 * @method static Builder<static>|ApprovalEventContributor whereCreatedBy($value)
 * @method static Builder<static>|ApprovalEventContributor whereDeletedAt($value)
 * @method static Builder<static>|ApprovalEventContributor whereDeletedBy($value)
 * @method static Builder<static>|ApprovalEventContributor whereId($value)
 * @method static Builder<static>|ApprovalEventContributor whereRejectedAt($value)
 * @method static Builder<static>|ApprovalEventContributor whereRollbackAt($value)
 * @method static Builder<static>|ApprovalEventContributor whereUpdatedAt($value)
 * @method static Builder<static>|ApprovalEventContributor whereUpdatedBy($value)
 * @method static Builder<static>|ApprovalEventContributor whereUserId($value)
 * @method static Builder<static>|ApprovalEventContributor withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|ApprovalEventContributor withUsers()
 * @method static Builder<static>|ApprovalEventContributor withoutTrashed()
 */
class ApprovalEventContributor extends ApprovalCoreAbstract
{
	protected $appends = [
		'is_approved',
		'is_rejected',
		'is_cancelled',
		'is_rollback',
	];

	/**
	 * The attributes that are mass-assignable.
	 */
	protected $fillable = [
		'company_id',
		'approval_event_component_id',
		'user_id',
		'approved_at',
		'rejected_at',
		'cancelled_at',
		'rollback_at',
		'created_by',
		'updated_by',
		'deleted_by',
	];

	protected $casts = [
		'approved_at' => 'datetime',
		'rejected_at' => 'datetime',
		'cancelled_at' => 'datetime',
		'rollback_at' => 'datetime',
	];

	/**
	 * Get the component associated with this contributor.
	 *
	 * @return BelongsTo<ApprovalEventComponent, $this>
	 */
	public function component(): BelongsTo
	{
		return $this->belongsTo(ApprovalEventComponent::class);
	}

	/**
	 * Get the user associated with this contributor.
	 *
	 * @return BelongsTo<\Illuminate\Database\Eloquent\Model, $this>
	 */
	public function user(): BelongsTo
	{
		return $this->belongsTo(config('approval.user'));
	}

	/**
	 * Get the approval status of the event.
	 *
	 * @return Attribute<bool, never>
	 *
	 * @noinspection PhpUnused
	 */
	protected function isApproved(): Attribute
	{
		return Attribute::make(
			get: fn(mixed $value, array $attributes) => isset($attributes['approved_at']),
		);
	}

	/**
	 * Get the approval status of the event.
	 *
	 * @return Attribute<bool, never>
	 *
	 * @noinspection PhpUnused
	 */
	protected function isRejected(): Attribute
	{
		return Attribute::make(
			get: fn(mixed $value, array $attributes) => isset($attributes['rejected_at']),
		);
	}

	/**
	 * Get the approval status of the event.
	 *
	 * @return Attribute<bool, never>
	 *
	 * @noinspection PhpUnused
	 */
	protected function isCancelled(): Attribute
	{
		return Attribute::make(
			get: fn(mixed $value, array $attributes) => isset($attributes['cancelled_at']),
		);
	}

	/**
	 * Get the approval status of the event.
	 *
	 * @return Attribute<bool, never>
	 *
	 * @noinspection PhpUnused
	 */
	protected function isRollback(): Attribute
	{
		return Attribute::make(
			get: fn(mixed $value, array $attributes) => isset($attributes['rollback_at']),
		);
	}
}
