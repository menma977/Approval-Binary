<?php

namespace Menma\Approval\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Menma\Approval\Abstracts\ApprovalCoreAbstract;
use Menma\Approval\Enums\ApprovalStatusEnum;
use Menma\Approval\Enums\ApprovalTypeEnum;

/**
 * @property string $id
 * @property int|null $company_id
 * @property int|null $approval_id
 * @property int $step The step using binary system: 0, 1, 3, 7, etc.
 * @property int $target The target of a binary system: 1, 2, 4, 8, etc.
 * @property string $requestable_type
 * @property string $requestable_id
 * @property ApprovalTypeEnum $type The type of workflow (0: parallel or 1: sequential)
 * @property ApprovalStatusEnum $status The current status of this approval Draft -> Pending -> Approved -> Rejected
 * @property Carbon|null $approved_at
 * @property Carbon|null $rejected_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $rollback_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read \Menma\Approval\Models\Approval|null $approval
 * @property-read mixed $can_approve
 * @property-read mixed $component
 * @property-read \Menma\Approval\Models\ApprovalEventComponent[] $components
 * @property-read int|null $components_count
 * @property-read \Menma\Approval\Models\ApprovalEventContributor[] $contributors
 * @property-read int|null $contributors_count
 * @property-read \Illuminate\Database\Eloquent\Model|null $createdBy
 * @property-read mixed $current_component
 * @property-read \Illuminate\Database\Eloquent\Model|null $deletedBy
 * @property-read bool $is_approved
 * @property-read bool $is_cancelled
 * @property-read bool $is_rejected
 * @property-read bool $is_rollback
 * @property-read \Illuminate\Database\Eloquent\Model $requestable
 * @property-read \Illuminate\Database\Eloquent\Model|null $updatedBy
 *
 * @method static Builder<static>|ApprovalEvent newModelQuery()
 * @method static Builder<static>|ApprovalEvent newQuery()
 * @method static Builder<static>|ApprovalEvent onlyTrashed()
 * @method static Builder<static>|ApprovalEvent query()
 * @method static Builder<static>|ApprovalEvent whereApprovalId($value)
 * @method static Builder<static>|ApprovalEvent whereApprovedAt($value)
 * @method static Builder<static>|ApprovalEvent whereCancelledAt($value)
 * @method static Builder<static>|ApprovalEvent whereCompanyId($value)
 * @method static Builder<static>|ApprovalEvent whereCreatedAt($value)
 * @method static Builder<static>|ApprovalEvent whereCreatedBy($value)
 * @method static Builder<static>|ApprovalEvent whereDeletedAt($value)
 * @method static Builder<static>|ApprovalEvent whereDeletedBy($value)
 * @method static Builder<static>|ApprovalEvent whereId($value)
 * @method static Builder<static>|ApprovalEvent whereRejectedAt($value)
 * @method static Builder<static>|ApprovalEvent whereRequestableId($value)
 * @method static Builder<static>|ApprovalEvent whereRequestableType($value)
 * @method static Builder<static>|ApprovalEvent whereRollbackAt($value)
 * @method static Builder<static>|ApprovalEvent whereStatus($value)
 * @method static Builder<static>|ApprovalEvent whereStep($value)
 * @method static Builder<static>|ApprovalEvent whereTarget($value)
 * @method static Builder<static>|ApprovalEvent whereType($value)
 * @method static Builder<static>|ApprovalEvent whereUpdatedAt($value)
 * @method static Builder<static>|ApprovalEvent whereUpdatedBy($value)
 * @method static Builder<static>|ApprovalEvent withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|ApprovalEvent withUsers()
 * @method static Builder<static>|ApprovalEvent withoutTrashed()
 */
class ApprovalEvent extends ApprovalCoreAbstract
{
	protected $appends = [
		'is_approved',
		'is_rejected',
		'is_cancelled',
		'is_rollback',
		'can_approve',
		'component',
		'current_component',
	];

	/**
	 * The attributes that are mass-assignable.
	 */
	protected $fillable = [
		'company_id',
		'approval_id',
		'step',
		'target',
		'requestable_type',
		'requestable_id',
		'type',
		'status',
		'approved_at',
		'rejected_at',
		'cancelled_at',
		'rollback_at',
		'created_by',
		'updated_by',
		'deleted_by',
	];

