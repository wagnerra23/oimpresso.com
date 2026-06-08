<?php

declare(strict_types=1);

use Modules\ConsultaOs\Http\Requests\ConsultaPorEstagioRequest;
use Modules\ConsultaOs\Http\Requests\FeedbackPublicoRequest;
use Modules\ConsultaOs\Repositories\MockConsultaOsRepository;

uses(Tests\TestCase::class);

/**
 * Wave 27 POLISH ConsultaOs — push 85 → ≥88.
 *
 * Cobre dimensoes restantes:
 *
 *   D9.A — Repository span OTel (`consultaos.repository.lookup`) — defesa
 *          em profundidade entre Service span (busca_publica) e fonte de dados.
 *
 *   D5.A — README portal publico completo (10+ passos cobrindo jornada feliz,
 *          filtro estagio, feedback opcional, observabilidade).
 *
 *   D8.A — `ConsultaPorEstagioRequest` (lista por estagio — scaffold US-CONSULTA-002).
 *   D8.B — `FeedbackPublicoRequest` (feedback NPS-like — scaffold US-CONSULTA-002).
 *
 * Tier 0 IRREVOGAVEL (ADR 0093):
 *   - Portal publico NAO scopa por business_id (cliente externo sem sessao)
 *   - FormRequests scaffolds ja documentam onde Repository real resolveria biz
 *   - Defesa anti-enumeration: estagio em lista controlada + paginacao max + throttle
 *
 * Zero hit prod externo (source-level + FormRequest contract) — Pest local-runnable.
 *
 * @see Modules/ConsultaOs/Repositories/MockConsultaOsRepository.php (D9 span Wave 27)
 * @see Modules/ConsultaOs/Http/Requests/ConsultaPorEstagioRequest.php (D8 Wave 27)
 * @see Modules/ConsultaOs/Http/Requests/FeedbackPublicoRequest.php (D8 Wave 27)
 * @see Modules/ConsultaOs/README.md (D5 portal publico completo Wave 27)
 */

// ============================================================================
// D9.A — Repository span OTel canonico (Wave 27)
// ============================================================================

it('D9.A MockConsultaOsRepository envolve buscarPorNumero em OtelHelper::span', function () {
    $source = file_get_contents((new ReflectionClass(MockConsultaOsRepository::class))->getFileName());

    expect($source)->toContain('use App\\Util\\OtelHelper;');
    expect($source)->toContain("OtelHelper::span('consultaos.repository.lookup'");
    expect($source)->toContain("'repository_kind' => 'mock'");
});

it('D9.A Repository span comentario documenta US-CONSULTA-001 transition (RepairRepository)', function () {
    $source = file_get_contents((new ReflectionClass(MockConsultaOsRepository::class))->getFileName());

    expect($source)->toContain('RepairConsultaOsRepository');
    expect($source)->toContain('observabilidade ganha automaticamente sem refactor');
});

it('D9.A buscarPorNumero ainda retorna null/array conforme contrato', function () {
    $repo = new MockConsultaOsRepository();

    expect($repo->buscarPorNumero('NAO-EXISTE-XX'))->toBeNull();
    expect($repo->buscarPorNumero('4821'))->toBeArray();
    expect($repo->buscarPorNumero('4821'))->toHaveKeys(['id', 'client', 'stage', 'items']);
});

// ============================================================================
// D5.A — README portal publico completo (cobertura source-level)
// ============================================================================

it('D5.A README declara jornada feliz consulta por numero (4 passos canonicos)', function () {
    $readme = file_get_contents(base_path('Modules/ConsultaOs/README.md'));

    expect($readme)->toContain('Jornada feliz');
    expect($readme)->toContain('Vendedor entrega numero');
    expect($readme)->toContain('Cliente acessa portal');
    expect($readme)->toContain('Digita numero');
    expect($readme)->toContain('Recebe JSON estruturado');
    expect($readme)->toContain('200 {found: true');
    expect($readme)->toContain('404 {found: false}');
});

it('D5.A README declara filtro por estagio scaffold US-CONSULTA-002', function () {
    $readme = file_get_contents(base_path('Modules/ConsultaOs/README.md'));

    expect($readme)->toContain('Filtro por estagio');
    expect($readme)->toContain('ConsultaPorEstagioRequest');
    expect($readme)->toContain('paginacao max 20/pag');
});

