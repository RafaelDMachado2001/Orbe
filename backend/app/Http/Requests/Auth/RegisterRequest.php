<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Domain\Identity\DTOs\RegisterUserData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:180', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
            'timezone' => ['sometimes', 'string', 'timezone', 'max:64'],
            'device_name' => ['sometimes', 'string', 'max:120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe seu nome.',
            'email.required' => 'Informe seu e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'email.unique' => 'Este e-mail já está cadastrado.',
            'password.required' => 'Informe uma senha.',
            'password.confirmed' => 'A confirmação da senha não confere.',
        ];
    }

    public function toData(): RegisterUserData
    {
        return new RegisterUserData(
            name: $this->string('name')->trim()->value(),
            email: $this->string('email')->lower()->trim()->value(),
            password: $this->string('password')->value(),
            timezone: $this->string('timezone', 'America/Sao_Paulo')->value(),
        );
    }

    public function deviceName(): string
    {
        return $this->string('device_name', 'web')->value();
    }
}
