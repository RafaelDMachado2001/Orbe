<?php

declare(strict_types=1);

namespace App\Domain\Banking\Actions;

use App\Domain\Banking\DTOs\BankData;
use App\Domain\Banking\Models\Bank;
use Illuminate\Support\Str;

/**
 * Renomeia ou recolore uma instituicao.
 *
 * A cor vale para tudo o que pende do banco — contas, cartoes e as fatias dos
 * graficos —, entao trocar a cor aqui muda a leitura de varias telas de uma
 * vez. E o comportamento desejado: a cor identifica a instituicao.
 */
final class UpdateBankAction
{
    public function handle(Bank $bank, BankData $data): Bank
    {
        $bank->forceFill([
            'name' => $data->name,
            'slug' => Str::slug($data->name),
            'color' => $data->color,
            'kind' => $data->kind,
        ])->save();

        return $bank->refresh();
    }
}
