<?php

namespace App\Enum;

enum PatientGender: string
{
    case MALE = 'MALE';
    case FEMALE = 'FEMALE';
    case OTHER = 'OTHER';
    case NOT_SPECIFIED = 'NOT_SPECIFIED';

    public function label(): string
    {
        return match ($this) {
            self::MALE => 'Homme',
            self::FEMALE => 'Femme',
            self::OTHER => 'Autre',
            self::NOT_SPECIFIED => 'Non renseigné',
        };
    }
}
