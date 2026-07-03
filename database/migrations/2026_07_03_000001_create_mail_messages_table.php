<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('storage_uuid');
            $table->uuid('recipient_uuid');
            $table->uuid('sender_uuid')->nullable();
            $table->string('sender_name');
            $table->string('subject');
            $table->text('body')->nullable();
            $table->string('status')->default('unread');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('storage_uuid')->references('uuid')->on('storages')->onDelete('cascade');
            $table->foreign('recipient_uuid')->references('uuid')->on('characters')->onDelete('cascade');
            $table->foreign('sender_uuid')->references('uuid')->on('characters')->onDelete('set null');
            $table->index(['recipient_uuid', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_messages');
    }
};
