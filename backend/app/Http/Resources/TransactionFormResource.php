<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Um lancamento como o formulario o edita.
 *
 * O formato vem inteiro da TransactionFormQuery — repetir os campos aqui so
 * criaria um segundo lugar para esquecer de atualizar.
 */
class TransactionFormResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $record */
        $record = $this->resource;

        return $record;
    }
}
