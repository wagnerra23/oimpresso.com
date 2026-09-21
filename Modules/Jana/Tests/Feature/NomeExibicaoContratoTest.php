<?php

declare(strict_types=1);

use App\User;

uses(Tests\TestCase::class);

/**
 * Nome do usuário na área da Jana — CONTRATO. Defende o `UC-JPAIN-25`.
 *
 * ── O DEFEITO QUE ESTE TESTE VEIO TRAVAR (medido em produção, 2026-09-18) ───
 * Os 6 controllers da área liam `optional(auth()->user())->name` — e a tabela
 * `users` NÃO TEM a coluna `name`: a migration original
 * (`2014_10_12_000000_create_users_table.php:17-27`) declara `surname`,
 * `first_name`, `last_name`, `username`, `email`, `password`, `language`, e o
 * model não tem accessor para `name`. A leitura devolvia **null em silêncio**.
 *
 * Consequência medida em `/ia` (biz=1, autenticado, DOM estabilizado em 1129
 * nós): o `<h2>` renderizava "Ações que VOCÊ sugere" (o fallback `|| 'você'` do
 * `JanaCockpit.tsx:318`) e a saudação do brief saía "Boa tarde." sem nome. Ou
 * seja: a personalização da área nunca funcionou, em nenhum tenant.
 *
 * ── POR QUE NÃO É TAUTOLÓGICO (§5 2026-06-05) ───────────────────────────────
 * As asserções não vêm do código consertado. Vêm de fontes externas:
 *   1. `resources/views/user/profile.blade.php:78` — prova que `surname` é o
 *      PREFIXO (rotulado `__('business.prefix')`), não sobrenome;
 *   2. `app/Http/Middleware/HandleInertiaRequests.php:68` — o idioma canônico
 *      do projeto para "nome do usuário" é `first_name . ' ' . last_name`;
 *   3. o DOM de produção medido acima, que é o que o UC-JPAIN-25 descreve.
 *
 * ── O CASO 2 É DISCRIMINANTE POR CONSTRUÇÃO (§5 2026-09-05) ─────────────────
 * Com `surname='Sr.'`, os três candidatos produzem valores DIFERENTES:
 *   `nome_exibicao`  → "Wagner Rocha"      ← o certo
 *   `user_full_name` → "Sr. Wagner Rocha"  ← o erro tentador (split daria "Sr.")
 *   `name`           → null                ← o defeito original
 * Um assert que passasse nos três não provaria nada. Este só passa no primeiro.
 *
 * Os casos 1-4 são de UNIDADE e não tocam o banco — rodam sempre, nunca skipam.
 * (Skip sai exit 0: leia ASSERTIONS, não "0 failed" — LC-13.)
 */
const AREA_JANA_CONTROLLERS = [
    'Modules/Jana/Http/Controllers/IndexController.php',
    'Modules/Jana/Http/Controllers/ChatController.php',
    'Modules/Jana/Http/Controllers/AcaoHitlController.php',
    'Modules/Jana/Http/Controllers/AlertasController.php',
    'Modules/Jana/Http/Controllers/SuperadminController.php',
    'Modules/KB/Http/Controllers/MemoriaController.php',
];

function usuarioDeExibicao(string $surname, string $first, ?string $last): User
{
    $u = new User;
    $u->surname = $surname;
    $u->first_name = $first;
    $u->last_name = $last;

    return $u;
}

it('UC-JPAIN-25: MORDE — nome_exibicao monta first_name + last_name', function () {
    $u = usuarioDeExibicao('Sr.', 'Wagner', 'Rocha');

    expect($u->nome_exibicao)->toBe('Wagner Rocha');
});

it('UC-JPAIN-25: MORDE — nome_exibicao NAO vaza o prefixo (surname), ao contrario de user_full_name', function () {
    $u = usuarioDeExibicao('Sr.', 'Wagner', 'Rocha');

    // O discriminante: os dois atributos existem e devolvem coisas diferentes.
    // Trocar o accessor por `user_full_name` derruba este caso.
    expect($u->nome_exibicao)->not->toContain('Sr.');
    expect($u->user_full_name)->toContain('Sr.');

    // E o que o consumidor de fato faz com o valor (JanaCockpit.tsx:318):
    $primeiroNome = explode(' ', $u->nome_exibicao)[0];
    expect($primeiroNome)->toBe('Wagner');
    expect($primeiroNome)->not->toBe('Sr.');
});

it('UC-JPAIN-25: MORDE — nome_exibicao sobrevive a last_name nulo, sem espaco sobrando', function () {
    $u = usuarioDeExibicao('Sra.', 'Larissa', null);

    expect($u->nome_exibicao)->toBe('Larissa');
});

it('UC-JPAIN-25: MORDE — a coluna `name` nao existe, entao le-la devolve null (a causa do defeito)', function () {
    $u = usuarioDeExibicao('Sr.', 'Wagner', 'Rocha');

    // Este assert documenta a CAUSA. Se um dia alguém adicionar a coluna ou um
    // accessor `name`, ele cai — e aí esta lápide precisa ser relida, não
    // "consertada" apagando o caso.
    expect($u->name)->toBeNull();
});

it('UC-JPAIN-25: MORDE — nenhum controller da area volta a ler `->name` do usuario', function () {
    $reincidentes = [];

    foreach (AREA_JANA_CONTROLLERS as $rel) {
        $caminho = base_path($rel);

        // Controle de execução: se o arquivo sumiu/mudou de lugar, o teste TEM
        // que falhar — e não passar por vacuidade varrendo um conjunto vazio.
        expect(file_exists($caminho))->toBeTrue("Controller da área não encontrado: {$rel}");

        $fonte = file_get_contents($caminho);

        if (str_contains($fonte, 'optional(auth()->user())->name,')
            || str_contains($fonte, 'optional($user)->name,')) {
            $reincidentes[] = $rel;
        }
    }

    expect($reincidentes)->toBe([]);
});
