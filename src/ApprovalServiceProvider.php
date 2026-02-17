<?php

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
					__DIR__ . "/../config/approval.php" => config_path(
						"approval.php",
					),
				],
				"approval-config",
			);

			$this->publishes(
				[
					__DIR__ . "/../lang" => $this->app->langPath("vendor/approval"),
				],
				"approval-lang",
			);
		}

		if (config('approval.is_auditable')) {
			ApprovalCoreAbstract::observe(AuditObserver::class);
		}
	}
}
