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

namespace Menma\Approval\Support;

use InvalidArgumentException;

class ApprovalExpression
{
	/** @var array<int, array{path: string, operator: string, value: mixed}> */
	private array $rules = [];

	private function __construct(private readonly string $logic)
	{
	}

	public static function all(): self
	{
		return new self('all');
	}

	public static function any(): self
	{
		return new self('any');
	}

	public function where(string $path, string $operator, mixed $value): self
	{
		$operatorList = config('approval.operators', []);
		if (!in_array($operator, $operatorList, true)) {
			throw new InvalidArgumentException("Unsupported approval condition operator [{$operator}].");
		}

		if ($path === '') {
			throw new InvalidArgumentException('Approval condition path cannot be empty.');
		}

		$this->rules[] = [
			'path' => $path,
			'operator' => $operator,
			'value' => $value,
		];

		return $this;
	}

	/**
	 * @return array{all?: array<int, array{path: string, operator: string, value: mixed}>, any?: array<int, array{path: string, operator: string, value: mixed}>}
	 */
	public function toArray(): array
	{
		return [$this->logic => $this->rules];
	}
}
