<?php

namespace Menma\Approval\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Menma\Approval\Abstracts\ApprovalCoreAbstract;

/**
 * @property string $id
 * @property int|null $company_id
 * @property string $key
 * @property string $name
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read \Menma\Approval\Models\ApprovalFlowComponent[] $components
 * @property-read int|null $components_count
 * @property-read \Illuminate\Database\Eloquent\Model|null $createdBy
 * @property-read \Illuminate\Database\Eloquent\Model|null $deletedBy
 * @property-read \Illuminate\Database\Eloquent\Model|null $updatedBy
 *
 * @method static Builder<static>|ApprovalDictionary newModelQuery()
 * @method static Builder<static>|ApprovalDictionary newQuery()
 * @method static Builder<static>|ApprovalDictionary onlyTrashed()
 * @method static Builder<static>|ApprovalDictionary query()
 * @method static Builder<static>|ApprovalDictionary whereCompanyId($value)
 * @method static Builder<static>|ApprovalDictionary whereCreatedAt($value)
 * @method static Builder<static>|ApprovalDictionary whereCreatedBy($value)
 * @method static Builder<static>|ApprovalDictionary whereDeletedAt($value)
 * @method static Builder<static>|ApprovalDictionary whereDeletedBy($value)
 * @method static Builder<static>|ApprovalDictionary whereId($value)
 * @method static Builder<static>|ApprovalDictionary whereKey($value)
 * @method static Builder<static>|ApprovalDictionary whereName($value)
 * @method static Builder<static>|ApprovalDictionary whereUpdatedAt($value)
 * @method static Builder<static>|ApprovalDictionary whereUpdatedBy($value)
 * @method static Builder<static>|ApprovalDictionary withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|ApprovalDictionary withUsers()
 * @method static Builder<static>|ApprovalDictionary withoutTrashed()
 */
class ApprovalDictionary extends ApprovalCoreAbstract
{
	/**
	 * The attributes that are mass-assignable.
	 */
	protected $fillable = [
		'key',
		'name',
		'created_by',
		'updated_by',
		'deleted_by',
	];

	/**
	 * Get the components associated with this dictionary.
	 *
	 * @return HasMany<ApprovalFlowComponent, $this>
	 */
	public function components(): HasMany
	{
		return $this->hasMany(ApprovalFlowComponent::class);
	}
}
