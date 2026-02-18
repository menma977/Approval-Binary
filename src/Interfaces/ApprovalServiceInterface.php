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

namespace Menma\Approval\Interfaces;


use Menma\Approval\Models\ApprovalEvent;

interface ApprovalServiceInterface
{
	public static function model(string $type, int|string $id): self;

	public function binary(int $binary): static;

	public function status(string $status): static;

	public function user(int $user): static;

	public function get(): ?ApprovalEvent;

	public function store(): ApprovalEvent;

	public function approve(): ApprovalEvent;

	public function reject(): ApprovalEvent;

	public function cancel(): ApprovalEvent;

	public function rollback(): ApprovalEvent;

	public function force(): ApprovalEvent;
}
