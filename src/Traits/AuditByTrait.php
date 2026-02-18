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
