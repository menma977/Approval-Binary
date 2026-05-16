<?php

declare(strict_types=1);
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

/**
 * Interface for models that support conditional dynamic masking.
 *
 * Implement this interface on models that need dynamic approval target masks
 * based on their properties (e.g., procurement value, priority level, etc.).
 *
 * When a model implements this interface, the resolver evaluates condition
 * component JSON expressions against this data to decide which components
 * are copied into a new approval event snapshot.
 *
 * Models that do NOT implement this interface will continue using
 * the existing static bitmask behavior (all components included).
 *
 * Example Usage:
 *
 * class Procurement extends ApprovalAbstract implements DynamicMaskingInterface
 * {
 *     public function getApprovalConditions(): array
 *     {
 *         return [
 *             'amount' => $this->total_amount,
 *             'user' => [
 *                 'position' => [
 *                     'name' => $this->user?->position?->name,
 *                 ],
 *             ],
 *         ];
 *     }
 * }
 */
interface DynamicMaskingInterface
{
	/**
	 * Get data used by approval condition component expressions.
	 *
	 * Expression paths use Laravel data_get dot notation, for example:
	 * user.position.name, amount, department.code.
	 *
	 * @return array<string, mixed>
	 */
	public function getApprovalConditions(): array;
}
