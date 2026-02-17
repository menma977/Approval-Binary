<?php

namespace Menma\Approval\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Menma\Approval\Abstracts\ApprovalCoreAbstract;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $ulid
 * @property int $approval_id
 * @property string $field The property name from getApprovalConditions() to compare
 * @property string $operator Comparison operator: <, >, <=, >=, ==, !=
 * @property string $threshold The value to compare against
 * @property int $max_step Maximum component step to include (inclusive)
 * @property int $priority Higher priority conditions are evaluated first
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read \Menma\Approval\Models\Approval $approval
 * @property-read \Illuminate\Database\Eloquent\Model|null $createdBy
 * @property-read \Illuminate\Database\Eloquent\Model|null $deletedBy
 * @property-read \Illuminate\Database\Eloquent\Model|null $updatedBy
 *
 * @method static Builder<static>|ApprovalCondition newModelQuery()
 * @method static Builder<static>|ApprovalCondition newQuery()
 * @method static Builder<static>|ApprovalCondition onlyTrashed()
 * @method static Builder<static>|ApprovalCondition query()
 * @method static Builder<static>|ApprovalCondition whereApprovalId($value)
 * @method static Builder<static>|ApprovalCondition whereField($value)
 * @method static Builder<static>|ApprovalCondition whereMaxStep($value)
 * @method static Builder<static>|ApprovalCondition whereOperator($value)
 * @method static Builder<static>|ApprovalCondition wherePriority($value)
 * @method static Builder<static>|ApprovalCondition whereThreshold($value)
 * @method static Builder<static>|ApprovalCondition withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|ApprovalCondition withoutTrashed()
 */
class ApprovalCondition extends ApprovalCoreAbstract
{
	/**
	 * The attributes that are mass-assignable.
	 */
	protected $fillable = [
		'approval_id',
		'field',
		'operator',
		'threshold',
		'max_step',
		'priority',
		'created_by',
		'updated_by',
		'deleted_by',
	];

	protected $casts = [
		'max_step' => 'integer',
		'priority' => 'integer',
	];

	/**
	 * @return array<int, string>
	 */
	public function uniqueIds(): array
	{
		return ['ulid'];
	}

	/**
	 * Get the approval associated with this condition.
	 *
	 * @return BelongsTo<Approval, $this>
	 */
	public function approval(): BelongsTo
	{
		return $this->belongsTo(Approval::class);
	}

	/**
	 * Evaluate this condition against the given value.
	 *
	 * Compares the provided value against $this->threshold using $this->operator.
	 * Uses numeric comparison when both values are numeric, string comparison otherwise.
	 * Only whitelisted operators are allowed — no eval() is used.
	 *
	 * @param mixed $value The value from the model's getApprovalConditions()
	 * @return bool Whether the condition is satisfied
	 */
	public function evaluate(mixed $value): bool
	{
		$threshold = $this->threshold;

		if (is_numeric($value) && is_numeric($threshold)) {
			$value = (float)$value;
			$threshold = (float)$threshold;
		}

		return match ($this->operator) {
			'<' => $value < $threshold,
			'>' => $value > $threshold,
			'<=' => $value <= $threshold,
			'>=' => $value >= $threshold,
			'==' => $value == $threshold,
			'!=' => $value != $threshold,
			default => false,
		};
	}
}