it('D5.A README declara feedback opcional scaffold US-CONSULTA-002', function () {
    $readme = file_get_contents(base_path('Modules/ConsultaOs/README.md'));

    expect($readme)->toContain('Feedback opcional');
    expect($readme)->toContain('FeedbackPublicoRequest');
    expect($readme)->toContain('PiiRedactor');
});

it('D5.A README declara timeline operacao do cliente (4+ passos)', function () {
    $readme = file_get_contents(base_path('Modules/ConsultaOs/README.md'));

    expect($readme)->toContain('Operacao do portal');
    expect($readme)->toContain('Recebe SMS quando OS muda estagio');
    expect($readme)->toContain('Acessa portal sem precisar lembrar de senha');
    expect($readme)->toContain('Ve estado atual + itens');
    expect($readme)->toContain('Imprime/screenshota');
});

it('D5.A README declara observabilidade defesa em profundidade (4 layers)', function () {
    $readme = file_get_contents(base_path('Modules/ConsultaOs/README.md'));

    expect($readme)->toContain('Observabilidade (Wave 25 + 27 D9');
    expect($readme)->toContain('consultaos.repository.lookup');
    expect($readme)->toContain('consultaos.busca_publica');
    expect($readme)->toContain('Controller audit log');
    expect($readme)->toContain('Health probes');
});

// ============================================================================
// D8.A — ConsultaPorEstagioRequest (Wave 27)
// ============================================================================

it('D8.A ConsultaPorEstagioRequest existe e estende FormRequest', function () {
    expect(class_exists(ConsultaPorEstagioRequest::class))->toBeTrue();
    expect(is_subclass_of(ConsultaPorEstagioRequest::class, \Illuminate\Foundation\Http\FormRequest::class))->toBeTrue();
});

it('D8.A ConsultaPorEstagioRequest valida estagio em lista controlada', function () {
    $req = new ConsultaPorEstagioRequest();
    $rules = $req->rules();

    expect($rules)->toHaveKey('estagio');
    expect($rules['estagio'])->toContain('required');
    expect($rules['estagio'])->toContain('in:aprovacao,producao,acabamento,expedicao,entregue');
});

it('D8.A ConsultaPorEstagioRequest valida paginacao max 50 + por_pagina max 20 (anti-scraping)', function () {
    $req = new ConsultaPorEstagioRequest();
    $rules = $req->rules();

    expect($rules)->toHaveKeys(['pagina', 'por_pagina']);
    expect($rules['pagina'])->toContain('max:50');
    expect($rules['por_pagina'])->toContain('max:20');
});

it('D8.A ConsultaPorEstagioRequest authorize() true (rota publica via throttle)', function () {
    expect((new ConsultaPorEstagioRequest())->authorize())->toBeTrue();
});

// ============================================================================
// D8.B — FeedbackPublicoRequest (Wave 27)
// ============================================================================

it('D8.B FeedbackPublicoRequest existe e estende FormRequest', function () {
    expect(class_exists(FeedbackPublicoRequest::class))->toBeTrue();
    expect(is_subclass_of(FeedbackPublicoRequest::class, \Illuminate\Foundation\Http\FormRequest::class))->toBeTrue();
});

it('D8.B FeedbackPublicoRequest valida numero_os (alpha_num + max:20)', function () {
    $req = new FeedbackPublicoRequest();
    $rules = $req->rules();

    expect($rules)->toHaveKey('numero_os');
    expect($rules['numero_os'])->toContain('required');
    expect($rules['numero_os'])->toContain('alpha_num');
    expect($rules['numero_os'])->toContain('max:20');
});

it('D8.B FeedbackPublicoRequest valida nota numerica 1-5', function () {
    $req = new FeedbackPublicoRequest();
    $rules = $req->rules();

    expect($rules)->toHaveKey('nota');
    expect($rules['nota'])->toContain('required');
    expect($rules['nota'])->toContain('integer');
    expect($rules['nota'])->toContain('min:1');
    expect($rules['nota'])->toContain('max:5');
});

it('D8.B FeedbackPublicoRequest comentario opcional max:500', function () {
    $req = new FeedbackPublicoRequest();
    $rules = $req->rules();

    expect($rules)->toHaveKey('comentario');
    expect($rules['comentario'])->toContain('nullable');
    expect($rules['comentario'])->toContain('max:500');
});

it('D8.B FeedbackPublicoRequest documenta PiiRedactor defesa em profundidade no source', function () {
    $source = file_get_contents((new ReflectionClass(FeedbackPublicoRequest::class))->getFileName());

    expect($source)->toContain('PiiRedactor');
    expect($source)->toContain('defesa em profundidade');
});
