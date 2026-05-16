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

namespace Menma\Approval\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class AuditObserver
{
	/**
	 * Handle the "creating" event of the model.
	 *
	 * @param Model $model The model instance being created.
	 */
	public function creating(Model $model): void
	{
		$this->setAuditColumnIfEmpty($model, 'created_by');
	}

	/**
	 * Handle the "created" event of the model.
	 *
	 * Sets the `created_by` attribute to the authenticated user's ID if not already set,
	 * and saves the model silently without firing further events.
	 *
	 * @param Model $model The model instance after being created.
	 */
	public function created(Model $model): void
	{
		if ($this->setAuditColumnIfEmpty($model, 'created_by')) {
			$model->saveQuietly();
		}
	}

	/**
	 * Handle the "updating" event for the given model.
	 *
	 * @param Model $model The model instance being updated.
	 */
	public function updating(Model $model): void
	{
		$this->setAuditColumnIfEmpty($model, 'updated_by');
	}

	/**
	 * Handle the "updated" event for the model.
	 *
	 * @param Model $model The model being updated.
	 */
	public function updated(Model $model): void
	{
		if ($this->setAuditColumnIfEmpty($model, 'updated_by')) {
			$model->saveQuietly();
		}
	}

	/**
	 * Handle the Model "deleted" event.
	 * Ensures deleted_by field is set and saved after model deletion.
	 *
	 * @param Model $model The model that was deleted
	 */
	public function deleted(Model $model): void
	{
		if ($this->setAuditColumnIfEmpty($model, 'deleted_by')) {
			$model->saveQuietly();
		}
	}

	private function setAuditColumnIfEmpty(Model $model, string $auditColumn): bool
	{
		$authenticatedUserId = Auth::id();

		if (!$authenticatedUserId || !$this->modelSupportsAuditColumn($model, $auditColumn) || $model->getAttribute($auditColumn)) {
			return false;
		}

		$model->setAttribute($auditColumn, $authenticatedUserId);

		return true;
	}

	private function modelSupportsAuditColumn(Model $model, string $auditColumn): bool
	{
		$modelCanReceiveAuditColumn = $model->isFillable($auditColumn)
			|| array_key_exists($auditColumn, $model->getAttributes());

		if (!$modelCanReceiveAuditColumn) {
			return false;
		}

		$modelTableName = $model->getTable();

		return Schema::hasColumn($modelTableName, $auditColumn);
	}
}
