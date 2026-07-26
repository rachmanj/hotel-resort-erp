<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('category', 30);
            $table->string('unit', 10);
            $table->decimal('current_stock', 12, 2)->default(0);
            $table->decimal('reorder_level', 12, 2)->default(0);
            $table->string('location_type')->nullable();
            $table->unsignedBigInteger('location_id')->nullable();
            $table->timestamps();

            $table->index(['hotel_id', 'category']);
            $table->index(['hotel_id', 'current_stock']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
