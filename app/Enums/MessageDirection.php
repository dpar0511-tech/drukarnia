<?php

namespace App\Enums;

enum MessageDirection: string
{
    case Przychodzacy = 'przychodzacy';
    case Wychodzacy = 'wychodzacy';
    case Systemowy = 'systemowy';
}
