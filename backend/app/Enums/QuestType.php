<?php

namespace App\Enums;

enum QuestType: string
{
    case Quest = 'quest';
    case Verification = 'verification';
    case Survey = 'survey';
}
