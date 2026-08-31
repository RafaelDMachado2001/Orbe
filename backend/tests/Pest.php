<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        // O rate limiter guarda os contadores no cache, que vive pelo processo
        // inteiro: sem limpar, um teste esgota a cota do seguinte.
        Cache::clear();
    })
    ->in('Feature', 'Unit');
