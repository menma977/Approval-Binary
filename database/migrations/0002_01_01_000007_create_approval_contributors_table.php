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
		Schema::create('approval_contributors', function (Blueprint $table) {
			$table->ulid('id')->primary()->index();
			$table->foreignId('approval_component_id')->constrained('approval_components')->cascadeOnDelete();
			$table->text('approvable_type');
			$table->text('approvable_id');
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
		Schema::dropIfExists('approval_contributors');
	}
};
