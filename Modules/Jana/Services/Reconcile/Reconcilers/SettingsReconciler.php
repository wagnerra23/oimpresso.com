<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Reconcile\Reconcilers;

use Closure;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Console\Commands\MeilisearchIndexSetupCommand;
use Modules\Jana\Contracts\Reconciler;
use Modules\Jana\Services\Reconcile\ReconcileDrift;
use Modules\Jana\Services\Reconcile\ReconcileResult;
use Throwable;

/**
 * SettingsReconciler — settings VIVOS do índice Meilisearch != config-as-code (ADR 0237).
 *
 * Fecha o buraco que já mordeu o projeto 2× (2026-05-04 Sprint 9b + recorrência
 * 2026-05-29): o embedder do `jana_memoria_facts` foi setado MANUAL via curl e SE
 * PERDEU — o índice voltou a `embedders {}` e o recall semântico do chat degradou em
 * SILÊNCIO até alguém descobrir na mão. Sem reconciliação, drift de settings de índice
 * é invisível: a busca "funciona" (BM25 puro) mas o vetor sumiu.
 *
 * Faceta deste Reconciler (contrato mental da interface canônica):
 *   desired()  = `config('copiloto.meilisearch_indexes')` — por índice: bloco
 *                `embedders` (qwen3_local ollama 1024d) + `filterableAttributes`.
 *   observed() = settings vivos do índice: `GET /indexes/{uid}/settings` no Meilisearch.
 *   diff()     = `analisar()` — núcleo PURO, compara os 2 mapas sem tocar rede.
 *   heal()     = re-aplica os settings (idempotente) reusando a lógica de
 *                `MeilisearchIndexSetupCommand::payloadPara()` + PATCH /settings.
 *   alert()    = drift de OBSERVAÇÃO (não consegui ler o índice) → não-curável, humano vê.
 *
 * DESIGN testável-sem-rede (espelha `DeployDriftChecker::analisar` / `Meilisearch...
 * ::driftsDoIndice` — método público puro + I/O injetável):
 *  - A leitura dos settings vivos é uma CLOSURE injetada no construtor
 *    (`$observarSettings: fn(string $uid): array<string,mixed>`). O default usa o
 *    client Meilisearch real (`Http`), o teste injeta um fake → ZERO rede.
 *  - A APLICAÇÃO da cura também é uma closure injetada
 *    (`$aplicarSettings: fn(string $uid, array $payload): bool`). Default = PATCH real;
 *    teste injeta fake que grava a chamada → a cura NUNCA dispara contra prod no teste.
 *  - O núcleo `analisar(array $desired, array $observed): ReconcileDrift[]` é puro.
 *
 * Cura SEGURA (R10): todo drift de settings tem fonte-de-verdade clara (a config no
 * git) → `healable=true`. Re-aplicar é idempotente + append-only (PATCH não rebaixa o
 * que já está certo). O único drift NÃO-curável é "não consegui observar" (rede/HTTP
 * falhou) — aí só alerta, humano investiga.
 *
 * @see Modules\Jana\Console\Commands\MeilisearchIndexSetupCommand (a CURA consolidada aqui)
 * @see Modules\Governance\Services\Checkers\MeilisearchSettingsDriftChecker (irmão "detect" no framework de drift)
 * @see memory/decisions/0237-jana-reconcile-loop-unico.md
 */
final class SettingsReconciler implements Reconciler
{
    /**
     * Campos do embedder comparados desired × vivo. Só esses 3 importam pro recall
     * (qual modelo, qual fonte, qual dimensionalidade) — o resto (documentTemplate,
     * url) é cosmético e varia por env, não vira drift. Mesma escolha do
     * MeilisearchSettingsDriftChecker::driftsDoIndice.
     */
    private const CAMPOS_EMBEDDER = ['source', 'model', 'dimensions'];

    /**
     * @var Closure(string): array<string, mixed> Lê os settings vivos de UM índice.
     */
    private Closure $observarSettings;

    /**
     * @var Closure(string, array<string, mixed>): bool Aplica (PATCH) settings de UM índice. true = aplicou.
     */
    private Closure $aplicarSettings;

