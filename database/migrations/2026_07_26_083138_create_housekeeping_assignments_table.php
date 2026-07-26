<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('housekeeping_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('housekeeper_id')->constrained('users')->cascadeOnDelete();
            $table->date('assignment_date');
            $table->string('shift')->default('morning');
            $table->string('status')->default('pending');
            $table->foreignId('assigned_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['room_id', 'assignment_date', 'shift']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('housekeeping_assignments');
    }
};
