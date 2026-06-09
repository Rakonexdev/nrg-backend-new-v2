<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('sponsorship_changes', 'identity_phone')) {
            Schema::table('sponsorship_changes', function (Blueprint $table) {
                $table->string('identity_phone')->nullable()->after('full_name');
            });
        }
        if (!Schema::hasColumn('sponsorship_changes', 'alt_phone')) {
            Schema::table('sponsorship_changes', function (Blueprint $table) {
                $table->string('alt_phone')->nullable()->after('phone');
            });
        }
        if (!Schema::hasColumn('sponsorship_changes', 'document')) {
            Schema::table('sponsorship_changes', function (Blueprint $table) {
                $table->string('document')->nullable()->after('remark');
            });
        }
    }

    public function down()
    {
        Schema::table('sponsorship_changes', function (Blueprint $table) {
            $table->dropColumn(['identity_phone', 'alt_phone', 'document']);
        });
    }
};
