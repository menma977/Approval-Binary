<?php

namespace Menma\Approval\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Menma\Approval\Models\ApprovalEvent;
use Menma\Approval\Services\BinaryService;

trait HasBinaryApproval
{
	/**
	 * Get the approval event associated with this model.
	 *
	 * @return MorphOne<ApprovalEvent, $this>
	 */
	public function approvalEvent(): MorphOne
	{
		return $this->morphOne(ApprovalEvent::class, 'requestable');
	}

	/**
	 * Initiate the approval process for this model.
	 *
	 * @return ApprovalEvent
	 */
	public function initiateApproval(): ApprovalEvent
	{
		return BinaryService::model($this::class, $this->getKey())->store();
	}

	/**
	 * Approve this model by the specified user.
	 *
	 * @param int $userId The ID of the user performing the approval
	 * @return ApprovalEvent
	 */
	public function approveBy(int $userId): ApprovalEvent
	{
		return BinaryService::model($this::class, $this->getKey())->user($userId)->approve();
	}

	/**
	 * Reject this model by the specified user.
	 *
	 * @param int $userId The ID of the user performing the rejection
	 * @return ApprovalEvent
	 */
	public function rejectBy(int $userId): ApprovalEvent
	{
		return BinaryService::model($this::class, $this->getKey())->user($userId)->reject();
	}

	/**
	 * Cancel this model's approval by the specified user.
	 *
	 * @param int $userId The ID of the user performing the cancellation
	 * @return ApprovalEvent
	 */
	public function cancelBy(int $userId): ApprovalEvent
	{
		return BinaryService::model($this::class, $this->getKey())->user($userId)->cancel();
	}

	/**
	 * Rollback this model's approval by the specified user.
	 *
	 * @param int $userId The ID of the user performing the rollback
	 * @return ApprovalEvent
	 */
	public function rollbackBy(int $userId): ApprovalEvent
	{
		return BinaryService::model($this::class, $this->getKey())->user($userId)->rollback();
	}

	/**
	 * Force approval of this model.
	 *
	 * @param int $userId The ID of the user performing the force approval
	 * @param int|null $binary Optional binary step value
	 * @param string|null $status Optional status to set
	 * @return ApprovalEvent
	 */
	public function forceApprovalBy(int $userId, ?int $binary = null, ?string $status = null): ApprovalEvent
	{
		$service = BinaryService::model($this::class, $this->getKey())->user($userId);

		if ($binary !== null) {
			$service->binary($binary);
		}

		if ($status !== null) {
			$service->status($status);
		}

		return $service->force();
	}
}
