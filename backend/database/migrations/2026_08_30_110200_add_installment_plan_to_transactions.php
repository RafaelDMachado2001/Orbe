<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parcelamento de despesa em conta e emprestimo.
 *
 * A despesa parcelada em conta nao reaproveita a tabela installments: aquela
 * modelagem existe porque a parcela de cartao nao e um lancamento de conta —
 * ela nasce dentro de uma fatura e so vira dinheiro quando a fatura e paga.
 * Fora do cartao a parcela sai da conta no proprio mes, entao cada uma e um
 * lancamento por direito, e o grupo apenas as costura para editar e excluir
 * em bloco.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->uuid('installment_group_id')->nullable()->after('paid_invoice_id');
            $table->unsignedSmallInteger('installment_number')->nullable()->after('is_installment_parent');
            $table->unsignedSmallInteger('installment_total')->nullable()->after('installment_number');
            $table->boolean('is_loan')->default(false)->after('installment_total');
            $table->string('lender')->nullable()->after('is_loan');

            $table->index(['user_id', 'installment_group_id']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'installment_group_id']);
            $table->dropColumn([
                'installment_group_id',
                'installment_number',
                'installment_total',
                'is_loan',
                'lender',
            ]);
        });
    }
};
