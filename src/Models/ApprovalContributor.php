<?php

namespace Menma\Approval\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Menma\Approval\Abstracts\ApprovalCoreAbstract;

/**
 * @property string $id
 * @property int|null $company_id
 * @property int $approval_component_id
 * @property string $approvable_type
 * @property string $approvable_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Model $approvable
 * @property-read \Menma\Approval\Models\ApprovalComponent $component
 * @property-read \Illuminate\Database\Eloquent\Model|null $createdBy
 * @property-read \Illuminate\Database\Eloquent\Model|null $deletedBy
 * @property-read \Illuminate\Database\Eloquent\Model|null $updatedBy
 *
 * @method static Builder<static>|ApprovalContributor newModelQuery()
 * @method static Builder<static>|ApprovalContributor newQuery()
 * @method static Builder<static>|ApprovalContributor onlyTrashed()
 * @method static Builder<static>|ApprovalContributor query()
 * @method static Builder<static>|ApprovalContributor whereApprovableId($value)
 * @method static Builder<static>|ApprovalContributor whereApprovableType($value)
 * @method static Builder<static>|ApprovalContributor whereApprovalComponentId($value)
 * @method static Builder<static>|ApprovalContributor whereCompanyId($value)
 * @method static Builder<static>|ApprovalContributor whereCreatedAt($value)
 * @method static Builder<static>|ApprovalContributor whereCreatedBy($value)
 * @method static Builder<static>|ApprovalContributor whereDeletedAt($value)
 * @method static Builder<static>|ApprovalContributor whereDeletedBy($value)
 * @method static Builder<static>|ApprovalContributor whereId($value)
 * @method static Builder<static>|ApprovalContributor whereUpdatedAt($value)
 * @method static Builder<static>|ApprovalContributor whereUpdatedBy($value)
 * @method static Builder<static>|ApprovalContributor withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|ApprovalContributor withUsers()
 * @method static Builder<static>|ApprovalContributor withoutTrashed()
 *
 */
class ApprovalContributor extends ApprovalCoreAbstract
{
	/**
	 * The attributes that are mass-assignable.
	 */
	protected $fillable = [
		'company_id',
		'approval_component_id',
		'approvable_type',
		'approvable_id',
		'created_by',
		'updated_by',
		'deleted_by',
	];

	/**
	 * Get the component associated with this contributor.
	 *
	 * @return BelongsTo<ApprovalComponent, $this>
	 */
	public function component(): BelongsTo
	{
		return $this->belongsTo(ApprovalComponent::class, 'approval_component_id')->withTrashed();
	}

	/**
	 * Get the parent-approvable model.
	 *
	 * @return MorphTo<Model, $this>
	 */
	public function approvable(): MorphTo
	{
		return $this->morphTo();
	}
}
