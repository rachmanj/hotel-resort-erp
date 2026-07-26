<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_id')->constrained();
            $table->foreignId('reservation_id')->nullable()->constrained();
            $table->string('type');
            $table->text('description');
            $table->foreignId('reported_by')->constrained('users');
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_incidents');
    }
};
