<?php

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
