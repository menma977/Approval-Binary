<?php

namespace Menma\Approval\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Menma\Approval\Interfaces\ApprovalServiceInterface;
use Menma\Approval\Models\ApprovalEvent;
use Throwable;

/**
 * Main entry point for the binary approval system.
 *
 * Provides a fluent API for configuring and executing approval operations.
 * Delegates actual work to focused sub-services:
 * - EventStoreService: event creation and contributor resolution
 * - EventActionService: approve, reject, cancel, rollback, force
 * - ConditionResolverService: dynamic masking filter
 */
class BinaryService implements ApprovalServiceInterface
{
	protected Model $model;

	protected ?int $binary = null;

	protected ?string $status = null;

	protected int $userId;

	protected string $userModel;

	protected EventStoreService $storeService;

	protected EventActionService $actionService;

	public function __construct()
	{
		$this->userModel = config('approval.user');
		$this->storeService = app(EventStoreService::class);
		$this->actionService = app(EventActionService::class);
	}

	/**
	 * Creates a new instance of the service for a given model.
	 *
	 * This static factory method initializes the service with a model instance,
	 * allowing for fluent method chaining in the approval workflow.
	 *
	 * @param string $type The morph class type of the model to be approved
	 * @param int|string $id The primary key of the model to be approved
	 * @return BinaryService A new instance configured for the given model
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
	 *
	 * Configures which specific approval step should be processed
	 * using a binary flag system where each bit represents a step.
	 *
	 * @param int $binary The binary flag value representing the step to process
	 * @return static Returns the current instance for method chaining
	 */
	public function binary(int $binary): static
	{
		$this->binary = $binary;

		return $this;
	}

	/**
	 * Sets the status for the approval process.
	 *
	 * Defines the desired status state for the approval workflow.
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
	 *
	 * Configures which user should be associated with the approval action.
	 * The user will be the one performing the approval, rejection, or cancellation.
	 *
	 * @param int $user The ID of the user to associate with the approval
	 * @return static Returns the current instance for method chaining
	 */
	public function user(int $user): static
	{
		$foundUser = app($this->userModel)::find($user);
		if (!$foundUser) {
			throw ValidationException::withMessages([
				'message' => trans('approval::approval.error.user_not_found'),
			]);
		}

		$this->userId = $user;

		return $this;
	}

	/**
	 * Retrieves the approval event for the current model with its relationships.
	 *
	 * Fetches the approval event and its associated components and contributors.
	 * Can be filtered by status and binary step if they have been set.
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
	 *
	 * @return ApprovalEvent The existing or newly created approval event
	 */
	public function store(): ApprovalEvent
	{
		try {
			return $this->storeService->store($this->model);
		} catch (Throwable $exception) {
			if ($exception instanceof ValidationException) {
				throw $exception;
			}

			throw ValidationException::withMessages([
				'message' => trans('approval::approval.error.store_failed', [
					'error' => $exception->getMessage(),
				]),
			]);
		}
	}

	/**
	 * Approves the current approval step for the configured user.
	 *
	 * @return ApprovalEvent The updated approval event
	 */
	public function approve(): ApprovalEvent
	{
		try {
			return $this->actionService->approve($this->model, $this->resolveUser(), $this->binary);
		} catch (Throwable $exception) {
			if ($exception instanceof ValidationException) {
				throw $exception;
			}

			throw ValidationException::withMessages([
				'message' => trans('approval::approval.error.approve_failed', [
					'error' => $exception->getMessage(),
				]),
			]);
		}
	}

	/**
	 * Rejects the current approval step for the configured user.
	 *
	 * @return ApprovalEvent The updated approval event
	 */
	public function reject(): ApprovalEvent
	{
		try {
			return $this->actionService->reject($this->model, $this->resolveUser(), $this->binary);
		} catch (Throwable $exception) {
			if ($exception instanceof ValidationException) {
				throw $exception;
			}

			throw ValidationException::withMessages([
				'message' => trans('approval::approval.error.reject_failed', [
					'error' => $exception->getMessage(),
				]),
			]);
		}
	}

	/**
	 * Cancels the approval process for the configured user.
	 *
	 * @return ApprovalEvent The updated approval event
	 */
	public function cancel(): ApprovalEvent
	{
		try {
			return $this->actionService->cancel($this->model, $this->resolveUser(), $this->binary);
		} catch (Throwable $exception) {
			if ($exception instanceof ValidationException) {
				throw $exception;
			}

			throw ValidationException::withMessages([
				'message' => trans('approval::approval.error.cancel_failed', [
					'error' => $exception->getMessage(),
				]),
			]);
		}
	}

	/**
	 * Rolls back the approval event to its initial draft state.
	 *
	 * @return ApprovalEvent The updated approval event
	 */
	public function rollback(): ApprovalEvent
	{
		try {
			return $this->actionService->rollback($this->model, $this->resolveUser());
		} catch (Throwable $exception) {
			if ($exception instanceof ValidationException) {
				throw $exception;
			}

			throw ValidationException::withMessages([
				'message' => trans('approval::approval.error.rollback_failed', [
					'error' => $exception->getMessage(),
				]),
			]);
		}
	}

	/**
	 * Forces an approval event to a specific state.
	 *
	 * @return ApprovalEvent The updated approval event
	 */
	public function force(): ApprovalEvent
	{
		try {
			return $this->actionService->force($this->model, $this->binary, $this->status);
		} catch (Throwable $exception) {
			if ($exception instanceof ValidationException) {
				throw $exception;
			}

			throw ValidationException::withMessages([
				'message' => trans('approval::approval.error.force_failed', [
					'error' => $exception->getMessage(),
				]),
			]);
		}
	}

	/**
	 * Resolves the configured user ID to an Authenticatable instance.
	 *
	 * @return \Illuminate\Contracts\Auth\Authenticatable The resolved user
	 */
	private function resolveUser(): Authenticatable
	{
		return app($this->userModel)::findOrFail($this->userId);
	}
}
