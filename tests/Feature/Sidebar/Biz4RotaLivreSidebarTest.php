<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')). NÃO redeclarar aqui — Pest 4 lança TestCaseAlreadyInUse.

/**
 * Anti-regressão hardcode biz=N — Wagner regra 2026-05-18 IRREVOGÁVEL.
 *
 * Visibilidade de módulos POR business é via subscription package (UI
 * Modules/Superadmin/PackagesController) — NUNCA `if ($business_id === N) return`.
 *
 * Este test substitui Biz4RotaLivreSidebarTest original (PRs #1073-#1076) que
 * validava hardcode `=== 4`. Após revert (PR #1077), o test inverte: garante
 * que NÃO existe hardcode biz=4 em nenhum dos 5 arquivos tocados.
 *
 * Validações OK que sobrevivem do trabalho anterior:
 *   - 4 entradas top-level no FINANCEIRO (não dropdown popover) — PR #1075
 *   - Lang keys cashflow/dre/gateway de pagamento — PR #1076
 *   - SIDEBAR_GROUPS items novos + MENU_ICON_MAP entries
 *
 * Refs:
 *   - memory/reference/feedback-habilitar-modulo-por-business.md
 *   - ADR 0093 (multi-tenant Tier 0)
 */

const ROOT = __DIR__ . '/../../..';

const FILES_QUE_NAO_PODEM_HARDCODE_BIZ_4 = [
    '/Modules/Financeiro/Http/Controllers/DataController.php',
    '/Modules/Governance/Http/Controllers/DataController.php',
    '/Modules/Woocommerce/Http/Controllers/DataController.php',
    '/app/Http/Middleware/HandleInertiaRequests.php',
    '/app/Http/Middleware/AdminSidebarMenu.php',
];

describe('Anti-regressão hardcode biz=N — IRREVOGÁVEL Wagner 2026-05-18', function () {
    it('NENHUM arquivo tocado na sessão biz=4 tem hardcode === 4 ou !== 4', function () {
        foreach (FILES_QUE_NAO_PODEM_HARDCODE_BIZ_4 as $rel) {
            $src = file_get_contents(ROOT . $rel);
            // Patterns a banir
            expect($src)->not->toContain('$business_id === 4');
            expect($src)->not->toContain('$businessId === 4');
            expect($src)->not->toContain('$current_biz === 4');
            expect($src)->not->toContain('$business_id !== 4');
            expect($src)->not->toContain('$businessId !== 4');
            expect($src)->not->toContain('$current_biz !== 4');
            expect($src)->not->toContain('$piloto_rotalivre');
            // Mais defensivo: nenhuma variável de business hardcoded == 4 sem espaço
            expect($src)->not->toMatch('/business_id\s*[!=]==\s*4/');
            expect($src)->not->toMatch('/businessId\s*[!=]==\s*4/');
        }
    });

    it('Módulos com gate de subscription preservaram pattern canônico', function () {
        // Financeiro: deve checar permission financeiro.access (não hardcode biz=4)
        $financeiro = file_get_contents(ROOT . '/Modules/Financeiro/Http/Controllers/DataController.php');
        expect($financeiro)->toContain('hasThePermissionInSubscription');
        expect($financeiro)->toContain("'financeiro_module'");

        // Woocommerce: idem
        $woo = file_get_contents(ROOT . '/Modules/Woocommerce/Http/Controllers/DataController.php');
        expect($woo)->toContain('hasThePermissionInSubscription');
        expect($woo)->toContain("'woocommerce_module'");
    });

    it('AdminSidebarMenu usa $enabled_modules (subscription) — não hardcode biz', function () {
        $src = file_get_contents(ROOT . '/app/Http/Middleware/AdminSidebarMenu.php');
        // Pattern canon: in_array('feature', $enabled_modules) + can()
        expect($src)->toContain("in_array('expenses', \$enabled_modules)");
        expect($src)->toContain("in_array('service_staff', \$enabled_modules)");
    });
});

