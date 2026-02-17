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
use Menma\Approval\Interfaces\ApprovalServiceInterface;
use Menma\Approval\Models\Approval;
use Menma\Approval\Models\ApprovalComponent;
use Menma\Approval\Models\ApprovalContributor;
use Menma\Approval\Models\ApprovalEvent;
use Menma\Approval\Models\ApprovalEventComponent;
use Menma\Approval\Models\ApprovalEventContributor;
use Menma\Approval\Models\ApprovalFlowComponent;
use Throwable;

class BinaryService implements ApprovalServiceInterface
{
	protected Model $model;

	protected ?int $binary = null;

	protected ?string $status = null;

	protected Authenticatable $user;

	protected array $allowedTypes;

	protected string $userModel;

	protected $now;

	public function __construct()
	{
		$this->allowedTypes = config('approval.group', []);
		$this->userModel = config('approval.user');
		$this->now = now();
	}

	/**
	 * Creates a new instance of the service for a given model.
	 * This static factory method initializes the service with a model instance,
	 * allowing for fluent method chaining in the approval workflow.
	 *
	 * @param string $type The morph class type of the model to be approved
	 * @param int|string $id The primary key of the model to be approved
	 * @return BinaryService A new instance of the approval service configured for the given model
	 */
	public static function model(string $type, int|string $id): self
	{
		if (empty($type)) {
			throw ValidationException::withMessages([
				'message' => trans('approval::approval.error.model_type_required'),
			]);
		}

		$instance = new self;
		$instance->model = app($type)->find($id);

		return $instance;
	}

	/**
	 * Sets the binary step value for the approval process.
	 * This method configures which specific approval step should be processed
	 * using a binary flag system where each bit represents a step.
	 *
	 * @param int $binary The binary flag value representing the step to the process
	 * @return static Returns the current instance for method chaining
	 */
	public function binary(int $binary): static
	{
		$this->binary = $binary;

		return $this;
	}

	/**
	 * Sets the status for the approval process.
	 * This method defines the desired status state for the approval workflow.
	 * The status should correspond to values defined in ApprovalStatusEnum.
	 *
	 * @param string $status The status to set for the approval
	 * @return static Returns the current instance for method chaining
	 */
	public function status(string $status): static
	{
		$this->status = $status;

		return $this;
	}

	/**
	 * Sets the user for the approval process.
	 * This method configures which user should be associated with the approval action.
	 * The user will be the one performing the approval, rejection, or cancellation.
	 *
	 * @param int $user The ID of the user to associate with the approval
	 * @return static Returns the current instance for method chaining
	 */
	public function user(int $user): static
	{
		$user = app($this->userModel)::find($user);
		if (!$user) {
			throw ValidationException::withMessages([
				'message' => trans('approval::approval.error.user_not_found'),
			]);
		}

		$this->user = $user;

		return $this;
	}

	/**
	 * Retrieves the approval event for the current model with its relationships.
	 * This method fetches the approval event and its associated components and contributors.
	 * It can be filtered by status and binary step if they have been set on the service instance.
	 */
	public function get(): ?ApprovalEvent
	{
		return ApprovalEvent::with([
			'requestable',
			'components.contributors.user',
		])
			->withSum('components', 'step')
			->where('requestable_type', $this->model->getMorphClass())
			->where('requestable_id', $this->model->getKey())
			->when($this->status, function ($query) {
				return $query->where('status', $this->status);
			})->when($this->binary, function ($query) {
				return $query->where('approvable_id', $this->binary);
			})
			->first();
	}

