<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Models;

use App\Support\Concerns\BelongsToUser;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Marca que um alerta ja foi mandado por e-mail para um assunto especifico
 * (uma fatura, um orcamento, uma meta) — a chave `(type, subject_id)` e unica
 * de proposito, para a varredura diaria nunca mandar o mesmo aviso duas vezes.
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property int $subject_id
 * @property CarbonImmutable $sent_at
 */
class SentAlert extends Model
{
    use BelongsToUser;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'type',
        'subject_id',
        'sent_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sent_at' => 'immutable_datetime',
        ];
    }
}
