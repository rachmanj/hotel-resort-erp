<?php

namespace App\Console\Commands;

use App\Enums\AgentType;
use App\Enums\CommissionType;
use App\Models\Agent;
use App\Models\AgentTierRate;
use App\Models\Hotel;
use App\Models\RoomType;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportAgentContractRates extends Command
{
    protected $signature = 'agents:import-contract {--dry-run : Print what would happen without writing to the database}';

    protected $description = 'Import 2026 travel agent contracts and tier rates from the Pratasaba CSV';

    private const CSV_PATH = 'database/data/pratasaba_agents_2026.csv';

    private const VALID_FROM = '2026-01-01';

    private const VALID_TO = '2026-12-31';

    /** @var array<string, array{codes: list<string>, rates: array<string, int>}> */
    private const CONTRACT_RATES = [
        'Deluxe' => [
            'codes' => ['DLTG', 'DLKS', 'DLKS 06', 'DLKG', 'DLTS'],
            'rates' => [
                'A' => 1300000,
                'B' => 1400000,
                'C' => 1500000,
                'D' => 1590000,
            ],
        ],
        'Grand Deluxe' => [
            'codes' => ['GDKS', 'GDTS'],
            'rates' => [
                'A' => 1650000,
                'B' => 1750000,
                'C' => 1900000,
                'D' => 1990000,
            ],
        ],
        'Suite' => [
            'codes' => ['STKS', 'STTS'],
            'rates' => [
                'A' => 2100000,
                'B' => 2200000,
                'C' => 2500000,
                'D' => 2590000,
            ],
        ],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $csvPath = base_path(self::CSV_PATH);

        if (! is_readable($csvPath)) {
            $this->error('CSV file not found: '.self::CSV_PATH);

            return self::FAILURE;
        }

        $hotel = Hotel::query()->orderBy('id')->first();

        if ($hotel === null) {
            $this->error('No hotel found. Seed a hotel before running this import.');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('Dry run — no changes will be written.');
        }

        $agentStats = $this->importAgents($csvPath, $hotel->id, $dryRun);
        $tierStats = $this->importTierRates($dryRun);

        $this->newLine();
        $this->info('Agent import summary:');
        $this->line("  Created: {$agentStats['created']}");
        $this->line("  Skipped (existing): {$agentStats['skipped_existing']}");
        $this->line("  Skipped (duplicate row): {$agentStats['skipped_duplicate']}");

        $this->newLine();
        $this->info('Tier rate import summary:');
        $this->line("  Created: {$tierStats['created']}");
        $this->line("  Updated: {$tierStats['updated']}");
        $this->line("  Missing room types: {$tierStats['missing_room_types']}");

        $this->newLine();
        $this->info('2026 contract tier rates:');
        $this->table(
            ['Tier', 'Room Type', 'Code', 'Nightly Rate'],
            $tierStats['summary_rows'],
        );

        return self::SUCCESS;
    }

    /**
     * @return array{created: int, skipped_existing: int, skipped_duplicate: int}
     */
    private function importAgents(string $csvPath, int $hotelId, bool $dryRun): array
    {
        $created = 0;
        $skippedExisting = 0;
        $skippedDuplicate = 0;

        $handle = fopen($csvPath, 'r');

        if ($handle === false) {
            $this->error('Unable to open CSV file.');

            return [
                'created' => 0,
                'skipped_existing' => 0,
                'skipped_duplicate' => 0,
            ];
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);
            $this->error('CSV file is empty.');

            return [
                'created' => 0,
                'skipped_existing' => 0,
                'skipped_duplicate' => 0,
            ];
        }

        while (($row = fgetcsv($handle)) !== false) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            $company = trim((string) ($row[0] ?? ''));
            $pic = $this->nullableString($row[1] ?? null);
            $phone = $this->nullableString($row[4] ?? null);
            $email = $this->nullableString($row[5] ?? null);
            $tier = strtoupper(trim((string) ($row[6] ?? '')));
            $duplicateOfRow = trim((string) ($row[8] ?? ''));

            if ($duplicateOfRow !== '') {
                $skippedDuplicate++;
                $this->line("  Skipping duplicate row for \"{$company}\" (duplicate_of_row={$duplicateOfRow})");

                continue;
            }

            if ($company === '') {
                continue;
            }

            $exists = Agent::query()
                ->withoutGlobalScope('hotel')
                ->where('hotel_id', $hotelId)
                ->where('name', $company)
                ->exists();

            if ($exists) {
                $skippedExisting++;

                continue;
            }

            $code = $this->generateUniqueCode($company, $hotelId);

            if ($dryRun) {
                $this->line("  Would create agent: {$company} ({$code}, tier {$tier})");
                $created++;

                continue;
            }

            Agent::query()->create([
                'hotel_id' => $hotelId,
                'agent_type' => AgentType::Travel->value,
                'rate_category' => $tier,
                'name' => $company,
                'code' => $code,
                'contact_person' => $pic,
                'phone' => $phone,
                'email' => $email,
                'commission_type' => CommissionType::Percent->value,
                'commission_percent' => 0,
                'is_active' => true,
            ]);

            $created++;
        }

        fclose($handle);

        return [
            'created' => $created,
            'skipped_existing' => $skippedExisting,
            'skipped_duplicate' => $skippedDuplicate,
        ];
    }

    /**
     * @return array{
     *     created: int,
     *     updated: int,
     *     missing_room_types: int,
     *     summary_rows: list<array{0: string, 1: string, 2: string, 3: string}>
     * }
     */
    private function importTierRates(bool $dryRun): array
    {
        $created = 0;
        $updated = 0;
        $missingRoomTypes = 0;
        $summaryRows = [];

        foreach (self::CONTRACT_RATES as $groupLabel => $group) {
            foreach ($group['codes'] as $code) {
                $roomType = RoomType::query()->where('code', $code)->first();

                if ($roomType === null) {
                    $missingRoomTypes++;
                    $this->warn("  Room type not found for code \"{$code}\", skipping tier rates.");

                    continue;
                }

                foreach ($group['rates'] as $tier => $nightlyRate) {
                    $summaryRows[] = [
                        $tier,
                        $groupLabel,
                        $code,
                        number_format($nightlyRate, 0, ',', '.'),
                    ];

                    if ($dryRun) {
                        $created++;

                        continue;
                    }

                    $tierRate = AgentTierRate::query()->updateOrCreate(
                        [
                            'rate_category' => $tier,
                            'room_type_id' => $roomType->id,
                            'valid_from' => self::VALID_FROM,
                        ],
                        [
                            'nightly_rate' => $nightlyRate,
                            'valid_to' => self::VALID_TO,
                            'is_active' => true,
                        ],
                    );

                    if ($tierRate->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $updated++;
                    }
                }
            }
        }

        usort($summaryRows, function (array $left, array $right): int {
            $tierCompare = strcmp($left[0], $right[0]);

            if ($tierCompare !== 0) {
                return $tierCompare;
            }

            return strcmp($left[2], $right[2]);
        });

        return [
            'created' => $created,
            'updated' => $updated,
            'missing_room_types' => $missingRoomTypes,
            'summary_rows' => $summaryRows,
        ];
    }

    private function generateUniqueCode(string $companyName, int $hotelId): string
    {
        $base = Str::upper(Str::slug($companyName, ''));
        $base = preg_replace('/[^A-Z0-9]/', '', $base) ?: 'AGENT';
        $base = Str::limit($base, 26, '');

        $code = $base;
        $suffix = 1;

        while (Agent::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $hotelId)
            ->where('code', $code)
            ->exists()) {
            $code = Str::limit($base, 24, '').$suffix;
            $suffix++;
        }

        return $code;
    }

    /**
     * @param  array<int, string|null>|false  $row
     */
    private function isBlankRow(array|false $row): bool
    {
        if ($row === false) {
            return true;
        }

        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
