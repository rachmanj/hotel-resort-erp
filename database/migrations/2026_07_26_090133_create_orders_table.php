<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('order_no')->unique();
            $table->string('order_type', 20);
            $table->foreignId('restaurant_table_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('new');
            $table->foreignId('opened_by')->constrained('users');
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->boolean('charged_to_room')->default(false);
            $table->foreignId('folio_item_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['hotel_id', 'status']);
            $table->index(['hotel_id', 'order_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
