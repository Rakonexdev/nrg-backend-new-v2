<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collector_id')->constrained('users');
            $table->date('settlement_date');
            $table->decimal('total_collected', 10, 2);
            $table->decimal('total_settled', 10, 2);
            $table->enum('status', ['pending', 'confirmed', 'discrepancy'])->default('pending');
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('settlements');
    }
};