<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('asset_type', 30);
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('location')->nullable();
            $table->date('purchased_at')->nullable();
            $table->date('warranty_until')->nullable();
            $table->string('status', 20)->default('operational');
            $table->timestamps();

            $table->index(['hotel_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
