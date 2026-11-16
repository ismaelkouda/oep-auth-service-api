<?php

namespace App\Enums;

enum OtpRequestResult
{
    case Sent;
    case TooSoon;
    case LimitReached;
}
