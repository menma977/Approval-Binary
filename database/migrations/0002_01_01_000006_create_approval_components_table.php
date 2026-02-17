<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Menma\Approval\Enums\ContributorTypeEnum;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		Schema::create('approval_components', function (Blueprint $table) {
			$table->id();
			$table->ulid()->unique();
			$table->foreignId('approval_id')->constrained('approvals')->cascadeOnDelete();
			$table->string('name');
			$table->integer('step')->default(0)->comment('The step using binary system: 1, 2, 3, 4, etc.');
			$table->integer('type')->default(ContributorTypeEnum::OR->value)->comment('The type of approval logic (0:and/1:or)');
			$table->string('color')->default('#000000')->comment('The color of the component');
			$table->boolean('can_drag')->default(true);
			$table->boolean('can_edit')->default(true);
			$table->boolean('can_delete')->default(true);
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
		Schema::dropIfExists('approval_components');
	}
};
