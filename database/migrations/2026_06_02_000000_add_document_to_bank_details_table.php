<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('bank_details', function (Blueprint $table) {
            $table->string('document')->nullable()->after('updated_date');
        });
    }

    public function down()
    {
        Schema::table('bank_details', function (Blueprint $table) {
            $table->dropColumn('document');
        });
    }
};
