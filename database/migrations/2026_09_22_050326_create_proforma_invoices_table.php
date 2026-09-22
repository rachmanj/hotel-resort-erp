<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proforma_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->unsignedSmallInteger('year');
            $table->string('number', 40);
            $table->string('status', 20)->default('draft');
            $table->unsignedSmallInteger('revision')->default(1);
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('prepared_by', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['hotel_id', 'number']);
            $table->unique(['hotel_id', 'year', 'sequence']);
            $table->index(['reservation_id', 'revision']);
            $table->index(['hotel_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proforma_invoices');
    }
};
