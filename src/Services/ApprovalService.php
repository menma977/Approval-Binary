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

namespace Menma\Approval\Services;


use Illuminate\Database\Eloquent\Model;
use Menma\Approval\Interfaces\ApprovalServiceInterface;

class ApprovalService
{
	/**
	 * Creates a binary approval service instance for the given Eloquent model.
	 * This method initializes a BinaryService that handles approval workflows
	 * for Eloquent models using binary flags to represent approval steps.
	 *
	 * @param Model $model The Eloquent model that requires approval processing
	 * @return ApprovalServiceInterface Returns a configured BinaryService instance for the model
	 *
	 * @example
	 * // Example usage with an Eloquent model
	 * $document = Document::find(1);
	 * $approvalService = app(ApprovalService::class)->forBinary($document);
	 */
	public function forBinary(Model $model): ApprovalServiceInterface
	{
		return BinaryService::model($model->getMorphClass(), $model->getKey());
	}
}
