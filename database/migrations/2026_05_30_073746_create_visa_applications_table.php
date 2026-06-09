<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('visa_applications')) {
            Schema::create('visa_applications', function (Blueprint $table) {
                $table->id();
                $table->string('serial_no');
                $table->date('vp_expiry_date');
                $table->string('vp_number');
                $table->string('position')->nullable();
                $table->string('nationality')->nullable();
                $table->foreignId('company_id')->constrained()->onDelete('cascade');
                $table->string('full_name');
                $table->string('passport_number')->nullable();
                $table->string('visa_number')->nullable();
                $table->string('description')->nullable();
                $table->date('appointment_date')->nullable();
                $table->string('contract_person')->nullable();
                $table->string('medical_report')->nullable();
                $table->string('attestation_details')->nullable();
                $table->date('payment_date')->nullable();
                $table->decimal('total_amount', 10, 2)->nullable();
                $table->decimal('total_pay', 10, 2)->nullable();
                $table->decimal('due_amount', 10, 2)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visa_applications');
    }
};
