<?php

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

			$approvalEvent->status = ApprovalStatusEnum::REJECTED;
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

			$approvalComponent = ApprovalComponent::where('approval_id', $approvalEvent->approval_id)->get();
			$approvalComponent = $conditionResolver->resolve($model, $approvalComponent, $approvalEvent->approval_id);

			$binary = 0;

			foreach ($approvalComponent as $component) {
				$binary |= 1 << $component->step;

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

				$collectorUser = collect();
				$approvalContributor = ApprovalContributor::where('approval_component_id', $component->id)->get();
				$allowedTypes = config('approval.group', []);
				$userModel = config('approval.user');

				foreach ($approvalContributor as $contributor) {
					$type = $contributor->approvable_type;

					if (in_array($type, $allowedTypes)) {
						$approvableEntity = $type::find($contributor->approvable_id);

						if ($approvableEntity instanceof ApprovalContributorInterface) {
							foreach ($approvableEntity->getApproverIds() as $userId) {
								$foundUser = $userModel::find($userId);
								if ($foundUser) {
									$this->storeService->setEventContributor($approvalEventComponent, $foundUser);
									$collectorUser->push($foundUser->id);
								}
							}
						}
					} else {
						$existingContributor = ApprovalEventContributor::where('approval_event_component_id', $approvalEventComponent->id)
							->where('user_id', $contributor->approvable_id)
							->first();
						if (!$existingContributor) {
							$existingContributor = new ApprovalEventContributor;
							$existingContributor->approval_event_component_id = $approvalEventComponent->id;
							$existingContributor->user_id = (int)$contributor->approvable_id;
							$existingContributor->save();
						}
						$collectorUser->push($contributor->approvable_id);
					}
				}

				ApprovalEventContributor::where('approval_event_component_id', $approvalEventComponent->id)
					->whereNotIn('user_id', $collectorUser)
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

			$binaryValue = $binary ?? $approvalEvent->target;

			$approvalEvent->step |= $binaryValue;
			$approvalEvent->status = ApprovalStatusEnum::from($status ?? ApprovalStatusEnum::APPROVED->value);
			if ($approvalEvent->step === $approvalEvent->target) {
				$approvalEvent->approved_at = $this->now;
				$approvalEvent->components()->update([
					'approved_at' => $this->now,
				]);
			}

			$approvalEvent->components()->whereRaw('(step & ?) = step', [$binaryValue])->orderBy('step')->update(['approved_at' => $this->now]);

			$approvalEvent->save();

			return $approvalEvent;
		});
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
			return ApprovalEventComponent::where('approval_event_id', $approvalEvent->id)
				->whereNull('approved_at')
				->whereRaw('(step & ?) = ?', [$binary, $binary])
				->orderBy('step')
				->lockForUpdate()
				->first();
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
