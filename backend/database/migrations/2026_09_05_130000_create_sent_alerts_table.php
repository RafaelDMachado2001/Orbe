<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sent_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->unsignedBigInteger('subject_id');
            $table->timestamp('sent_at');

            // Um e-mail por fatura/orcamento/meta, nunca de novo — sem isso a
            // varredura diaria reenviaria o mesmo alerta todo dia enquanto a
            // condicao continuasse valendo.
            $table->unique(['type', 'subject_id']);
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_alerts');
    }
};
