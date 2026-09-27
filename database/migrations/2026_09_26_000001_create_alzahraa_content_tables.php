<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->timestamps();
        });
        Schema::create('content_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('status', 20)->default('draft');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('share_image')->nullable();
            $table->json('sections');
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('content_items', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 30);
            $table->string('title');
            $table->string('slug')->nullable();
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->longText('body')->nullable();
            $table->string('image')->nullable();
            $table->string('alt')->nullable();
            $table->string('location')->nullable();
            $table->unsignedSmallInteger('project_year')->nullable();
            $table->unsignedInteger('area')->nullable();
            $table->string('quantity')->nullable();
            $table->json('data')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->unique(['kind', 'slug']);
            $table->index(['kind', 'is_published', 'sort_order']);
        });
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->string('alt')->default('');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('estimate_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('whatsapp', 40);
            $table->string('project_type', 40);
            $table->string('area', 80)->nullable();
            $table->text('message');
            $table->string('status', 30)->default('new');
            $table->text('notes')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
        Schema::create('content_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('model_type', 40);
            $table->unsignedBigInteger('model_id');
            $table->json('version_data');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['model_type', 'model_id', 'created_at']);
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('summary');
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('content_revisions');
        Schema::dropIfExists('estimate_requests');
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('content_items');
        Schema::dropIfExists('content_pages');
        Schema::dropIfExists('site_settings');
    }
};
