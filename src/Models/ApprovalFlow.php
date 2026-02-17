<?php

namespace Menma\Approval\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Menma\Approval\Abstracts\ApprovalCoreAbstract;

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
 * @property-read \Menma\Approval\Models\Approval|null $approval
 * @property-read \Menma\Approval\Models\ApprovalFlowComponent[] $components
 * @property-read int|null $components_count
 * @property-read \Illuminate\Database\Eloquent\Model|null $createdBy
 * @property-read \Illuminate\Database\Eloquent\Model|null $deletedBy
 * @property-read \Illuminate\Database\Eloquent\Model|null $updatedBy
 *
 * @method static Builder<static>|ApprovalFlow newModelQuery()
 * @method static Builder<static>|ApprovalFlow newQuery()
 * @method static Builder<static>|ApprovalFlow onlyTrashed()
 * @method static Builder<static>|ApprovalFlow query()
 * @method static Builder<static>|ApprovalFlow whereCompanyId($value)
 * @method static Builder<static>|ApprovalFlow whereCreatedAt($value)
 * @method static Builder<static>|ApprovalFlow whereCreatedBy($value)
 * @method static Builder<static>|ApprovalFlow whereDeletedAt($value)
 * @method static Builder<static>|ApprovalFlow whereDeletedBy($value)
 * @method static Builder<static>|ApprovalFlow whereId($value)
 * @method static Builder<static>|ApprovalFlow whereName($value)
 * @method static Builder<static>|ApprovalFlow whereUpdatedAt($value)
 * @method static Builder<static>|ApprovalFlow whereUpdatedBy($value)
 * @method static Builder<static>|ApprovalFlow withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|ApprovalFlow withUsers()
 * @method static Builder<static>|ApprovalFlow withoutTrashed()
 */
class ApprovalFlow extends ApprovalCoreAbstract
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
	 * Get the approval associated with this flow.
	 *
	 * @return HasOne<Approval, $this>
	 */
	public function approval(): HasOne
	{
		return $this->hasOne(Approval::class);
	}

	/**
	 * Get the components associated with this flow.
	 *
	 * @return HasMany<ApprovalFlowComponent, $this>
	 */
	public function components(): HasMany
	{
		return $this->hasMany(ApprovalFlowComponent::class);
	}
}
