<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Renders rupiah amounts in Indonesian words for printed documents
 * ("terbilang"), e.g. 310000000 becomes "Tiga Ratus Sepuluh Juta Rupiah".
 */
class IndonesianNumberWordsService
{
    /**
     * @var array<int, string>
     */
    private const UNITS = [
        0 => '',
        1 => 'satu',
        2 => 'dua',
        3 => 'tiga',
        4 => 'empat',
        5 => 'lima',
        6 => 'enam',
        7 => 'tujuh',
        8 => 'delapan',
        9 => 'sembilan',
        10 => 'sepuluh',
        11 => 'sebelas',
    ];

    public function rupiah(float|int|string $amount): string
    {
        $cents = (int) round(abs((float) $amount) * 100);
        $rupiah = intdiv($cents, 100);
        $sen = $cents % 100;

        $words = $this->titleCase($this->convert($rupiah)).' Rupiah';

        if ($sen > 0) {
            $words .= ' '.$this->titleCase($this->convert($sen)).' Sen';
        }

        return ((float) $amount < 0 ? 'Minus ' : '').$words;
    }

    public function convert(int $number): string
    {
        if ($number === 0) {
            return 'nol';
        }

        return $this->compose($number);
    }

    private function compose(int $number): string
    {
        return match (true) {
            $number <= 0 => '',
            $number < 12 => self::UNITS[$number],
            $number < 20 => $this->compose($number - 10).' belas',
            $number < 100 => $this->compose(intdiv($number, 10)).' puluh '.$this->compose($number % 10),
            $number < 200 => 'seratus '.$this->compose($number - 100),
            $number < 1_000 => $this->compose(intdiv($number, 100)).' ratus '.$this->compose($number % 100),
            $number < 2_000 => 'seribu '.$this->compose($number - 1_000),
            $number < 1_000_000 => $this->compose(intdiv($number, 1_000)).' ribu '.$this->compose($number % 1_000),
            $number < 1_000_000_000 => $this->compose(intdiv($number, 1_000_000)).' juta '.$this->compose($number % 1_000_000),
            $number < 1_000_000_000_000 => $this->compose(intdiv($number, 1_000_000_000)).' miliar '.$this->compose($number % 1_000_000_000),
            default => $this->compose(intdiv($number, 1_000_000_000_000)).' triliun '.$this->compose($number % 1_000_000_000_000),
        };
    }

    private function titleCase(string $words): string
    {
        return Str::title(trim(preg_replace('/\s+/', ' ', $words) ?? ''));
    }
}
