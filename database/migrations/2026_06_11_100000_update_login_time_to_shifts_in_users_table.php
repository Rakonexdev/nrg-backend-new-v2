<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'allowed_login_start_time')) {
                $table->dropColumn(['allowed_login_start_time', 'allowed_login_end_time']);
            }
            if (!Schema::hasColumn('users', 'allowed_login_shifts')) {
                $table->json('allowed_login_shifts')->nullable()->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('allowed_login_shifts');
            $table->time('allowed_login_start_time')->nullable();
            $table->time('allowed_login_end_time')->nullable();
        });
    }
};
