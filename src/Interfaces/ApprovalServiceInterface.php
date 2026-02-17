<?php

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
