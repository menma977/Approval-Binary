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
use Illuminate\Support\Carbon;
use Menma\Approval\Abstracts\ApprovalCoreAbstract;

/**
 * @property int $id
 * @property string $ulid
 * @property int $approval_condition_id
 * @property int $approval_component_id
 * @property array<string, mixed>|null $expression
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read \Menma\Approval\Models\ApprovalCondition $condition
 * @property-read \Menma\Approval\Models\ApprovalComponent $component
 * @property-read \Illuminate\Database\Eloquent\Model|null $createdBy
 * @property-read \Illuminate\Database\Eloquent\Model|null $deletedBy
 * @property-read \Illuminate\Database\Eloquent\Model|null $updatedBy
 *
 * @method static Builder<static>|ApprovalConditionComponent newModelQuery()
 * @method static Builder<static>|ApprovalConditionComponent newQuery()
 * @method static Builder<static>|ApprovalConditionComponent onlyTrashed()
 * @method static Builder<static>|ApprovalConditionComponent query()
 * @method static Builder<static>|ApprovalConditionComponent whereApprovalComponentId($value)
 * @method static Builder<static>|ApprovalConditionComponent whereApprovalConditionId($value)
 * @method static Builder<static>|ApprovalConditionComponent withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|ApprovalConditionComponent withoutTrashed()
 */
class ApprovalConditionComponent extends ApprovalCoreAbstract
{
	/**
	 * The attributes that are mass-assignable.
	 */
	protected $fillable = [
		'approval_condition_id',
		'approval_component_id',
		'expression',
		'created_by',
		'updated_by',
		'deleted_by',
	];

	protected $casts = [
		'expression' => 'array',
	];

	/**
	 * @return array<int, string>
	 */
	public function uniqueIds(): array
	{
		return ['ulid'];
	}

	/**
	 * Get the condition associated with this rule.
	 *
	 * @return BelongsTo<ApprovalCondition, $this>
	 */
	public function condition(): BelongsTo
	{
		return $this->belongsTo(ApprovalCondition::class, 'approval_condition_id');
	}

	/**
	 * Get the approval component selected by this rule.
	 *
	 * @return BelongsTo<ApprovalComponent, $this>
	 */
	public function component(): BelongsTo
	{
		return $this->belongsTo(ApprovalComponent::class, 'approval_component_id');
	}
}
