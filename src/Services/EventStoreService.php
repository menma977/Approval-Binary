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

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Menma\Approval\Enums\ApprovalStatusEnum;
use Menma\Approval\Enums\ApprovalTypeEnum;
use Menma\Approval\Interfaces\ApprovalContributorInterface;
use Menma\Approval\Models\Approval;
use Menma\Approval\Models\ApprovalComponent;
use Menma\Approval\Models\ApprovalContributor;
use Menma\Approval\Models\ApprovalEvent;
use Menma\Approval\Models\ApprovalEventComponent;
use Menma\Approval\Models\ApprovalEventContributor;
use Menma\Approval\Models\ApprovalFlowComponent;
use Throwable;

/**
 * Handles the creation and retrieval of approval events.
 *
 * This service manages the lifecycle of creating an ApprovalEvent,
 * including resolving flow components, building event components,
 * and assigning contributors from both direct users and group-based sources.
 */
class EventStoreService
{
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
	 * Creates or retrieves an approval event for the given model.
	 *
	 * If an approval event already exists for the model, returns the existing one.
	 * Otherwise, creates a new one with all necessary components and contributors
	 * based on the approval flow configuration.
	 *
	 * Uses the ConditionResolverService to apply dynamic masking when the model
	 * implements DynamicMaskingInterface.
	 *
	 * @param Model $model The model requesting approval
	 * @return ApprovalEvent The existing or newly created approval event
	 *
	 * @throws Throwable When the database transaction fails
	 *
	 * @noinspection DuplicatedCode
	 */
	public function store(Model $model): ApprovalEvent
	{
		$conditionResolver = app(ConditionResolverService::class);

		return DB::transaction(function () use ($model, $conditionResolver) {
			$approvalEvent = ApprovalEvent::where('requestable_type', $model->getMorphClass())
				->where('requestable_id', $model->getKey())
				->lockForUpdate()
				->first();

			if (!$approvalEvent) {
				$flowComponent = ApprovalFlowComponent::where('key', $model->getMorphClass())->first();
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
				$approvalEvent->requestable_type = $model->getMorphClass();
				$approvalEvent->requestable_id = $model->getKey();
				$approvalEvent->approval_id = $approvalId;
				$approvalEvent->type = $approvalType;
				$approvalEvent->status = ApprovalStatusEnum::DRAFT;
				$approvalEvent->step = 0;
				$approvalEvent->target = 0;
				$approvalEvent->save();

				$approvalComponent = ApprovalComponent::where('approval_id', $approvalId)->get();
				$approvalComponent = $conditionResolver->resolve($model, $approvalComponent, $approvalId);

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
							$newEventContributor = new ApprovalEventContributor;
							$newEventContributor->approval_event_component_id = $approvalEventComponent->id;
							$newEventContributor->user_id = (int)$contributor->approvable_id;
							$newEventContributor->save();
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
	 * Creates or retrieves an approval event contributor for a given component and user.
	 *
	 * Ensures that a contributor record exists for the specified user and component,
	 * creating a new one if necessary.
	 *
	 * @param ApprovalEventComponent $approvalEventComponent The component to associate the contributor with
	 * @param Authenticatable $user The user to be set as a contributor
	 * @return ApprovalEventContributor|null The existing or newly created contributor record
	 */
	public function setEventContributor(ApprovalEventComponent $approvalEventComponent, Authenticatable $user): ?ApprovalEventContributor
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
	 * Gets the display name for a user.
	 *
	 * Attempts to retrieve a human-readable name from various common properties.
	 * Falls back to the user's authentication identifier if no name property is available.
	 *
	 * @param Authenticatable $user The user to get the display name for
	 * @return string The user's display name
	 */
	public function getUserName(Authenticatable $user): string
	{
		if ($user instanceof Model) {
			return $user->name ??
				$user->username ??
				$user->full_name ??
				$user->email ??
				'User #' . $user->getAuthIdentifier();
		}

		return 'User #' . $user->getAuthIdentifier();
	}
}
