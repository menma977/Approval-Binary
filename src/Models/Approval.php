<?php

namespace Menma\Approval\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Menma\Approval\Abstracts\ApprovalCoreAbstract;
use Menma\Approval\Enums\ApprovalTypeEnum;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $ulid
 * @property string $approval_flow_id
 * @property string $name
 * @property ApprovalTypeEnum $type The type of workflow (0: parallel or 1: sequential)
 * @property bool $can_change Whether the approval can be changed
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read \Menma\Approval\Models\ApprovalComponent[] $components
 * @property-read int|null $components_count
 * @property-read \Illuminate\Database\Eloquent\Model|null $createdBy
 * @property-read \Illuminate\Database\Eloquent\Model|null $deletedBy
 * @property-read \Menma\Approval\Models\ApprovalEvent[] $events
 * @property-read int|null $events_count
 * @property-read \Menma\Approval\Models\ApprovalFlow $flow
 * @property-read \Illuminate\Database\Eloquent\Model|null $updatedBy
 *
 * @method static Builder<static>|Approval newModelQuery()
 * @method static Builder<static>|Approval newQuery()
 * @method static Builder<static>|Approval onlyTrashed()
 * @method static Builder<static>|Approval query()
 * @method static Builder<static>|Approval whereApprovalFlowId($value)
 * @method static Builder<static>|Approval whereCanChange($value)
 * @method static Builder<static>|Approval whereCompanyId($value)
 * @method static Builder<static>|Approval whereCreatedAt($value)
 * @method static Builder<static>|Approval whereCreatedBy($value)
 * @method static Builder<static>|Approval whereDeletedAt($value)
 * @method static Builder<static>|Approval whereDeletedBy($value)
 * @method static Builder<static>|Approval whereId($value)
 * @method static Builder<static>|Approval whereName($value)
 * @method static Builder<static>|Approval whereType($value)
 * @method static Builder<static>|Approval whereUlid($value)
 * @method static Builder<static>|Approval whereUpdatedAt($value)
 * @method static Builder<static>|Approval whereUpdatedBy($value)
 * @method static Builder<static>|Approval withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Approval withUsers()
 * @method static Builder<static>|Approval withoutTrashed()
 */
class Approval extends ApprovalCoreAbstract
{
	/**
	 * The attributes that are mass-assignable.
	 */
	protected $fillable = [
		"company_id",
		"approval_flow_id",
		"name",
		"type",
		"can_change",
		"created_by",
		"updated_by",
		"deleted_by",
	];

	protected $casts = [
		"type" => ApprovalTypeEnum::class,
		"can_change" => "boolean",
	];

	/**
	 * @return array<int, string>
	 */
	public function uniqueIds(): array
	{
		return ["ulid"];
	}

	/**
	 * Get the approval flow associated with the approval.
	 *
	 * @return BelongsTo<ApprovalFlow, $this>
	 */
	public function flow(): BelongsTo
	{
		return $this->belongsTo(ApprovalFlow::class, "approval_flow_id", "id");
	}

	/**
	 * Get the components associated with the approval.
	 *
	 * @return HasMany<ApprovalComponent, $this>
	 */
	public function components(): HasMany
	{
		return $this->hasMany(ApprovalComponent::class);
	}

	/**
	 * Get the events associated with the approval.
	 *
	 * @return HasMany<ApprovalEvent, $this>
	 */
	public function events(): HasMany
	{
		return $this->hasMany(ApprovalEvent::class);
	}

	/**
	 * Get the conditions associated with the approval.
	 * Used for conditional dynamic masking (Smart Threshold).
	 *
	 * @return HasMany<ApprovalCondition, $this>
	 */
	public function conditions(): HasMany
	{
		return $this->hasMany(ApprovalCondition::class);
	}
}
