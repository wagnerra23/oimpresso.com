<?php

declare(strict_types=1);

// Contrato do glob de discovery das Pages do Inertia.
//
// POR QUE ESTE ARQUIVO EXISTE SEPARADO (2026-08-12):
// Estes 3 UCs viviam no `CoworkBundleIntegralTest.php`, que está em
// `.github/financeiro-pest-quarantine.list` por causa de OUTROS 4 describes
// (os do bundle CSS, quebrados desde que o CSS foi deletado no #2127). A lane
// exclui o arquivo INTEIRO da quarentena — então o contrato do glob era vítima
// colateral e NUNCA rodava no CI de PR, apesar de verde. Medido no run
// 31603374244: `grep "discovery Inertia"` no log = 0 ocorrências, enquanto a
// suíte reportava 348 passed. Verde que não inclui o que você acha que inclui.
//
// Aqui fora ele roda: a lane monta o run-set por `find Modules/Financeiro/Tests`
// MENOS a quarentena, e arquivo novo entra rodando (fail-closed pra código novo).
//
// POR QUE EM Modules/Financeiro/Tests E NÃO EM tests/Feature (o contrato é app-wide):
// a regra existe POR CAUSA do Financeiro — é o `Pages/Financeiro/_cowork-bundle/`
// (10 `.jsx`) que precisa ficar inerte. E a lane que dispara com os entrypoints
// (`resources/js/app.tsx`/`ssr.tsx` no `push.paths` + no filtro `fin:`) é esta.
// Movê-lo pra `tests/Feature/` exigiria nomeá-lo como "extra" na lane — mais
// acoplamento, mesmo resultado.
//
// O CONTRATO EM UMA LINHA: o local das Pages é CONVENÇÃO DO PROJETO, não
// imposição do Inertia — o `resolve` do `createInertiaApp` é callback arbitrário.
// Ver `.claude/rules/pages.md` §"Onde as Pages vivem".

