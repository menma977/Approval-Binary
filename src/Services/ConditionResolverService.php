<?php

declare(strict_types=1);
/*******************************************************************************
 * Approval-Binary - Binary bitmask-based approval workflows for Laravel
 * Copyright (C) 2026 menma977 <https://github.com/menma977/Approval-Binary>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 ******************************************************************************/

namespace Menma\Approval\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Menma\Approval\Interfaces\DynamicMaskingInterface;
use Menma\Approval\Models\ApprovalComponent;
use Menma\Approval\Models\ApprovalCondition;

/**
 * Resolves approval components from condition groups.
 * Higher priority conditions are checked first; priority 0 is default fallback.
 */
class ConditionResolverService
{
	public function __construct(private readonly ConditionExpressionService $expressionService)
	{
	}

	/**
	 * @param Model $model The model being approved
	 * @param Collection<int, ApprovalComponent> $approvalComponent The full set of approval components
	 * @param int|null $approvalId The approval ID to query conditions for
	 * @return Collection<int, ApprovalComponent>
	 */
	public function resolve(Model $model, Collection $approvalComponent, ?int $approvalId): Collection
	{
		if (!$model instanceof DynamicMaskingInterface || !$approvalId) {
			return $approvalComponent;
		}

		$conditions = ApprovalCondition::with('conditionComponents.component')
			->where('approval_id', $approvalId)
			->orderByDesc('priority')
			->get();

		if ($conditions->isEmpty()) {
			return $approvalComponent;
		}

		$componentById = $approvalComponent->keyBy('id');
		$modelConditions = $model->getApprovalConditions();

		foreach ($conditions as $condition) {
			$matchedComponents = $condition->conditionComponents
				->filter(fn($conditionComponent): bool => $this->expressionService->matches($conditionComponent->expression, $modelConditions))
				->map(fn($conditionComponent) => $componentById->get($conditionComponent->approval_component_id))
				->filter()
				->sortBy('step')
				->values();

			if ($matchedComponents->isNotEmpty()) {
				return $matchedComponents;
			}
		}

		return $approvalComponent;
	}
}
