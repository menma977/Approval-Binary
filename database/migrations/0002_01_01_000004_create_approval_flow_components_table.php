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
		Schema::create('approval_flow_components', function (Blueprint $table) {
			$table->ulid('id')->primary()->index();
			$table->foreignUlid('approval_flow_id')->constrained('approval_flows')->cascadeOnDelete();
			$table->foreignUlid('approval_dictionary_id')->constrained('approval_dictionaries')->cascadeOnDelete();
			$table->string('key');
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
		Schema::dropIfExists('approval_flow_components');
	}
};
