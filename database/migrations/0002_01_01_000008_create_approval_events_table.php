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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Menma\Approval\Enums\ApprovalStatusEnum;
use Menma\Approval\Enums\ApprovalTypeEnum;

return new class extends Migration {
	/**
	 * Run the migrations.
	 *
	 * @noinspection DuplicatedCode
	 */
	public function up(): void
	{
		Schema::create('approval_events', function (Blueprint $table) {
			$table->ulid('id')->primary()->index();
			$table->foreignId('approval_id')->nullable()->constrained('approvals')->cascadeOnDelete();
			$table->integer('step')->default(0)->comment('The step using binary system: 0, 1, 3, 7, etc.');
			$table->integer('target')->default(0)->comment('The target of a binary system: 1, 2, 4, 8, etc.');
			$table->string('requestable_type')->index();
			$table->string('requestable_id')->index();
			$table->integer('type')->default(ApprovalTypeEnum::PARALLEL->value)->comment('The type of workflow (0: parallel or 1: sequential)');
			$table->string('status')->default(ApprovalStatusEnum::DRAFT->value)->index()->comment('The current status of this approval Draft -> Pending -> Approved -> Rejected');
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
		Schema::dropIfExists('approval_events');
	}
};
