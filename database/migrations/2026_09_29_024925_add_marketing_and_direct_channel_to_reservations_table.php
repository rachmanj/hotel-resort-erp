<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('direct_channel', 20)->nullable()->after('source');
            $table->foreignId('marketing_user_id')->nullable()->after('direct_channel')->constrained('users')->nullOnDelete();
            $table->boolean('is_marketing_non_agent')->default(false)->after('marketing_user_id');
            $table->timestamp('marketing_non_agent_confirmed_at')->nullable()->after('is_marketing_non_agent');

            $table->index('direct_channel', 'reservations_direct_channel_idx');
            $table->index('marketing_user_id', 'reservations_marketing_user_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('reservations_direct_channel_idx');
            $table->dropIndex('reservations_marketing_user_id_idx');
            $table->dropConstrainedForeignId('marketing_user_id');
            $table->dropColumn([
                'direct_channel',
                'is_marketing_non_agent',
                'marketing_non_agent_confirmed_at',
            ]);
        });
    }
};
