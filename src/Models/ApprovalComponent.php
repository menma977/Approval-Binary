<?php

namespace Menma\Approval\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Menma\Approval\Abstracts\ApprovalCoreAbstract;
use Menma\Approval\Enums\ContributorTypeEnum;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $ulid
 * @property int $approval_id
 * @property string $name
 * @property int $step The step using binary system: 1, 2, 3, 4, etc.
 * @property ContributorTypeEnum $type The type of approval logic (0:and/1:or)
 * @property string $color The color of the component
 * @property bool $can_drag
 * @property bool $can_edit
 * @property bool $can_delete
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read \Menma\Approval\Models\Approval $approval
 * @property-read \Menma\Approval\Models\ApprovalContributor[] $contributors
 * @property-read int|null $contributors_count
 * @property-read \Illuminate\Database\Eloquent\Model|null $createdBy
 * @property-read \Illuminate\Database\Eloquent\Model|null $deletedBy
 * @property-read \Illuminate\Database\Eloquent\Model|null $updatedBy
 *
 * @method static Builder<static>|ApprovalComponent newModelQuery()
 * @method static Builder<static>|ApprovalComponent newQuery()
 * @method static Builder<static>|ApprovalComponent onlyTrashed()
 * @method static Builder<static>|ApprovalComponent query()
 * @method static Builder<static>|ApprovalComponent whereApprovalId($value)
 * @method static Builder<static>|ApprovalComponent whereCanDelete($value)
 * @method static Builder<static>|ApprovalComponent whereCanDrag($value)
 * @method static Builder<static>|ApprovalComponent whereCanEdit($value)
 * @method static Builder<static>|ApprovalComponent whereColor($value)
 * @method static Builder<static>|ApprovalComponent whereCompanyId($value)
 * @method static Builder<static>|ApprovalComponent whereCreatedAt($value)
 * @method static Builder<static>|ApprovalComponent whereCreatedBy($value)
 * @method static Builder<static>|ApprovalComponent whereDeletedAt($value)
 * @method static Builder<static>|ApprovalComponent whereDeletedBy($value)
 * @method static Builder<static>|ApprovalComponent whereId($value)
 * @method static Builder<static>|ApprovalComponent whereName($value)
 * @method static Builder<static>|ApprovalComponent whereStep($value)
 * @method static Builder<static>|ApprovalComponent whereType($value)
 * @method static Builder<static>|ApprovalComponent whereUlid($value)
 * @method static Builder<static>|ApprovalComponent whereUpdatedAt($value)
 * @method static Builder<static>|ApprovalComponent whereUpdatedBy($value)
 * @method static Builder<static>|ApprovalComponent withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|ApprovalComponent withUsers()
 * @method static Builder<static>|ApprovalComponent withoutTrashed()
 */
class ApprovalComponent extends ApprovalCoreAbstract
{
	/**
	 * The attributes that are mass-assignable.
	 */
	protected $fillable = [
		'company_id',
		'approval_id',
		'name',
		'step',
		'type',
		'color',
		'can_drag',
		'can_edit',
		'can_delete',
		'created_by',
		'updated_by',
		'deleted_by',
	];

	protected $casts = [
		'step' => 'integer',
		'type' => ContributorTypeEnum::class,
		'can_drag' => 'boolean',
		'can_edit' => 'boolean',
		'can_delete' => 'boolean',
	];

	/**
	 * @return array<int, string>
	 */
	public function uniqueIds(): array
	{
		return ['ulid'];
	}

	/**
	 * Get the approval associated with this component.
	 *
	 * @return BelongsTo<Approval, $this>
	 */
	public function approval(): BelongsTo
	{
		return $this->belongsTo(Approval::class);
	}

	/**
	 * Get the contributors associated with this component.
	 *
	 * @return HasMany<ApprovalContributor, $this>
	 */
	public function contributors(): HasMany
	{
		return $this->hasMany(ApprovalContributor::class);
	}
}
