<?php

namespace App\Enums;

enum QaFlagResult: string
{
    case Pass = 'pass';
    case Flag = 'flag';
    case Fail = 'fail';
}
