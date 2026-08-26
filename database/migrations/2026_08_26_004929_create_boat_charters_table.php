<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boat_charters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('boat_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('dive_package_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('folio_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('folio_item_id')->nullable()->constrained('folio_items')->nullOnDelete();
            $table->date('trip_date');
            $table->string('destination', 150);
            $table->string('charter_type', 20);
            $table->decimal('price', 12, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('bbm_liters', 10, 2)->nullable();
            $table->decimal('bbm_cost', 12, 2)->nullable();
            $table->string('guide_type', 20);
            $table->decimal('guide_fee', 12, 2)->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['hotel_id', 'trip_date']);
            $table->index(['hotel_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boat_charters');
    }
};
