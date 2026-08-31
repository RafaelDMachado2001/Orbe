<?php

declare(strict_types=1);

namespace App\Domain\Identity\DTOs;

final readonly class RegisterUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public string $timezone = 'America/Sao_Paulo',
        public string $currency = 'BRL',
    ) {}
}
