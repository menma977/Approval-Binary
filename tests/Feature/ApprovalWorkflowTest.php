<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Validation\ValidationException;
use Menma\Approval\Enums\ApprovalStatusEnum;
use Menma\Approval\Enums\ApprovalTypeEnum;
use Menma\Approval\Enums\ContributorTypeEnum;
use Menma\Approval\Models\Approval;
use Menma\Approval\Models\ApprovalComponent;
use Menma\Approval\Models\ApprovalCondition;
use Menma\Approval\Models\ApprovalConditionComponent;
use Menma\Approval\Models\ApprovalContributor;
use Menma\Approval\Models\ApprovalDictionary;
use Menma\Approval\Models\ApprovalFlow;
use Menma\Approval\Models\ApprovalFlowComponent;
use Menma\Approval\Services\ApprovalService;
use Menma\Approval\Support\ApprovalExpression;
use Menma\Approval\Tests\Models\TestDocument;

beforeEach(function () {
	// Create Users
	$this->user = User::forceCreate(['name' => 'User', 'email' => 'user@example.com', 'password' => 'password']);
	$this->manager = User::forceCreate(['name' => 'Manager', 'email' => 'manager@example.com', 'password' => 'password']);
	$this->director = User::forceCreate(['name' => 'Director', 'email' => 'director@example.com', 'password' => 'password']);

	// Setup Approval Flow (Database Configuration)
	// 1. Register the Model (Dictionary)
	$dictionary = ApprovalDictionary::create([
		'key' => TestDocument::class,
		'name' => 'Test Document',
	]);

	// 2. Create the Flow
	$flow = ApprovalFlow::create([
		'name' => 'Standard Workflow',
	]);

	// 3. Link Model to Flow
	ApprovalFlowComponent::create([
		'approval_flow_id' => $flow->id,
		'approval_dictionary_id' => $dictionary->id,
		'key' => TestDocument::class,
	]);

	// 4. Define Logic Container
	$approval = Approval::create([
		'approval_flow_id' => $flow->id,
		'name' => 'Doc Logic v1',
		'type' => ApprovalTypeEnum::SEQUENTIAL,
	]);
	$this->approval = $approval;

	// 5. Create Steps
	// Step 0: Manager (Bit 0 = 1)
	$stepManager = ApprovalComponent::create([
		'approval_id' => $approval->id,
		'name' => 'Manager Approval',
		'step' => 0,
		'type' => ContributorTypeEnum::OR,
	]);
	$this->stepManager = $stepManager;

	// Step 1: Director (Bit 1 = 2)
	$stepDirector = ApprovalComponent::create([
		'approval_id' => $approval->id,
		'name' => 'Director Approval',
		'step' => 1,
		'type' => ContributorTypeEnum::OR,
	]);
	$this->stepDirector = $stepDirector;

	// 6. Assign Contributors
	ApprovalContributor::create([
		'approval_component_id' => $stepManager->id,
		'approvable_type' => User::class,
		'approvable_id' => $this->manager->id,
	]);

	ApprovalContributor::create([
		'approval_component_id' => $stepDirector->id,
		'approvable_type' => User::class,
		'approvable_id' => $this->director->id,
	]);
});

test('full approval lifecycle', function () {
	// 1. Create Document
	$doc = TestDocument::create(['name' => 'Project Alpha']);

	// 2. Initialize Event
	$doc->initEvent($this->user);

	$doc->refresh();
	expect($doc->event)->not->toBeNull()
		->and($doc->event->status)->toBe(ApprovalStatusEnum::DRAFT)
		->and($doc->event->step)->toBe(0) // No steps completed
		->and($doc->event->target)->toBe(3); // 1 (Manager) + 2 (Director) = 3

	// 3. Manager Approves
	$doc->approve($this->manager);

	$doc->refresh();
	expect($doc->event->step)->toBe(1) // Bit 0 set
	->and($doc->event->status)->toBe(ApprovalStatusEnum::DRAFT); // Still draft, waiting for Director

	// 4. Director Approves
	$doc->approve($this->director);

	$doc->refresh();
	expect($doc->event->step)->toBe(3) // Bit 0 + Bit 1 set
	->and($doc->event->status)->toBe(ApprovalStatusEnum::APPROVED)
		->and($doc->event->approved_at)->not->toBeNull();
});

test('rejection flow', function () {
	$doc = TestDocument::create(['name' => 'Project Beta']);
	$doc->initEvent($this->user);

	// Manager Rejects
	$doc->reject($this->manager);

	$doc->refresh();
	expect($doc->event->status)->toBe(ApprovalStatusEnum::REJECTED)
		->and($doc->event->rejected_at)->not->toBeNull();
});

