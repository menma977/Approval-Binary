<?php

declare(strict_types=1);
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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Menma\Approval\Abstracts\ApprovalCoreAbstract;

/**
 * @property int $id
 * @property string $ulid
 * @property int $approval_id
 * @property int $priority Higher priority conditions are evaluated first. Priority 0 is the default fallback.
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read \Menma\Approval\Models\Approval $approval
 * @property-read \Menma\Approval\Models\ApprovalConditionComponent[] $conditionComponents
 * @property-read int|null $condition_components_count
 * @property-read \Illuminate\Database\Eloquent\Model|null $createdBy
 * @property-read \Illuminate\Database\Eloquent\Model|null $deletedBy
 * @property-read \Illuminate\Database\Eloquent\Model|null $updatedBy
 *
 * @method static Builder<static>|ApprovalCondition newModelQuery()
 * @method static Builder<static>|ApprovalCondition newQuery()
 * @method static Builder<static>|ApprovalCondition onlyTrashed()
 * @method static Builder<static>|ApprovalCondition query()
 * @method static Builder<static>|ApprovalCondition whereApprovalId($value)
 * @method static Builder<static>|ApprovalCondition wherePriority($value)
 * @method static Builder<static>|ApprovalCondition withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|ApprovalCondition withoutTrashed()
 */
class ApprovalCondition extends ApprovalCoreAbstract
{
	/**
	 * The attributes that are mass-assignable.
	 */
	protected $fillable = [
		'approval_id',
		'priority',
		'created_by',
		'updated_by',
		'deleted_by',
	];

	protected $casts = [
		'priority' => 'integer',
	];

	/**
	 * @return array<int, string>
	 */
	public function uniqueIds(): array
	{
		return ['ulid'];
	}

	protected static function boot(): void
	{
		parent::boot();

		static::creating(function (ApprovalCondition $condition): void {
			if ($condition->priority !== null) {
				return;
			}

			$latestPriority = static::query()
				->where('approval_id', $condition->approval_id)
				->max('priority');

			$condition->priority = $latestPriority === null ? 0 : ((int)$latestPriority + 1);
		});
	}

	/**
	 * Get the approval associated with this condition.
	 *
	 * @return BelongsTo<Approval, $this>
	 */
	public function approval(): BelongsTo
	{
		return $this->belongsTo(Approval::class);
	}

	/**
	 * Get component rules for this condition.
	 *
	 * @return HasMany<ApprovalConditionComponent, $this>
	 */
	public function conditionComponents(): HasMany
	{
		return $this->hasMany(ApprovalConditionComponent::class);
	}
}
