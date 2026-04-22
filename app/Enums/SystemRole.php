<?php

namespace App\Enums;

enum SystemRole: string
{
    case Admin = 'Admin';
    case Menedzer = 'Menedżer';
    case Projektant = 'Projektant';
    case Operator = 'Operator';
    case Ksiegowosc = 'Księgowość';
    case Klient = 'Klient';
}