test('audit columns are filled when model and table support them', function () {
	\Illuminate\Support\Facades\Auth::login($this->user);

	$approvalDictionary = ApprovalDictionary::create([
		'key' => 'audited-key',
		'name' => 'Audited Dictionary',
	]);

	expect($approvalDictionary->created_by)->toBe($this->user->id);
});

test('cancelled component sets cancelled status', function () {
	$doc = TestDocument::create(['name' => 'Project Gamma']);
	$doc->initEvent($this->user);

	$doc->cancel($this->manager);

	$doc->refresh();
	expect($doc->event->status)->toBe(ApprovalStatusEnum::CANCELED)
		->and($doc->event->cancelled_at)->not->toBeNull()
		->and($doc->event->rejected_at)->toBeNull();
});

test('force resets conflicting event state', function () {
	$doc = TestDocument::create(['name' => 'Project Delta']);
	$doc->initEvent($this->user);

	$doc->force($this->user, 3);
	$doc->refresh();

	expect($doc->event->status)->toBe(ApprovalStatusEnum::APPROVED)
		->and($doc->event->approved_at)->not->toBeNull();

	$doc->force($this->user);
	$doc->refresh();

	expect($doc->event->status)->toBe(ApprovalStatusEnum::DRAFT)
		->and($doc->event->step)->toBe(0)
		->and($doc->event->approved_at)->toBeNull()
		->and($doc->event->rejected_at)->toBeNull()
		->and($doc->event->cancelled_at)->toBeNull()
		->and($doc->event->rollback_at)->toBeNull();

	$doc->force($this->user, 0, ApprovalStatusEnum::REJECTED->value);
	$doc->refresh();

	expect($doc->event->status)->toBe(ApprovalStatusEnum::REJECTED)
		->and($doc->event->approved_at)->toBeNull()
		->and($doc->event->rejected_at)->not->toBeNull()
		->and($doc->event->cancelled_at)->toBeNull()
		->and($doc->event->rollback_at)->toBeNull();

	expect($doc->event->components()->whereNotNull('approved_at')->exists())->toBeFalse();
});

test('event accessors resolve pending and current component through portable bitmask query', function () {
	$doc = TestDocument::create(['name' => 'Project Epsilon']);
	$doc->initEvent($this->user);

	\Illuminate\Support\Facades\Auth::login($this->manager);

	$doc->refresh();
	expect($doc->event->component->name)->toBe('Manager Approval')
		->and($doc->event->current_component)->toBeNull()
		->and($doc->event->can_approve)->toBeTrue();

	$doc->approve($this->manager);

	$doc->refresh();
	expect($doc->event->component->name)->toBe('Director Approval')
		->and($doc->event->current_component->name)->toBe('Manager Approval');

	$doc->load('event.components.contributors');
	expect($doc->event->component->name)->toBe('Director Approval')
		->and($doc->event->current_component->name)->toBe('Manager Approval');
});

test('binary service get filters event by component mask', function () {
	$doc = TestDocument::create(['name' => 'Project Zeta']);
	$doc->initEvent($this->user);

	$approvalService = app(ApprovalService::class);

	expect($approvalService->forBinary($doc)->binary(2)->get())->not->toBeNull()
		->and($approvalService->forBinary($doc)->binary(4)->get())->toBeNull();
});

test('explicit binary action selects matching component', function () {
	$doc = TestDocument::create(['name' => 'Project Eta']);
	$doc->initEvent($this->user);

	app(ApprovalService::class)
		->forBinary($doc)
		->user($this->director->id)
		->binary(2)
		->approve();

	$doc->refresh();
	expect($doc->event->step)->toBe(2)
		->and($doc->event->component->name)->toBe('Manager Approval');
});

test('raw bitwise SQL is isolated in bitmask query service', function () {
	$sourceFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../../src'));
	$filesWithRawBitwiseSql = [];

	foreach ($sourceFiles as $sourceFile) {
		if (!$sourceFile->isFile() || $sourceFile->getExtension() !== 'php') {
			continue;
		}

		$sourcePath = $sourceFile->getPathname();
		$sourceCode = file_get_contents($sourcePath);

		if (str_contains($sourceCode, 'whereRaw(') && !str_ends_with($sourcePath, 'BitmaskQueryService.php')) {
			$filesWithRawBitwiseSql[] = $sourcePath;
		}
	}

	expect($filesWithRawBitwiseSql)->toBe([]);
});

