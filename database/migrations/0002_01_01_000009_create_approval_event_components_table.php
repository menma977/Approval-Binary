<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Menma\Approval\Enums\ContributorTypeEnum;

return new class extends Migration {
	/**
	 * Run the migrations.
	 *
	 * @noinspection DuplicatedCode
	 */
	public function up(): void
	{
		Schema::create('approval_event_components', function (Blueprint $table) {
			$table->ulid('id')->primary()->index();
			$table->foreignUlid('approval_event_id')->constrained('approval_events')->cascadeOnDelete();
			$table->string('name');
			$table->smallInteger('step')->default(1)->comment('The step using binary 1 -> 10 -> 100 -> 1000');
			$table->integer('type')->default(ContributorTypeEnum::OR->value)->comment('The type of approval logic (0:and/1:or)');
			$table->string('color')->default('#000000');
			$table->timestamp('approved_at')->nullable();
			$table->timestamp('rejected_at')->nullable();
			$table->timestamp('cancelled_at')->nullable();
			$table->timestamp('rollback_at')->nullable();
			$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
			$table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
			$table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
			$table->timestamps();
			$table->softDeletes();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('approval_event_components');
	}
};
