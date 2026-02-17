<?php

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
