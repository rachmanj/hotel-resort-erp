<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained();
            $table->string('group_code', 20)->unique();
            $table->string('group_type', 20);
            $table->string('name', 150);
            $table->foreignId('pic_guest_id')->nullable()->constrained('guests')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_mode', 20)->default('per_room');
            $table->date('arrival_date')->nullable();
            $table->date('departure_date')->nullable();
            $table->decimal('deposit_amount', 14, 2)->default(0);
            $table->timestamp('deposit_paid_at')->nullable();
            $table->string('status', 20)->default('draft');
            $table->text('special_requests')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['hotel_id', 'status']);
            $table->index('arrival_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_groups');
    }
};
