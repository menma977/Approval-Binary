<?php

namespace Menma\Approval\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Menma\Approval\Interfaces\DynamicMaskingInterface;
use Menma\Approval\Models\ApprovalCondition;

/**
 * Resolves which approval components should be included in the approval flow
 * based on conditional dynamic masking rules.
 *
 * If the model implements DynamicMaskingInterface, this service evaluates
 * the model's condition data against ApprovalCondition rules to determine
 * which components should be included (filtered by max_step).
 *
 * Conditions are evaluated in priority order (highest first).
 * The first matching condition wins and its max_step is used to filter.
 *
 * If the model does NOT implement DynamicMaskingInterface, or no conditions
 * match, all components are returned unchanged (existing static behavior).
 */
class ConditionResolverService
{
	/**
	 * Filters approval components based on conditional dynamic masking.
	 *
	 * @param Model $model The model being approved
	 * @param Collection $approvalComponent The full set of approval components
	 * @param int|null $approvalId The approval ID to query conditions for
	 * @return Collection The filtered (or unfiltered) collection of components
	 */
	public function resolve(Model $model, Collection $approvalComponent, ?int $approvalId): Collection
	{
		if (!$model instanceof DynamicMaskingInterface || !$approvalId) {
			return $approvalComponent;
		}

		$conditions = ApprovalCondition::where('approval_id', $approvalId)
			->orderByDesc('priority')
			->get();

		if ($conditions->isEmpty()) {
			return $approvalComponent;
		}

		$modelConditions = $model->getApprovalConditions();
		$maxStep = null;

		foreach ($conditions as $condition) {
			$fieldValue = $modelConditions[$condition->field] ?? null;
			if ($fieldValue !== null && $condition->evaluate($fieldValue)) {
				$maxStep = $condition->max_step;
				break;
			}
		}

		if ($maxStep !== null) {
			return $approvalComponent->filter(fn($component) => $component->step <= $maxStep);
		}

		return $approvalComponent;
	}
}
