<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requisition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained();
            $table->decimal('quantity_requested', 12, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requisition_items');
    }
};
