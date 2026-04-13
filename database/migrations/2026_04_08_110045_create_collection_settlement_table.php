<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('collection_settlement', function (Blueprint $table) {
            $table->foreignId('settlement_id')->constrained('settlements')->onDelete('cascade');
            $table->foreignId('collection_id')->constrained('collections')->onDelete('cascade');
            $table->primary(['settlement_id', 'collection_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('collection_settlement');
    }
};