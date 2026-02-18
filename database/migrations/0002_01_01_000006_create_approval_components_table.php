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
