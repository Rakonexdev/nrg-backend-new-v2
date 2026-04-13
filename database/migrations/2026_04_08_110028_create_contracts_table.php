<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff');
            $table->foreignId('company_id')->constrained('companies');
            $table->date('joining_date');
            $table->date('contract_start');
            $table->date('contract_end');
            $table->decimal('monthly_salary', 10, 2);
            $table->enum('status', ['active', 'completed', 'terminated'])->default('active');
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('contracts');
    }
};