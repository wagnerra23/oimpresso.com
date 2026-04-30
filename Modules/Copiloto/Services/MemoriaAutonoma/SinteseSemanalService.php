<?php

namespace Modules\Copiloto\Services\MemoriaAutonoma;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Copiloto\Ai\Agents\SinteseSemanalAgent;
use RuntimeException;

/**
 * SinteseSemanalService — Fase 1 MemoriaAutonoma.
 *
 * Coleta artefatos da semana via git (commits, files novos, diffs), monta contexto
 * pro Haiku via SinteseSemanalAgent, salva resultado em memory/sessions/.
 *
 * Idempotente por semana: re-rodar com --force sobrescreve; sem --force aborta.
 *
 * Ver ADR `MemoriaAutonoma/adr/arq/0001-fase-1-sintese-semanal.md`.
 */
class SinteseSemanalService
{
    public const PATH_OUTPUT = 'memory/sessions';

    /**
     * Gera ou retorna preview (dry-run) da síntese semanal.
     *
     * @param  string  $semana    Identificador ISO YYYY-Www (ex.: 2026-W18)
     * @param  bool    $dryRun    Se true, NÃO chama LLM e NÃO salva — só devolve contexto coletado
     * @param  bool    $force     Se true, sobrescreve arquivo existente
     * @return array{path:?string, contexto:string, sintese:?string, custo_estimado:?array}
     */
    public function gerar(string $semana, bool $dryRun = false, bool $force = false): array
    {
        [$inicio, $fim] = $this->resolverRangeIso($semana);

        $arquivoDestino = base_path(self::PATH_OUTPUT . "/SEMANA-{$semana}-resumo.md");

        if (file_exists($arquivoDestino) && ! $force && ! $dryRun) {
            throw new RuntimeException(
                "Arquivo já existe: {$arquivoDestino}. Use --force pra sobrescrever."
            );
        }

        $contexto = $this->coletarContexto($inicio, $fim);

        if ($dryRun) {
            return [
                'path' => null,
                'contexto' => $contexto,
                'sintese' => null,
                'custo_estimado' => $this->estimarCusto($contexto),
            ];
        }

        $startedAt = microtime(true);

        $agent = new SinteseSemanalAgent(
            semana: $semana,
            rangeInicio: $inicio->toDateString(),
            rangeFim: $fim->toDateString(),
            contextoBruto: $contexto,
        );

        try {
            $sintese = (string) $agent->prompt($agent->montarPromptUsuario());
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->error('SinteseSemanal LLM falhou', [
                'semana' => $semana,
                'erro' => $e->getMessage(),
            ]);
            throw new RuntimeException("LLM falhou: {$e->getMessage()}", 0, $e);
        }

        $duracaoMs = (int) round((microtime(true) - $startedAt) * 1000);

        $conteudoFinal = $this->montarFrontmatter($semana, $inicio, $fim, $duracaoMs) . $sintese;

        file_put_contents($arquivoDestino, $conteudoFinal);

        $this->registrarMetrica($semana, $duracaoMs, strlen($contexto), strlen($sintese));

        Log::channel('copiloto-ai')->info('SinteseSemanal gerada', [
            'semana' => $semana,
            'arquivo' => $arquivoDestino,
            'duracao_ms' => $duracaoMs,
            'input_chars' => strlen($contexto),
            'output_chars' => strlen($sintese),
        ]);

        return [
            'path' => $arquivoDestino,
            'contexto' => $contexto,
            'sintese' => $sintese,
            'custo_estimado' => $this->estimarCusto($contexto),
        ];
    }

    /**
     * Resolve YYYY-Www → [Carbon $inicio (segunda 00:00), Carbon $fim (domingo 23:59)].
     */
    public function resolverRangeIso(string $semana): array
    {
        if (! preg_match('/^(\d{4})-W(\d{2})$/', $semana, $m)) {
            throw new RuntimeException("Semana inválida: {$semana}. Formato esperado: YYYY-Www (ex.: 2026-W18)");
        }
        $ano = (int) $m[1];
        $sem = (int) $m[2];
        $inicio = Carbon::now()->setISODate($ano, $sem)->startOfWeek()->startOfDay();
        $fim    = $inicio->copy()->endOfWeek()->endOfDay();
        return [$inicio, $fim];
    }

