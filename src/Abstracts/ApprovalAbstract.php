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

	public function getApproverIds(): array
	{
		return [];
	}

	public function getApprovalConditions(): array
	{
		return [];
	}

	public function initEvent(Model $user): void
	{
		$this->approvalService()
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

	public function force(Model $user, ?int $binary = null, ?string $status = null): void
	{
		$service = $this->approvalService()
			->user($user->id)
			->binary($binary ?? 0);

		if ($status !== null) {
			$service->status($status);
		}

		$approvalEvent = $service->force();
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
