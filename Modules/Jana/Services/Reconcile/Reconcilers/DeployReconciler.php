<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Reconcile\Reconcilers;

use Modules\Governance\Services\Checkers\DeployDriftChecker;
use Modules\Jana\Contracts\Reconciler;
use Modules\Jana\Services\Reconcile\ReconcileDrift;
use Modules\Jana\Services\Reconcile\ReconcileResult;

/**
 * DeployReconciler — faceta 'deploy' do loop `jana:reconcile` (ADR 0237).
 *
 * Reconcilia o SHA do código que ESTÁ rodando (observed) contra o HEAD de
 * `origin/main` (desired). Fecha a pior dimensão medida em 2026-05-29: o código
 * deployado no CT 100 ficou **1302 commits atrás** de main em silêncio, cego até
 * quebrar. Esta faceta torna esse drift VISÍVEL todo dia.
 *
 * ── Relação com o DeployDriftChecker (ADR 0216) ──────────────────────────────
 * O `DeployDriftChecker` já faz exatamente esta detecção (SHA deployado × main) e
 * já isolou o I/O da lógica (`deployedSha()` / `latestMainSha()` leem .git/HEAD +
 * o arquivo do webhook; `analisar()` é puro). Este Reconciler **DELEGA** a
 * obtenção dos SHAs pra ele (não reimplementa leitura de .git) e re-expressa o
 * resultado no contrato Reconciler (ReconcileDrift × DriftFinding). NÃO edita o
 * checker — só o consome. Assim a única fonte-de-verdade de "como ler o SHA"
 * continua no Governance, e o Jana ganha a faceta sem duplicar parsing de git.
 *
 * ── healable = FALSE (alerta-only, R10) ──────────────────────────────────────
 * Deploy é ato humano/CI (`git reset --hard origin/main` + composer + octane:reload
 * — ver INFRA-ACESSO-CANON), NUNCA auto-cura. Por isso TODO ReconcileDrift daqui
 * nasce `healable=false`. Com `heal=true` este Reconciler **não executa deploy
 * nenhum** — apenas RE-REPORTA o mesmo alerta ("drift de deploy — rodar deploy
 * manual/CI"), respeitando a invariante do contrato: "drift com fonte-de-verdade
 * clara cura; ambíguo/perigoso alerta humano". Disparar deploy de dentro de um
 * reconciler idempotente violaria append-only (ADR 0237 §Invariantes) e a
 * separação de runtime (ADR 0062). `healedCount` é sempre 0.
 *
 * ── Testabilidade (sem ssh / sem git real) ───────────────────────────────────
 * A obtenção dos dois SHAs + do "N commits atrás" é INJETÁVEL via closures no
 * construtor. Default delega ao DeployDriftChecker (real). Teste injeta valores
 * fixos e exercita o núcleo puro `analisar()` direto — espelha o padrão já provado
 * em `DeployDriftChecker::analisar` / `DesignDocsFreshnessChecker::analisarDoc`.
 *
 * Refs:
 * - ADR 0237 (jana:reconcile loop único — contrato Reconciler)
 * - ADR 0216 (DriftChecker framework — DeployDriftChecker delegado)
 * - ADR 0062 (separação runtime Hostinger × CT 100 — por que não auto-deploya)
 * - memory/reference/INFRA-ACESSO-CANON (o procedimento humano de deploy)
 */
final class DeployReconciler implements Reconciler
{
    /**
     * @var \Closure(): ?string Resolve o SHA deployado (observed). Default: lê
     *   .git/HEAD via DeployDriftChecker. Teste injeta um stub.
     */
    private \Closure $observedShaResolver;

    /**
     * @var \Closure(): ?string Resolve o SHA de origin/main (desired). Default: lê
     *   o arquivo do webhook GitHub→MCP (fresco, cross-process) com fallback no ref
     *   origin/main, via DeployDriftChecker. Teste injeta um stub.
     */
    private \Closure $desiredShaResolver;

    /**
     * @var \Closure(?string, ?string): int "N commits atrás" do observed em relação
     *   ao desired. Default: 0 (o container não tem git binary pra contar — o número
     *   exato vem do webhook quando disponível; 0 = "desconhecido/não-contável", o
     *   drift é sinalizado pela diferença de SHA, não pela contagem). Injetável pra
     *   exercitar a mensagem "N commits atrás".
     */
    private \Closure $commitsBehindResolver;

