<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Name suffix shown after the last name (Jr., Sr., II ...). */
enum NameSuffix: string
{
    use HasOptions;

    case Jr = 'Jr.';
    case Sr = 'Sr.';
    case II = 'II';
    case III = 'III';
    case IV = 'IV';
    case V = 'V';

    public function label(): string
    {
        return $this->value;
    }
}
