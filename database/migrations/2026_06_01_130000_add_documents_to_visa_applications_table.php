<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visa_applications', function (Blueprint $table) {
            $table->string('passport_photo')->nullable()->after('due_amount');
            $table->string('personal_photo')->nullable()->after('passport_photo');
            $table->string('medical_appointment_page')->nullable()->after('personal_photo');
            $table->string('visa_copy')->nullable()->after('medical_appointment_page');
        });
    }

    public function down(): void
    {
        Schema::table('visa_applications', function (Blueprint $table) {
            $table->dropColumn([
                'passport_photo',
                'personal_photo',
                'medical_appointment_page',
                'visa_copy'
            ]);
        });
    }
};
