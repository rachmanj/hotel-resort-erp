<?php

namespace Tests\Unit;

use App\Services\IndonesianNumberWordsService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IndonesianNumberWordsTest extends TestCase
{
    #[DataProvider('rupiahAmounts')]
    public function test_it_spells_rupiah_amounts_in_indonesian(float $amount, string $expected): void
    {
        $this->assertSame($expected, (new IndonesianNumberWordsService)->rupiah($amount));
    }

    /**
     * @return array<string, array{float, string}>
     */
    public static function rupiahAmounts(): array
    {
        return [
            'client sample down payment' => [310_000_000, 'Tiga Ratus Sepuluh Juta Rupiah'],
            'zero' => [0, 'Nol Rupiah'],
            'one thousand uses seribu' => [1_000, 'Seribu Rupiah'],
            'eleven uses sebelas' => [11, 'Sebelas Rupiah'],
            'teens use belas' => [15_000, 'Lima Belas Ribu Rupiah'],
            'hundreds use seratus' => [155_000_000, 'Seratus Lima Puluh Lima Juta Rupiah'],
            'proforma total' => [9_000_000, 'Sembilan Juta Rupiah'],
            'nightly rate' => [1_500_000, 'Satu Juta Lima Ratus Ribu Rupiah'],
            'mixed thousands' => [2_345_678, 'Dua Juta Tiga Ratus Empat Puluh Lima Ribu Enam Ratus Tujuh Puluh Delapan Rupiah'],
            'billions use miliar' => [1_250_000_000, 'Satu Miliar Dua Ratus Lima Puluh Juta Rupiah'],
            'cents are spelled as sen' => [1_000.5, 'Seribu Rupiah Lima Puluh Sen'],
        ];
    }
}
