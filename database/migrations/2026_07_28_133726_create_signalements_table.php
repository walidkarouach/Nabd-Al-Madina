<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::create('signalements', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        $table->text('texte');
        $table->decimal('lat', 10, 7);
        $table->decimal('lng', 10, 7);
        $table->string('photo_path')->nullable();

        // Champs remplis par l'IA
        $table->string('category')->nullable();
        $table->enum('priority', ['low','medium','high'])->nullable();
        $table->unsignedTinyInteger('urgency')->nullable(); // 1-5
        $table->text('summary')->nullable();
        $table->string('ai_analysis_status')->default('en_attente'); // en_attente | succes | echec

        $table->foreignId('department_id')->nullable()->constrained('departements')->nullOnDelete();
        $table->foreignId('incident_id')->nullable()->constrained('incidents')->nullOnDelete();
        $table->enum('status', ['nouveau','en_cours','resolu','rejete'])->default('nouveau');

        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('signalements');
}
};