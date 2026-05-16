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

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Menma\Approval\Enums\ApprovalStatusEnum;
use Menma\Approval\Enums\ApprovalTypeEnum;
use Menma\Approval\Enums\ContributorTypeEnum;
use Menma\Approval\Interfaces\ApprovalContributorInterface;
use Menma\Approval\Models\ApprovalComponent;
use Menma\Approval\Models\ApprovalContributor;
use Menma\Approval\Models\ApprovalEvent;
use Menma\Approval\Models\ApprovalEventComponent;
use Menma\Approval\Models\ApprovalEventContributor;
use Throwable;

/**
 * Handles all approval workflow actions: approve, reject, cancel, rollback, and force.
 *
 * Each action operates within a database transaction for data integrity.
 * All actions first call EventStoreService::store() to ensure the approval event exists.
 */
class EventActionService
{
	protected EventStoreService $storeService;

	protected $now;

	public function __construct(EventStoreService $storeService)
	{
		$this->storeService = $storeService;
		$this->now = now();
	}

	/**
	 * Approves the current approval step for the given user.
	 *
	 * Handles the approval process by:
	 * 1. Creating or retrieving the approval event
	 * 2. Validating the component and contributor existence
	 * 3. Updating approval status
	 * 4. Checking and updating the overall approval status if all contributors approved
	 *
	 * For OR-type components, approval by any contributor is enough.
	 * For AND-type components, all contributors must approve.
	 *
	 * @param Model $model The model being approved
	 * @param Authenticatable $user The user performing the approval
	 * @param int|null $binary The binary step value to target
	 * @return ApprovalEvent The updated approval event
	 *
	 * @throws Throwable When database transaction fails
	 */
	public function approve(Model $model, Authenticatable $user, ?int $binary = null): ApprovalEvent
	{
		return DB::transaction(function () use ($model, $user, $binary) {
			$approvalEvent = $this->storeService->store($model);

			if ($approvalEvent->is_approved || $approvalEvent->is_rejected || $approvalEvent->is_cancelled) {
				return $approvalEvent;
			}

			$approvalEventComponent = $this->getFirstEventComponent($approvalEvent, $binary, $user);
			if (!$approvalEventComponent) {
				$approvalEvent->status = ApprovalStatusEnum::APPROVED;
				$approvalEvent->step |= $approvalEvent->target;
				$approvalEvent->approved_at = $this->now;
				$approvalEvent->save();

				return $approvalEvent;
			}

			$approvalEventContributorIsNotEmpty = ApprovalEventContributor::where('approval_event_component_id', $approvalEventComponent->id)->exists();
			if ($approvalEventContributorIsNotEmpty) {
				$approvalEventContributor = ApprovalEventContributor::where('approval_event_component_id', $approvalEventComponent->id)
					->where('user_id', $user->id)
					->lockForUpdate()
					->first();

				if (!$approvalEventContributor) {
					throw ValidationException::withMessages([
						'approval_event_contributor' => trans('approval::approval.message.fail.action.cost', [
							'action' => 'Approve',
							'attribute' => $approvalEventComponent->name,
							'target' => $this->storeService->getUserName($user),
						]),
					]);
				}

				$approvalEventContributor->approved_at = $this->now;
				$approvalEventContributor->save();

				if ($approvalEventComponent->type === ContributorTypeEnum::OR) {
					$shouldApproveComponent = true;
				} else {
					$allContributorsApproved = ApprovalEventContributor::where('approval_event_component_id', $approvalEventComponent->id)
						->whereNull('approved_at')
						->doesntExist();
					$shouldApproveComponent = $allContributorsApproved;
				}
			} else {
				$shouldApproveComponent = true;
			}

			if ($shouldApproveComponent) {
				$approvalEventComponent->approved_at = $this->now;
				$approvalEventComponent->save();

				$approvalEvent->step |= $approvalEventComponent->step;
				if (($approvalEvent->step & $approvalEvent->target) === $approvalEvent->target) {
					$approvalEvent->status = ApprovalStatusEnum::APPROVED;
					$approvalEvent->approved_at = $this->now;
				} else {
					$approvalEvent->status = ApprovalStatusEnum::DRAFT;
				}
				$approvalEvent->save();
			}

			return $approvalEvent;
		});
	}

