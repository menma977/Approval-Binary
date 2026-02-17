<?php

namespace Menma\Approval\Abstracts;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApprovalCoreAbstract extends Model
{
	use HasUlids, SoftDeletes;
}