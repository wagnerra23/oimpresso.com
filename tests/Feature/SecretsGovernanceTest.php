<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

/**
 * R-SEC-0215 — anti-regressão Sprint S-0215-1 ADR 0215 secrets governance.
 *
 * Garante que comandos secrets:scan e secrets:audit funcionam + index canon
 * parser está OK + drift detection funciona.
 *
 * NOTA: smoke real validação Hostinger API + ssh creds não roda em CI free
 * (precisa SSH key Wagner). Esses tests cobrem shape + lógica core.
 */

it('R-SEC-0215-001 — comando secrets:scan registrado', function () {
    $commands = collect(Artisan::all())->keys();
    expect($commands)->toContain('secrets:scan');
});

it('R-SEC-0215-002 — comando secrets:audit registrado', function () {
    $commands = collect(Artisan::all())->keys();
    expect($commands)->toContain('secrets:audit');
});

it('R-SEC-0215-003 — memory/_INDEX-SECRETS.md existe e tem tabela canônica', function () {
    $path = base_path('memory/_INDEX-SECRETS.md');
    expect(file_exists($path))->toBeTrue();

    $content = file_get_contents($path);
    // Header da tabela canon
    expect($content)->toContain('| Nome | Tipo |');
    // Entry Hostinger DNS (caso fundador)
    expect($content)->toContain('Hostinger DNS API token');
    // Status legends
    expect($content)->toContain('✅ active');
    expect($content)->toContain('EXPIRED');
});

it('R-SEC-0215-004 — secrets:scan roda sem exception', function () {
    $exitCode = Artisan::call('secrets:scan');
    // Exit code 0 (success) OR 1 (drift detected); ambos válidos
    expect($exitCode)->toBeIn([0, 1]);
});

it('R-SEC-0215-005 — secrets:audit roda sem exception (filter Hostinger)', function () {
    $exitCode = Artisan::call('secrets:audit', ['--filter' => 'hostinger']);
    expect($exitCode)->toBeIn([0, 1]);
});

it('R-SEC-0215-006 — cron secrets:audit registrado em schedule', function () {
    // Validar via reflection do Kernel
    $kernel = app(\App\Console\Kernel::class);
    $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);

    $reflection = new ReflectionClass($kernel);
    $method = $reflection->getMethod('schedule');
    $method->setAccessible(true);
    $method->invoke($kernel, $schedule);

    $events = $schedule->events();
    $hasAuditCron = collect($events)->contains(
        fn ($e) => str_contains($e->command ?? '', 'secrets:audit')
    );
    expect($hasAuditCron)->toBeTrue();
});

it('R-SEC-0215-007 — .githooks/pre-commit tem bloco secrets:scan', function () {
    $hookPath = base_path('.githooks/pre-commit');
    if (! file_exists($hookPath)) {
        $this->markTestSkipped('.githooks/pre-commit não existe em ambiente CI Linux fresh');
    }
    $content = file_get_contents($hookPath);
    expect($content)->toContain('secrets:scan');
    expect($content)->toContain('SECRETS GOVERNANCE');
});

it('R-SEC-0215-008 — secrets governance consolidado no governance-drift.yml (ADR 0271 onda 2)', function () {
    // secrets-governance.yml foi deletado na onda 2 dos gates (ADR 0271 item F3):
    // o scan de PR passou a ser coberto por `governance:audit` (SecretsDriftChecker
    // dentro do --diff-only) e o auto-PR de rotação (Camada 3) foi portado pro
    // governance-drift.yml. Fecha o canary 7d da ADR 0216, nunca encerrado.
    expect(file_exists(base_path('.github/workflows/secrets-governance.yml')))->toBeFalse();

    $path = base_path('.github/workflows/governance-drift.yml');
    expect(file_exists($path))->toBeTrue();

    $content = file_get_contents($path);
    expect($content)->toContain('secrets:audit --auto-pr'); // Camada 3 portada pra cá
    expect($content)->toContain('governance:audit');         // cobre o SecretsDriftChecker no PR
});

/**
 * R-SEC-0215-009..012 — o que É drift.
 *
 * Origem (medido 2026-08-03): o `governance-drift.yml` acumulou 52 runs agendadas
 * vermelhas em sequência (último sucesso 2026-06-11) por DOIS falsos-positivos, e
 * nenhum deles era dívida de segredo:
 *
 *   ⚠️ Hostinger DNS API token: 🔴 EXPIRED 2026-05-28 — Wagner regerar → ⏸ pending
 *   ⚠️ CT 100 root SSH (LAN): ✅ active (verificado 2026-06-05 — …)     → ✅ active
 *
 * O 1º é NÃO-MEDIÇÃO lida como mudança: `validateHostingerApi` lê
 * `memory/claude/reference_hostinger_hpanel.md`, diretório PURGADO na auditoria de
 * 2026-06-07, e devolve `⏸ pending` todo dia. O 2º é comparação string-exata contra
 * anotação humana — o estado é o mesmo.
 */
it('R-SEC-0215-009 — NÃO-MEDIÇÃO não é drift (o fail-open que a ADR 0317 §2 pune)', function () {
    // o caso real: índice diz 🔴 e o validador não conseguiu medir
    expect(App\Console\Commands\SecretsAuditCommand::ehDrift(
        '🔴 **EXPIRED 2026-05-28** — Wagner regerar',
        '⏸ pending'
    ))->toBeFalse();
});

it('R-SEC-0215-010 — anotação humana não é drift (mesmo estado, prosa a mais)', function () {
    // o caso real: CT 100 root SSH
    expect(App\Console\Commands\SecretsAuditCommand::ehDrift(
        '✅ active (verificado 2026-06-05 — conecta + `oimpresso-staging` Laravel 13.6 vivo)',
        '✅ active'
    ))->toBeFalse();
});

