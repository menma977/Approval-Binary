<?php

return [
	'message' => [
		'fail' => [
			'model' => [
				'type' => 'Model type is required',
				'undefined' => 'Model not found',
			],
			'user' => [
				'undefined' => 'User not found',
			],
			'action' => [
				'cost' => ':action :attribute failed for :target',
			],
			'store' => 'Failed to store approval event: :error',
			'approve' => 'Failed to approve: :error',
			'reject' => 'Failed to reject: :error',
			'cancel' => 'Failed to cancel: :error',
			'rollback' => 'Failed to rollback: :error',
			'force' => 'Failed to force approval: :error',
		],
	],
];
