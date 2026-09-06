<?php

declare(strict_types=1);

namespace App\Http\Requests\Banking;

use App\Domain\Banking\DTOs\BankData;
use App\Domain\Banking\Enums\BankKind;
use App\Domain\Banking\Models\Bank;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Regras de cadastro e edicao de instituicao.
 *
 * Nome, cor e tipo chegam prontos quando a pessoa escolhe um banco do
 * catalogo, e digitados quando ela cadastra um banco que nao esta na lista —
 * para a API os dois casos sao o mesmo. A unicidade e conferida pelo slug do
 * nome, para "Nubank" e "nubank " nao virarem duas instituicoes.
 */
class WriteBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:60', $this->uniqueName()],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'kind' => ['required', Rule::enum(BankKind::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Dê um nome ao banco.',
            'color.required' => 'Escolha uma cor para o banco.',
            'color.regex' => 'A cor deve estar no formato #RRGGBB.',
            'kind.required' => 'Escolha o tipo de instituição.',
        ];
    }

    public function toData(): BankData
    {
        return new BankData(
            name: trim((string) $this->input('name')),
            color: strtoupper((string) $this->input('color')),
            kind: $this->enum('kind', BankKind::class),
        );
    }

    /**
     * Duas instituicoes com o mesmo nome deixariam a lista de contas ilegivel
     * e nao ha o que uma faca que a outra nao faca.
     */
    private function uniqueName(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $current = $this->route('bank');
            $currentId = $current instanceof Bank ? $current->id : null;

            $exists = Bank::query()
                ->ownedBy($this->user()->id)
                ->where('slug', Str::slug((string) $value))
                ->when(
                    $currentId !== null,
                    fn (Builder $query): Builder => $query->whereKeyNot($currentId),
                )
                ->exists();

            if ($exists) {
                $fail('Você já cadastrou este banco.');
            }
        };
    }
}