describe('Sidebar FINANÇAS canon — 4 entries flat (Wagner 2026-05-26)', function () {
    it('Financeiro DataController publica 3 entries top-level (Caixa · Cobrança · Financeiro)', function () {
        $src = file_get_contents(ROOT . '/Modules/Financeiro/Http/Controllers/DataController.php');
        // 3 URLs primary distintos (4ª entrada Cobrança Recorrente vem do RecurringBilling)
        expect($src)->toContain("url('/financeiro/caixa')");
        expect($src)->toContain("url('/financeiro/cobranca')");
        expect($src)->toContain("url('/financeiro/unificado')");
        // Orders fracionários 85.00 / 85.10 / 85.20 (3 entries Financeiro)
        expect($src)->toContain('->order(85.00)');
        expect($src)->toContain('->order(85.10)');
        expect($src)->toContain('->order(85.20)');
        // Labels canon
        expect($src)->toContain("'Caixa'");
        expect($src)->toContain("'Cobrança'");
        // Gateway é GHOST da Cobrança — não entry separada
        expect($src)->toContain("'key' => 'gateway'");
        expect($src)->toContain("/settings/payment-gateways");
    });

    it('Cobrança Recorrente continua entry própria (RecurringBilling DataController)', function () {
        $src = file_get_contents(ROOT . '/Modules/RecurringBilling/Http/Controllers/DataController.php');
        expect($src)->toContain("'Cobrança Recorrente'");
        expect($src)->toContain("'group'   => 'financas'");
        expect($src)->toContain('->order(86)');
    });

    it('PaymentGateway DataController NÃO injeta sidebar (Gateway virou ghost)', function () {
        $src = file_get_contents(ROOT . '/Modules/PaymentGateway/Http/Controllers/DataController.php');
        // Método modifyAdminMenu existe MAS é no-op (vazio + docblock)
        expect($src)->toContain('public function modifyAdminMenu(): void');
        expect($src)->not->toContain("Menu::modify");
        expect($src)->not->toContain("'Gateway de Pagamento'");
    });

    it('Sidebar.tsx SIDEBAR_GROUPS.financas tem 4 labels canon flat', function () {
        $src = file_get_contents(ROOT . '/resources/js/Components/cockpit/Sidebar.tsx');
        // Whitelist canon (substitui ['Financeiro'] de antes)
        expect($src)->toContain("items: ['Caixa', 'Cobrança', 'Financeiro', 'Cobrança Recorrente'],");
        // MENU_ICON_MAP entries novos
        expect($src)->toContain("caixa: Banknote,");
        expect($src)->toContain("'cobrança': HandCoins");
        // Ícones legacy preservados pra ghost render
        expect($src)->toContain("'gateway de pagamento': CreditCard");
        expect($src)->toContain("'cobrança recorrente': RefreshCw");
        // Imports lucide novos
        expect($src)->toContain("Banknote,");
        expect($src)->toContain("HandCoins,");
    });
});

/**
 * ALCANCE — a tela existe E o humano CHEGA nela (rota → permission → menu → pacote).
 *
 * Por que estes asserts nasceram (2026-08-25): a tela `/arquivos` fechou o ciclo — trio no
 * main, rota `arquivos.index` registrada, 26 testes Feature verdes — e em produção NINGUÉM
 * chegava nela. O `modifyAdminMenu()` do `Modules/Arquivos` era NO-OP, sob um comentário
 * afirmando que o módulo "não tem tela própria" (falso desde a ADR 0360). Quem pegou foi o
 * [W] a olho, no smoke do sidebar. Corrigido no PR #6245, hoje o golden das 3 camadas.
 *
 * Este arquivo já assertava sidebar POR MÓDULO (Financeiro publica 3 entries · Cobrança
 * Recorrente tem entry própria · PaymentGateway NÃO injeta). Não pegou o Arquivos por um
 * motivo simples e instrutivo: os asserts são escritos À MÃO, um por módulo, e ninguém
 * escreveu o do Arquivos. Daí as duas metades abaixo — o assert nominal que faltava, e um
 * assert GENERALIZADO que não depende de alguém lembrar do próximo módulo.
 *
 * ORÁCULO: `token_get_all`, o tokenizer do próprio PHP — mesma escolha do
 * `ArquivosAdminControllerTest`, e pelo mesmo motivo: regex sobre delimitador de comentário
 * confunde comentário com string literal, e um assert que lê a PROSA se autodenuncia
 * (lápide §5 2026-07-26). Um `modifyAdminMenu` de fachada é justamente `{}` sob um docblock.
 *
 * LIMITE DECLARADO: estes asserts leem o REPO, não o runtime — respondem "o módulo publica
 * a entrada", nunca "o Laravel renderizou o item pra este business". As 3 camadas de
 * habilitação são por-business em RUNTIME (pacote → permission ligada na role → menu), e a
 * permission nasce `default: false`: nenhum teste de repo prova que o item aparece pro
 * usuário X. Isso é smoke real (R1) — e é fora do escopo por decisão (§8 do pedido).
 *
 * O irmão deste arquivo é `scripts/governance/ciclo-completo.mjs` (item 7 — alcance), que
 * cobra os mesmos elos POR TELA a partir do bloco `alcance:` do charter.
 */
