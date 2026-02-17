<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		Schema::create('approval_conditions', function (Blueprint $table) {
			$table->id();
			$table->ulid()->unique();
			$table->foreignId('approval_id')->constrained('approvals')->cascadeOnDelete();
			$table->string('field')->comment('The property name from getApprovalConditions() to compare');
			$table->string('operator')->comment('Comparison operator: <, >, <=, >=, ==, !=');
			$table->string('threshold')->comment('The value to compare against');
			$table->integer('max_step')->comment('Maximum component step to include (inclusive). Steps 0..max_step will be included.');
			$table->integer('priority')->default(0)->comment('Higher priority conditions are evaluated first. First match wins.');
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
		Schema::dropIfExists('approval_conditions');
	}
};
