<?php

declare(strict_types=1);

// @covers-us US-VEST-020

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Vestuario\Http\Controllers\EtiquetaTagController;
use Modules\Vestuario\Services\EtiquetaTagService;
use Modules\Vestuario\Services\VestuarioSettingsResolver;

uses(Tests\TestCase::class);

/**
 * Contrato da tela /vestuario/etiquetas (US-VEST-020).
 *
 * Os UC vêm do §6 do SDD (CU-VEST-03/04/07) — NUNCA do Index.tsx: teste derivado do
 * código é tautológico e trava o desvio em vez de pegá-lo (proibicoes §5, 2026-06-05).
 *
 *   UC-VET-02 ← CU-VEST-01  (config exposta ao front não carrega segredo do cliente)
 *   UC-VET-03 ← CU-VEST-03  (EAN-13 que a leitora do balcão aceita)
 *   UC-VET-05 ← CU-VEST-04  (etiqueta cabe na mídia 50×30mm + acento legível)
 *   UC-VET-06 ← CU-VEST-07  [V0] gerar etiqueta NÃO grava nada
 *   UC-VET-10 ← CU-VEST-03  (o código impresso é o sub_sku que o caixa procura)
 *   UC-VET-11 ← CU-VEST-02  (texto do cadastro não vira comando ZPL)
 *
 * DESENHO PARA A LANE (importa): o job `Pest Vestuario` (modules-pest.yml, matrix)
 * roda sqlite :memory: SEM migrar — teste que depende de tabela PULA, e teste que pula
 * não prova nada (verde por não-execução, lápide §5 2026-07-24). Por isso tudo aqui é
 * pure-logic, e o caso [V0] carrega CONTROLE-POSITIVO (prova que o listener está armado)
 * + GUARDA ANTI-VÁCUO (prova que a geração aconteceu) antes de afirmar o que NÃO houve.
 *
 * Multi-tenant: biz=1 (Wagner) e biz=99 (adversário). NUNCA biz=4 — é a ROTA LIVRE em
 * produção (ADR 0101).
 *
 * @see memory/requisitos/Vestuario/SDD-tela-etiqueta-tag-v1.0.md §6
 * @see resources/js/Pages/Vestuario/Etiquetas/Index.casos.md
 * @see Modules/Vestuario/Services/EtiquetaTagService.php
 */

// ─────────────────────────────────────────────────────────────────────────────
// UC-VET-03 · EAN-13 que a leitora do balcão aceita  (CU-VEST-03)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Recalcula o dígito verificador GS1 por um caminho INDEPENDENTE do da implementação:
 * o service soma da esquerda (posições ímpares ×1, pares ×3); aqui somamos da DIREITA
 * (peso 3 no dígito mais à direita do payload, alternando). Dois caminhos que precisam
 * concordar — é a dupla-confirmação, não um espelho do código sob teste.
 */
function ucVet03CheckDigitPelaDireita(string $payload12): int
{
    $peso = 3;
    $soma = 0;
    for ($i = strlen($payload12) - 1; $i >= 0; $i--) {
        $soma += ((int) $payload12[$i]) * $peso;
        $peso = $peso === 3 ? 1 : 3;
    }

    return (10 - ($soma % 10)) % 10;
}

it('UC-VET-03: todo EAN-13 impresso passa no check GS1 recalculado por caminho independente', function () {
    $svc = new EtiquetaTagService();

    foreach (['789100000001', '590123412345', '400638133393'] as $payload) {
        $ean = $payload.ucVet03CheckDigitPelaDireita($payload);
        $r = $svc->gerarEtiqueta(1, 1, ['nome' => 'Peça', 'sku' => $ean]);

        expect($r['zpl'])->toContain('^BEN');
        expect($r['zpl'])->toContain('^FD'.$ean.'^FS');
    }
});

it('UC-VET-03: EAN informado com 12 dígitos ganha o check; com 13 inválido é RECUSADO', function () {
    $svc = new EtiquetaTagService();

    // 12 dígitos → completa. O esperado vem do cálculo independente, não do service.
    $payload = '789100000001';
    $completo = $svc->normalizeEan13($payload);
    expect($completo)->toBe($payload.ucVet03CheckDigitPelaDireita($payload));

    // 13 dígitos com check ERRADO → não vira etiqueta (barcode falso não se imprime).
    $errado = $payload.(string) ((ucVet03CheckDigitPelaDireita($payload) + 1) % 10);
    expect(fn () => $svc->normalizeEan13($errado))->toThrow(InvalidArgumentException::class);
});

