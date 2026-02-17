<?php

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
