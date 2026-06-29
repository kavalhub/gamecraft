<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guilds_members', function (Blueprint $table) {
            $table->id();
            $table->uuid('head_uuid');
            $table->uuid('member_uuid');
            $table->string('role_type');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('head_uuid')->references('uuid')->on('characters')->onDelete('cascade');
            $table->foreign('member_uuid')->references('uuid')->on('characters')->onDelete('cascade');
            $table->index(['head_uuid', 'active']);
            $table->unique(['head_uuid', 'member_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guilds_members');
    }
};
