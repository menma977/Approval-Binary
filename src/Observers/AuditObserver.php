<?php
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

class AuditObserver
{
	/**
	 * Handle the "creating" event of the model.
	 *
	 * @param Model $model The model instance being created.
	 */
	public function creating(Model $model): void
	{
		if (property_exists($model, 'created_by') && !$model->created_by) {
			$model->created_by = Auth::id();
		}
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
		if (property_exists($model, 'created_by') && !$model->created_by) {
			$model->created_by = Auth::id();
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
		if (property_exists($model, 'updated_by') && !$model->updated_by) {
			$model->updated_by = Auth::id();
		}
	}

	/**
	 * Handle the "updated" event for the model.
	 *
	 * @param Model $model The model being updated.
	 */
	public function updated(Model $model): void
	{
		if (property_exists($model, 'updated_by') && !$model->updated_by) {
			$model->updated_by = Auth::id();
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
		if (property_exists($model, 'deleted_by') && !$model->deleted_by) {
			$model->deleted_by = Auth::id();
			$model->saveQuietly();
		}
	}
}