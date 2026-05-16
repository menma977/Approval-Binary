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

use Illuminate\Validation\ValidationException;

class ConditionExpressionService
{
	/**
	 * @param array<string, mixed>|null $expression
	 * @param array<string, mixed> $modelConditions
	 * @throws ValidationException
	 */
	public function matches(?array $expression, array $modelConditions): bool
	{
		if ($expression === null || $expression === []) {
			return true;
		}

		$logicKeys = array_values(array_intersect(['all', 'any'], array_keys($expression)));
		if (count($logicKeys) !== 1 || count($expression) !== 1) {
			$this->fail('Approval condition expression must contain exactly one logic key: all or any.');
		}

		$logic = $logicKeys[0];
		$rules = $expression[$logic];
		if (!is_array($rules)) {
			$this->fail('Approval condition expression rules must be an array.');
		}

		$results = array_map(
			fn(mixed $rule): bool => $this->matchesRule($rule, $modelConditions),
			$rules,
		);

		return $logic === 'all'
			? !in_array(false, $results, true)
			: in_array(true, $results, true);
	}

	/**
	 * @param mixed $rule
	 * @param array<string, mixed> $modelConditions
	 * @throws ValidationException
	 */
	private function matchesRule(mixed $rule, array $modelConditions): bool
	{
		if (!is_array($rule)) {
			$this->fail('Approval condition rule must be an object.');
		}

		$path = $rule['path'] ?? null;
		$operator = $rule['operator'] ?? null;
		if (!is_string($path) || $path === '') {
			$this->fail('Approval condition rule path must be a non-empty string.');
		}

		if (!is_string($operator) || !in_array($operator, config('approval.operators', []), true)) {
			$this->fail('Approval condition rule operator is not allowed.');
		}

		$value = data_get($modelConditions, $path);
		$threshold = $rule['value'] ?? null;

		if (is_numeric($value) && is_numeric($threshold)) {
			$value = (float)$value;
			$threshold = (float)$threshold;
		}

		return match ($operator) {
			'<' => $value < $threshold,
			'>' => $value > $threshold,
			'<=' => $value <= $threshold,
			'>=' => $value >= $threshold,
			'==' => $value == $threshold,
			'!=' => $value != $threshold,
		};
	}

	/**
	 * @throws ValidationException
	 */
	private function fail(string $message): never
	{
		throw ValidationException::withMessages([
			'approval_condition_expression' => [$message],
		]);
	}
}