	/**
	 * Rejects the current approval step for the given user.
	 *
	 * Handles the rejection process based on component type:
	 * - For OR type: Immediately rejects if any contributor rejects
	 * - For AND type: Compares approvals vs. rejections.
	 *   If more rejections than approvals, the component is rejected.
	 *   If tied or more approvals, the component continues.
	 *
	 * @param Model $model The model being rejected
	 * @param Authenticatable $user The user performing the rejection
	 * @param int|null $binary The binary step value to target
	 * @return ApprovalEvent The updated approval event
	 *
	 * @throws Throwable When database transaction fails
	 */
	public function reject(Model $model, Authenticatable $user, ?int $binary = null): ApprovalEvent
	{
		return DB::transaction(function () use ($model, $user, $binary) {
			$approvalEvent = $this->storeService->store($model);

			if ($approvalEvent->is_approved || $approvalEvent->is_rejected || $approvalEvent->is_cancelled) {
				return $approvalEvent;
			}

			$approvalEventComponent = $this->getFirstEventComponent($approvalEvent, $binary, $user);
			if (!$approvalEventComponent) {
				$approvalEvent->status = ApprovalStatusEnum::REJECTED;
				$approvalEvent->rejected_at = $this->now;
				$approvalEvent->save();

				return $approvalEvent;
			}

			$approvalEventContributor = ApprovalEventContributor::where('approval_event_component_id', $approvalEventComponent->id)
				->where('user_id', $user->id)
				->lockForUpdate()
				->first();
			if (!$approvalEventContributor) {
				throw ValidationException::withMessages([
					'approval_event_contributor' => trans('approval::approval.message.fail.action.cost', [
						'action' => 'Reject',
						'attribute' => $approvalEventComponent->name,
						'target' => $this->storeService->getUserName($user),
					]),
				]);
			}

			$approvalEventContributor->rejected_at = $this->now;
			$approvalEventContributor->save();

			$shouldRejectComponent = false;

			if ($approvalEventComponent->type === ContributorTypeEnum::OR) {
				$shouldRejectComponent = true;
			} else {
				$approvalCount = ApprovalEventContributor::where('approval_event_component_id', $approvalEventComponent->id)
					->whereNotNull('approved_at')
					->count();

				$rejectionCount = ApprovalEventContributor::where('approval_event_component_id', $approvalEventComponent->id)
					->whereNotNull('rejected_at')
					->count();

				if ($rejectionCount > $approvalCount) {
					$shouldRejectComponent = true;
				}
			}

			if ($shouldRejectComponent) {
				$approvalEventComponent->rejected_at = $this->now;
				$approvalEventComponent->save();

				$approvalEvent->status = ApprovalStatusEnum::REJECTED;
				$approvalEvent->rejected_at = $this->now;
				$approvalEvent->save();
			}

			return $approvalEvent;
		});
	}

