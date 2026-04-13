<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices');
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('collector_id')->constrained('users');
            $table->decimal('collected_amount', 10, 2);
            $table->enum('payment_channel', ['cash', 'mobile_pay', 'bank_transfer']);
            $table->date('next_due_date')->nullable();
            $table->date('collection_date');
            $table->text('notes')->nullable();
            $table->boolean('is_settled')->default(false);
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('collections');
    }
};