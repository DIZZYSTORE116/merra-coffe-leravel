<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deploys', function (Blueprint $table) {
            $table->id();
            $table->string('deployment_id')->nullable()->unique();
            $table->string('version', 40);
            $table->string('environment', 20)->default('Production');
            $table->string('status', 20)->default('Berhasil');
            $table->string('branch', 100)->default('main');
            $table->string('commit_sha')->nullable();
            $table->string('commit_message', 300)->default('-');
            $table->string('deployed_by', 100)->default('-');
            $table->string('url', 300)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('source', 20)->default('manual');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deploys');
    }
};
