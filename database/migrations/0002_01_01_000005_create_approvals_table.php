<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Menma\Approval\Enums\ApprovalTypeEnum;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		Schema::create('approvals', function (Blueprint $table) {
			$table->id();
			$table->ulid()->unique();
			$table->foreignUlid('approval_flow_id')->constrained('approval_flows')->cascadeOnDelete();
			$table->string('name');
			$table->integer('type')->default(ApprovalTypeEnum::PARALLEL->value)->comment('The type of workflow (0: parallel or 1: sequential)');
			$table->boolean('can_change')->default(true)->comment('Whether the approval can be changed');
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
		Schema::dropIfExists('approvals');
	}
};