    /**
     * @param (Closure(string): array<string, mixed>)|null         $observarSettings injeta no teste pra não tocar rede.
     * @param (Closure(string, array<string, mixed>): bool)|null   $aplicarSettings  injeta no teste pra cura não bater em prod.
     */
    public function __construct(
        ?Closure $observarSettings = null,
        ?Closure $aplicarSettings = null,
    ) {
        $this->observarSettings = $observarSettings ?? $this->observadorReal();
        $this->aplicarSettings = $aplicarSettings ?? $this->aplicadorReal();
    }

    public function name(): string
    {
        return 'settings';
    }

    public function description(): string
    {
        return 'Embedder/filterable vivos do índice Meilisearch != config-as-code (recall degrada em silêncio)';
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['tier_1', 'retrieval', 'memory_canon'];
    }

    public function reconcile(array $opts = []): ReconcileResult
    {
        $start = microtime(true);

        $heal = ($opts['heal'] ?? false) === true;
        $dryRun = ($opts['dry_run'] ?? false) === true;

        $desiredPorIndice = $this->desired();

        if ($desiredPorIndice === []) {
            return ReconcileResult::synced($this->name(), $this->elapsedMs($start), [
                'skipped' => 'config copiloto.meilisearch_indexes vazia',
            ]);
        }

        $drifts = [];

        foreach ($desiredPorIndice as $uid => $desired) {
            $observed = $this->observarComSeguranca($uid);

            // Observação falhou (rede/HTTP) → drift NÃO-curável (não dá pra curar às
            // cegas o que não consegui ler). Alerta humano (R10). NÃO é fallback
            // silencioso: vira finding explícito.
            if ($observed === null) {
                $drifts[] = new ReconcileDrift(
                    target: $uid,
                    detail: "Não consegui ler os settings vivos do índice '{$uid}' (Meilisearch inacessível). Drift não verificável.",
                    desired: $this->resumoDesired($desired),
                    observed: '(indisponível)',
                    healable: false,
                );

                continue;
            }

            $driftsIndice = $this->analisar([$uid => $desired], $observed);

            // Cura: re-aplica os settings desejados (idempotente). Só roda com heal=true
            // e fora de dry_run, e só se HOUVE drift curável neste índice.
            if ($heal && ! $dryRun && $this->algumCuravel($driftsIndice)) {
                $driftsIndice = $this->curarIndice($uid, $desired, $driftsIndice);
            }

            foreach ($driftsIndice as $d) {
                $drifts[] = $d;
            }
        }

        return ReconcileResult::from($this->name(), $drifts, $this->elapsedMs($start), [
            'indexes' => array_keys($desiredPorIndice),
            'heal' => $heal,
            'dry_run' => $dryRun,
            'cura' => 'php artisan jana:meilisearch-setup',
        ]);
    }

    /**
     * Estado DESEJADO: `config('copiloto.meilisearch_indexes')` (config-as-code).
     * Cada entrada = ['embedders' => [...], 'filterableAttributes' => [...]].
     *
     * @return array<string, array<string, mixed>>
     */
    public function desired(): array
    {
        /** @var array<string, array<string, mixed>> $indexes */
        $indexes = (array) config('copiloto.meilisearch_indexes', []);

        return $indexes;
    }

