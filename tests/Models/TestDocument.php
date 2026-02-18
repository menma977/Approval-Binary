<?php

namespace Menma\Approval\Tests\Models;

use Menma\Approval\Abstracts\ApprovalAbstract;

class TestDocument extends ApprovalAbstract
{
	protected $table = 'test_documents';
	protected $guarded = [];
}