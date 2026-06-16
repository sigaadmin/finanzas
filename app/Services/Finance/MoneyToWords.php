<?php

namespace App\Services\Finance;

class MoneyToWords
{
    public function convert(int $amountPesos): string
    {
        return trim($this->numberToWords($amountPesos)).' PESOS 00/100 M.N.';
    }

    private function numberToWords(int $number): string
    {
        if ($number === 0) {
            return 'CERO';
        }

        $parts = [];

        $millions = intdiv($number, 1000000);
        if ($millions > 0) {
            $parts[] = $millions === 1 ? 'UN MILLON' : $this->numberToWords($millions).' MILLONES';
            $number %= 1000000;
        }

        $thousands = intdiv($number, 1000);
        if ($thousands > 0) {
            $parts[] = $thousands === 1 ? 'MIL' : $this->numberToWords($thousands).' MIL';
            $number %= 1000;
        }

        if ($number > 0) {
            $parts[] = $this->underThousand($number);
        }

        return implode(' ', $parts);
    }

    private function underThousand(int $number): string
    {
        $hundreds = [
            1 => 'CIENTO',
            2 => 'DOSCIENTOS',
            3 => 'TRESCIENTOS',
            4 => 'CUATROCIENTOS',
            5 => 'QUINIENTOS',
            6 => 'SEISCIENTOS',
            7 => 'SETECIENTOS',
            8 => 'OCHOCIENTOS',
            9 => 'NOVECIENTOS',
        ];

        if ($number === 100) {
            return 'CIEN';
        }

        $words = [];
        $hundred = intdiv($number, 100);
        if ($hundred > 0) {
            $words[] = $hundreds[$hundred];
            $number %= 100;
        }

        if ($number > 0) {
            $words[] = $this->underHundred($number);
        }

        return implode(' ', $words);
    }

    private function underHundred(int $number): string
    {
        $units = [
            1 => 'UNO',
            2 => 'DOS',
            3 => 'TRES',
            4 => 'CUATRO',
            5 => 'CINCO',
            6 => 'SEIS',
            7 => 'SIETE',
            8 => 'OCHO',
            9 => 'NUEVE',
            10 => 'DIEZ',
            11 => 'ONCE',
            12 => 'DOCE',
            13 => 'TRECE',
            14 => 'CATORCE',
            15 => 'QUINCE',
            20 => 'VEINTE',
            30 => 'TREINTA',
            40 => 'CUARENTA',
            50 => 'CINCUENTA',
            60 => 'SESENTA',
            70 => 'SETENTA',
            80 => 'OCHENTA',
            90 => 'NOVENTA',
        ];

        if (isset($units[$number])) {
            return $units[$number];
        }

        if ($number < 20) {
            return 'DIECI'.$units[$number - 10];
        }

        if ($number < 30) {
            return 'VEINTI'.$units[$number - 20];
        }

        $ten = intdiv($number, 10) * 10;
        $unit = $number % 10;

        return $units[$ten].' Y '.$units[$unit];
    }
}
