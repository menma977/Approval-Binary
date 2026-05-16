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

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BitmaskQueryService
{
	/**
	 * @param Builder<*>|Relation<*, *, *>|QueryBuilder $query
	 * @return Builder<*>|Relation<*, *, *>|QueryBuilder
	 */
	public function whereMaskContains(Builder|Relation|QueryBuilder $query, string $column, int $mask): Builder|Relation|QueryBuilder
	{
		return $query->whereRaw(
			$this->bitwiseAndExpression($column) . ' = ?',
			[$mask, $mask],
		);
	}

	/**
	 * @param Builder<*>|Relation<*, *, *>|QueryBuilder $query
	 * @return Builder<*>|Relation<*, *, *>|QueryBuilder
	 */
	public function whereMaskHasNoOverlap(Builder|Relation|QueryBuilder $query, string $column, int $mask): Builder|Relation|QueryBuilder
	{
		return $query->whereRaw(
			$this->bitwiseAndExpression($column) . ' = 0',
			[$mask],
		);
	}

	/**
	 * @param Builder<*>|Relation<*, *, *>|QueryBuilder $query
	 * @return Builder<*>|Relation<*, *, *>|QueryBuilder
	 */
	public function whereMaskFullyInside(Builder|Relation|QueryBuilder $query, string $column, int $mask): Builder|Relation|QueryBuilder
	{
		return $query->whereRaw(
			$this->bitwiseAndExpression($column) . ' = ' . $this->wrapColumn($column),
			[$mask],
		);
	}

	private function bitwiseAndExpression(string $column): string
	{
		return '(' . $this->wrapColumn($column) . ' & ?)';
	}

	private function wrapColumn(string $column): string
	{
		$databaseDriver = DB::connection()->getDriverName();

		return match ($databaseDriver) {
			'mysql', 'mariadb', 'pgsql', 'sqlite' => DB::connection()->getQueryGrammar()->wrap($column),
			default => throw new RuntimeException("Unsupported database driver for bitmask queries: {$databaseDriver}"),
		};
	}
}
