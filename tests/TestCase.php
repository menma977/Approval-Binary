<?php

namespace Menma\Approval\Tests;

use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Menma\Approval\ApprovalServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
	use RefreshDatabase;

	protected function setUp(): void
	{
		parent::setUp();
	}

	protected function getPackageProviders($app)
	{
		return [
			ApprovalServiceProvider::class,
		];
	}

	protected function defineEnvironment($app)
	{
		// Use the default Laravel User model for testing
		$app['config']->set('approval.user', User::class);

		// Setup default database to use sqlite :memory:
		$app['config']->set('database.default', 'sqlite');
		$app['config']->set('database.connections.sqlite', [
			'driver' => 'sqlite',
			'database' => ':memory:',
			'prefix' => '',
		]);
	}

	protected function defineDatabaseMigrations()
	{
		// Manually create users table
		$this->app['db']->connection()->getSchemaBuilder()->create('users', function ($table) {
			$table->id();
			$table->string('name');
			$table->string('email')->unique();
			$table->timestamp('email_verified_at')->nullable();
			$table->string('password');
			$table->rememberToken();
			$table->timestamps();
		});

		// Load package migrations (approvals, flows, events, etc.)
		$this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
		// Load test-specific migrations (for our dummy document)
		$this->loadMigrationsFrom(__DIR__ . '/database/migrations');
	}
}