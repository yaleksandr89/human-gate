<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Unit\Challenge;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\HumanGate\Challenge\Purpose;

final class PurposeTest extends TestCase
{
    #[DataProvider('validValues')]
    #[TestDox('Допустимое назначение сохраняется без изменений')]
    public function testCanonicalValueIsPreserved(string $value): void
    {
        self::assertSame($value, new Purpose($value)->value());
    }

    #[DataProvider('invalidValues')]
    #[TestDox('Неканоническое назначение отклоняется')]
    public function testInvalidValueIsRejected(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Purpose($value);
    }

    /** @return iterable<string, array{string}> */
    public static function validValues(): iterable
    {
        yield 'hyphen' => ['contact-form'];
        yield 'letters' => ['registration'];
        yield 'underscore' => ['password_reset'];
        yield 'dot' => ['account.recovery'];
        yield 'single letter' => ['a'];
        yield 'single digit' => ['0'];
        yield 'maximum length' => [str_repeat('a', 64)];
        yield 'trailing punctuation' => ['0._-'];
    }

    /** @return iterable<string, array{string}> */
    public static function invalidValues(): iterable
    {
        yield 'empty' => [''];
        yield 'leading hyphen' => ['-contact'];
        yield 'leading dot' => ['.contact'];
        yield 'leading underscore' => ['_contact'];
        yield 'space' => ['contact form'];
        yield 'uppercase' => ['Contact-form'];
        yield 'Cyrillic lookalike' => ['contаct-form'];
        yield 'slash' => ['account/recovery'];
        yield 'colon' => ['account:recovery'];
        yield 'too long' => [str_repeat('a', 65)];
        yield 'trailing newline' => ["contact-form\n"];
        yield 'null byte' => ["contact\0form"];
    }
}
