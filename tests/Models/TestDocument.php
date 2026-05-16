<?php

declare(strict_types=1);

namespace Menma\Approval\Tests\Models;

use Menma\Approval\Abstracts\ApprovalAbstract;
use Menma\Approval\Interfaces\DynamicMaskingInterface;

class TestDocument extends ApprovalAbstract implements DynamicMaskingInterface
{
	protected $table = 'test_documents';

	protected $guarded = [];

	/**
	 * @return array<string, mixed>
	 */
	public function getApprovalConditions(): array
	{
		return [
			'position' => $this->position,
			'amount' => $this->amount,
			'user' => [
				'position' => [
					'name' => $this->position,
				],
			],
		];
	}
}
