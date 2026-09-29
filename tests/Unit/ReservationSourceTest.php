<?php

namespace Tests\Unit;

use App\Enums\DirectChannel;
use App\Enums\ReservationSource;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReservationSourceTest extends TestCase
{
    public function test_categorised_returns_five_guest_categories(): void
    {
        $values = array_map(fn (ReservationSource $case) => $case->value, ReservationSource::categorised());

        $this->assertSame(
            ['ota', 'travel_agent', 'corporate', 'direct', 'walkin'],
            $values,
        );
    }

    #[DataProvider('legacySourceProvider')]
    public function test_legacy_sources_still_cast(string $value): void
    {
        $source = ReservationSource::from($value);

        $this->assertTrue($source->isLegacy() || in_array($source, ReservationSource::categorised(), true));
        $this->assertNotEmpty($source->label());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function legacySourceProvider(): array
    {
        return [
            'phone' => ['phone'],
            'web' => ['web'],
            'telegram' => ['telegram'],
            'agent' => ['agent'],
            'walkin' => ['walkin'],
            'ota' => ['ota'],
        ];
    }

    public function test_legacy_mapping_shape(): void
    {
        $mapping = ReservationSource::legacyMapping();

        $this->assertSame('travel_agent', $mapping['agent']['source']);
        $this->assertNull($mapping['agent']['direct_channel']);

        $this->assertSame('direct', $mapping['phone']['source']);
        $this->assertSame(DirectChannel::Phone->value, $mapping['phone']['direct_channel']);

        $this->assertSame('direct', $mapping['web']['source']);
        $this->assertSame(DirectChannel::Web->value, $mapping['web']['direct_channel']);

        $this->assertSame('direct', $mapping['telegram']['source']);
        $this->assertSame(DirectChannel::Telegram->value, $mapping['telegram']['direct_channel']);

        $this->assertSame('walkin', $mapping['walkin']['source']);
        $this->assertNull($mapping['walkin']['direct_channel']);

        $this->assertSame('ota', $mapping['ota']['source']);
        $this->assertNull($mapping['ota']['direct_channel']);
    }
}
