<?php

namespace Tests\Feature;

use App\Enums\BankAccountType;
use App\Enums\BankReconciliationStatus;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\ChartOfAccount;
use App\Models\Hotel;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BankReconciliationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private BankReconciliation $reconciliation;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $this->hotel = Hotel::query()->create([
            'name' => 'Bank Rec Auth Hotel',
            'code' => 'BRA',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        $bankCoa = ChartOfAccount::query()->create([
            'hotel_id' => $this->hotel->id,
            'account_code' => '1-1400',
            'name' => 'Auth Bank GL',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $bankAccount = BankAccount::query()->create([
            'hotel_id' => $this->hotel->id,
            'bank_name' => 'BCA',
            'account_no' => '1122334455',
            'account_name' => 'Auth Account',
            'type' => BankAccountType::Bank->value,
            'chart_of_account_id' => $bankCoa->id,
            'currency_code' => 'IDR',
            'is_active' => true,
        ]);

        $this->reconciliation = BankReconciliation::factory()->for($bankAccount)->create([
            'period_end_date' => '2026-09-30',
            'periode' => '2026-09-01',
            'status' => BankReconciliationStatus::InReview,
        ]);
    }

    public function test_user_without_bank_rec_permission_cannot_view_index(): void
    {
        $user = $this->userForRole('housekeeping');

        $this->actingAs($user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get(route('accounting.bank-rec.index'))
            ->assertForbidden();
    }

    /**
     * @param  array<string, bool>  $expectations
     */
    #[DataProvider('roleAuthorizationMatrixProvider')]
    public function test_role_authorization_matrix(
        string $role,
        array $expectations,
    ): void {
        $user = $this->userForRole($role);

        $this->assertRouteAccess(
            $user,
            'accounting.bank-rec.index',
            $expectations['view'],
        );

        $this->assertRouteAccess(
            $user,
            'accounting.bank-rec.status',
            $expectations['view'],
            ['bankReconciliation' => $this->reconciliation],
        );

        $this->assertRouteAccess(
            $user,
            'accounting.bank-rec.store',
            $expectations['import'],
            [],
            'post',
            [
                'bank_account_id' => $this->reconciliation->bank_account_id,
                'period_end_date' => '2026-10-31',
                'statement_balance' => 100,
            ],
        );

        $this->assertRouteAccess(
            $user,
            'accounting.bank-rec.auto-match',
            $expectations['reconcile'],
            ['bankReconciliation' => $this->reconciliation],
            'post',
        );

        $this->assertRouteAccess(
            $user,
            'accounting.bank-rec.adjustments.store',
            $expectations['adjust'],
            ['bankReconciliation' => $this->reconciliation],
            'post',
            [
                'statement_line_id' => 1,
                'description' => 'Test',
            ],
        );

        $this->assertRouteAccess(
            $user,
            'accounting.bank-rec.validate',
            $expectations['validate'],
            ['bankReconciliation' => $this->reconciliation],
            'post',
        );
    }

    /**
     * @return array<string, array{0: string, 1: array<string, bool>}>
     */
    public static function roleAuthorizationMatrixProvider(): array
    {
        return [
            'admin' => ['admin', ['view' => true, 'import' => true, 'reconcile' => true, 'adjust' => true, 'validate' => true]],
            'finance' => ['finance', ['view' => true, 'import' => true, 'reconcile' => true, 'adjust' => true, 'validate' => true]],
            'manager' => ['manager', ['view' => true, 'import' => false, 'reconcile' => false, 'adjust' => false, 'validate' => true]],
            'front_office' => ['front_office', ['view' => true, 'import' => false, 'reconcile' => false, 'adjust' => false, 'validate' => false]],
            'housekeeping' => ['housekeeping', ['view' => false, 'import' => false, 'reconcile' => false, 'adjust' => false, 'validate' => false]],
        ];
    }

    private function userForRole(string $role): User
    {
        $user = User::factory()->create(['hotel_id' => $this->hotel->id]);
        $user->assignRole($role);
        $this->hotel->users()->attach($user->id);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $routeParameters
     * @param  array<string, mixed>  $payload
     */
    private function assertRouteAccess(
        User $user,
        string $routeName,
        bool $allowed,
        array $routeParameters = [],
        string $method = 'get',
        array $payload = [],
    ): void {
        $url = route($routeName, $routeParameters);

        $response = $this->actingAs($user)
            ->withSession(['current_hotel_id' => $this->hotel->id]);

        $response = $method === 'post'
            ? $response->post($url, $payload)
            : $response->get($url);

        if ($allowed) {
            $this->assertNotSame(403, $response->getStatusCode());

            return;
        }

        $response->assertForbidden();
    }
}
