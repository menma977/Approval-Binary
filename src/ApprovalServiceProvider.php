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

namespace Menma\Approval;

use Illuminate\Support\ServiceProvider;
use Menma\Approval\Abstracts\ApprovalCoreAbstract;
use Menma\Approval\Observers\AuditObserver;

class ApprovalServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->mergeConfigFrom(__DIR__ . "/../config/approval.php", "approval");
	}

	public function boot(): void
	{
		$this->loadTranslationsFrom(__DIR__ . "/../lang", "approval");

		if ($this->app->runningInConsole()) {
			$this->loadMigrationsFrom(__DIR__ . "/../database/migrations");

			$this->publishes(
				[
					__DIR__ . "/../database/migrations" => database_path("migrations"),
				],
				"approval-migrations",
			);

			$this->publishes(
				[
					__DIR__ . "/../config/approval.php" => config_path(
						"approval.php",
					),
				],
				"approval-config",
			);

			$this->publishes(
				[
					__DIR__ . "/../lang" => $this->app->langPath(),
				],
				"approval-lang",
			);
		}

		if (config('approval.is_auditable')) {
			ApprovalCoreAbstract::observe(AuditObserver::class);
		}
	}
}
