<?php

declare(strict_types=1);

use App\Domain\Ledger\Enums\CategoryType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chave estavel para as categorias que o sistema precisa achar sozinho.
 *
 * is_system diz que a categoria nasceu com a conta e nao pode ser excluida,
 * mas nao diz *qual* categoria e — e o usuario pode renomea-la. O formulario
 * de emprestimo precisa apontar para uma categoria especifica sem depender do
 * nome, entao a referencia vira uma chave.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->string('system_key', 30)->nullable()->after('is_system');

            $table->unique(['user_id', 'system_key']);
        });

        $this->backfillLoanCategory();
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'system_key']);
            $table->dropColumn('system_key');
        });
    }

    /** Contas criadas antes desta fatia nao tem a categoria de emprestimo. */
    private function backfillLoanCategory(): void
    {
        $now = now();

        $rows = DB::table('users')
            ->select('id')
            ->get()
            ->map(static fn (object $user): array => [
                'user_id' => (int) $user->id,
                'name' => 'Empréstimos e financiamentos',
                'type' => CategoryType::Despesa->value,
                'color' => '#FF8A3D',
                'icon' => 'landmark',
                'is_system' => true,
                'system_key' => 'emprestimos',
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($rows !== []) {
            DB::table('categories')->insert($rows);
        }
    }
};