    /**
     * Núcleo PURO + determinístico — sem I/O. Compara os settings desejados (git) com
     * os settings vivos (já lidos) de UM índice e devolve os drifts. Testável sem tocar
     * rede/disco (mesmo contrato de DeployDriftChecker::analisar).
     *
     * Drift detectado (todos healable — a config no git é a fonte-de-verdade):
     *   1. embedder esperado AUSENTE ou `{}` vivo  — O BUG RECORRENTE (recall degrada).
     *   2. embedder presente mas source/model/dimensions divergente.
     *   3. filterableAttributes divergente (comparado como CONJUNTO, ordem-insensível).
     *
     * @param  array<string, array<string, mixed>> $desired  mapa uid → config desejada (1 ou N índices).
     * @param  array<string, mixed>                $observed settings vivos do índice (corpo do GET /settings).
     * @return array<int, ReconcileDrift>
     */
    public function analisar(array $desired, array $observed): array
    {
        $drifts = [];

        foreach ($desired as $uid => $cfg) {
            $cfg = (array) $cfg;

            $expEmb = $this->asMapDeMapas($cfg['embedders'] ?? []);
            $vivoEmb = $this->asMapDeMapas($observed['embedders'] ?? []);

            // ── (1) + (2) embedders ────────────────────────────────────────────
            foreach ($expEmb as $nome => $espec) {
                if (! array_key_exists($nome, $vivoEmb)) {
                    // embedder ausente OU vivoEmb == {} (índice resetado) → o bug clássico.
                    Log::warning(
                        "SettingsReconciler: embedder '{$nome}' ausente no índice '{$uid}' — recall semântico degrada (ADR 0237 rastreabilidade).",
                        ['index' => $uid, 'embedder' => $nome],
                    );
                    $drifts[] = new ReconcileDrift(
                        target: "{$uid}.embedders.{$nome}",
                        detail: "Índice '{$uid}': embedder '{$nome}' AUSENTE nos settings vivos (esperado na config). "
                            . 'Recall semântico degrada em silêncio.',
                        desired: $this->resumoEmbedder($nome, $espec),
                        observed: $vivoEmb === [] ? 'embedders {} (índice resetado)' : 'embedder ausente',
                        healable: true,
                    );

                    continue;
                }

                $vivoEspec = $vivoEmb[$nome];
                foreach (self::CAMPOS_EMBEDDER as $campo) {
                    if (! array_key_exists($campo, $espec)) {
                        continue;
                    }
                    $esperado = $espec[$campo];
                    $vivoVal = $vivoEspec[$campo] ?? null;

                    // `!=` loose de propósito: dimensions pode vir 1024 (int) vs "1024"
                    // (string) do JSON vivo — divergência de TIPO não é drift real.
                    if ($vivoVal != $esperado) {
                        $drifts[] = new ReconcileDrift(
                            target: "{$uid}.embedders.{$nome}.{$campo}",
                            detail: "Índice '{$uid}': embedder '{$nome}'.{$campo} divergente do desejado.",
                            desired: $this->scalarToStr($esperado),
                            observed: $this->scalarToStr($vivoVal),
                            healable: true,
                        );
                    }
                }
            }

            // ── (3) filterableAttributes (como CONJUNTO) ───────────────────────
            $expFilt = $this->asListaStr($cfg['filterableAttributes'] ?? []);
            $vivoFilt = $this->asListaStr($observed['filterableAttributes'] ?? []);
            sort($expFilt);
            sort($vivoFilt);

            if ($expFilt !== $vivoFilt) {
                $drifts[] = new ReconcileDrift(
                    target: "{$uid}.filterableAttributes",
                    detail: "Índice '{$uid}': filterableAttributes divergente do desejado.",
                    desired: '[' . implode(', ', $expFilt) . ']',
                    observed: '[' . implode(', ', $vivoFilt) . ']',
                    healable: true,
                );
            }
        }

        return $drifts;
    }

    /**
     * Cura UM índice: monta o payload canônico (reusa a lógica do
     * MeilisearchIndexSetupCommand) e aplica via a closure injetada. Idempotente:
     * re-aplicar settings já corretos é no-op no Meilisearch. Marca cada drift como
     * `healed=true` SE a aplicação retornou sucesso.
     *
     * @param  array<string, mixed>     $cfg    config desejada do índice.
     * @param  array<int, ReconcileDrift> $drifts drifts detectados deste índice.
     * @return array<int, ReconcileDrift>        os mesmos drifts, healed=true se aplicou.
     */
    private function curarIndice(string $uid, array $cfg, array $drifts): array
    {
        $payload = (new MeilisearchIndexSetupCommand())->payloadPara($cfg);

        $aplicou = ($this->aplicarSettings)($uid, $payload);

        if (! $aplicou) {
            Log::warning("SettingsReconciler: PATCH settings do índice '{$uid}' não confirmou aplicação — drift segue aberto.", ['index' => $uid]);

            return $drifts;
        }

        return array_map(
            static fn (ReconcileDrift $d): ReconcileDrift => $d->healable
                ? new ReconcileDrift(
                    target: $d->target,
                    detail: $d->detail,
                    desired: $d->desired,
                    observed: $d->observed,
                    healable: true,
                    healed: true,
                )
                : $d,
            $drifts,
        );
    }

    /**
     * Observa os settings vivos de um índice tolerando falha: a closure pode lançar
     * (rede caiu) — capturamos e devolvemos null pra virar drift NÃO-curável explícito
     * lá em cima (não é fallback silencioso: o caller registra finding).
     *
     * @return array<string, mixed>|null
     */
    private function observarComSeguranca(string $uid): ?array
    {
        try {
            return ($this->observarSettings)($uid);
        } catch (Throwable $e) {
            Log::warning(
                "SettingsReconciler: falha ao ler settings vivos de '{$uid}' (vira drift não-curável): " . $e->getMessage(),
                ['index' => $uid],
            );

            return null;
        }
    }

