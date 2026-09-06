<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goal_contributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            // Nulo quando o aporte e so um registro manual de progresso (meta
            // sem conta vinculada). Fica nulo tambem se a transacao ligada for
            // excluida direto pela tela de Lancamentos — o aporte permanece no
            // historico, so perde o vinculo com o dinheiro que a gerou.
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('contributed_at');
            $table->timestamps();

            $table->index(['user_id', 'goal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_contributions');
    }
};
