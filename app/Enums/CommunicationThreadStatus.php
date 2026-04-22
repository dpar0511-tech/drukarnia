<?php

namespace App\Enums;

enum CommunicationThreadStatus: string
{
    case Nowy = 'nowy';
    case Otwarty = 'otwarty';
    case Zamkniety = 'zamkniety';
}
