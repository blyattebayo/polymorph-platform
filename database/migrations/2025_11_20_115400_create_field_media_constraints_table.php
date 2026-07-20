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
        Schema::create('field_media_constraints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_id')->constrained('fields')->onDelete('cascade');
            $table->string('allowed_mime'); // image/jpeg, video/mp4, etc.
            $table->timestamps();

            // Уникальность: одно поле не может иметь дубликатов MIME-типов
            $table->unique(['field_id', 'allowed_mime']);
            $table->index('field_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('field_media_constraints');
    }
};
