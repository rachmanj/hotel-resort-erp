<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained();
            $table->string('reservation_code', 20)->unique();
            $table->foreignId('guest_id')->constrained();
            $table->string('source')->default('walkin');
            $table->string('status')->default('confirmed');
            $table->date('arrival_date');
            $table->date('departure_date');
            $table->smallInteger('adults')->default(1);
            $table->smallInteger('children')->default(0);
            $table->text('special_requests')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_via')->default('web');
            $table->text('cancelled_reason')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('arrival_date');
            $table->index('guest_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