	/**
	 * Creates or retrieves an approval event for the current model.
	 * This method handles the creation of approval events, components, and contributors
	 * based on the flow component configuration.
	 * If an approval event already exists
	 * for the model, it returns the existing one.
	 * Otherwise, it creates a new one with
	 * all necessary parts and contributors based on the approval flow configuration.
	 *
	 *
	 * @throws Throwable When a flow component is not found for the current model
	 *
	 * @noinspection DuplicatedCode
	 */
	public function store(): ApprovalEvent
	{
		return DB::transaction(function () {
			$approvalEvent = ApprovalEvent::where('requestable_type', $this->model->getMorphClass())->where('requestable_id', $this->model->getKey())->lockForUpdate()->first();
			if (!$approvalEvent) {
				$flowComponent = ApprovalFlowComponent::where('key', $this->model->getMorphClass())->first();
				if ($flowComponent) {
					$approval = Approval::where('approval_flow_id', $flowComponent->approval_flow_id)->first();
					if ($approval) {
						$approvalId = $approval->id;
						$approvalType = $approval->type;
					}
				}

				$approvalId ??= null;
				$approvalType ??= ApprovalTypeEnum::PARALLEL;

				$approvalEvent = new ApprovalEvent;
				$approvalEvent->requestable_type = $this->model->getMorphClass();
				$approvalEvent->requestable_id = $this->model->getKey();
				$approvalEvent->approval_id = $approvalId;
				$approvalEvent->type = $approvalType;
				$approvalEvent->status = ApprovalStatusEnum::DRAFT;
				$approvalEvent->step = 0;
				$approvalEvent->target = 0;
				$approvalEvent->save();

				$approvalComponent = ApprovalComponent::where('approval_id', $approvalId)->get();
				$binary = 0;
				$hasAnyContributor = false;
				$componentsWithoutContributors = [];

				foreach ($approvalComponent as $component) {
					$binary |= 1 << $component->step;

					$approvalEventComponent = new ApprovalEventComponent;
					$approvalEventComponent->approval_event_id = $approvalEvent->id;
					$approvalEventComponent->name = $component->name;
					$approvalEventComponent->step = 0 | 1 << $component->step;
					$approvalEventComponent->color = $component->color;
					$approvalEventComponent->type = $component->type;
					$approvalEventComponent->save();

					$approvalContributor = ApprovalContributor::where('approval_component_id', $component->id)->get();
					$componentHasContributor = false;

					foreach ($approvalContributor as $contributor) {
						$type = $contributor->approvable_type;

						if (in_array($type, $this->allowedTypes)) {
							$approvableEntity = $type::find($contributor->approvable_id);

							if ($approvableEntity instanceof ApprovalContributorInterface) {
								foreach ($approvableEntity->getApproverIds() as $userId) {
									$user = $this->userModel::find($userId);
									if ($user) {
										$this->setEventContributor($approvalEventComponent, $user);
										$componentHasContributor = true;
									}
								}
							}
						} else {
							$approvalContributor = new ApprovalEventContributor;
							$approvalContributor->approval_event_component_id = $approvalEventComponent->id;
							$approvalContributor->user_id = (int)$contributor->approvable_id;
							$approvalContributor->save();
							$componentHasContributor = true;
						}
					}

					if ($componentHasContributor) {
						$hasAnyContributor = true;
					} else {
						$approvalEventComponent->approved_at = $this->now;
						$approvalEventComponent->save();
						$componentsWithoutContributors[] = $component->step;
					}
				}

				$approvalEvent->status = ApprovalStatusEnum::DRAFT;
				$approvalEvent->target = $binary;

				if (!$hasAnyContributor) {
					$approvalEvent->status = ApprovalStatusEnum::APPROVED;
					$approvalEvent->step = $binary;
					$approvalEvent->approved_at = $this->now;
				} else {
					foreach ($componentsWithoutContributors as $step) {
						$approvalEvent->step |= 1 << $step;
					}

					if (($approvalEvent->step & $binary) === $binary) {
						$approvalEvent->status = ApprovalStatusEnum::APPROVED;
						$approvalEvent->approved_at = $this->now;
					}
				}

				if (!$flowComponent) {
					$approvalEvent->status = ApprovalStatusEnum::APPROVED;
					$approvalEvent->step = $approvalEvent->target;
					$approvalEvent->approved_at = $this->now;
				}

				$approvalEvent->save();
			}

			return $approvalEvent;
		});
	}

