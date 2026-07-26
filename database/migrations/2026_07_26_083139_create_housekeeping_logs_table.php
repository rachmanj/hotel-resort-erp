<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('housekeeping_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('housekeeping_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status');
            $table->foreignId('changed_by')->constrained('users')->cascadeOnDelete();
            $table->string('changed_via')->default('web');
            $table->text('notes')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['room_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('housekeeping_logs');
    }
};
