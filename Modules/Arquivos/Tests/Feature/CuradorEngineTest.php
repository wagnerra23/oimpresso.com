<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\Arquivos\Entities\Arquivo;
use Modules\Arquivos\Services\Curador\CuradorEngine;

uses(Tests\TestCase::class);

beforeEach(function () {
    // CI SQLite :memory: — pula gracioso se migrate não criou tabela arquivos.
    // CuradorEngine é pura logic mas Arquivo Model resolve casts/global scope
    // ao instanciar, e isso pode tocar Schema metadata.
    if (! Schema::hasTable('arquivos')) {
        $this->markTestSkipped('Tabela arquivos ausente — rode migrate Modules/Arquivos primeiro.');
    }
});

/**
 * Pest tests do CuradorEngine PHP — port das 18 regras de scripts/curador/lib/rules.mjs.
 *
 * Sprint 1 dia 3 (US-ARQ-005..007). ParityTest com 100 fixtures comuns vs JS
 * (US-ARQ-007 completo) fica pra Sprint 1 dia 5 — esses tests cobrem fixtures
 * sintéticas das 18 regras pra garantir bucket correto.
 *
 * @see Modules/Arquivos/Services/Curador/CuradorEngine.php
 * @see scripts/curador/lib/rules.mjs (fonte de verdade JS)
 */

function curadorStub(string $name, string $path = '', int $size = 1024): Arquivo
{
    return new Arquivo([
        'original_name' => $name,
        'storage_path'  => $path ?: "biz-1/2026/05/{$name}",
        'size_bytes'    => $size,
        'mime_type'     => 'application/octet-stream',
        'md5'           => str_repeat('a', 32),
    ]);
}

it('classifica .env real como sensitive', function () {
    $engine = new CuradorEngine();
    $result = $engine->classify(curadorStub('.env'));

    expect($result['bucket'])->toBe('sensitive');
    expect($result['rule_matched'])->toBe('sensitive_env_real');
    expect($result['sub_destination'])->toBe('_VAULT-PENDING/env-files/');
});

it('NÃO classifica .env.example como sensitive', function () {
    $engine = new CuradorEngine();
    $result = $engine->classify(curadorStub('.env.example'));

    expect($result['bucket'])->toBe('active');
    expect($result['rule_matched'])->toBe('no_rule_matched');
});

it('classifica .pfx como sensitive_by_extension', function () {
    $engine = new CuradorEngine();
    $result = $engine->classify(curadorStub('cert.pfx'));

    expect($result['bucket'])->toBe('sensitive');
    expect($result['rule_matched'])->toBe('sensitive_by_extension');
});

it('classifica id_rsa como ssh_key', function () {
    $engine = new CuradorEngine();
    $result = $engine->classify(curadorStub('id_rsa'));

    expect($result['bucket'])->toBe('sensitive');
    expect($result['rule_matched'])->toBe('sensitive_ssh_key');
});

it('classifica XML em pasta XML Clientes como pii_xml', function () {
    $engine = new CuradorEngine();
    $result = $engine->classify(curadorStub(
        'cliente.xml',
        'biz-1/Suporte/XML Clientes/cliente.xml'
    ));

    expect($result['bucket'])->toBe('sensitive');
    expect($result['rule_matched'])->toBe('sensitive_pii_xml_cliente');
});

it('classifica credentialsChatWoot.json como sensitive', function () {
    $engine = new CuradorEngine();
    $result = $engine->classify(curadorStub('credentialsChatWoot.json'));

    expect($result['bucket'])->toBe('sensitive');
    expect($result['rule_matched'])->toBe('sensitive_credentials_json');
});

it('classifica path Software/ como discard OSS', function () {
    $engine = new CuradorEngine();
    $result = $engine->classify(curadorStub(
        'controller.rb',
        'D:\\Conhecimento\\Software\\chatwoot\\controllers\\foo.rb'
    ));

    expect($result['bucket'])->toBe('discard');
    expect($result['rule_matched'])->toBe('oss_software_folder');
});

it('classifica path node_modules como discard OSS clone', function () {
    $engine = new CuradorEngine();
    $result = $engine->classify(curadorStub(
        'index.js',
        'D:\\app\\node_modules\\foo\\index.js'
    ));

    expect($result['bucket'])->toBe('discard');
    expect($result['rule_matched'])->toBe('oss_clone_path');
});

