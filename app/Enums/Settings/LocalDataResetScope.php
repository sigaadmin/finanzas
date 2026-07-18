<?php

namespace App\Enums\Settings;

enum LocalDataResetScope: string
{
    case Ventanilla = 'ventanilla';
    case U300 = 'u300';
    case OwnRevenue = 'own-revenue';
    case All = 'all';

    public function confirmationPhrase(): string
    {
        return match ($this) {
            self::Ventanilla => 'BORRAR VENTANILLA',
            self::U300 => 'BORRAR U300',
            self::OwnRevenue => 'BORRAR INGRESOS PROPIOS',
            self::All => 'REINICIAR TODO',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Ventanilla => 'Ventanilla Finanzas',
            self::U300 => 'U300',
            self::OwnRevenue => 'Ingresos Propios',
            self::All => 'Toda la aplicación',
        };
    }
}
