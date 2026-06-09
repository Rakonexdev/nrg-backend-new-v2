<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('vehicles')) {
            Schema::create('vehicles', function (Blueprint $table) {
                $table->id();
                $table->string('plate_number');
                $table->string('vehicle_name');
                $table->text('description')->nullable();
                $table->string('chassis_no')->nullable();
                $table->date('reg_expiry_date')->nullable();
                
                $table->unsignedBigInteger('company_id')->nullable();
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('set null');

                $table->string('driver_qid');
                $table->string('driver_name');
                $table->string('driver_phone');
                
                $table->dateTime('handover_datetime')->nullable();
                $table->dateTime('return_datetime')->nullable();
                
                $table->boolean('is_active')->default(true);

                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vehicles');
    }
};