	protected $casts = [
		'type' => ApprovalTypeEnum::class,
		'status' => ApprovalStatusEnum::class,
		'approved_at' => 'datetime',
		'rejected_at' => 'datetime',
		'cancelled_at' => 'datetime',
		'rollback_at' => 'datetime',
	];

	/**
	 * Get the approval associated with this event.
	 *
	 * @return BelongsTo<Approval, $this>
	 */
	public function approval(): BelongsTo
	{
		return $this->belongsTo(Approval::class);
	}

	/**
	 * Get the parent-requestable model.
	 *
	 * @return MorphTo<Model, $this>
	 */
	public function requestable(): BelongsTo
	{
		return $this->morphTo();
	}

	/**
	 * Get the components associated with this event.
	 *
	 * @return HasMany<ApprovalEventComponent, $this>
	 */
	public function components(): HasMany
	{
		return $this->hasMany(ApprovalEventComponent::class);
	}

	/**
	 * Get the contributors through the components.
	 *
	 * @return HasManyThrough<ApprovalEventContributor, ApprovalEventComponent, $this>
	 */
	public function contributors(): HasManyThrough
	{
		return $this->hasManyThrough(ApprovalEventContributor::class, ApprovalEventComponent::class);
	}

	/**
	 * Get the approval status of the event.
	 *
	 *
	 * @noinspection PhpUnused
	 */
	/**
	 * @return Attribute<bool, never>
	 */
	protected function isApproved(): Attribute
	{
		return Attribute::make(
			get: fn(mixed $value, array $attributes) => isset($attributes['approved_at']),
		);
	}

	/**
	 * Get the approval status of the event.
	 *
	 *
	 * @noinspection PhpUnused
	 */
	/**
	 * @return Attribute<bool, never>
	 */
	protected function isRejected(): Attribute
	{
		return Attribute::make(
			get: fn(mixed $value, array $attributes) => isset($attributes['rejected_at']),
		);
	}

	/**
	 * Get the approval status of the event.
	 *
	 *
	 * @noinspection PhpUnused
	 */
	/**
	 * @return Attribute<bool, never>
	 */
	protected function isCancelled(): Attribute
	{
		return Attribute::make(
			get: fn(mixed $value, array $attributes) => isset($attributes['cancelled_at']),
		);
	}

	/**
	 * Get the approval status of the event.
	 *
	 *
	 * @noinspection PhpUnused
	 */
	/**
	 * @return Attribute<bool, never>
	 */
	protected function isRollback(): Attribute
	{
		return Attribute::make(
			get: fn(mixed $value, array $attributes) => isset($attributes['rollback_at']),
		);
	}

	/**
	 * Get the approval status of the event.
	 *
	 *
	 * @noinspection PhpUnused
	 */
	/**
	 * @return Attribute<bool, never>
	 */
	protected function canApprove(): Attribute
	{
		return Attribute::get(function (mixed $value, array $attributes) {
			if ($this->is_approved || $this->is_cancelled || $this->is_rejected) {
				return false;
			}

			if ($this->components()->whereRaw('(step & ?) = 0', [$attributes['step']])->orderBy('step')->count() <= 0) {
				return false;
			}

			$component = $this->components()->whereRaw('(step & ?) = 0', [$attributes['step']])->orderBy('step')->first();
			if ($component && ($component->is_approved || $component->is_cancelled || $component->is_rejected)) {
				return false;
			}

			$contributors = $component->contributors ?? collect();
			if ($contributors->isEmpty()) {
				return true;
			}

			foreach ($contributors as $contributor) {
				if ((int)$contributor->user_id === (int)Auth::id() && $contributor->approved_at === null) {
					return true;
				}
			}

			return false;
		});
	}

	/**
	 * Get the approval status of the event.
	 */
	/**
	 * @return Attribute<ApprovalEventComponent|null, never>
	 */
	protected function component(): Attribute
	{
		return Attribute::get(function (mixed $value, array $attributes) {
			return $this->components()->whereRaw('(step & ?) = 0', [$attributes['step']])->orderBy('step')->first() ?? null;
		});
	}

	/**
	 * Get the approval status of the event.
	 *
	 *
	 * @noinspection PhpUnused
	 */
	/**
	 * @return Attribute<ApprovalEventComponent|null, never>
	 */
	protected function currentComponent(): Attribute
	{
		return Attribute::get(function (mixed $value, array $attributes) {
			return $this->components()->whereRaw('(step & ~?) = 0', [$attributes['step']])->latest('id')->first() ?? null;
		});
	}
}
