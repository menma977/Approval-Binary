<?php

namespace Menma\Approval\Abstracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Menma\Approval\Interfaces\ApprovalContributorInterface;
use Menma\Approval\Interfaces\ApprovalServiceInterface;
use Menma\Approval\Interfaces\DynamicMaskingInterface;
use Menma\Approval\Models\ApprovalEvent;
use Menma\Approval\Services\ApprovalService;

abstract class ApprovalAbstract extends Model implements ApprovalContributorInterface, DynamicMaskingInterface
{
	/**
	 * @return MorphOne<ApprovalEvent, $this>
	 */
	public function event(): MorphOne
	{
		return $this->morphOne(ApprovalEvent::class, "requestable");
	}

	public function initEvent(Model $user): void
	{
		$this->approvalService()
			->model($this::class, $this->getKey())
			->user($user->id)
			->store();
	}

	public function approve(Model $user): void
	{
		$approvalEvent = $this->approvalService()->user($user->id)->approve();
		$this->onApprove($approvalEvent);
	}

	public function reject(Model $user): void
	{
		$approvalEvent = $this->approvalService()->user($user->id)->reject();
		$this->onReject($approvalEvent);
	}

	public function cancel(Model $user): void
	{
		$approvalEvent = $this->approvalService()->user($user->id)->cancel();
		$this->onCancel($approvalEvent);
	}

	public function rollback(Model $user): void
	{
		$approvalEvent = $this->approvalService()->user($user->id)->rollback();
		$this->onRollback($approvalEvent);
	}

	public function force(
		Model   $user,
		?int    $binary = null,
		?string $status = null,
	): void
	{
		$approvalEvent = $this->approvalService()
			->user($user->id)
			->binary($binary ?? 0)
			->status($status ?? "")
			->force();
		$this->onForce($approvalEvent);
	}

	/**
	 * @param \Illuminate\Database\Eloquent\Builder<static> $query
	 * @return \Illuminate\Database\Eloquent\Builder<static>
	 * @noinspection PhpUnused
	 */
	public function scopeWithContributors(Builder $query): Builder
	{
		return $query->with("event.components.contributors.user");
	}

	/**
	 * @param \Illuminate\Database\Eloquent\Builder<static> $query
	 * @return \Illuminate\Database\Eloquent\Builder<static>
	 * @noinspection PhpUnused
	 */
	public function scopeWithUsers(Builder $query): Builder
	{
		return $query->with(["createdBy", "updatedBy", "deletedBy"]);
	}

	/**
	 * @param \Illuminate\Database\Eloquent\Builder<static> $query
	 * @param int $target The bitmask target to filter by
	 * @return \Illuminate\Database\Eloquent\Builder<static>
	 * @noinspection PhpUnused
	 */
	public function scopeWhereApprovalTarget(Builder $query, int $target): Builder
	{
		return $query->whereHas('event', fn(Builder $eventQuery) => $eventQuery->where('target', $target));
	}

	protected function approvalService(): ApprovalServiceInterface
	{
		/** @var ApprovalService $factory */
		$factory = app(ApprovalService::class);

		return $factory->forBinary($this);
	}

	protected function onApprove(ApprovalEvent $approvalEvent): void
	{
	}

	protected function onReject(ApprovalEvent $approvalEvent): void
	{
	}

	protected function onCancel(ApprovalEvent $approvalEvent): void
	{
	}

	protected function onRollback(ApprovalEvent $approvalEvent): void
	{
	}

	protected function onForce(ApprovalEvent $approvalEvent): void
	{
	}
}