	/**
	 * Cancels the approval process for the current user.
	 *
	 * Resets all contributor timestamps and sets the approval status to rejected.
	 * The cancellation clears the step bit for the cancelled component.
	 *
	 * @param Model $model The model being cancelled
	 * @param Authenticatable $user The user performing the cancellation
	 * @param int|null $binary The binary step value to target
	 * @return ApprovalEvent The updated approval event
	 *
	 * @throws Throwable When database transaction fails
	 */
	public function cancel(Model $model, Authenticatable $user, ?int $binary = null): ApprovalEvent
	{
		return DB::transaction(function () use ($model, $user, $binary) {
			$approvalEvent = $this->storeService->store($model);

			if ($approvalEvent->is_approved || $approvalEvent->is_rejected || $approvalEvent->is_cancelled) {
				return $approvalEvent;
			}

			$approvalEventComponent = $this->getFirstEventComponent($approvalEvent, $binary, $user);
			if (!$approvalEventComponent) {
				$approvalEvent->status = ApprovalStatusEnum::CANCELED;
				$approvalEvent->cancelled_at = $this->now;
				$approvalEvent->save();

				return $approvalEvent;
			}

			ApprovalEventContributor::where('approval_event_component_id', $approvalEventComponent->id)->update([
				'cancelled_at' => $this->now,
				'approved_at' => null,
				'rejected_at' => null,
				'rollback_at' => null,
			]);

			$approvalEventComponent->cancelled_at = $this->now;
			$approvalEventComponent->approved_at = null;
			$approvalEventComponent->save();

			$approvalEvent->status = ApprovalStatusEnum::CANCELED;
			$approvalEvent->step &= ~$approvalEventComponent->step;
			$approvalEvent->cancelled_at = $this->now;
			$approvalEvent->save();

			return $approvalEvent;
		});
	}

	/**
	 * Rolls back an approval event to its initial draft state.
	 *
	 * Performs the following actions:
	 * 1. Retrieves or creates the approval event
	 * 2. Resets all approval event components by clearing their timestamps
	 * 3. Synchronizes contributors based on the current approval configuration
	 * 4. Resets the main approval event to draft status
	 *
	 * Uses ConditionResolverService to apply dynamic masking during rollback.
	 *
	 * @param Model $model The model being rolled back
	 * @return ApprovalEvent The updated approval event
	 *
	 * @throws Throwable When database transaction fails
	 */
	public function rollback(Model $model): ApprovalEvent
	{
		$conditionResolver = app(ConditionResolverService::class);

		return DB::transaction(function () use ($model, $conditionResolver) {
			$approvalEvent = $this->storeService->store($model);

			$approvalComponents = ApprovalComponent::with('contributors')
				->where('approval_id', $approvalEvent->approval_id)
				->get();
			$approvalComponents = $conditionResolver->resolve($model, $approvalComponents, $approvalEvent->approval_id);

			$binary = 0;
			$allowedTypes = config('approval.group', []);
			$userModel = config('approval.user');

			$approvableIdsByType = [];
			foreach ($approvalComponents as $component) {
				foreach ($component->contributors as $contributor) {
					if (in_array($contributor->approvable_type, $allowedTypes, true)) {
						$approvableIdsByType[$contributor->approvable_type][] = $contributor->approvable_id;
					}
				}
			}

			$approvables = [];
			$userIds = [];

			foreach ($approvableIdsByType as $approvableType => $approvableIds) {
				/** @var Model $approvableModel */
				$approvableModel = app($approvableType);
				$entities = $approvableModel->whereIn($approvableModel->getKeyName(), array_unique($approvableIds))->get();

				foreach ($entities as $entity) {
					$approvables[$approvableType][$entity->getKey()] = $entity;
					if ($entity instanceof ApprovalContributorInterface) {
						foreach ($entity->getApproverIds() as $userId) {
							$userIds[] = $userId;
						}
					}
				}
			}

			$users = $userModel::whereIn('id', array_unique($userIds))->get()->keyBy('id');

			foreach ($approvalComponents as $component) {
				$binary |= 1 << $component->step;

				/** @var ApprovalEventComponent $approvalEventComponent */
				$approvalEventComponent = ApprovalEventComponent::updateOrCreate([
					'approval_event_id' => $approvalEvent->id,
					'step' => 0 | 1 << $component->step,
				], [
					'name' => $component->name,
					'type' => $component->type,
					'color' => $component->color,
					'approved_at' => null,
					'cancelled_at' => null,
					'rejected_at' => null,
					'rollback_at' => $this->now,
				]);

				$existingContributorUserIds = ApprovalEventContributor::where('approval_event_component_id', $approvalEventComponent->id)
					->pluck('user_id')
					->flip()
					->toArray();

				$collectorUserIds = collect();

				foreach ($component->contributors as $contributor) {
					$approvableType = $contributor->approvable_type;

					if (in_array($approvableType, $allowedTypes, true)) {
						$approvableEntity = $approvables[$approvableType][$contributor->approvable_id] ?? null;

						if ($approvableEntity instanceof ApprovalContributorInterface) {
							foreach ($approvableEntity->getApproverIds() as $userId) {
								$foundUser = $users->get($userId);
								if ($foundUser) {
									if (!isset($existingContributorUserIds[$foundUser->id])) {
										$newContributor = new ApprovalEventContributor;
										$newContributor->approval_event_component_id = $approvalEventComponent->id;
										$newContributor->user_id = $foundUser->id;
										$newContributor->save();
										$existingContributorUserIds[$foundUser->id] = true;
									}
									$collectorUserIds->push($foundUser->id);
								}
							}
						}
					} else {
						$userId = (int)$contributor->approvable_id;
						if (!isset($existingContributorUserIds[$userId])) {
							$newContributor = new ApprovalEventContributor;
							$newContributor->approval_event_component_id = $approvalEventComponent->id;
							$newContributor->user_id = $userId;
							$newContributor->save();
							$existingContributorUserIds[$userId] = true;
						}
						$collectorUserIds->push($userId);
					}
				}

				ApprovalEventContributor::where('approval_event_component_id', $approvalEventComponent->id)
					->whereNotIn('user_id', $collectorUserIds)
					->delete();
			}

			$approvalEvent->status = ApprovalStatusEnum::DRAFT;
			$approvalEvent->step = 0;
			$approvalEvent->approved_at = null;
			$approvalEvent->cancelled_at = null;
			$approvalEvent->rejected_at = null;
			$approvalEvent->rollback_at = $this->now;
			$approvalEvent->target = $binary;
			$approvalEvent->save();

			return $approvalEvent;
		});
	}