    /**
     * Coleta artefatos da semana via git.
     * Retorna texto formatado pronto pra prompt LLM.
     */
    public function coletarContexto(Carbon $inicio, Carbon $fim): string
    {
        $since = $inicio->toIso8601String();
        $until = $fim->toIso8601String();

        $commits = $this->git("log --since={$since} --until={$until} --pretty=format:'%h | %an | %s' --no-merges");
        $arquivosMemoryNovos = $this->git("log --since={$since} --until={$until} --diff-filter=A --name-only --pretty=format: -- memory/");
        $arquivosMemoryMod   = $this->git("log --since={$since} --until={$until} --diff-filter=M --name-only --pretty=format: -- memory/");
        $diffCurrent = $this->git("log --since={$since} --until={$until} --pretty=format:'### %h %s' -p -- CURRENT.md TASKS.md TEAM.md");

        // Limita commits a 200 + dedup arquivos
        $commits = $this->primeirasLinhas($commits, 200);
        $arquivosMemoryNovos = $this->dedup($arquivosMemoryNovos);
        $arquivosMemoryMod   = $this->dedup($arquivosMemoryMod);
        $diffCurrent = mb_substr($diffCurrent, 0, 8000); // limita

        return <<<CTX
        == COMMITS ({$inicio->format('Y-m-d')} a {$fim->format('Y-m-d')}) ==
        {$commits}

        == ARQUIVOS NOVOS EM memory/ ==
        {$arquivosMemoryNovos}

        == ARQUIVOS MODIFICADOS EM memory/ ==
        {$arquivosMemoryMod}

        == DIFFS CURRENT.md / TASKS.md / TEAM.md (até 8000 chars) ==
        {$diffCurrent}
        CTX;
    }

    protected function git(string $args): string
    {
        $cmd = 'git -C ' . escapeshellarg(base_path()) . ' ' . $args . ' 2>&1';
        $out = shell_exec($cmd) ?? '';
        return trim((string) $out);
    }

    protected function primeirasLinhas(string $texto, int $n): string
    {
        $linhas = explode("\n", $texto);
        return implode("\n", array_slice($linhas, 0, $n));
    }

    protected function dedup(string $texto): string
    {
        $linhas = array_filter(array_unique(explode("\n", $texto)), fn ($l) => trim($l) !== '');
        return implode("\n", $linhas);
    }

    protected function montarFrontmatter(string $semana, Carbon $inicio, Carbon $fim, int $duracaoMs): string
    {
        $geradoEm = Carbon::now()->toIso8601String();
        return <<<FM
        ---
        tipo: sintese-semanal
        semana: {$semana}
        range: {$inicio->toDateString()}..{$fim->toDateString()}
        gerado_em: {$geradoEm}
        gerado_por: copiloto-haiku-4-5
        duracao_ms: {$duracaoMs}
        ---

        # Síntese semanal {$semana}

        > Range: {$inicio->toDateString()} a {$fim->toDateString()} · gerado automaticamente por Copiloto Haiku 4.5.
        > Não invente dados ao revisar — Wagner é fonte canônica.


        FM;
    }

    /**
     * Custos estimados Haiku 4.5 (preços ref. 2026-04).
     */
    public function estimarCusto(string $contexto): array
    {
        $inputTokens = (int) ceil(mb_strlen($contexto) / 4); // ~4 chars/token aprox
        $outputTokens = 1500; // estimativa síntese
        // Haiku 4.5: $1 / 1M input · $5 / 1M output (valores aproximados — ajustar se mudar)
        $custoUsd = ($inputTokens * 1 / 1_000_000) + ($outputTokens * 5 / 1_000_000);
        return [
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'usd' => round($custoUsd, 6),
            'brl_aprox' => round($custoUsd * 5.5, 4), // câmbio aprox
        ];
    }

    protected function registrarMetrica(string $semana, int $duracaoMs, int $inputChars, int $outputChars): void
    {
        if (! \Schema::hasTable('copiloto_memoria_metricas')) {
            return; // skip se tabela não existe (ex.: testes sem migration)
        }
        try {
            DB::table('copiloto_memoria_metricas')->insert([
                'business_id' => 0, // sintese é cross-business (escopo plataforma)
                'metric_date' => now()->toDateString(),
                'metric_name' => 'sintese_semanal_total',
                'metric_value' => 1,
                'metadata' => json_encode([
                    'semana' => $semana,
                    'duracao_ms' => $duracaoMs,
                    'input_chars' => $inputChars,
                    'output_chars' => $outputChars,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->warning('Metrica sintese_semanal nao registrada', [
                'erro' => $e->getMessage(),
            ]);
        }
    }
}
