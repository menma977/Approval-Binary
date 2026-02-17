<?php

namespace Menma\Approval\Abstracts;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Menma\Approval\Traits\AuditByTrait;

class ApprovalCoreAbstract extends Model
{
	use HasUlids, SoftDeletes, AuditByTrait;
}