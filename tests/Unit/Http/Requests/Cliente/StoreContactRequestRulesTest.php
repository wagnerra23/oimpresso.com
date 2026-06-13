<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Cliente;

use App\Http\Requests\Cliente\StoreContactRequest;
use App\Http\Requests\Cliente\UpdateContactRequest;
use App\Rules\BR\CpfCnpj;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Slice 7 — sanity check do shape do FormRequest sem booting o framework.
 *
 * Garante que as keys canon BR estão no array rules() e que cpf_cnpj usa
 * a Rule canônica App\Rules\BR\CpfCnpj (e não validação inline ad-hoc).
 *
 * Smoke pre-merge — não substitui o Feature test que exercita validação real.
 */
class StoreContactRequestRulesTest extends TestCase
{
    #[Test]
    public function it_has_br_canon_keys_on_store(): void
    {
        $rules = (new StoreContactRequest)->rules();

        foreach ([
            'cpf_cnpj', 'rg', 'inscricao_estadual', 'inscricao_municipal',
            'indicador_ie', 'nome_fantasia', 'consumidor_final',
            'contribuinte', 'regime', 'suframa',
        ] as $key) {
            $this->assertArrayHasKey($key, $rules, "Store rule missing key: {$key}");
        }
    }

    #[Test]
    public function it_has_br_canon_keys_on_update(): void
    {
        $rules = (new UpdateContactRequest)->rules();

        foreach ([
            'cpf_cnpj', 'rg', 'inscricao_estadual', 'inscricao_municipal',
            'indicador_ie', 'nome_fantasia', 'consumidor_final',
            'contribuinte', 'regime', 'suframa',
        ] as $key) {
            $this->assertArrayHasKey($key, $rules, "Update rule missing key: {$key}");
        }
    }

    #[Test]
    public function cpf_cnpj_uses_canonical_rule_on_store(): void
    {
        $rules = (new StoreContactRequest)->rules();

        $this->assertArrayHasKey('cpf_cnpj', $rules);
        $this->assertContains('nullable', $rules['cpf_cnpj']);

        $hasCpfCnpjRule = false;
        foreach ($rules['cpf_cnpj'] as $r) {
            if ($r instanceof CpfCnpj) {
                $hasCpfCnpjRule = true;
                break;
            }
        }
        $this->assertTrue($hasCpfCnpjRule, 'cpf_cnpj deve usar App\\Rules\\BR\\CpfCnpj');
    }

    #[Test]
    public function cpf_cnpj_uses_canonical_rule_on_update(): void
    {
        $rules = (new UpdateContactRequest)->rules();

        $this->assertArrayHasKey('cpf_cnpj', $rules);

        $hasCpfCnpjRule = false;
        foreach ($rules['cpf_cnpj'] as $r) {
            if ($r instanceof CpfCnpj) {
                $hasCpfCnpjRule = true;
                break;
            }
        }
        $this->assertTrue($hasCpfCnpjRule, 'cpf_cnpj deve usar App\\Rules\\BR\\CpfCnpj');
    }

    #[Test]
    public function indicador_ie_restricted_to_1_2_9(): void
    {
        $rules = (new StoreContactRequest)->rules();
        $this->assertContains('in:1,2,9', $rules['indicador_ie']);
        $this->assertContains('integer', $rules['indicador_ie']);
    }

    #[Test]
    public function regime_restricted_to_canonical_set(): void
    {
        $rules = (new StoreContactRequest)->rules();
        $this->assertContains('in:simples,presumido,real,mei', $rules['regime']);
    }

    #[Test]
    public function messages_do_not_leak_recived_value(): void
    {
        $messages = (new StoreContactRequest)->messages();

        // Defensividade LGPD ADR 0127 — mensagens não devem ter placeholders pro valor recebido.
        foreach ($messages as $key => $msg) {
            $this->assertStringNotContainsString(':input', $msg, "msg {$key} não pode ecoar :input");
            $this->assertStringNotContainsString(':value', $msg, "msg {$key} não pode ecoar :value");
        }
    }
}
