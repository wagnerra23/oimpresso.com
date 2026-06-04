<?php

declare(strict_types=1);

/**
 * Pest test estrutural — Pages/Sells/Create.tsx — subtitle do header NÃO duplica Local.
 *
 * Feature "subtitle" (dedup do header):
 *   O subtitle/cabeçalho da tela de venda NÃO deve repetir "Local: <nome>"
 *   (defaultLocation.name) porque o campo Local já aparece no card
 *   "Dados da venda" (linha "Local" do grid sec-dados). Larissa (ROTA LIVRE,
 *   biz=1 em teste — NUNCA biz=4) lê a informação 2× = ruído.
 *
 * Estado-alvo (o "pronto"):
 *   - O bloco do subtitle que renderiza defaultLocation.name foi REMOVIDO.
 *   - O único lugar que mostra o Local é o <Select id="location_id"> do
 *     card "Dados da venda".
 *   - Um marcador-sentinela (comentário) prova que a dedup foi intencional —
 *     evita que o teste passe trivialmente hoje (o subtitle genérico atual
 *     ainda não foi tocado pela feature).
 *
 * Teste TEST-FIRST: enquanto a feature não for implementada os it() ficam
 * VERMELHOS (sentinela ausente). Quando eu implementar (remover o bloco dup +
 * adicionar a sentinela), ficam VERDES.
 *
 * Estilo: estrutural — lê o source com file_get_contents e faz expect(...)
 * sobre o texto (paridade com SaleSheetComponentTest + CustomerAutoApplyOnSelectTest).
 *
 * Refs: ADR 0101 (tests biz=1 nunca cliente), ADR 0104 (MWART canônico),
 *        US-SELL-004 (triagem de campos visíveis).
 */

const CREATE_PATH_SUBTITLE = 'resources/js/Pages/Sells/Create.tsx';

function readCreateSubtitle(): string
{
    return file_get_contents(base_path(CREATE_PATH_SUBTITLE));
}

/**
 * Recorta a região do header (do <h1 do título até o início do card
 * "Dados da venda" / sec-dados). É nessa faixa que o subtitle vive —
 * escopar evita falso-positivo com o <Select id="location_id"> legítimo
 * (que está DENTRO do card sec-dados, fora do recorte).
 */
function headerRegionSubtitle(string $src): string
{
    $start = strpos($src, '<h1');
    expect($start)->not->toBeFalse(); // header existe

    $end = strpos($src, "id=\"sec-dados\"");
    expect($end)->not->toBeFalse();   // card "Dados da venda" existe

    return substr($src, $start, $end - $start);
}

it('Create.tsx existe', function () {
    expect(file_exists(base_path(CREATE_PATH_SUBTITLE)))->toBeTrue();
});

// ─── Sentinela: prova a dedup intencional (RED hoje, GREEN após implementar) ──

it('subtitle do header tem sentinela de dedup do Local (dedup intencional aplicada)', function () {
    // Marcador que o implementador adiciona ao remover o bloco dup.
    // Enquanto ausente → VERMELHO (feature não implementada).
    $src = readCreateSubtitle();
    expect($src)->toMatch('/subtitle.*(não|nao).*duplica.*Local|Local.*card.*Dados da venda/i');
});

// ─── Negativa escopada: subtitle NÃO renderiza defaultLocation.name ───────────

it('subtitle do header NÃO renderiza defaultLocation.name (sem dup do card Dados da venda)', function () {
    $src = readCreateSubtitle();
    $header = headerRegionSubtitle($src);
    // O nome do local NÃO pode aparecer no cabeçalho — só vive no <Select> do card.
    expect($header)->not->toContain('defaultLocation.name');
    expect($header)->not->toContain('defaultLocation?.name');
});

it('subtitle do header NÃO escreve label literal "Local:" (Larissa não lê 2×)', function () {
    $src = readCreateSubtitle();
    $header = headerRegionSubtitle($src);
    // Nenhuma string "Local:" no recorte do header (o label do campo é só "Local").
    expect($header)->not->toMatch('/Local\s*:/');
});