	/**
	 * Forces an approval event to a specific state.
	 *
	 * Bypasses the normal approval flow and immediately sets the desired state.
	 * Typically used for administrative or system-level operations.
	 *
	 * @param Model $model The model being force-approved
	 * @param int|null $binary The binary step value to set
	 * @param string|null $status The status to set (defaults to APPROVED)
	 * @return ApprovalEvent The updated approval event
	 *
	 * @throws Throwable When database transaction fails
	 */
	public function force(Model $model, ?int $binary = null, ?string $status = null): ApprovalEvent
	{
		return DB::transaction(function () use ($model, $binary, $status) {
			$approvalEvent = $this->storeService->store($model);

			$forcedBinaryValue = $binary ?? 0;
			$defaultForcedStatus = $forcedBinaryValue === 0
				? ApprovalStatusEnum::DRAFT
				: ApprovalStatusEnum::APPROVED;
			$forcedApprovalStatus = ApprovalStatusEnum::from($status ?? $defaultForcedStatus->value);

			$approvalEvent->status = $forcedApprovalStatus;
			$this->resetApprovalEventTimestamps($approvalEvent);
			$this->resetApprovalEventComponentTimestamps($approvalEvent);

			if ($forcedApprovalStatus === ApprovalStatusEnum::APPROVED) {
				$approvalEvent->step |= $forcedBinaryValue;

				if (($approvalEvent->step & $approvalEvent->target) === $approvalEvent->target) {
					$approvalEvent->approved_at = $this->now;
					$approvalEvent->components()->update([
						'approved_at' => $this->now,
						'rejected_at' => null,
						'cancelled_at' => null,
						'rollback_at' => null,
					]);
				}

				$forcedComponentQuery = $approvalEvent->components()->orderBy('step');
				app(BitmaskQueryService::class)->whereMaskFullyInside($forcedComponentQuery, 'step', $forcedBinaryValue);
				$forcedApprovalEventComponentIds = $forcedComponentQuery->pluck('id');

				ApprovalEventComponent::whereKey($forcedApprovalEventComponentIds)->update([
					'approved_at' => $this->now,
					'rejected_at' => null,
					'cancelled_at' => null,
					'rollback_at' => null,
				]);
			} elseif ($forcedApprovalStatus === ApprovalStatusEnum::REJECTED) {
				$approvalEvent->rejected_at = $this->now;
			} elseif ($forcedApprovalStatus === ApprovalStatusEnum::CANCELED) {
				$approvalEvent->cancelled_at = $this->now;
			} elseif ($forcedApprovalStatus === ApprovalStatusEnum::ROLLBACK) {
				$approvalEvent->rollback_at = $this->now;
			} else {
				$approvalEvent->step = 0;
			}

			$approvalEvent->save();

			return $approvalEvent;
		});
	}