it('classifica CNAB Itau como memory/Financeiro/CNAB-Itau', function () {
    $engine = new CuradorEngine();
    $result = $engine->classify(curadorStub(
        'Cnab240_Itau.pdf',
        'D:\\Conhecimento\\ManuaisTecnicos\\Itau\\Cnab240_Itau.pdf'
    ));

    expect($result['bucket'])->toBe('memory');
    expect($result['rule_matched'])->toBe('cnab_Itau');
    expect($result['sub_destination'])->toBe('memory/requisitos/Financeiro/CNAB-Itau/');
});

it('classifica CNAB Sicoob (basename only) como memory', function () {
    $engine = new CuradorEngine();
    // Path do bug Agent B: nome do banco no basename, não na pasta
    $result = $engine->classify(curadorStub(
        'Sicred_ManualCnab400.pdf',
        'D:\\foo\\Sicred_ManualCnab400.pdf'
    ));

    expect($result['bucket'])->toBe('memory');
    expect($result['rule_matched'])->toBe('cnab_Sicred');
});

it('classifica fiscal SPED basename como NfeBrasil memory', function () {
    $engine = new CuradorEngine();
    $result = $engine->classify(curadorStub('GUIA_PRATICO_EFD_ICMS_IPI.pdf'));

    expect($result['bucket'])->toBe('memory');
    expect($result['rule_matched'])->toBe('fiscal_sped');
    expect($result['sub_destination'])->toBe('memory/requisitos/NfeBrasil/');
});

it('classifica Imagens/Jana como branding memory', function () {
    $engine = new CuradorEngine();
    $result = $engine->classify(curadorStub(
        'logo.png',
        'D:\\Conhecimento\\Imagens\\Jana\\logo.png'
    ));

    expect($result['bucket'])->toBe('memory');
    expect($result['rule_matched'])->toBe('branding_jana');
});

it('classifica Suporte/Base de Conhecimento como KB legacy', function () {
    $engine = new CuradorEngine();
    $result = $engine->classify(curadorStub(
        'faq.txt',
        'D:\\Conhecimento\\Suporte ao Cliente\\Base de Conhecimento (KB)\\FAQs\\faq.txt'
    ));

    expect($result['bucket'])->toBe('memory');
    expect($result['rule_matched'])->toBe('kb_legacy_faq');
});

it('classifica yaml Portainer Docker como infra stack', function () {
    $engine = new CuradorEngine();
    $result = $engine->classify(curadorStub(
        'chatwoot.yaml',
        'D:\\Conhecimento\\Infraestrutura & Operações\\Portainer\\Docker\\chatwoot.yaml'
    ));

    expect($result['bucket'])->toBe('memory');
    expect($result['rule_matched'])->toBe('infra_portainer_stack');
});

it('classifica Manuais Técnicos pra modulo canônico (Officeimpresso fallback venda)', function () {
    $engine = new CuradorEngine();
    $result = $engine->classify(curadorStub(
        'Venda.txt',
        'D:\\Conhecimento\\Manuais Técnicos\\Venda.txt'
    ));

    expect($result['bucket'])->toBe('memory');
    expect($result['rule_matched'])->toBe('office_comercial_legacy_Officeimpresso');
});

it('classifica Manuais Técnicos pra Manufacturing (não Producao)', function () {
    $engine = new CuradorEngine();
    $result = $engine->classify(curadorStub(
        'Producao.txt',
        'D:\\Conhecimento\\Manuais Técnicos\\Producao.txt'
    ));

    expect($result['bucket'])->toBe('memory');
    expect($result['rule_matched'])->toBe('office_comercial_legacy_Manufacturing');
});

it('classifica PDF >1MB sem regra como large_binary_indexed', function () {
    $engine = new CuradorEngine();
    $result = $engine->classify(curadorStub(
        'big.pdf',
        'D:\\foo\\big.pdf',
        size: 2 * 1024 * 1024
    ));

    // Pode cair em fiscal_sped se basename match — aqui basename neutral
    expect($result['bucket'])->toBe('memory');
    expect($result['rule_matched'])->toBe('large_binary_indexed');
    expect($result['sensitive_flags'])->toContain('large_binary_index_only');
});

it('classifica arquivo comum como bucket=active fallback', function () {
    $engine = new CuradorEngine();
    $result = $engine->classify(curadorStub('relatorio_qualquer.txt'));

    expect($result['bucket'])->toBe('active');
    expect($result['rule_matched'])->toBe('no_rule_matched');
});