if (! function_exists('sidebarCodigoSemComentarios')) {
    function sidebarCodigoSemComentarios(string $caminho): string
    {
        $codigo = '';

        foreach (token_get_all(file_get_contents($caminho)) as $t) {
            if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $codigo .= is_array($t) ? $t[1] : $t;
        }

        return $codigo;
    }
}

/**
 * Corpo de UM método, já sem comentários, por balanceamento de chaves.
 *
 * `RuntimeException` quando o método some — nunca string vazia. Assert sobre vazio PASSA, e
 * um gate que se desliga sozinho quando o alvo é renomeado é exatamente o defeito que estes
 * testes existem pra não cometer.
 */
if (! function_exists('sidebarCorpoDoMetodo')) {
    function sidebarCorpoDoMetodo(string $caminho, string $metodo): string
    {
        $codigo = sidebarCodigoSemComentarios($caminho);
        $ini = strpos($codigo, 'function ' . $metodo . '(');

        if ($ini === false) {
            throw new RuntimeException("método {$metodo}() não existe em {$caminho}");
        }

        $abre = strpos($codigo, '{', $ini);
        $prof = 1;
        $i = $abre + 1;

        while ($i < strlen($codigo) && $prof > 0) {
            if ($codigo[$i] === '{') {
                $prof++;
            } elseif ($codigo[$i] === '}') {
                $prof--;
            }
            $i++;
        }

        return trim(substr($codigo, $abre + 1, $i - $abre - 2));
    }
}

/** Todo `Modules/<X>/Http/Controllers/DataController.php` que existe. */
if (! function_exists('sidebarDataControllers')) {
    function sidebarDataControllers(): array
    {
        $out = [];

        // dirname($f, 3): Controllers → Http → <Modulo>. Com 4 o basename devolveria
        // "Modules" pra todo mundo, e o array colapsaria numa entrada só.
        foreach (glob(ROOT . '/Modules/*/Http/Controllers/DataController.php') ?: [] as $f) {
            $out[basename(dirname($f, 3))] = $f;
        }

        return $out;
    }
}

/** Fonte concatenada de todos os `Modules/<X>/Routes/*.php` + `routes/*.php`. */
if (! function_exists('sidebarFonteDasRotas')) {
    function sidebarFonteDasRotas(): string
    {
        $src = '';

        foreach (array_merge(glob(ROOT . '/Modules/*/Routes/*.php') ?: [], glob(ROOT . '/routes/*.php') ?: []) as $f) {
            $src .= file_get_contents($f) . "\n";
        }

        return $src;
    }
}

