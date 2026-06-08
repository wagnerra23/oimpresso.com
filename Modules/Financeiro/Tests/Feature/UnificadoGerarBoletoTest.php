<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Gerar Boleto no drawer (2026-06-08) — emitir boleto Inter pra um título
 * existente direto da Visão Unificada, sem ir pra /financeiro/cobranca.
 *
 * Cobre o WIRING (estilo smoke estrutural do módulo — não boota app, sobrevive
 * DB greenfield) das 5 peças:
 *   1. UnificadoController::emitirBoletoTitulo — resolve conta Inter (077),
 *      monta EmitirCobrancaInput com origem_type='fin_titulo', emite via
 *      PaymentGatewayContract, persiste linha digitável em metadata.boleto.
 *   2. Routes/web.php — POST /unificado/{tituloId}/boleto + name.
 *   3. OnCobrancaPagaCreateFinanceiroTitulo — branch que BAIXA o título de
 *      origem (anti-duplo-recebível) em vez de criar PG-xxx.
 *   4. Migration — enum cobrancas.origem_type aceita 'fin_titulo'.
 *   5. Frontend Index.tsx — botão "Gerar boleto" posta no endpoint.
 *   + HTTP smoke: endpoint responde gate de auth sem login.
 *
 * ⚠️ A cobertura COMPORTAMENTAL (mock do gateway emitindo + evento CobrancaPaga
 * dando baixa sem duplicar) deve rodar no CT-100 com app bootado — fica como
 * follow-up; aqui garantimos que a fiação existe e não regride.
 */

const FIN_GB_CONTROLLER = __DIR__ . '/../../Http/Controllers/UnificadoController.php';
const FIN_GB_ROUTES = __DIR__ . '/../../Routes/web.php';
const FIN_GB_LISTENER = __DIR__ . '/../../Listeners/OnCobrancaPagaCreateFinanceiroTitulo.php';
const FIN_GB_PAGE = __DIR__ . '/../../../../resources/js/Pages/Financeiro/Unificado/Index.tsx';

describe('Gerar Boleto — Controller (emissão título-aware)', function () {
    it('UnificadoController tem emitirBoletoTitulo() injetando o gateway', function () {
        $src = file_get_contents(FIN_GB_CONTROLLER);
        expect($src)->toContain('public function emitirBoletoTitulo(');
        expect($src)->toContain('PaymentGatewayContract $gateway');
        expect($src)->toContain('->emitirBoleto($input)');
    });

    it('Tier 0: escopa Titulo por business_id da session + findOrFail', function () {
        $src = file_get_contents(FIN_GB_CONTROLLER);
        expect($src)->toContain("session('user.business_id')");
        expect($src)->toContain("Titulo::where('business_id', \$businessId)->findOrFail(\$tituloId)");
    });

    it('resolve conta Banco Inter (077) ativa para boleto', function () {
        $src = file_get_contents(FIN_GB_CONTROLLER);
        expect($src)->toContain("->where('banco_codigo', '077')");
        expect($src)->toContain("->where('ativo_para_boleto', true)");
    });

    it('amarra a cobrança ao título via origem_type=fin_titulo (anti-duplo-recebível)', function () {
        $src = file_get_contents(FIN_GB_CONTROLLER);
        expect($src)->toContain("origemType: 'fin_titulo'");
        expect($src)->toContain('origemId: (int) $titulo->id');
    });

    it('persiste a linha digitável em metadata.boleto', function () {
        $src = file_get_contents(FIN_GB_CONTROLLER);
        expect($src)->toContain("'boleto' => [");
        expect($src)->toContain('$result->linhaDigitavel');
    });

    it('vencimento nunca no passado (gateway exige >= hoje)', function () {
        $src = file_get_contents(FIN_GB_CONTROLLER);
        expect($src)->toContain('greaterThan(now())');
    });
});

describe('Gerar Boleto — Rota', function () {
    it('Routes/web.php registra POST /unificado/{tituloId}/boleto + name', function () {
        $src = file_get_contents(FIN_GB_ROUTES);
        expect($src)->toContain("Route::post('/unificado/{tituloId}/boleto'");
        expect($src)->toContain('emitirBoletoTitulo');
        expect($src)->toContain('unificado.emitir-boleto');
    });
});

describe('Gerar Boleto — Reconciliação (baixa sem duplicar)', function () {
    it('listener ramifica em origem_type=fin_titulo e baixa o título existente', function () {
        $src = file_get_contents(FIN_GB_LISTENER);
        expect($src)->toContain("\$cobranca->origem_type === 'fin_titulo'");
        expect($src)->toContain('private function baixarTituloExistente(');
        // baixa o título de origem (não cria PG-xxx)
        expect($src)->toContain("->where('id', \$cobranca->origem_id)");
        expect($src)->toContain('TituloBaixa::create(');
    });
});

describe('Gerar Boleto — Frontend (drawer)', function () {
    it('Index.tsx tem botão "Gerar boleto" postando no endpoint', function () {
        $src = file_get_contents(FIN_GB_PAGE);
        expect($src)->toContain('Gerar boleto');
        expect($src)->toContain('/financeiro/unificado/${selected.id}/boleto');
        expect($src)->toContain('boleto:'); // campo na interface do título
    });
});

describe('Gerar Boleto — Endpoint funcional (HTTP smoke)', function () {
    it('POST /unificado/{id}/boleto retorna gate (302/401/403/404/419/422) sem auth', function () {
        $response = $this->post('/financeiro/unificado/1/boleto');
        expect($response->status())->toBeIn([302, 401, 403, 404, 419, 422]);
    });
});
