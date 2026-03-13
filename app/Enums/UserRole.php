<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Novio = 'novio';
    case Novia = 'novia';
    case Padrino1 = 'padrino_1';
    case Padrino2 = 'padrino_2';
    case Padrino3 = 'padrino_3';
    case Madrina1 = 'madrina_1';
    case Madrina2 = 'madrina_2';
    case Madrina3 = 'madrina_3';
    case Colaborador = 'colaborador';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Novio => 'Novio',
            self::Novia => 'Novia',
            self::Padrino1 => 'Padrino 1',
            self::Padrino2 => 'Padrino 2',
            self::Padrino3 => 'Padrino 3',
            self::Madrina1 => 'Madrina 1',
            self::Madrina2 => 'Madrina 2',
            self::Madrina3 => 'Madrina 3',
            self::Colaborador => 'Colaborador',
        };
    }
}
