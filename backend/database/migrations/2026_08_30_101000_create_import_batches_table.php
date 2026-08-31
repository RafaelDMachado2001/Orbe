<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // O destino e exclusivo: ou o extrato caiu em uma conta, ou a
            // fatura caiu em um cartao. Nunca nos dois.
            $table->foreignId('account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('credit_card_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->string('format', 5);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            // Periodo coberto pelo arquivo, para a tela de historico dizer o
            // que aquela importacao trouxe sem abrir os lancamentos.
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::table('transactions', function (Blueprint $table): void {
            // Marca de origem. E o que permite desfazer uma importacao inteira
            // sem caçar lancamento por lancamento no extrato.
            $table->foreignId('import_batch_id')
                ->nullable()
                ->after('paid_invoice_id')
                ->constrained('import_batches')
                ->nullOnDelete();

            $table->index(['import_batch_id']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropForeign(['import_batch_id']);
            $table->dropIndex(['import_batch_id']);
            $table->dropColumn('import_batch_id');
        });

        Schema::dropIfExists('import_batches');
    }
};
