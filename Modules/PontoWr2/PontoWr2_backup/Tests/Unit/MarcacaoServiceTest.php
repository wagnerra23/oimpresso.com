<?php

namespace Modules\PontoWr2\Tests\Unit;

use Modules\PontoWr2\Services\MarcacaoService;
use Modules\PontoWr2\Services\NsrService;
use Tests\TestCase;

/**
 * Testa a pureza do payload canônico e a estabilidade do hash SHA-256.
 * (Os testes que tocam DB ficam em Tests/Feature com RefreshDatabase.)
 */
class MarcacaoServiceTest extends TestCase
{
    public function test_payload_canonico_e_deterministico()
    {
        $svc = new MarcacaoService(new NsrService());

        $dados = [
            'business_id'           => 1,
            'colaborador_config_id' => 42,
            'rep_id'                => 'abc-123',
            'nsr'                   => 99,
            'momento'               => '2026-04-15 08:00:00',
            'origem'                => 'REP_P',
            'tipo'                  => 'ENTRADA',
            'hash_anterior'         => null,
            'usuario_criador_id'    => 7,
        ];

        $p1 = $svc->payloadCanonico($dados);
        $p2 = $svc->payloadCanonico($dados);

        $this->assertSame($p1, $p2);
        $this->assertStringContainsString('REP_P', $p1);
        $this->assertStringContainsString('ENTRADA', $p1);
    }

    public function test_hash_muda_quando_campo_relevante_muda()
    {
        $svc = new MarcacaoService(new NsrService());
        $base = [
            'business_id'           => 1,
            'colaborador_config_id' => 42,
            'rep_id'                => 'abc',
            'nsr'                   => 1,
            'momento'               => '2026-04-15 08:00:00',
            'origem'                => 'REP_P',
            'tipo'                  => 'ENTRADA',
            'hash_anterior'         => null,
            'usuario_criador_id'    => 7,
        ];

        $h1 = hash('sha256', $svc->payloadCanonico($base));
        $h2 = hash('sha256', $svc->payloadCanonico(array_merge($base, ['tipo' => 'SAIDA'])));

        $this->assertNotEquals($h1, $h2);
    }
}
