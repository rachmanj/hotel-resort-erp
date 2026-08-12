<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained();
            $table->string('agent_type', 20);
            $table->string('name', 150);
            $table->string('code', 30);
            $table->string('channel_code', 30)->nullable();
            $table->string('contact_person', 150)->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->decimal('commission_percent', 5, 2)->default(0);
            $table->string('commission_basis', 20)->default('net_room');
            $table->unsignedInteger('payment_terms_days')->default(30);
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('api_config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['hotel_id', 'code']);
            $table->index(['hotel_id', 'is_active']);
            $table->index('channel_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
