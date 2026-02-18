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

/**
 * Interface for models that support conditional dynamic masking.
 *
 * Implement this interface on models that need dynamic approval target masks
 * based on their properties (e.g., procurement value, priority level, etc.).
 *
 * When a model implements this interface, the BinaryService will evaluate
 * the model's conditions against ApprovalCondition rules to determine
 * which ApprovalComponents should be included in the approval flow.
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
 *             'value' => $this->total_amount,
 *             'priority' => $this->priority_level,
 *         ];
 *     }
 *
 *     public function getApproverIds(): array
 *     {
 *         return [];
 *     }
 * }
 *
 * The returned array keys must match the 'field' column in the
 * approval_conditions table. The values will be compared against
 * the 'threshold' using the configured 'operator'.
 */
interface DynamicMaskingInterface
{
	/**
	 * Get the key-value pairs used to evaluate approval conditions.
	 *
	 * Each key represents a condition field name (must match the 'field'
	 * column in approval_conditions table), and each value is the current
	 * model property to compare against the threshold.
	 *
	 * @return array<string, mixed> Associative array of field => value pairs
	 */
	public function getApprovalConditions(): array;
}
