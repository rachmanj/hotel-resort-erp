<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proforma_invoices', function (Blueprint $table) {
            $table->decimal('received_total', 14, 2)->default(0)->after('total');
            $table->decimal('outstanding_total', 14, 2)->default(0)->after('received_total');
        });
    }

    public function down(): void
    {
        Schema::table('proforma_invoices', function (Blueprint $table) {
            $table->dropColumn(['received_total', 'outstanding_total']);
        });
    }
};
