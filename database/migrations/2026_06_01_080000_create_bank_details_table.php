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
        if (!Schema::hasTable('bank_details')) {
            Schema::create('bank_details', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
                $table->string('person_name');
                $table->string('bank_name');
                $table->string('account_number');
                $table->decimal('balance', 15, 2)->default(0);
                $table->string('card_type')->nullable(); // e.g., 'Credit Card', 'Debit Card'
                $table->date('updated_date')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_details');
    }
};
