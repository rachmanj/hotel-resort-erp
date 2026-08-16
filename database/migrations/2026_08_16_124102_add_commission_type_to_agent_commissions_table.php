<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_commissions', function (Blueprint $table) {
            $table->string('commission_type')->default('percent')->after('commission_percent');
            $table->decimal('commission_flat_amount', 12, 2)->nullable()->after('commission_type');
        });
    }

    public function down(): void
    {
        Schema::table('agent_commissions', function (Blueprint $table) {
            $table->dropColumn(['commission_type', 'commission_flat_amount']);
        });
    }
};
