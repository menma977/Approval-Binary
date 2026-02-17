<?php

namespace Menma\Approval\Enums;

enum ApprovalStatusEnum: string
{
	case DRAFT = 'DRAFT';
	case APPROVED = 'APPROVED';
	case REJECTED = 'REJECTED';
	case CANCELED = 'CANCELED';
	case ROLLBACK = 'ROLLBACK';
}