    /**
     * Closure default de OBSERVAÇÃO: GET /indexes/{uid}/settings no Meilisearch real.
     * Substituída por um fake no teste (injeção via construtor) → zero rede em CI.
     *
     * @return Closure(string): array<string, mixed>
     */
    private function observadorReal(): Closure
    {
        return static function (string $uid): array {
            $host = rtrim((string) config('scout.meilisearch.host', 'http://localhost:7700'), '/');
            $key = (string) config('scout.meilisearch.key', '');

            $resp = Http::withToken($key)->timeout(30)->get("{$host}/indexes/{$uid}/settings");

            if ($resp->failed()) {
                // Sobe pro observarComSeguranca → drift não-curável (HTTP não-200).
                throw new \RuntimeException("GET settings '{$uid}' falhou (HTTP {$resp->status()})");
            }

            /** @var array<string, mixed> $json */
            $json = (array) $resp->json();

            return $json;
        };
    }

    /**
     * Closure default de CURA: PATCH /indexes/{uid}/settings (mesma chamada do
     * MeilisearchIndexSetupCommand). Substituída por fake no teste → cura NUNCA bate
     * em prod no teste. true = Meilisearch aceitou (2xx).
     *
     * @return Closure(string, array<string, mixed>): bool
     */
    private function aplicadorReal(): Closure
    {
        return static function (string $uid, array $payload): bool {
            $host = rtrim((string) config('scout.meilisearch.host', 'http://localhost:7700'), '/');
            $key = (string) config('scout.meilisearch.key', '');

            $resp = Http::withToken($key)->timeout(30)->patch("{$host}/indexes/{$uid}/settings", $payload);

            return $resp->successful();
        };
    }

    /**
     * @param  array<int, ReconcileDrift> $drifts
     */
    private function algumCuravel(array $drifts): bool
    {
        foreach ($drifts as $d) {
            if ($d->healable) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normaliza um valor mixed (a config é array<string,mixed>) num mapa de mapas
     * embedders: nome-do-embedder → spec (array<string,mixed>). Robusto a tipos
     * inesperados (descarta entradas não-array).
     *
     * @return array<string, array<string, mixed>>
     */
    private function asMapDeMapas(mixed $valor): array
    {
        if (! is_array($valor)) {
            return [];
        }

        $out = [];
        foreach ($valor as $nome => $spec) {
            if (is_array($spec)) {
                /** @var array<string, mixed> $spec */
                $out[(string) $nome] = $spec;
            }
        }

        return $out;
    }

    /**
     * Normaliza um valor mixed numa lista de strings (filterableAttributes).
     *
     * @return array<int, string>
     */
    private function asListaStr(mixed $valor): array
    {
        if (! is_array($valor)) {
            return [];
        }

        $out = [];
        foreach ($valor as $item) {
            if (is_scalar($item)) {
                $out[] = (string) $item;
            }
        }

        return $out;
    }

    /**
     * Resumo 1-linha do estado desejado de um índice (pra ReconcileDrift::desired).
     *
     * @param  array<string, mixed> $cfg
     */
    private function resumoDesired(array $cfg): string
    {
        $embedders = implode(', ', array_keys($this->asMapDeMapas($cfg['embedders'] ?? [])));
        $filter = implode(', ', $this->asListaStr($cfg['filterableAttributes'] ?? []));

        return "embedders=[{$embedders}] filterable=[{$filter}]";
    }

    /**
     * @param  array<string, mixed> $espec
     */
    private function resumoEmbedder(string $nome, array $espec): string
    {
        $source = $this->scalarToStr($espec['source'] ?? null);
        $model = $this->scalarToStr($espec['model'] ?? null);
        $dims = $this->scalarToStr($espec['dimensions'] ?? null);

        return "{$nome} (source={$source}, model={$model}, dimensions={$dims})";
    }

    /**
     * mixed escalar → string legível pra ReconcileDrift (guarda contra array/null).
     */
    private function scalarToStr(mixed $v): string
    {
        if ($v === null) {
            return '(null)';
        }
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if (is_scalar($v)) {
            return (string) $v;
        }

        return (string) json_encode($v);
    }

    private function elapsedMs(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }
}
