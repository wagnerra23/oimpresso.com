<?php

declare(strict_types=1);
// @covers-us US-CRM-072

namespace Tests\Unit\Rules\BR;

use App\Rules\BR\CpfCnpj;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit — Rule App\Rules\BR\CpfCnpj.
 *
 * Delegate-test pra Eduardokum\LaravelBoleto\Util::validarCnpjCpf
 * (lib-custom/laravel-boleto/src/Util.php:1211, mod-11 SEFAZ).
 *
 * Mantém regressão local: se atualizarem o boleto vendored e ele quebrar
 * mod-11, este teste alerta.
 */
class CpfCnpjTest extends TestCase
{
    private function validate(?string $value): ?string
    {
        $rule = new CpfCnpj;
        $error = null;
        $rule->validate('doc', $value, function (string $msg) use (&$error) {
            $error = $msg;
        });

        return $error;
    }

    #[Test]
    public function it_accepts_null_and_empty(): void
    {
        $this->assertNull($this->validate(null));
        $this->assertNull($this->validate(''));
    }

    #[Test]
    #[DataProvider('validCpfs')]
    public function it_accepts_valid_cpf(string $cpf): void
    {
        $this->assertNull($this->validate($cpf));
    }

    #[Test]
    #[DataProvider('validCnpjs')]
    public function it_accepts_valid_cnpj(string $cnpj): void
    {
        $this->assertNull($this->validate($cnpj));
    }

    #[Test]
    #[DataProvider('invalidDocs')]
    public function it_rejects_invalid_doc(string $doc): void
    {
        $this->assertNotNull($this->validate($doc));
    }

    public static function validCpfs(): array
    {
        return [
            'cpf-puro-digitos' => ['11144477735'],
            'cpf-com-mascara' => ['111.444.777-35'], // pii-allowlist (vetor fake mod-11 do validador CpfCnpj, não PII real)
        ];
    }

    public static function validCnpjs(): array
    {
        return [
            'cnpj-puro-digitos' => ['11444777000161'],
            'cnpj-com-mascara' => ['11.444.777/0001-61'], // pii-allowlist (vetor fake mod-11 do validador CpfCnpj, não PII real)
        ];
    }

    public static function invalidDocs(): array
    {
        return [
            'cpf-com-todos-iguais' => ['11111111111'],
            'cpf-com-digito-errado' => ['11144477700'],
            'cnpj-com-todos-iguais' => ['11111111111111'],
            'cnpj-com-digito-errado' => ['11444777000100'],
            'string-curta' => ['123'],
            'string-letras' => ['abcdefghijk'],
            'string-vazia-com-espaco' => [' '],
        ];
    }
}
