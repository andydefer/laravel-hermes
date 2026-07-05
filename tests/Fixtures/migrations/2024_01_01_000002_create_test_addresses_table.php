<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('test_users')->onDelete('cascade');
            $table->string('street');
            $table->string('city');
            $table->string('country');
            $table->string('postal_code');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_addresses');
    }
};
