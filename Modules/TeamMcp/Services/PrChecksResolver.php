<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * PrChecksResolver — Loop de Handoff Zero-Paste, Gap 2 do adversário [AH] (ADR 0283).
 *
 * O `gate_status` do handoff-ack é AUTO-REPORTADO pelo [CC] (conformance / critique /
 * a11y). Ele pode MENTIR: afirmar "verde" enquanto um required check do PR está
 * vermelho ou pendente no GitHub. Este resolver lê o ESTADO REAL dos required checks
 * do PR via GitHub API e devolve um veredito (`green`/`red`/`pending`) pro
 * {@see \Modules\TeamMcp\Services\Forja\ForjaMcpService} cruzar com o ack — a
 * divergência vira o badge `conflito`.
 *
 * Espelha {@see GitMainResolver}: mesmo PAT (`config('services.github.*')`, ADR 0076),
 * Http best-effort com timeout, cache curto, `OtelHelper::span`, e **degrada gracioso**
 * — sem token / API fora / PR inválido / branch protection ilegível → `null` ("não dá
 * pra afirmar"), e o ForjaMcpService cai pro comportamento atual (sem `conflito` falso).
 * NUNCA lança: o badge não pode quebrar por causa de um cross-check best-effort.
 */
class PrChecksResolver
{
    /** Cache curto: o CI evolui (checks completam); 60s é o teto de stale aceitável. */
    private const TTL_SECONDS = 60;

    /** Teto por chamada externa — igual {@see GitMainResolver}. */
    private const HTTP_TIMEOUT = 8;

    private function token(): ?string
    {
        $t = config('services.github.token');

        return is_string($t) && $t !== '' ? $t : null;
    }

    /**
     * Veredito dos required checks do PR (required_status_checks da branch protection
     * do base):
     *
     *   'green'   = todos os required presentes e em success;
     *   'red'     = algum required falhou;
     *   'pending' = algum required ainda rodando OU ainda não reportado no head SHA;
     *   null      = não dá pra afirmar (sem token / sem PR / API fora / sem branch
     *               protection legível) → o caller degrada pro comportamento atual.
     */
    public function verdict(?string $prUrl): ?string
    {
        $ref = $this->parsePrUrl($prUrl);
        if ($ref === null) {
            return null;
        }

        $token = $this->token();
        if ($token === null) {
            return null;
        }

        // OTel span (ADR 0156) instrumenta as chamadas externas; o try/catch que degrada
        // vive DENTRO de resolve() pra manter o contrato "nunca lança". `Cache::remember`
        // não persiste null (re-tenta a cada leitura), então outage transiente não congela.
        return OtelHelper::span('teammcp.prchecks.verdict', ['pr' => $ref['number']], fn () => Cache::remember(
            "prchecks.verdict.{$ref['owner']}.{$ref['repo']}.{$ref['number']}",
            now()->addSeconds(self::TTL_SECONDS),
            fn () => $this->resolve($ref, $token),
        ));
    }

    /**
     * Faz as chamadas reais. O try/catch que degrada fica DENTRO (igual GitMainResolver).
     *
     * @param  array{owner:string,repo:string,number:int}  $ref
     */
    private function resolve(array $ref, string $token): ?string
    {
        try {
            // 1) PR → head SHA + base branch.
            $pr = $this->get($token, "/repos/{$ref['owner']}/{$ref['repo']}/pulls/{$ref['number']}");
            if ($pr === null) {
                return null;
            }
            $head = is_array($pr['head'] ?? null) ? $pr['head'] : [];
            $sha = is_string($head['sha'] ?? null) ? $head['sha'] : '';
            $baseArr = is_array($pr['base'] ?? null) ? $pr['base'] : [];
            $base = is_string($baseArr['ref'] ?? null) && $baseArr['ref'] !== '' ? $baseArr['ref'] : 'main';
            if ($sha === '') {
                return null;
            }

            // 2) Quais checks são REQUIRED na branch protection do base. null = não deu
            //    pra ler (sem protection / sem admin no PAT) → degrada. Sem isso, um check
            //    ADVISORY vermelho (ex.: visual-regression) viraria `conflito` falso.
            $required = $this->requiredContexts($token, $ref, $base);
            if ($required === null) {
                return null;
            }
            if ($required === []) {
                return 'green'; // base protegido mas sem required → nada pode reprovar
            }

            // 3) Estado real de cada check no head SHA (Checks API + status legado).
            $states = $this->stateMap($token, $ref, $sha);

            return $this->rollup($required, $states);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Rollup sobre SÓ os required:
     *   algum required em failure                  → 'red';
     *   algum required pending OU ausente (não reportado) → 'pending';
     *   todos os required em success               → 'green'.
     *
     * @param  list<string>  $required
     * @param  array<string,string>  $states
     */
    private function rollup(array $required, array $states): string
    {
        $sawPendingOrMissing = false;

        foreach ($required as $ctx) {
            $state = $states[$ctx] ?? null;
            if ($state === 'failure') {
                return 'red';
            }
            if ($state !== 'success') { // pending OU ausente (ainda não reportou no SHA)
                $sawPendingOrMissing = true;
            }
        }

        return $sawPendingOrMissing ? 'pending' : 'green';
    }

    /**
     * Estado por nome de check no SHA, unindo Checks API (Actions reporta aqui) com o
     * status legado (integrações antigas). Se o mesmo nome aparece em ambos / em re-runs,
     * a PIOR vence (failure > pending > success).
     *
     * @param  array{owner:string,repo:string,number:int}  $ref
     * @return array<string,string>
     */
    private function stateMap(string $token, array $ref, string $sha): array
    {
        $states = $this->checkRunStates($token, $ref, $sha);

        foreach ($this->statusStates($token, $ref, $sha) as $ctx => $st) {
            $states[$ctx] = $this->worst($states[$ctx] ?? 'success', $st);
        }

        return $states;
    }

    /**
     * @param  array{owner:string,repo:string,number:int}  $ref
     * @return array<string,string>
     */
    private function checkRunStates(string $token, array $ref, string $sha): array
    {
        $json = $this->get($token, "/repos/{$ref['owner']}/{$ref['repo']}/commits/{$sha}/check-runs?per_page=100");
        $runs = is_array($json['check_runs'] ?? null) ? $json['check_runs'] : [];

        $states = [];
        foreach ($runs as $run) {
            if (! is_array($run)) {
                continue;
            }
            $name = is_string($run['name'] ?? null) ? $run['name'] : '';
            if ($name === '') {
                continue;
            }
            $states[$name] = $this->worst($states[$name] ?? 'success', $this->runState($run));
        }

        return $states;
    }

    /**
     * queued/in_progress/etc → 'pending'; completed: success/neutral/skipped → 'success',
     * resto (failure/cancelled/timed_out/action_required) → 'failure'.
     *
     * @param  array<string,mixed>  $run
     */
    private function runState(array $run): string
    {
        $status = is_string($run['status'] ?? null) ? $run['status'] : '';
        if ($status !== 'completed') {
            return 'pending';
        }
        $conclusion = is_string($run['conclusion'] ?? null) ? $run['conclusion'] : '';

        return in_array($conclusion, ['success', 'neutral', 'skipped'], true) ? 'success' : 'failure';
    }

    /**
     * Status legado (commit status): success → 'success', pending → 'pending', resto
     * (failure/error) → 'failure'.
     *
     * @param  array{owner:string,repo:string,number:int}  $ref
     * @return array<string,string>
     */
    private function statusStates(string $token, array $ref, string $sha): array
    {
        $json = $this->get($token, "/repos/{$ref['owner']}/{$ref['repo']}/commits/{$sha}/status");
        $statuses = is_array($json['statuses'] ?? null) ? $json['statuses'] : [];

        $states = [];
        foreach ($statuses as $s) {
            if (! is_array($s)) {
                continue;
            }
            $ctx = is_string($s['context'] ?? null) ? $s['context'] : '';
            if ($ctx === '') {
                continue;
            }
            $raw = is_string($s['state'] ?? null) ? $s['state'] : '';
            $state = match ($raw) {
                'success' => 'success',
                'pending' => 'pending',
                default   => 'failure', // failure / error
            };
            $states[$ctx] = $this->worst($states[$ctx] ?? 'success', $state);
        }

        return $states;
    }

    /** Pior de dois estados (failure > pending > success). */
    private function worst(string $a, string $b): string
    {
        $rank = ['success' => 0, 'pending' => 1, 'failure' => 2];

        return ($rank[$b] ?? 0) > ($rank[$a] ?? 0) ? $b : $a;
    }

    /**
     * Contexts dos required status checks do base. null = não deu pra ler (degrada);
     * [] = base protegido mas sem required configurado.
     *
     * @param  array{owner:string,repo:string,number:int}  $ref
     * @return list<string>|null
     */
    private function requiredContexts(string $token, array $ref, string $base): ?array
    {
        $json = $this->get($token, "/repos/{$ref['owner']}/{$ref['repo']}/branches/{$base}/protection/required_status_checks");
        if ($json === null) {
            return null;
        }

        // 'checks' (formato novo: [{context, app_id}]) tem prioridade; 'contexts' (legado:
        // [string]) é o fallback.
        $out = [];
        $checks = is_array($json['checks'] ?? null) ? $json['checks'] : [];
        foreach ($checks as $c) {
            $ctx = is_array($c) && is_string($c['context'] ?? null) ? $c['context'] : '';
            if ($ctx !== '') {
                $out[$ctx] = true;
            }
        }
        if ($out === []) {
            $contexts = is_array($json['contexts'] ?? null) ? $json['contexts'] : [];
            foreach ($contexts as $ctx) {
                if (is_string($ctx) && $ctx !== '') {
                    $out[$ctx] = true;
                }
            }
        }

        return array_keys($out);
    }

    /**
     * GET JSON best-effort na GitHub API. null em erro/não-2xx (o caller decide o
     * significado: degrada ou trata como vazio).
     *
     * @return array<mixed>|null
     */
    private function get(string $token, string $path): ?array
    {
        $r = Http::withToken($token)
            ->withHeaders([
                'Accept'               => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->timeout(self::HTTP_TIMEOUT)
            ->get("https://api.github.com{$path}");

        if (! $r->successful()) {
            return null;
        }

        $json = $r->json();

        return is_array($json) ? $json : null;
    }

    /**
     * Extrai owner/repo/número de uma URL de PR do GitHub. null se não casar.
     *
     * @return array{owner:string,repo:string,number:int}|null
     */
    private function parsePrUrl(?string $prUrl): ?array
    {
        if (! is_string($prUrl) || $prUrl === '') {
            return null;
        }
        if (! preg_match('#github\.com/([^/]+)/([^/]+)/pull/(\d+)#', $prUrl, $m)) {
            return null;
        }

        return ['owner' => $m[1], 'repo' => $m[2], 'number' => (int) $m[3]];
    }
}
