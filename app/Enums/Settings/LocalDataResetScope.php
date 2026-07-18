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

    public function description(): string
    {
        return match ($this) {
            self::Ventanilla => 'Elimina cobros, trámites, recibos, cancelaciones, depósitos y reportes SEQ registrados en esta instalación.',
            self::U300 => 'Elimina programas, planeación, ejecución, fichas técnicas e importaciones de U300.',
            self::OwnRevenue => 'Elimina presupuestos, importaciones, planeación, ejecución, fondos, comisiones y archivos de Ingresos Propios.',
            self::All => 'Elimina todos los datos locales y vuelve a crear únicamente el acceso del propietario institucional.',
        };
    }

    /**
     * @return list<string>
     */
    public function preserves(): array
    {
        return match ($this) {
            self::Ventanilla => ['Conceptos de cobro y tarifas oficiales', 'U300 e Ingresos Propios', 'Usuarios y accesos'],
            self::U300 => ['Clasificación del gasto', 'Ventanilla e Ingresos Propios', 'Usuarios y accesos'],
            self::OwnRevenue => ['Clasificación del gasto', 'Ventanilla y U300', 'Usuarios y accesos'],
            self::All => ['Esquema de la base de datos', 'Migraciones instaladas', 'Configuración y registros técnicos'],
        };
    }

    public function isGlobal(): bool
    {
        return $this === self::All;
    }
}