	private function resetApprovalEventTimestamps(ApprovalEvent $approvalEvent): void
	{
		$approvalEvent->approved_at = null;
		$approvalEvent->rejected_at = null;
		$approvalEvent->cancelled_at = null;
		$approvalEvent->rollback_at = null;
	}

	private function resetApprovalEventComponentTimestamps(ApprovalEvent $approvalEvent): void
	{
		$approvalEventComponentIds = $approvalEvent->components()->pluck('id');

		$approvalEvent->components()->update([
			'approved_at' => null,
			'rejected_at' => null,
			'cancelled_at' => null,
			'rollback_at' => null,
		]);

		ApprovalEventContributor::whereIn('approval_event_component_id', $approvalEventComponentIds)->update([
			'approved_at' => null,
			'rejected_at' => null,
			'cancelled_at' => null,
			'rollback_at' => null,
		]);
	}

	/**
	 * Gets the first event component based on a binary step or approval event step.
	 *
	 * If binary is set, finds the component matching the exact binary step value.
	 * If binary is not set:
	 * 1. Checks if the approval event is PARALLEL type. If so, prioritizing finding a
	 *    pending component that the current $user is a contributor for.
	 * 2. Otherwise (SEQUENTIAL or no matching user component), returns the first
	 *    pending component based on step order.
	 *
	 * @param ApprovalEvent $approvalEvent The approval event to get component from
	 * @param int|null $binary Optional binary step to filter by
	 * @param Authenticatable|null $user Optional user to find specific component for (Parallel only)
	 * @return ApprovalEventComponent|null The matching approval event component
	 */
	private function getFirstEventComponent(ApprovalEvent $approvalEvent, ?int $binary = null, ?Authenticatable $user = null): ?ApprovalEventComponent
	{
		if ($binary !== null) {
			$approvalEventComponentQuery = ApprovalEventComponent::where('approval_event_id', $approvalEvent->id)
				->whereNull('approved_at')
				->orderBy('step')
				->lockForUpdate();

			app(BitmaskQueryService::class)->whereMaskContains($approvalEventComponentQuery, 'step', $binary);

			return $approvalEventComponentQuery->first();
		}

		/**
		 * Parallel Approval: Prioritize components the user can approve
		 */
		if ($user && $approvalEvent->type === ApprovalTypeEnum::PARALLEL) {
			$userComponent = ApprovalEventComponent::where('approval_event_id', $approvalEvent->id)
				->whereNull('approved_at')
				->whereHas('contributors', function (Builder $query) use ($user) {
					$query->where('user_id', $user->id);
				})
				->orderBy('step')
				->lockForUpdate()
				->first();

			if ($userComponent) {
				return $userComponent;
			}
		}

		/**
		 * Sequential / Fallback: Return first pending component
		 */
		return ApprovalEventComponent::where('approval_event_id', $approvalEvent->id)
			->whereNull('approved_at')
			->orderBy('step')
			->lockForUpdate()
			->first();
	}
}
