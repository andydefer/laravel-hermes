<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_medication_pharmacy', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medication_id')->constrained('test_medications')->onDelete('cascade');
            $table->foreignId('pharmacy_id')->constrained('test_pharmacies')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['medication_id', 'pharmacy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_medication_pharmacy');
    }
};
