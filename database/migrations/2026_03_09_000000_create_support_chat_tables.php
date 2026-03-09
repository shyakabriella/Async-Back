<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_token')->unique();
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();
            $table->foreignId('assigned_agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->boolean('bot_enabled')->default(true);
            $table->json('meta')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'last_message_at']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->enum('sender_type', ['customer', 'agent', 'bot', 'system']);
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->text('message');
            $table->json('meta')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['chat_conversation_id', 'created_at']);
        });

        Schema::create('agent_presences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->boolean('is_online')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['is_online', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_presences');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
    }
};