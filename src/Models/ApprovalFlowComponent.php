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
 * @property string $approval_flow_id
 * @property string $approval_dictionary_id
 * @property string $key
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Model|null $createdBy
 * @property-read \Illuminate\Database\Eloquent\Model|null $deletedBy
 * @property-read \Menma\Approval\Models\ApprovalDictionary $dictionary
 * @property-read \Menma\Approval\Models\ApprovalFlow|null $flow
 * @property-read \Illuminate\Database\Eloquent\Model|null $updatedBy
 *
 * @method static Builder<static>|ApprovalFlowComponent newModelQuery()
 * @method static Builder<static>|ApprovalFlowComponent newQuery()
 * @method static Builder<static>|ApprovalFlowComponent onlyTrashed()
 * @method static Builder<static>|ApprovalFlowComponent query()
 * @method static Builder<static>|ApprovalFlowComponent whereApprovalDictionaryId($value)
 * @method static Builder<static>|ApprovalFlowComponent whereApprovalFlowId($value)
 * @method static Builder<static>|ApprovalFlowComponent whereCompanyId($value)
 * @method static Builder<static>|ApprovalFlowComponent whereCreatedAt($value)
 * @method static Builder<static>|ApprovalFlowComponent whereCreatedBy($value)
 * @method static Builder<static>|ApprovalFlowComponent whereDeletedAt($value)
 * @method static Builder<static>|ApprovalFlowComponent whereDeletedBy($value)
 * @method static Builder<static>|ApprovalFlowComponent whereId($value)
 * @method static Builder<static>|ApprovalFlowComponent whereKey($value)
 * @method static Builder<static>|ApprovalFlowComponent whereUpdatedAt($value)
 * @method static Builder<static>|ApprovalFlowComponent whereUpdatedBy($value)
 * @method static Builder<static>|ApprovalFlowComponent withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|ApprovalFlowComponent withUsers()
 * @method static Builder<static>|ApprovalFlowComponent withoutTrashed()
 */
class ApprovalFlowComponent extends ApprovalCoreAbstract
{
	/**
	 * The attributes that are mass-assignable.
	 */
	protected $fillable = [
		'company_id',
		'approval_flow_id',
		'approval_dictionary_id',
		'key',
		'created_by',
		'updated_by',
		'deleted_by',
	];

	/**
	 * Get the flow associated with this component.
	 *
	 * @return BelongsTo<ApprovalFlow, $this>
	 */
	public function flow(): BelongsTo
	{
		return $this->belongsTo(ApprovalFlow::class);
	}

	/**
	 * Get the dictionary associated with this component.
	 *
	 * @return BelongsTo<ApprovalDictionary, $this>
	 */
	public function dictionary(): BelongsTo
	{
		return $this->belongsTo(ApprovalDictionary::class, 'approval_dictionary_id');
	}
}