describe('Contrato — discovery das Pages do Inertia', function () {
    // UC-1 · o glob do client. Trocar a extensão pra .jsx faria o discovery
    // varrer `_cowork-bundle/` e importar o bundle por engano.
    it('UC-1 · app.tsx usa o glob ./Pages/**/*.tsx (nunca .jsx)', function () {
        $src = file_get_contents(__DIR__ . '/../../../../resources/js/app.tsx');
        expect($src)->toContain("import.meta.glob('./Pages/**/*.tsx')");
        expect($src)->not->toContain('./Pages/**/*.jsx');
    });

    // UC-2 · O SEGUNDO GLOB. `app.tsx` (client) e `ssr.tsx` (SSR) declaram o MESMO
    // glob e são sincronizados À MÃO. O UC-1 sozinho cobria só o client: trocar o
    // glob apenas no ssr.tsx passava verde. As DUAS pontas contam.
    it('UC-2 · ssr.tsx usa o mesmo glob ./Pages/**/*.tsx (nunca .jsx)', function () {
        $src = file_get_contents(__DIR__ . '/../../../../resources/js/ssr.tsx');
        expect($src)->toContain("import.meta.glob('./Pages/**/*.tsx')");
        expect($src)->not->toContain('./Pages/**/*.jsx');
    });

    // UC-3 · O GATILHO. Os asserts acima só valem se a lane RODAR quando o glob
    // muda. Até 2026-08-12 ela não rodava: `resources/js/**` não estava no
    // `push.paths` nem no filtro `fin:` do dorny, então PR que trocasse o glob caía
    // em skip-as-pass — VERDE sem executar nada (LC-11/LC-13). Medido com picomatch
    // sobre o filtro real: ANTES false/false, DEPOIS true/true; controles negativos
    // (Pages/Sells/Create.tsx, README.md) não disparam.
    //
    // LIMITE HONESTO: compara o path LITERAL. Se alguém substituir por um glob
    // equivalente (ex. 'resources/js/*.tsx'), este teste avermelha sem haver
    // regressão — falha ruidosa e de conserto óbvio (atualize a lista abaixo).
    // Preferido a um matcher de glob em PHP, que não tem `**` nativo.
    it('UC-3 · a lane financeiro-pest dispara quando os entrypoints do glob mudam', function () {
        $yml = file_get_contents(__DIR__ . '/../../../../.github/workflows/financeiro-pest.yml');

        // As DUAS listas decidem — push.paths (main) e o filtro `fin:` (skip-as-pass no PR).
        // 2 ocorrências de cada = presente nas duas.
        foreach (['resources/js/app.tsx', 'resources/js/ssr.tsx'] as $entrypoint) {
            expect(substr_count($yml, "'{$entrypoint}'"))
                ->toBe(2, "{$entrypoint} deve estar em push.paths E no filtro fin: do dorny — "
                    . 'senão trocar o glob dá skip-as-pass (verde sem rodar este arquivo).');
        }
    });

    // UC-4 · ESTE arquivo não pode voltar pra quarentena sem alguém perceber.
    // Foi exatamente assim que os UCs acima ficaram mudos: herdaram a quarentena
    // de describes vizinhos quebrados. Se um dia precisar entrar, que seja com o
    // teste vermelho avisando — não em silêncio.
    //
    // PARSEIA COMO A LANE PARSEIA — e isso não é preciosismo, é o conserto de um
    // bug real deste próprio teste (2026-08-12). A 1ª versão fazia `toContain` no
    // texto BRUTO e ficou vermelha porque o nome deste arquivo aparece num
    // COMENTÁRIO da linha do CoworkBundleIntegralTest — presence-gate sobre texto
    // livre, a família LC-11 que este PR conserta, cometida dentro da correção.
    // A lane monta o run-set com `sed 's/#.*//'` + trim + drop-vazias; medir
    // qualquer outra coisa responde a pergunta errada.
    //
    // Lista AUSENTE = nenhum arquivo em quarentena = passa (a pergunta é "este
    // arquivo está no run-set excluído?", e sem lista a resposta é não). O guard
    // `is_file` existe porque `file_get_contents` num arquivo ausente devolve
    // `false` COM warning: o `false` estoura `InvalidExpectationValue` (erro
    // confuso que esconde a causa) e o warning marca o teste como risky em vez de
    // passar. Medido nos dois casos no CT100, cujo checkout antecede a lista.
    it('UC-4 · este arquivo não está na quarentena da lane', function () {
        $lista = __DIR__ . '/../../../../.github/financeiro-pest-quarantine.list';
        $bruto = is_file($lista) ? (string) file_get_contents($lista) : '';

        $entradas = array_values(array_filter(array_map(
            static fn (string $l): string => trim(explode('#', $l, 2)[0]),
            explode("\n", $bruto),
        ), static fn (string $l): bool => $l !== ''));

        expect($entradas)->not->toContain('Modules/Financeiro/Tests/Feature/InertiaPagesGlobContratoTest.php');
    });

    // UC-5 — O SEGUNDO GLOB DE CADA PONTA. Desde 2026-08-12 uma tela pode morar dentro do módulo
    // dono (`Modules/<X>/Resources/js/Pages/**`) e o resolver mescla esse glob com o do núcleo,
    // normalizando a chave — é o que faz `Inertia::render('Settings/PaymentGateways/Index')`
    // resolver sem que NENHUM call-site mude (a afirmação independe da contagem).
    //
    // POR QUE PRECISA DE ASSERT: medido no dia com controle negativo — removendo o glob de
    // módulos, o build sai exit 0 IGUAL e a tela apenas não entra no bundle (0 chunks contra 1).
    // Build verde não prova descoberta, então o contrato tem de estar aqui.
    //
    // `Resources` MAIÚSCULO não é capricho: é a convenção nWidart deste repo (711 arquivos contra
    // 12) e o glob do Vite é case-SENSITIVE — com o casing errado o mapa sai vazio em silêncio.
    // O `[Rr]` do regex de normalização é o que transforma esse silêncio em erro visível.
    // ⚠️ `toContain` do Pest recebe N NEEDLES, não (needle, mensagem) — passar a explicação como
    // 2º argumento faz o assert procurar a FRASE dentro do arquivo e falhar sempre. Foi o que
    // aconteceu na 1ª versão deste UC, e é a mesma classe do §5 2026-07-28 (38 casos corrigidos
    // no #4918). O porquê de cada asserção mora em comentário; a mensagem vai por `->toBeTrue()`
    // quando precisar de texto.
    it('UC-5 · as duas pontas descobrem telas dentro de Modules/<X>/Resources/js/Pages', function () {
        foreach (['app.tsx', 'ssr.tsx'] as $ponta) {
            $src = (string) file_get_contents(__DIR__ . '/../../../../resources/js/' . $ponta);

            // Sem o glob de módulos a tela migrada some do bundle SEM ERRO (build segue exit 0):
            // medido com controle negativo em 2026-08-12 — 0 chunks contra 1.
            expect($src)->toContain("import.meta.glob('../../Modules/*/Resources/js/Pages/**/*.tsx')");

            // Sem a normalização o glob acha os arquivos e nenhum `Inertia::render` os resolve.
            expect($src)->toContain('[Rr]esources');
        }
    });
});
