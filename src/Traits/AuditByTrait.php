<?php

namespace Menma\Approval\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait AuditByTrait
{
	/**
	 * @return BelongsTo<\Illuminate\Contracts\Auth\Authenticatable, $this>
	 */
	public function createdBy(): BelongsTo
	{
		return $this->belongsTo(config('approval.user'), 'created_by')->withTrashed();
	}

	/**
	 * @return BelongsTo<\Illuminate\Contracts\Auth\Authenticatable, $this>
	 */
	public function deletedBy(): BelongsTo
	{
		return $this->belongsTo(config('approval.user'), 'deleted_by')->withTrashed();
	}

	/**
	 * @return BelongsTo<\Illuminate\Contracts\Auth\Authenticatable, $this>
	 */
	public function updatedBy(): BelongsTo
	{
		return $this->belongsTo(config('approval.user'), 'updated_by')->withTrashed();
	}
}
