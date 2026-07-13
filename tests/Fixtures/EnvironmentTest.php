<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use PHPUnit\Framework\TestCase;

final class EnvironmentTest extends TestCase
{
    public function test_putenv_and_getenv(): void
    {
        putenv('ESSENTIA_TEST_VAR=hello');

        $this->assertSame('hello', getenv('ESSENTIA_TEST_VAR'));

        putenv('ESSENTIA_TEST_VAR');
    }

    public function test_modify_server_superglobal(): void
    {
        $_SERVER['ESSENTIA_TEST'] = 'value';

        $this->assertSame('value', $_SERVER['ESSENTIA_TEST']);

        unset($_SERVER['ESSENTIA_TEST']);
    }

    public function test_modify_env_superglobal(): void
    {
        $_ENV['ESSENTIA_TEST'] = 'env_value';

        $this->assertSame('env_value', $_ENV['ESSENTIA_TEST']);

        unset($_ENV['ESSENTIA_TEST']);
    }
}
