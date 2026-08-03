<?php

/**
 * Contrato da rota /documentacao (ADR 0256 — a página É a fonte, renderizada).
 *
 * O que cada caso defende:
 *   1. a rota exige login (decisão [W] 2026-08-02: doc interna não fica pública);
 *   2. o documento fonte EXISTE no repo — é o defeito mais provável desta rota:
 *      alguém renomeia/move o GUIA e a página passa a dar 503 em produção,
 *      silenciosamente, até um humano abrir. Este caso pega isso no PR;
 *   3. autenticado, a página responde e traz o conteúdo do dono.
 *
 * O caso 3 pula sem user semeado — declarado, não escondido: "0 failed" não
 * prova execução, então o caso 2 (que sempre roda) é quem sustenta o contrato.
 */

use App\User;

it('exige login em /documentacao', function () {
    $r = $this->get('/documentacao');

    // 302 pro login — nunca 200 pra anônimo.
    expect($r->getStatusCode())->toBe(302);
    expect($r->headers->get('Location'))->toContain('login');
});

it('o documento fonte que a rota renderiza existe no repo', function () {
    // Espelha a const FONTE do DocumentacaoController. Se divergir, a rota
    // devolve 503 em prod — este caso quebra antes, no PR.
    $fonte = base_path('memory/GUIA-DO-SISTEMA.md');

    expect(file_exists($fonte))->toBeTrue();

    $conteudo = file_get_contents($fonte);
    expect(strlen($conteudo))->toBeGreaterThan(500);
    // O controller remove o frontmatter; se o formato mudar, a remoção falha calada.
    expect($conteudo)->toStartWith('---');
});

it('responde 200 e renderiza o conteudo do dono quando autenticado', function () {
    $user = User::query()->whereNotNull('email')->first();
    if (! $user) {
        $this->markTestSkipped('Sem users no DB — este caso não executou.');
    }

    $r = $this->actingAs($user)->get('/documentacao');

    expect($r->getStatusCode())->toBe(200);

    $html = $r->getContent();
    // veio do markdown convertido, não de um HTML estático commitado
    expect($html)->toContain('memory/GUIA-DO-SISTEMA.md');
    // o frontmatter YAML NÃO pode vazar pra página
    expect($html)->not->toContain('slug: guia-do-sistema');
    // e o markdown virou HTML de verdade (não texto cru com '##')
    expect($html)->toContain('<h2');
});