    /**
     * Reutiliza o DeployDriftChecker como leitor canônico de SHA (não reimplementa
     * parsing de .git). Closures default fecham sobre uma instância única dele.
     *
     * @param (\Closure(): ?string)|null              $observedShaResolver
     * @param (\Closure(): ?string)|null              $desiredShaResolver
     * @param (\Closure(?string, ?string): int)|null  $commitsBehindResolver
     */
    public function __construct(
        ?\Closure $observedShaResolver = null,
        ?\Closure $desiredShaResolver = null,
        ?\Closure $commitsBehindResolver = null,
        ?DeployDriftChecker $checker = null,
    ) {
        $checker ??= new DeployDriftChecker();

        $this->observedShaResolver = $observedShaResolver
            ?? static fn (): ?string => $checker->deployedSha(base_path());

        $this->desiredShaResolver = $desiredShaResolver
            ?? static fn (): ?string => $checker->latestMainSha(base_path());

        // Sem git binary no container não dá pra contar commits localmente. O número
        // só é confiável quando o webhook anota; default conservador = 0 ("não-contável").
        $this->commitsBehindResolver = $commitsBehindResolver
            ?? static fn (?string $desired, ?string $observed): int => 0;
    }

    public function name(): string
    {
        return 'deploy';
    }

    public function description(): string
    {
        return 'SHA deployado (CT 100) vs origin/main HEAD — o "N commits atrás" silencioso (alerta-only)';
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['tier_1', 'deploy', 'infra', 'alert_only'];
    }

    public function reconcile(array $opts = []): ReconcileResult
    {
        $start = microtime(true);

        $observed = ($this->observedShaResolver)();
        $desired = ($this->desiredShaResolver)();
        $commitsBehind = ($this->commitsBehindResolver)($desired, $observed);

        $drifts = $this->analisar($desired, $observed, $commitsBehind);

        $durationMs = (int) round((microtime(true) - $start) * 1000);

        $metadata = [
            'desired' => $desired,
            'observed' => $observed,
            'commits_behind' => $commitsBehind,
            // Documenta no resultado que esta faceta NUNCA cura (deploy é humano/CI).
            'heal_supported' => false,
        ];

        return ReconcileResult::from($this->name(), $drifts, $durationMs, $metadata);
    }

    /**
     * Núcleo PURO + determinístico (sem I/O, sem ssh, sem git): decide o(s)
     * ReconcileDrift a partir dos dois SHAs + da contagem. Testável direto.
     *
     * Semântica (espelha DeployDriftChecker::analisar, re-expressa no contrato):
     *  - observed null              → drift NÃO-curável: não dá pra ler o que roda.
     *  - desired null               → drift NÃO-curável: main desconhecido (webhook
     *                                 ainda não anotou) — reverifica no próximo push.
     *  - observed != desired        → drift NÃO-curável: deploy atrasado (alerta humano).
     *  - iguais                     → [] (synced).
     *
     * TODO drift nasce healable=false: deploy NUNCA é auto-curado aqui (R10).
     *
     * @return array<int, ReconcileDrift>
     */
    public function analisar(?string $desired, ?string $observed, int $commitsBehind = 0): array
    {
        // Não dá pra saber o que está rodando → não verificável, alerta humano.
        if ($observed === null) {
            return [new ReconcileDrift(
                target: 'deploy',
                detail: 'Não consegui ler o SHA deployado (.git/HEAD ausente/ilegível). '
                    . 'Drift de deploy não verificável — investigar o ambiente.',
                desired: $desired ?? 'desconhecido',
                observed: 'desconhecido',
                healable: false,
            )];
        }

        // main ainda desconhecido (webhook não anotou + sem ref origin/main) → alerta
        // informativo, reverifica no próximo push. Não curável (nada a sincronizar ainda).
        if ($desired === null) {
            return [new ReconcileDrift(
                target: 'deploy',
                detail: 'SHA de origin/main desconhecido ainda (webhook GitHub→MCP não anotou). '
                    . 'Reverifica no próximo push em main.',
                desired: 'desconhecido',
                observed: $observed,
                healable: false,
            )];
        }

        // Sincronizado: short × full do mesmo commit conta como igual (delega a comparação
        // tolerante já provada no checker via mesmoSha pra não duplicar a regra de prefixo).
        if ($this->mesmoCommit($desired, $observed)) {
            return [];
        }

        // Drift real: deploy atrasado. NÃO-curável — só alerta o humano/CI rodar o deploy.
        $atraso = $commitsBehind > 0
            ? " ({$commitsBehind} commits atrás)"
            : '';

        return [new ReconcileDrift(
            target: 'deploy',
            detail: "Código deployado ({$observed}) != origin/main ({$desired}){$atraso}. "
                . 'Drift de deploy — rodar deploy manual/CI (`git reset --hard origin/main` + composer + '
                . 'octane:reload, ver INFRA-ACESSO-CANON). Este reconciler NÃO executa deploy.',
            desired: $desired,
            observed: $observed,
            healable: false,
        )];
    }

    /**
     * Mesmo commit? Tolera short × full (prefix match) reusando a regra canônica do
     * DeployDriftChecker — uma instância stateless basta, não há I/O em mesmoSha().
     */
    private function mesmoCommit(string $a, string $b): bool
    {
        return (new DeployDriftChecker())->mesmoSha($a, $b);
    }
}
