<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_medications', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('laboratory')->nullable();
            $table->string('active_substance')->nullable();
            $table->string('dosage')->nullable();
            $table->string('form')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_prescription_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_medications');
    }
};
