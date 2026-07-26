<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained();
            $table->foreignId('hotel_id')->nullable()->constrained();
            $table->unsignedBigInteger('chat_id')->nullable()->unique();
            $table->string('telegram_username', 50)->nullable();
            $table->string('link_code', 10)->nullable();
            $table->timestamp('link_code_expires_at')->nullable();
            $table->timestamp('linked_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_users');
    }
};
