<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_conversation_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_user_id')->constrained();
            $table->string('flow', 50);
            $table->string('step', 50);
            $table->json('payload')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('telegram_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_conversation_states');
    }
};
