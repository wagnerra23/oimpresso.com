<?php

namespace Modules\Arquivos\Tests\Feature;

use Tests\TestCase;

/**
 * Pest tests scaffold pra Modules/Arquivos (ADR 0123 + Sprint 1 US-ARQ-009).
 *
 * Cobertura crítica pendente Sprint 1 dia 3-5:
 * - multi-tenant Tier 0 (biz=1 não vê arquivo biz=4)
 * - sensitive bloqueia disk default (vai pra vault automaticamente)
 * - dedupe (2× upload mesmo MD5 mesmo business retorna mesma row)
 * - soft-delete (deleted_at populado, withTrashed encontra)
 * - audit log preenche pras 8 ações enum
 *
 * Sprint 1 ParityTest (US-ARQ-007) JS×PHP fixtures comuns:
 * tests/Fixtures/CuradorParity/*.jsonl com 100 cenários — agent_C 2026-05-10
 * sinalizou que sem fixtures comuns paridade fica circular.
 */
class ScaffoldTest extends TestCase
{
    public function test_classes_existem(): void
    {
        $this->assertTrue(class_exists(\Modules\Arquivos\Entities\Arquivo::class));
        $this->assertTrue(trait_exists(\Modules\Arquivos\Concerns\HasArquivos::class));
        $this->assertTrue(class_exists(\Modules\Arquivos\Services\ArquivosService::class));
        $this->assertTrue(class_exists(\Modules\Arquivos\Services\Curador\CuradorEngine::class));
        $this->assertTrue(class_exists(\Modules\Arquivos\Providers\ArquivosServiceProvider::class));
    }

    /**
     * Smoke do CuradorEngine com fixtures sintéticas — sem precisar DB.
     * ParityTest completo com 100 fixtures vs JS rules.mjs vem em US-ARQ-007.
     */
    public function test_curador_engine_classifica_env_real_como_sensitive(): void
    {
        $engine = new \Modules\Arquivos\Services\Curador\CuradorEngine();

        $stub = new \Modules\Arquivos\Entities\Arquivo([
            'original_name' => '.env',
            'storage_path'  => 'biz-1/2026/05/abc123.env',
            'size_bytes'    => 9280,
            'mime_type'     => 'text/plain',
            'md5'           => str_repeat('a', 32),
        ]);
        $result = $engine->classify($stub);

        $this->assertSame('sensitive', $result['bucket']);
        $this->assertSame('sensitive_env_real', $result['rule_matched']);
        $this->assertSame('_VAULT-PENDING/env-files/', $result['sub_destination']);
    }

    public function test_curador_engine_classifica_env_example_como_active(): void
    {
        $engine = new \Modules\Arquivos\Services\Curador\CuradorEngine();

        $stub = new \Modules\Arquivos\Entities\Arquivo([
            'original_name' => '.env.example',
            'storage_path'  => 'biz-1/2026/05/abc.env.example',
            'size_bytes'    => 200,
            'mime_type'     => 'text/plain',
            'md5'           => str_repeat('b', 32),
        ]);
        $result = $engine->classify($stub);

        $this->assertSame('active', $result['bucket']);
        $this->assertSame('no_rule_matched', $result['rule_matched']);
    }

    /**
     * @todo US-ARQ-007 — ParityTest JS×PHP com fixtures comuns
     *   tests/Fixtures/CuradorParity/*.jsonl (input) + expected_*.json (output JS)
     *   Asserta classify_match >= 95 E mean_abs_confidence_delta < 0.05.
     */
    public function test_todo_parity_test_js_php(): void
    {
        $this->markTestSkipped('US-ARQ-007 — implementar com fixtures comuns Sprint 1 dia 3.');
    }
}
