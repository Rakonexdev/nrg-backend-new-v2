<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('sponsorship_changes', function (Blueprint $table) {
            $table->id();
            $table->string('sr_number');
            $table->string('qid_number');
            $table->date('qid_expiry_date')->nullable();
            $table->string('full_name');
            $table->date('submitted_date')->nullable();
            $table->string('being_here')->nullable();
            $table->string('ec_number')->nullable();
            $table->string('computer_card')->nullable();
            $table->foreignId('new_company_id')->constrained('companies')->onDelete('restrict');
            $table->string('phone')->nullable();
            $table->date('approval_date')->nullable();
            $table->date('approval_expiry')->nullable();
            $table->string('labour_contract')->nullable();
            $table->string('final_status')->default('Pending');
            $table->decimal('total_contract_amount', 10, 2)->default(0);
            $table->decimal('pay_amount', 10, 2)->default(0);
            $table->decimal('due_amount', 10, 2)->default(0);
            $table->text('remark')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sponsorship_changes');
    }
};