describe('Alcance — a tela existe E o humano chega nela (regressão /arquivos 2026-08-25)', function () {
    it('Arquivos publica a entrada do acervo no sidebar — as 3 camadas de habilitação', function () {
        $src = file_get_contents(ROOT . '/Modules/Arquivos/Http/Controllers/DataController.php');

        // camada 1 — módulo no pacote do business (superadmin_package)
        expect($src)->toContain("'arquivos_module'");
        expect($src)->toContain('hasThePermissionInSubscription');
        // camada 2 — permission por função, declarada e default false
        expect($src)->toContain("'arquivos.access'");
        // camada 3 — a entrada de menu em si, apontando pra rota real
        expect($src)->toContain('Menu::modify');
        expect($src)->toContain("url('/arquivos')");
        // e NUNCA o atalho proibido Tier 0 (feedback-habilitar-modulo-por-business)
        expect($src)->not->toMatch('/business_id\s*[!=]==\s*\d+/');
    });

    it('Arquivos::modifyAdminMenu NÃO é fachada — corpo executável, não docblock', function () {
        // O defeito real: o método EXISTIA e o corpo era `{}` sob um comentário. Um assert de
        // presença (`toContain('function modifyAdminMenu')`) fica VERDE nisso — é o
        // presence-gate da classe LC-11. Só o corpo, sem comentário, distingue os dois.
        $corpo = sidebarCorpoDoMetodo(ROOT . '/Modules/Arquivos/Http/Controllers/DataController.php', 'modifyAdminMenu');

        expect($corpo)->not->toBe('');
        expect($corpo)->toContain('Menu::modify');
    });

    it('o método existir NÃO basta — o tokenizer distingue menu real de fachada', function () {
        // Controle positivo da própria sonda, nas DUAS pontas, contra o repo VIVO. Sem isto,
        // um `not->toBe('')` verde é indistinguível de sonda cega (§5 2026-08-01): as duas
        // pontas existem de verdade — Arquivos publica menu (#6245) e PaymentGateway é
        // fachada DELIBERADA (ghost do PageHeader, assertado logo acima neste arquivo).
        $real = sidebarCorpoDoMetodo(ROOT . '/Modules/Arquivos/Http/Controllers/DataController.php', 'modifyAdminMenu');
        $ghost = sidebarCorpoDoMetodo(ROOT . '/Modules/PaymentGateway/Http/Controllers/DataController.php', 'modifyAdminMenu');

        expect($ghost)->toBe('');
        expect($real)->not->toBe($ghost);
    });

    it('BITE — se o Arquivos voltasse a ser NO-OP, o assert acima REPROVA', function () {
        // Sem este caso os asserts acima são decorativos: um `not->toBe('')` verde não prova
        // que o oposto seria pego. Aqui a mutação é o estado REAL de até 2026-08-25 — o
        // corpo esvaziado sob o docblock — e a sonda tem que enxergar a diferença.
        $original = file_get_contents(ROOT . '/Modules/Arquivos/Http/Controllers/DataController.php');
        $mutado = preg_replace('/(function modifyAdminMenu\(\): void\s*\{).*?(\n    \})/s', '$1$2', $original);

        // Controle da própria mutação: se o regex não pegasse, o teste abaixo passaria de
        // graça comparando o arquivo com ele mesmo.
        expect($mutado)->not->toBe($original);

        $tmp = tempnam(sys_get_temp_dir(), 'dc') . '.php';
        file_put_contents($tmp, $mutado);

        try {
            expect(sidebarCorpoDoMetodo($tmp, 'modifyAdminMenu'))->toBe(
                '',
                'a sonda NÃO enxergou o método esvaziado — ela está cega e todo assert de menu acima é decorativo'
            );
        } finally {
            @unlink($tmp);
        }
    });

    it('nenhum módulo com permission gateando rota tem menu de FACHADA', function () {
        // A generalização que não depende de alguém lembrar do próximo módulo.
        //
        // Predicado: se o módulo DECLARA uma permission em `user_permissions` E existe rota
        // gateada por ela (`can:<permission>`), então há tela alcançável por permissão — e o
        // `modifyAdminMenu` não pode ser fachada, senão ninguém chega nela pelo menu. É
        // exatamente o estado em que o `/arquivos` esteve.
        //
        // NÃO se asserta o TAMANHO do conjunto: ele é derivado de sistema vivo e sobe a cada
        // módulo que adota `can:` na rota. Congelar `count === 2` reprovaria por GANHO, que é
        // a lápide §5 2026-08-24. O denominador se REPORTA (abaixo), não se cobra.
        $rotas = sidebarFonteDasRotas();
        $medidos = [];
        $fachadas = [];

        foreach (sidebarDataControllers() as $modulo => $arquivo) {
            $codigo = sidebarCodigoSemComentarios($arquivo);

            if (! str_contains($codigo, 'function user_permissions(')) {
                continue;
            }

            preg_match_all("/'value'\s*=>\s*'([^']+)'/", sidebarCorpoDoMetodo($arquivo, 'user_permissions'), $m);
            $gateadas = array_filter($m[1] ?? [], fn ($p) => str_contains($rotas, 'can:' . $p));

            if ($gateadas === []) {
                continue;
            }

            $medidos[] = $modulo;

            if (! str_contains($codigo, 'function modifyAdminMenu(')
                || sidebarCorpoDoMetodo($arquivo, 'modifyAdminMenu') === '') {
                $fachadas[] = $modulo . ' (permissions gateadas: ' . implode(', ', $gateadas) . ')';
            }
        }

        // O denominador sai no nome do assert: "0 fachadas" sem dizer "de N medidos" é o
        // fail-open da lápide §5 2026-07-29 — um superlativo cujo universo ninguém percorreu.
        expect($medidos)->not->toBeEmpty(
            'nenhum módulo casou o predicado — a sonda de `can:` na rota ficou cega, não é saúde'
        );
        expect($fachadas)->toBe(
            [],
            'módulo(s) com permission gateando rota MAS menu de fachada — ninguém chega na tela pelo sidebar. '
            . 'Medidos: ' . implode(', ', $medidos) . '. Golden: Modules/Arquivos/Http/Controllers/DataController.php (#6245).'
        );
    });
});

describe('Memória canon documenta pattern correto', function () {
    it('feedback-habilitar-modulo-por-business documenta pattern subscription', function () {
        $md = file_get_contents(ROOT . '/memory/reference/feedback-habilitar-modulo-por-business.md');
        expect($md)->toContain('SUBSCRIPTION PACKAGES, NÃO hardcode');
        expect($md)->toContain('habilitar e desabilitar é compra de pacote no modulo superadmin');
        expect($md)->toContain('hasThePermissionInSubscription');
        expect($md)->toContain('Modules/Superadmin/PackagesController');
        expect($md)->toContain('IRREVOGÁVEL Wagner 2026-05-18');
    });
});
