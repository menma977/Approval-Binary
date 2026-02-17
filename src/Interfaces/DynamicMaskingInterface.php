<?php

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
