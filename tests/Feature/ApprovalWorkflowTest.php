<?php

use Illuminate\Foundation\Auth\User;
use Menma\Approval\Enums\ApprovalStatusEnum;
use Menma\Approval\Enums\ApprovalTypeEnum;
use Menma\Approval\Enums\ContributorTypeEnum;
use Menma\Approval\Models\Approval;
use Menma\Approval\Models\ApprovalComponent;
use Menma\Approval\Models\ApprovalContributor;
use Menma\Approval\Models\ApprovalDictionary;
use Menma\Approval\Models\ApprovalFlow;
use Menma\Approval\Models\ApprovalFlowComponent;
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

	// 5. Create Steps
	// Step 0: Manager (Bit 0 = 1)
	$stepManager = ApprovalComponent::create([
		'approval_id' => $approval->id,
		'name' => 'Manager Approval',
		'step' => 0,
		'type' => ContributorTypeEnum::OR,
	]);

	// Step 1: Director (Bit 1 = 2)
	$stepDirector = ApprovalComponent::create([
		'approval_id' => $approval->id,
		'name' => 'Director Approval',
		'step' => 1,
		'type' => ContributorTypeEnum::OR,
	]);

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