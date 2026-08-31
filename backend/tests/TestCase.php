<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /** Sufixo obrigatorio no nome da base usada pela suite. */
    private const TEST_DATABASE_SUFFIX = '_test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->guardAgainstNonTestDatabase();
    }

    /**
     * A suite apaga e recria as tabelas a cada teste. Se a configuracao
     * apontar para qualquer base que nao seja a de teste, abortamos antes de
     * a primeira migration rodar — um erro de ambiente nao pode custar os
     * dados de desenvolvimento.
     */
    private function guardAgainstNonTestDatabase(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if (! is_string($database) || ! str_ends_with($database, self::TEST_DATABASE_SUFFIX)) {
            throw new RuntimeException(sprintf(
                'A suite esta apontando para a base [%s]. Esperado um nome terminado em "%s". '.
                'Verifique as variaveis <server> do phpunit.xml.',
                is_string($database) ? $database : '(indefinida)',
                self::TEST_DATABASE_SUFFIX,
            ));
        }
    }
}
