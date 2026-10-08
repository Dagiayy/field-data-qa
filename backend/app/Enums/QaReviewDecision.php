<?php

namespace App\Enums;

enum QaReviewDecision: string
{
    case Approve = 'approve';
    case Reject = 'reject';
    case FlagBackcheck = 'flag_backcheck';
    case SendBack = 'send_back';
}
