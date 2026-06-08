<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('sponsorship_changes', function (Blueprint $table) {
            $table->string('identity_phone')->nullable()->after('full_name');
            $table->string('alt_phone')->nullable()->after('phone');
            $table->string('document')->nullable()->after('remark');
        });
    }

    public function down()
    {
        Schema::table('sponsorship_changes', function (Blueprint $table) {
            $table->dropColumn(['identity_phone', 'alt_phone', 'document']);
        });
    }
};
