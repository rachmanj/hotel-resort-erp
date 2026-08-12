<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained();
            $table->foreignId('reservation_id')->constrained();
            $table->foreignId('folio_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('base_amount', 14, 2);
            $table->decimal('commission_percent', 5, 2);
            $table->decimal('commission_amount', 14, 2);
            $table->string('status', 20)->default('pending');
            $table->foreignId('ar_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('deduction_folio_item_id')->nullable()->constrained('folio_items')->nullOnDelete();
            $table->timestamp('earned_at');
            $table->timestamps();

            $table->unique('reservation_id');
            $table->index(['agent_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_commissions');
    }
};