it('R-SEC-0215-011 — MUDANÇA DE ESTADO continua sendo drift (o alarme não foi desligado)', function () {
    // segredo que estava ativo e expirou: o alarme TEM que morder
    expect(App\Console\Commands\SecretsAuditCommand::ehDrift(
        '✅ active',
        '🔴 EXPIRED 2026-08-03'
    ))->toBeTrue();

    // e o inverso: algo marcado como comprometido que volta a validar
    expect(App\Console\Commands\SecretsAuditCommand::ehDrift(
        '🔴 **COMPROMETIDA 2026-05-28**',
        '✅ active'
    ))->toBeTrue();
});

it('R-SEC-0215-012 — estadoDe() extrai o estado e descarta a prosa', function () {
    expect(App\Console\Commands\SecretsAuditCommand::estadoDe('✅ active (verificado 2026-06-05)'))->toBe('✅');
    expect(App\Console\Commands\SecretsAuditCommand::estadoDe('🔴 **EXPIRED** — Wagner regerar'))->toBe('🔴');
    expect(App\Console\Commands\SecretsAuditCommand::estadoDe('🟡 rotacionando 2026-06-08'))->toBe('🟡');
    expect(App\Console\Commands\SecretsAuditCommand::estadoDe('🔒 LOCKED humano-only'))->toBe('🔒');
    // mudança de DATA dentro do mesmo estado não é mudança de estado
    expect(App\Console\Commands\SecretsAuditCommand::ehDrift('🔴 EXPIRED 2026-05-28', '🔴 EXPIRED 2026-08-03'))->toBeFalse();
});

/**
 * R-SEC-0215-013..016 — o aviso de drift SAI (não morre no log).
 *
 * Origem (medido 2026-08-03, confirmado 2026-08-04): a Camada 3 nasceu como
 * auto-PR na ADR 0215 e NUNCA abriu um PR — `secrets:audit` só LÊ o índice,
 * então `git commit` caía sempre em "nothing to commit" e a cadeia `&&` morria
 * antes do `gh pr create`. O drift ficava só no log de um job vermelho que todo
 * mundo aprendeu a ignorar. Autorizado por [W] 2026-08-04 ("pode arrumar").
 *
 * Estes testes exercem os dois métodos PUROS do caminho novo. O `exec`/gh em si
 * não é testável aqui (precisa de token + rede) — o que dá pra travar é a
 * identidade da issue (idempotência) e o que vai no corpo (não-vazamento).
 */
it('R-SEC-0215-013 — mesmo conjunto de drifts = mesmo título, independente da ORDEM (idempotência)', function () {
    $a = [
        ['name' => 'Hostinger DNS API token', 'old' => '✅', 'new' => '🔴'],
        ['name' => 'Meilisearch master key',  'old' => '✅', 'new' => '🔴'],
    ];
    // mesmo conjunto, ordem invertida — a ordem de leitura do índice não pode
    // mudar a identidade do conjunto, senão o cron duplica issue todo dia
    $b = array_reverse($a);

    expect(App\Console\Commands\SecretsAuditCommand::tituloDaIssue($a))
        ->toBe(App\Console\Commands\SecretsAuditCommand::tituloDaIssue($b));
});

it('R-SEC-0215-014 — conjunto DIFERENTE = título diferente (estado novo merece aviso novo)', function () {
    $antes = [['name' => 'Hostinger DNS API token', 'old' => '✅', 'new' => '🔴']];
    $depois = [
        ['name' => 'Hostinger DNS API token', 'old' => '✅', 'new' => '🔴'],
        ['name' => 'Vaultwarden ADMIN_TOKEN',  'old' => '✅', 'new' => '🔴'],
    ];

    expect(App\Console\Commands\SecretsAuditCommand::tituloDaIssue($antes))
        ->not->toBe(App\Console\Commands\SecretsAuditCommand::tituloDaIssue($depois));

    // e o título conta quantos são, pra dar o tamanho do problema de relance
    expect(App\Console\Commands\SecretsAuditCommand::tituloDaIssue($depois))->toContain('2 drift(s)');
});

it('R-SEC-0215-015 — corpo lista cada secret com o estado do índice e o da validação', function () {
    $corpo = App\Console\Commands\SecretsAuditCommand::corpoDaIssue(
        [['name' => 'Hostinger DNS API token', 'old' => '✅ active', 'new' => '🔴 EXPIRED']],
        '2026-08-04 10:00'
    );

    expect($corpo)->toContain('Hostinger DNS API token');
    expect($corpo)->toContain('✅ active');
    expect($corpo)->toContain('🔴 EXPIRED');
    expect($corpo)->toContain('2026-08-04 10:00');
    // diz o que fazer — aviso sem ação é ruído
    expect($corpo)->toContain('_INDEX-SECRETS.md');
});

it('R-SEC-0215-016 — corpo NÃO imprime nada além de name/old/new (chokepoint anti-vazamento)', function () {
    // Se um dia o índice passar a carregar valor, este método é o lugar onde o
    // vazamento aconteceria: ele monta o texto que vai pro GitHub. O teste passa
    // uma chave a mais de propósito — o corpo tem que ignorá-la.
    $corpo = App\Console\Commands\SecretsAuditCommand::corpoDaIssue(
        [[
            'name'  => 'Meilisearch master key',
            'old'   => '✅ active',
            'new'   => '🔴 COMPROMETIDA',
            'value' => 'MASTER_KEY_QUE_NAO_PODE_VAZAR',
        ]],
        '2026-08-04 10:00'
    );

    expect($corpo)->toContain('Meilisearch master key');
    expect($corpo)->not->toContain('MASTER_KEY_QUE_NAO_PODE_VAZAR');
});