/** Controller de verdade, com usuário fictício: a lane sqlite não sobe request autenticada. */
function ucVetPost(array $body, string $formato = 'zpl')
{
    $user = new class {
        public function can(string $perm): bool { return true; }
        public function getAuthIdentifier(): int { return 98; }
    };
    $request = Request::create('/vestuario/etiquetas/lote/'.$formato, 'POST', $body);
    $request->setUserResolver(fn () => $user);
    $controller = new EtiquetaTagController(new EtiquetaTagService());

    return $formato === 'zpl' ? $controller->storeZpl($request) : $controller->storePdf($request);
}

it('UC-VET-03: EAN informado com letra ou dígito errado devolve 422, não 500', function () {
    foreach (['78910000000A4', '7891000000015'] as $ean) {
        $res = ucVetPost(['items' => [['product_id' => 1, 'sku' => 'X-1', 'ean13' => $ean]]]);

        expect($res->getStatusCode())->toBe(422);
        expect(json_decode($res->getContent(), true)['message'])->toContain('EAN-13');
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-VET-10 · O código impresso é o que o caixa bipa  (CU-VEST-03)
// ─────────────────────────────────────────────────────────────────────────────

it('UC-VET-10: tamanhos do mesmo produto saem com códigos diferentes, cada um o próprio sub_sku', function () {
    $svc = new EtiquetaTagService();

    $m = $svc->gerarEtiqueta(42, 7, ['nome' => 'Camiseta', 'sku' => 'CAMI-042-M-AZU'])['zpl'];
    $g = $svc->gerarEtiqueta(42, 8, ['nome' => 'Camiseta', 'sku' => 'CAMI-042-G-AZU'])['zpl'];

    expect($m)->toContain('^BCN')->toContain('^FDCAMI-042-M-AZU^FS')->not->toContain('^BEN');
    expect($g)->toContain('^BCN')->toContain('^FDCAMI-042-G-AZU^FS')->not->toContain('^BEN');
    expect($m)->not->toContain('7890000000420');
});

it('UC-VET-10: sub_sku EAN-13 válido sai como EAN-13; 13 dígitos com check errado saem em Code128', function () {
    $svc = new EtiquetaTagService();

    expect($svc->gerarEtiqueta(1, 1, ['sku' => '7891000000014'])['zpl'])->toContain('^BEN')->toContain('^FD7891000000014^FS');
    expect($svc->gerarEtiqueta(1, 1, ['sku' => '7891000000015'])['zpl'])->toContain('^BCN')->toContain('^FD7891000000015^FS');
});

it('UC-VET-10: item vindo da remarcação (sem ean13) imprime o sub_sku', function () {
    $res = ucVetPost(['items' => [[
        'product_id' => 42, 'variation_id' => 7, 'nome' => 'Vestido', 'tamanho' => 'M',
        'preco' => 10.5, 'sku' => 'REM-123456-P',
    ]]]);

    expect($res->getStatusCode())->toBe(200);
    expect($res->getContent())->toContain('^BCN')->toContain('^FDREM-123456-P^FS');
});

it('UC-VET-10: o PDF desenha o Code128 do sub_sku e o EAN-13 do sub_sku EAN', function () {
    $svc = new EtiquetaTagService();
    $etiquetas = array_map(fn (array $r) => $r['meta'] + ['ean13' => $r['ean13'], 'sku' => $r['sku']], [
        $svc->gerarEtiqueta(1, 1, ['nome' => 'A', 'sku' => 'CAMI-042-M-AZU']),
        $svc->gerarEtiqueta(1, 2, ['nome' => 'B', 'sku' => '7891000000014']),
    ]);

    $html = view('vestuario::etiquetas.pdf', ['etiquetas' => $etiquetas, 'business_id' => null])->render();

    expect(substr_count($html, 'data:image/png;base64'))->toBe(2);
    expect($html)->toContain('alt="CAMI-042-M-AZU"')->toContain('alt="7891000000014"');
});

it('UC-VET-10: sem SKU, com acento ou comprido demais para a etiqueta devolve 422', function () {
    foreach ([null, 'CAMISETA-AÇAÍ', str_repeat('A', 32)] as $sku) {
        $res = ucVetPost(['items' => [array_filter(['product_id' => 1, 'sku' => $sku])]]);

        expect($res->getStatusCode())->toBe(422);
        expect(json_decode($res->getContent(), true)['message'])->toContain('SKU');
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-VET-11 · Texto do cadastro não vira comando de impressora  (CU-VEST-02)
// ─────────────────────────────────────────────────────────────────────────────

it('UC-VET-11: ^ e ~ nos campos de texto não abrem comando ZPL', function () {
    $r = (new EtiquetaTagService())->gerarEtiqueta(1, 1, [
        'nome' => '^XZ^XA^JUF^XZ', 'tamanho' => '~JA', 'cor' => '^PQ9', 'colecao' => '~JR', 'sku' => 'INJ^XZ',
    ]);

    expect(substr_count($r['zpl'], '^XA'))->toBe(1);
    expect(substr_count($r['zpl'], '^XZ'))->toBe(1);
    expect($r['zpl'])->not->toContain('^JUF')->not->toContain('~JA')->not->toContain('^PQ9')->not->toContain('~JR');
    $bs = chr(92);
    expect($r['zpl'])->toContain($bs.'5EXZ'.$bs.'5EXA');
});

it('UC-VET-11: barra invertida e quebra de linha no texto não desmontam o campo', function () {
    $bs = chr(92);
    $r = (new EtiquetaTagService())->gerarEtiqueta(1, 1, ['nome' => "Linha{$bs}5E\n^XZ", 'sku' => 'BS-1']);

    expect(substr_count($r['zpl'], '^XZ'))->toBe(1);
    expect($r['zpl'])->toContain("Linha{$bs}5C5E");
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-VET-05 · A etiqueta cabe na mídia física de 50×30mm  (CU-VEST-04)
// ─────────────────────────────────────────────────────────────────────────────

it('UC-VET-05: nome/cor/coleção longos são truncados — o texto inteiro NÃO vai pra etiqueta', function () {
    $svc = new EtiquetaTagService();

    $nomeLongo    = str_repeat('A', 20).'BCDEFGHIJKLMNOPQRSTUVWXYZ'; // 46 chars (> 30)
    $corLonga     = str_repeat('B', 40);                              // 40 chars (> 20)
    $colecaoLonga = str_repeat('C', 40);                              // 40 chars (> 25)

    $r = $svc->gerarEtiqueta(1, 1, [
        'nome'    => $nomeLongo,
        'cor'     => $corLonga,
        'colecao' => $colecaoLonga,
        'sku'     => 'TRUNC-001',
    ]);

    // Anti-vácuo: a geração ACONTECEU (senão "não contém o nome inteiro" é trivialmente verdade).
    expect($r['zpl'])->toStartWith('^XA')->toEndWith('^XZ');
    expect($r['zpl'])->toContain('TRUNC-001');

    // O contrato: o texto inteiro não chega à etiqueta física.
    expect($r['zpl'])->not->toContain($nomeLongo);
    expect($r['zpl'])->not->toContain($corLonga);
    expect($r['zpl'])->not->toContain($colecaoLonga);

    // E o começo de cada campo continua legível (truncar ≠ apagar).
    expect($r['zpl'])->toContain(mb_substr($nomeLongo, 0, 20));
    expect($r['zpl'])->toContain(mb_substr($corLonga, 0, 15));
});

it('UC-VET-05: acento sobrevive — ZPL declara UTF-8 e o texto não corrompe', function () {
    $svc = new EtiquetaTagService();

    $r = $svc->gerarEtiqueta(1, 1, [
        'nome'    => 'Camiseta Básica',
        'cor'     => 'Açaí',
        'colecao' => 'Coleção Verão 2026',
        'sku'     => 'ACENTO-001',
    ]);

    expect($r['zpl'])->toContain('^CI28');                 // sem isto a térmica cospe lixo
    expect($r['zpl'])->toContain('Camiseta Básica');
    expect($r['zpl'])->toContain('Coleção Verão 2026');
    expect(mb_check_encoding($r['zpl'], 'UTF-8'))->toBeTrue();
});

it('UC-VET-05: truncagem é mb-safe — não parte caractere multibyte ao meio', function () {
    $svc = new EtiquetaTagService();

    // 40 caracteres multibyte (> 30): se o corte fosse por BYTE, sairia lixo.
    $r = $svc->gerarEtiqueta(1, 1, ['nome' => str_repeat('ç', 40), 'sku' => 'MB-001']);

    expect($r['zpl'])->toContain('MB-001');                            // anti-vácuo
    expect(mb_check_encoding($r['zpl'], 'UTF-8'))->toBeTrue();
    expect($r['zpl'])->not->toContain(str_repeat('ç', 40));
});

it('UC-VET-05: dimensões do ZPL seguem a configuração efetiva (default 50×30mm @203dpi)', function () {
    $svc = new EtiquetaTagService();

    $r = $svc->gerarEtiqueta(1, 1, ['nome' => 'X', 'sku' => 'DIM-001']);
    $cfg = $svc->getPublicConfig(null);

    expect($r['zpl'])->toContain('^PW'.$cfg['width_dots']);
    expect($r['zpl'])->toContain('^LL'.$cfg['height_dots']);
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-VET-06 · [V0] Gerar etiqueta NÃO altera valor nem estoque  (CU-VEST-07)
// ─────────────────────────────────────────────────────────────────────────────

it('UC-VET-06: gerar lote NÃO emite nenhuma escrita no banco (etiqueta é saída, não movimento)', function () {
    Cache::forget('vestuario.settings.1'); // força o resolver a tentar ler de verdade

    $svc = new EtiquetaTagService(new VestuarioSettingsResolver());

    $sqls = [];
    DB::listen(function ($q) use (&$sqls) {
        $sqls[] = $q->sql;
    });

    // CONTROLE-POSITIVO: prova que o listener está armado. Sem isto, "zero escritas"
    // poderia significar apenas "zero queries observadas" — que não prova nada.
    DB::select('select 1 as sentinela');

    $zpl = $svc->gerarLote([
        ['product_id' => 1, 'variation_id' => 1, 'opts' => ['nome' => 'P1', 'preco' => 10.5, 'sku' => 'V-1', 'businessId' => 1]],
        ['product_id' => 2, 'variation_id' => 1, 'opts' => ['nome' => 'P2', 'preco' => 20.0, 'sku' => 'V-2', 'businessId' => 1]],
    ]);

    // GUARDA ANTI-VÁCUO: a operação aconteceu de fato (2 etiquetas saíram).
    expect(substr_count($zpl, '^XA'))->toBe(2);
    expect($sqls)->not->toBeEmpty(); // o listener capturou ao menos o sentinela

    $escritas = array_values(array_filter(
        $sqls,
        fn (string $sql) => (bool) preg_match('/^\s*(insert|update|delete|replace|truncate|drop|alter)\b/i', $sql)
    ));

    expect($escritas)->toBe([], 'gerar etiqueta escreveu no banco: '.implode(' | ', $escritas));
});

it('UC-VET-06: o preço impresso é o que o operador informou — nada é lido nem gravado do produto', function () {
    $svc = new EtiquetaTagService();

    $r = $svc->gerarEtiqueta(1, 1, ['nome' => 'Camiseta', 'preco' => 12.34, 'sku' => 'PRECO-001']);

    // Comportamento: o valor digitado chega formatado em pt-BR (vírgula decimal),
    // e o service devolve o MESMO valor no meta — sem consultar preço de variação.
    expect($r['meta']['preco'])->toBe(12.34);
    expect($r['zpl'])->toContain('12,34');
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-VET-02 · A config que chega ao front não carrega segredo do cliente  (CU-VEST-01)
// ─────────────────────────────────────────────────────────────────────────────

it('UC-VET-02: a config exposta pro front NÃO carrega o template de URL do QR', function () {
    $svc = new EtiquetaTagService(new VestuarioSettingsResolver());

    $cfg = $svc->getPublicConfig(1);

    // O contrato é de COMPORTAMENTO, não de nome de chave: nenhum valor da config
    // exposta pode conter a URL de consulta do cliente (que o ZPL usa internamente).
    expect($cfg)->toHaveKeys(['width_dots', 'height_dots', 'dpi', 'margin_dots', 'qr_enabled']);

    $vazamentos = array_filter(
        $cfg,
        fn ($v) => is_string($v) && (str_contains($v, '://') || str_contains($v, '{ean13}'))
    );
    expect($vazamentos)->toBe([], 'config pública vazou URL/template: '.json_encode($vazamentos));
});
