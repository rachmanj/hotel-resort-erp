<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spa_treatments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('duration_minutes');
            $table->decimal('price', 14, 2);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('hotel_id');
        });

        Schema::create('spa_therapists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->timestamps();

            $table->index('hotel_id');
        });

        Schema::create('spa_therapist_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spa_therapist_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->index(['spa_therapist_id', 'work_date']);
        });

        Schema::create('spa_appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spa_treatment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spa_therapist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('scheduled_at');
            $table->string('status', 20)->default('booked');
            $table->boolean('charged_to_room')->default(false);
            $table->foreignId('folio_item_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['hotel_id', 'scheduled_at']);
            $table->index(['spa_therapist_id', 'scheduled_at']);
            $table->index(['hotel_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spa_appointments');
        Schema::dropIfExists('spa_therapist_schedules');
        Schema::dropIfExists('spa_therapists');
        Schema::dropIfExists('spa_treatments');
    }
};
