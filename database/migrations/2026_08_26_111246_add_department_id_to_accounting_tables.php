<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('general_ledger', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('chart_of_account_id')->constrained()->nullOnDelete();
        });

        Schema::table('folio_items', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('revenue_category_id')->constrained()->nullOnDelete();
        });

        Schema::table('supplier_invoice_lines', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('chart_of_account_id')->constrained()->nullOnDelete();
        });

        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('chart_of_account_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });

        Schema::table('supplier_invoice_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });

        Schema::table('folio_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });

        Schema::table('general_ledger', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