test('approval creates default condition and links new components to it', function () {
	$defaultCondition = $this->approval->conditions()->where('priority', 0)->first();

	expect($defaultCondition)->not->toBeNull()
		->and($defaultCondition->conditionComponents()->pluck('approval_component_id')->all())
		->toEqualCanonicalizing([$this->stepManager->id, $this->stepDirector->id]);
});

test('condition priority auto increments after default zero and cannot duplicate per approval', function () {
	$firstCustomCondition = ApprovalCondition::create(['approval_id' => $this->approval->id]);
	$secondCustomCondition = ApprovalCondition::create(['approval_id' => $this->approval->id]);

	expect($firstCustomCondition->priority)->toBe(1)
		->and($secondCustomCondition->priority)->toBe(2);

	ApprovalCondition::create([
		'approval_id' => $this->approval->id,
		'priority' => 2,
	]);
})->throws(
	Illuminate\Database\QueryException::class,
);

test('higher priority condition can narrow driver approval to manager only', function () {
	$driverCondition = ApprovalCondition::create([
		'approval_id' => $this->approval->id,
		'priority' => 1,
	]);

	ApprovalConditionComponent::create([
		'approval_condition_id' => $driverCondition->id,
		'approval_component_id' => $this->stepManager->id,
		'expression' => ApprovalExpression::all()
			->where('position', '==', 'driver')
			->toArray(),
	]);

	$driverDoc = TestDocument::create([
		'name' => 'Driver Request',
		'position' => 'driver',
	]);
	$driverDoc->initEvent($this->user);
	$driverDoc->refresh();

	$staffDoc = TestDocument::create([
		'name' => 'Staff Request',
		'position' => 'staff',
	]);
	$staffDoc->initEvent($this->user);
	$staffDoc->refresh();

	expect($driverDoc->event->target)->toBe(1)
		->and($driverDoc->event->components()->pluck('name')->all())->toBe(['Manager Approval'])
		->and($staffDoc->event->target)->toBe(3)
		->and($staffDoc->event->components()->pluck('name')->all())->toEqualCanonicalizing(['Manager Approval', 'Director Approval']);
});

test('condition expression supports nested path and all logic', function () {
	$specialCondition = ApprovalCondition::create([
		'approval_id' => $this->approval->id,
		'priority' => 1,
	]);

	ApprovalConditionComponent::create([
		'approval_condition_id' => $specialCondition->id,
		'approval_component_id' => $this->stepManager->id,
		'expression' => ApprovalExpression::all()
			->where('user.position.name', '==', 'driver')
			->where('amount', '>', 10000)
			->toArray(),
	]);

	$doc = TestDocument::create([
		'name' => 'Nested Request',
		'position' => 'driver',
		'amount' => 15000,
	]);
	$doc->initEvent($this->user);
	$doc->refresh();

	expect($doc->event->target)->toBe(1);
});

test('running approval keeps condition snapshot until rollback recomputes it', function () {
	$driverCondition = ApprovalCondition::create([
		'approval_id' => $this->approval->id,
		'priority' => 1,
	]);

	ApprovalConditionComponent::create([
		'approval_condition_id' => $driverCondition->id,
		'approval_component_id' => $this->stepManager->id,
		'expression' => ApprovalExpression::all()
			->where('position', '==', 'driver')
			->toArray(),
	]);

	$doc = TestDocument::create([
		'name' => 'Snapshot Request',
		'position' => 'driver',
	]);
	$doc->initEvent($this->user);
	$doc->refresh();

	expect($doc->event->target)->toBe(1);

	$doc->update(['position' => 'staff']);
	$doc->initEvent($this->user);
	$doc->refresh();

	expect($doc->event->target)->toBe(1);

	$doc->rollback($this->user);
	$doc->refresh();

	expect($doc->event->target)->toBe(3);
});

test('invalid condition expression fails loudly', function () {
	$badCondition = ApprovalCondition::create([
		'approval_id' => $this->approval->id,
		'priority' => 1,
	]);

	ApprovalConditionComponent::create([
		'approval_condition_id' => $badCondition->id,
		'approval_component_id' => $this->stepManager->id,
		'expression' => [
			'all' => [
				[
					'path' => 'amount',
					'operator' => 'contains',
					'value' => 1000,
				],
			],
		],
	]);

	$doc = TestDocument::create([
		'name' => 'Bad Expression',
		'amount' => 2000,
	]);
	$doc->initEvent($this->user);
})->throws(ValidationException::class);
