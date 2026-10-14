<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 200);
            // Sanitized HTML produced by the editor (see App\Support\HtmlSanitizer).
            $table->longText('content');
            // Optimistic-concurrency counter: every successful save increments it.
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('last_edited_by')->nullable()->constrained('users')->nullOnDelete();
            // Where the document came from, e.g. "notes.docx" when created by an import.
            $table->string('source_filename')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'updated_at']);
        });

        Schema::create('document_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16); // viewer | editor
            $table->timestamps();

            $table->unique(['document_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_shares');
        Schema::dropIfExists('documents');
    }
};
