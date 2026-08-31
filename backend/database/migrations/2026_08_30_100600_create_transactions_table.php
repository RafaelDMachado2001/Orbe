<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('credit_card_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recurrence_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('transfer_pair_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('paid_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->string('type', 15);
            // Direcao do dinheiro na conta. Derivada do tipo para receita/despesa
            // e explicita para transferencia, que existe nos dois lados do par.
            $table->string('direction', 10);
            $table->string('status', 12)->default('confirmado');
            // Coluna gerada: o valor com sinal, para somar saldo em uma unica
            // agregacao sem CASE espalhado por varias queries.
            $table->decimal('signed_amount', 15, 2)
                ->storedAs("amount * (CASE WHEN direction = 'entrada' THEN 1 ELSE -1 END)");
            $table->string('method', 15)->nullable();
            $table->date('competence_date');
            $table->date('paid_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->boolean('is_installment_parent')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'competence_date']);
            $table->index(['user_id', 'type', 'status']);
            $table->index(['account_id', 'status']);
            $table->index(['credit_card_id', 'competence_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
