<?php

namespace App\Enums;

/** How a consultation/appointment takes place. */
enum ConsultationType: string
{
    case ONLINE = 'online';
    case OFFICE = 'office';
    case PHONE = 'phone';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