	/**
	 * Approves the current approval step for the given user.
	 * This method handles the approval process by:
	 * 1. Creating or retrieving the approval event
	 * 2. Validating the component and contributor existence
	 * 3. Updating approval status
	 * 4. Checking and updating the overall approval status if all contributors approved
	 *
	 * For OR-type components, approval by any contributor is enough.
	 * For AND-type components, all contributors must approve.
	 *
	 * @return ApprovalEvent The updated approval event
	 *
	 * @throws Throwable When database transaction fails
	 */
	public function approve(): ApprovalEvent
	{
		return DB::transaction(function () {
			$approvalEvent = $this->store();

			if ($approvalEvent->is_approved || $approvalEvent->is_rejected || $approvalEvent->is_cancelled) {
				return $approvalEvent;
			}

			$approvalEventComponent = $this->getFirstEventComponent($approvalEvent);
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
					->where('user_id', $this->user->id)
					->lockForUpdate()
					->first();

				if (!$approvalEventContributor) {
					throw ValidationException::withMessages([
						'approval_event_contributor' => trans('approval::approval.fail.action.cost', [
							'action' => 'Approve',
							'attribute' => $approvalEventComponent->name,
							'target' => $this->getUserName(),
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
	 * This method handles the rejection process based on component type:
	 * - For OR type: Immediately rejects if any contributor rejects
	 * - For AND type: Compares approvals vs. rejections:
	 *   - If more approvals, continues
	 *   - If more rejections, rejects
	 *   - If 50-50, approves (tie goes to approval)
	 *
	 * The rejection is performed within a database transaction to ensure data integrity.
	 *
	 * @return ApprovalEvent The updated approval event
	 *
	 * @throws Throwable When database transaction fails
	 */
	public function reject(): ApprovalEvent
	{
		return DB::transaction(function () {
			$approvalEvent = $this->store();

			$approvalEventComponent = $this->getFirstEventComponent($approvalEvent);
			if (!$approvalEventComponent) {
				$approvalEvent->status = ApprovalStatusEnum::REJECTED;
				$approvalEvent->rejected_at = $this->now;
				$approvalEvent->save();

				return $approvalEvent;
			}

			$approvalEventContributor = ApprovalEventContributor::where('approval_event_component_id', $approvalEventComponent->id)->where('user_id', $this->user->id)->first();
			if (!$approvalEventContributor) {
				throw ValidationException::withMessages([
					'approval_event_contributor' => trans('approval::approval.fail.action.cost', [
						'action' => 'Reject',
						'attribute' => $approvalEventComponent->name,
						'target' => $this->getUserName(),
					]),
				]);
			}

			$approvalEventContributor->rejected_at = $this->now;
			$approvalEventContributor->save();

			$shouldRejectComponent = false;

			if ($approvalEventComponent->type === ContributorTypeEnum::OR) {
				$shouldRejectComponent = true;
			} else {
				$contributors = ApprovalEventContributor::where('approval_event_component_id', $approvalEventComponent->id)->get();

				$approvalCount = $contributors->filter(function ($contributor) {
					return $contributor->approved_at !== null;
				})->count();

				$rejectionCount = $contributors->filter(function ($contributor) {
					return $contributor->rejected_at !== null;
				})->count();

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
	 *  Cancels the approval process for the current user.
	 *  This method checks if the user trying to cancel is the same user who approved,
	 *  and if so, marks all processes as canceled.
	 *  It resets all timestamps and sets the approval status to reject.
	 *
	 *  The cancellation is performed within a database transaction to ensure data integrity.
	 *
	 * @return ApprovalEvent The updated approval event
	 *
	 * @throws Throwable When database transaction fails
	 */
	public function cancel(): ApprovalEvent
	{
		return DB::transaction(function () {
			$approvalEvent = $this->store();

			$approvalEventComponent = $this->getFirstEventComponent($approvalEvent);
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
	 * This method performs the following actions:
	 * 1. Retrieves or creates the approval event
	 * 2. Resets all approval event components by clearing their timestamps
	 * 3. Resets all approval event contributors to draft status
	 * 4. Resets the main approval event to draft status and clears its step counter
	 *
	 * The rollback process also synchronizes contributors based on the current approval
	 * configuration, removing any that are no longer relevant and adding new ones.
	 *
	 * @return ApprovalEvent The updated approval event
	 *
	 * @throws Throwable When database transaction fails
	 */
	public function rollback(): ApprovalEvent
	{
		return DB::transaction(function () {
			$approvalEvent = $this->store();

			$approvalComponent = ApprovalComponent::where('approval_id', $approvalEvent->approval_id)->get();
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
				foreach ($approvalContributor as $contributor) {
					$type = $contributor->approvable_type;

					if (in_array($type, $this->allowedTypes)) {
						$approvableEntity = $type::find($contributor->approvable_id);

						if ($approvableEntity instanceof ApprovalContributorInterface) {
							foreach ($approvableEntity->getApproverIds() as $userId) {
								$user = $this->userModel::find($userId);
								if ($user) {
									$this->setEventContributor($approvalEventComponent, $user);
									$collectorUser->push($user->id);
								}
							}
						}
					} else {
						$approvalContributor = ApprovalEventContributor::where('approval_event_component_id', $approvalEventComponent->id)
							->where('user_id', $contributor->approvable_id)
							->first();
						if (!$approvalContributor) {
							$approvalContributor = new ApprovalEventContributor;
							$approvalContributor->approval_event_component_id = $approvalEventComponent->id;
							$approvalContributor->user_id = (int)$contributor->approvable_id;
							$approvalContributor->save();
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
	 * Forces an approval event to a specific state by setting the step and status directly.
	 * This method bypasses the normal approval flow and immediately sets the desired state.
	 * Typically used for administrative or system-level operations where manual
	 * intervention is required to override the standard approval process.
	 *
	 * This method performs the following:
	 * 1. Creates or retrieves the approval event
	 * 2. Sets the specified binary step value
	 * 3. Updates the status (defaults to APPROVED if not specified)
	 *
	 * @return ApprovalEvent The updated approval event
	 *
	 * @throws Throwable When database transaction fails
	 */
	public function force(): ApprovalEvent
	{
		return DB::transaction(function () {
			$approvalEvent = $this->store();

			$binary = $this->binary ?? $approvalEvent->target;

			$approvalEvent->step |= $binary;
			$approvalEvent->status = ApprovalStatusEnum::from($this->status ?? ApprovalStatusEnum::APPROVED->value);
			if ($approvalEvent->step === $approvalEvent->target) {
				$approvalEvent->approved_at = $this->now;
				$approvalEvent->components()->update([
					'approved_at' => $this->now,
				]);
			}

			$approvalEvent->components()->whereRaw('(step & ?) = step', [$binary])->orderBy('step')->update(['approved_at' => $this->now]);

			$approvalEvent->save();

			return $approvalEvent;
		});
	}

	/**
	 * Gets the display name for the current user.
	 * This method attempts to retrieve a human-readable name from various common properties.
	 * Falls back to the user's authentication identifier if no name property is available.
	 *
	 * @return string The user's display name
	 */
	protected function getUserName(): string
	{
		if ($this->user instanceof Model) {
			return $this->user->name ??
				$this->user->username ??
				$this->user->full_name ??
				$this->user->email ??
				'User #' . $this->user->getAuthIdentifier();
		}

		return 'User #' . $this->user->getAuthIdentifier();
	}

	/**
	 * Creates or retrieves an approval event contributor for a given component and user.
	 * This method ensures that a contributor record exists for the specified user and component,
	 * creating a new one if necessary.
	 *
	 * @param ApprovalEventComponent $approvalEventComponent The approval event component to associate the contributor with
	 * @param Authenticatable $user The user to be set as a contributor
	 * @return ApprovalEventContributor|null The existing or newly created contributor record, or null if creation fails
	 */
	protected function setEventContributor(ApprovalEventComponent $approvalEventComponent, Authenticatable $user): ?ApprovalEventContributor
	{
		$approvalContributor = ApprovalEventContributor::where('approval_event_component_id', $approvalEventComponent->id)
			->where('user_id', $user->id)
			->first();
		if (!$approvalContributor) {
			$approvalContributor = new ApprovalEventContributor;
			$approvalContributor->approval_event_component_id = $approvalEventComponent->id;
			$approvalContributor->user_id = $user->id;
			$approvalContributor->save();
		}

		return $approvalContributor;
	}

	/**
	 * Gets the first event component based on a binary step or approval event step.
	 * If binary is not set, finds the first component where the step has not been completed.
	 * If binary is set, finds the component matching the exact binary step value.
	 *
	 * This method uses bitwise operations to determine which components are still pending
	 * approval based on the current state of the approval event.
	 *
	 * @param ApprovalEvent $approvalEvent The approval event to get component from
	 * @return ApprovalEventComponent|null The first matching approval event component
	 */
	private function getFirstEventComponent(ApprovalEvent $approvalEvent): ?ApprovalEventComponent
	{

		if (!$this->binary) {
			$approvalEventComponent = ApprovalEventComponent::where('approval_event_id', $approvalEvent->id)
				->where(function (Builder $query) use ($approvalEvent) {
					$query
						->whereHas('event', fn(Builder $query) => $query->whereColumn('target', '!=', 'step'))
						->orWhereRaw('(step & ?) = 0', [$approvalEvent->step]);
				})
				->orderBy('step')
				->lockForUpdate()
				->first();
		} else {
			$approvalEventComponent = ApprovalEventComponent::where('approval_event_id', $approvalEvent->id)
				->where(function (Builder $query) {
					$query
						->whereHas('event', fn(Builder $query) => $query->where('target', $this->binary))
						->orWhereRaw('(step & ?) = 0', [$this->binary]);
				})
				->orderBy('step')
				->lockForUpdate()
				->first();
		}

		return $approvalEventComponent;
	}
}
