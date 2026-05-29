<?php

namespace Modules\Jana\Services\Mcp;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\DB;
use Modules\Jana\Entities\Mcp\McpAutomation;

/**
 * ADR 0234 (Onda 1.1) — sync do Registry de Automações.
 *
 * Espelha ImportarSkillsDoGitService traço-a-traço: varre o filesystem do repo
 * → upsert em mcp_automations (por slug) → drift detection em 2 direções →
 * alerta em mcp_alertas_eventos (tipo=automation_drift). Idempotente: rodar 2×
 * não duplica. Sem custo de LLM (varredura determinística + parse Kernel.php).
 *
 * 3 coletores (um por classe de fonte):
 *   (a) coletarHooks()   — .claude/hooks/*.{ps1,mjs} (exclui *.test.*), tipo
 *                          inferido por .claude/settings.json (qual evento registra)
 *   (b) coletarCrons()   — parse de app/Console/Kernel.php (regex em
 *                          ->command('X')->dailyAt/cron/weeklyOn/...)
 *   (c) coletarRotinas() — .claude/*.json com marcador "_automation_registry": true
 *
 * Span jana.mcp.automation_registry_sync SEM business_id (registry global),
 * igual ao span de import de skills.
 *
 * Sem business_id by design — registry global de infra de plataforma (ADR 0093
 * exceção). Não lê dados de tenant; lê arquivos do repo.
 */
class AutomationRegistrySync
{
    /**
     * Raiz do repo a varrer. Default = base_path() (produção/CLI). Os testes
     * injetam um diretório fixture via comRepoBasePath() pra controlar drift
     * de forma determinística (mesmo precedente de StalenessDetectorService).
     */
    private ?string $repoBasePath = null;

    public function comRepoBasePath(?string $repoBasePath): static
    {
        $this->repoBasePath = $repoBasePath;

        return $this;
    }

    /** Resolve um path relativo contra a raiz configurada (ou base_path()). */
    private function repoPath(string $relative = ''): string
    {
        $root = $this->repoBasePath ?? base_path();
        $root = rtrim($root, '/\\');

        return $relative === '' ? $root : $root . DIRECTORY_SEPARATOR . ltrim($relative, '/\\');
    }

    /**
     * @return array{
     *     created: int, updated: int, unchanged: int,
     *     orphan_files: array<int,string>, missing_files: array<int,string>,
     *     alerts_created: int, errors: array<int,string>
     * }
     */
    public function run(): array
    {
        // Span sem business_id (global registry), igual jana.mcp.importar_skills.
        return OtelHelper::span('jana.mcp.automation_registry_sync', [], fn () => $this->runInternal());
    }

    private function runInternal(): array
    {
        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $errors = [];

        // 1) Coleta as 3 classes de fonte do filesystem.
        $coletadas = [];
        foreach (['coletarHooks', 'coletarCrons', 'coletarRotinas'] as $coletor) {
            try {
                foreach ($this->{$coletor}() as $slug => $dados) {
                    // Slug é único global — última fonte a definir vence (não deve colidir
                    // na prática: hooks/crons/rotinas têm namespaces de slug distintos).
                    $coletadas[$slug] = $dados;
                }
            } catch (\Throwable $e) {
                $errors[] = "$coletor: " . $e->getMessage();
            }
        }

        // 2) Upsert por slug (igual ImportarSkillsDoGitService): atualiza campos
        //    descritivos; NÃO toca last_run_at/last_status (escritos pelas próprias
        //    automações via automations-report futuro — passo 6 do plano).
        foreach ($coletadas as $slug => $dados) {
            try {
                $existente = McpAutomation::where('slug', $slug)->first();

                $camposDescritivos = [
                    'business_id'     => null, // global por default (infra de plataforma)
                    'tipo'            => $dados['tipo'],
                    'gatilho'         => $dados['gatilho'],
                    'descricao'       => $dados['descricao'] ?? null,
                    'arquivo'         => $dados['arquivo'],
                    'owner'           => $dados['owner'] ?? null,
                    'governed_by_adr' => $dados['governed_by_adr'] ?? null,
                ];

                if ($existente === null) {
                    McpAutomation::create($camposDescritivos + [
                        'slug'    => $slug,
                        'enabled' => $dados['enabled'] ?? true,
                    ]);
                    $created++;

                    continue;
                }

                // Detecta se algum campo descritivo mudou desde o último sync.
                $mudou = false;
                foreach ($camposDescritivos as $col => $val) {
                    if ($existente->{$col} != $val) {
                        $mudou = true;
                        break;
                    }
                }

                if (! $mudou) {
                    $unchanged++;

                    continue;
                }

                $existente->fill($camposDescritivos);
                $existente->save();
                $updated++;
            } catch (\Throwable $e) {
                $errors[] = "$slug: " . $e->getMessage();
            }
        }

        // 3) Drift detection bidirecional → alerta idempotente em mcp_alertas_eventos.
        $slugsColetados = array_keys($coletadas);
        $arquivosColetados = array_map(
            static fn (array $d) => $d['arquivo'],
            array_values($coletadas)
        );

        $orphanFiles  = $this->detectarOrfaos($slugsColetados);
        $missingFiles = $this->detectarAusentes($arquivosColetados);

        $alertsCreated = $this->alertarDrift($orphanFiles, $missingFiles);

        return [
            'created'        => $created,
            'updated'        => $updated,
            'unchanged'      => $unchanged,
            'orphan_files'   => $orphanFiles,
            'missing_files'  => array_values($missingFiles),
            'alerts_created' => $alertsCreated,
            'errors'         => $errors,
        ];
    }

    // ───────────────────────── COLETOR (a) — HOOKS ─────────────────────────

    /**
     * Varre .claude/hooks/*.ps1 + *.mjs (exclui *.test.*) e cruza com
     * .claude/settings.json pra inferir o tipo (SessionStart/PreToolUse/
     * PostToolUse) e o gatilho (matcher do evento).
     *
     * @return array<string, array<string, mixed>>
     */
    public function coletarHooks(): array
    {
        $base = $this->repoPath('.claude/hooks');
        if (! is_dir($base)) {
            return [];
        }

        $files = array_merge(
            glob("$base/*.ps1") ?: [],
            glob("$base/*.mjs") ?: [],
        );

        // Mapa basename → ['tipo' => ..., 'gatilho' => ...] a partir do settings.json.
        $mapa = $this->mapearHooksDoSettings();

        $resultado = [];
        foreach ($files as $file) {
            $basename = basename($file);

            // Exclui testes (*.test.ps1 / *.test.mjs).
            if (preg_match('/\.test\.(ps1|mjs)$/i', $basename)) {
                continue;
            }

            $slug = preg_replace('/\.(ps1|mjs)$/i', '', $basename);
            $relativo = $this->relativo($file);

            $info = $mapa[$basename] ?? null;
            if ($info !== null) {
                $tipo = $info['tipo'];
                $gatilho = $info['gatilho'];
            } else {
                // Hook existe no filesystem mas não está registrado em nenhum evento
                // do settings.json (ex: hooks de teste/smoke como test-all-hooks-smoke).
                // Default conservador: trata como PreToolUse com gatilho desconhecido.
                // O drift orphan_file NÃO se aplica (está registrado aqui), mas o
                // gatilho="(nao mapeado)" sinaliza honestamente que o settings não cobre.
                $tipo = 'hook_pretooluse';
                $gatilho = '(nao mapeado no settings.json)';
            }

            $resultado[$slug] = [
                'tipo'            => $tipo,
                'gatilho'         => $gatilho,
                'descricao'       => $this->extrairDescricaoHook($file),
                'arquivo'         => $relativo,
                'owner'           => null,
                'governed_by_adr' => $this->extrairAdrDoHeader($file),
                'enabled'         => true,
            ];
        }

        return $resultado;
    }

    /**
     * Lê .claude/settings.json e devolve basename do hook → tipo + gatilho.
     *
     * @return array<string, array{tipo: string, gatilho: string}>
     */
    private function mapearHooksDoSettings(): array
    {
        $path = $this->repoPath('.claude/settings.json');
        if (! is_file($path)) {
            return [];
        }

        $json = json_decode((string) @file_get_contents($path), true);
        if (! is_array($json) || ! isset($json['hooks']) || ! is_array($json['hooks'])) {
            return [];
        }

        // Evento Claude Code → tipo do registry.
        $eventoParaTipo = [
            'SessionStart'     => 'hook_sessionstart',
            'PreToolUse'       => 'hook_pretooluse',
            'PostToolUse'      => 'hook_posttooluse',
            // UserPromptSubmit / Stop não têm enum próprio — mapeiam pro mais próximo
            // (advisory pós-prompt ~ posttooluse). Honesto: gatilho carrega o nome real.
            'UserPromptSubmit' => 'hook_posttooluse',
            'Stop'             => 'hook_posttooluse',
        ];

        $mapa = [];
        foreach ($json['hooks'] as $evento => $grupos) {
            $tipo = $eventoParaTipo[$evento] ?? 'hook_pretooluse';
            if (! is_array($grupos)) {
                continue;
            }
            foreach ($grupos as $grupo) {
                $matcher = $grupo['matcher'] ?? '*';
                $hooks = $grupo['hooks'] ?? [];
                if (! is_array($hooks)) {
                    continue;
                }
                foreach ($hooks as $h) {
                    $cmd = (string) ($h['command'] ?? '');
                    $basename = $this->extrairBasenameDoComando($cmd);
                    if ($basename === null) {
                        continue;
                    }
                    // Primeiro evento que registra o hook vence (um hook pode estar em
                    // múltiplos eventos; o gatilho concatena matcher + evento real).
                    // Guard-clause em vez de `if (!isset) { assign }`: NÃO é fallback
                    // silencioso (ADR 0212) — é dedup determinístico, o 1º valor é o
                    // dado real, não um default que mascara ausência.
                    if (isset($mapa[$basename])) {
                        continue;
                    }

                    $mapa[$basename] = [
                        'tipo'    => $tipo,
                        'gatilho' => "{$evento}:{$matcher}",
                    ];
                }
            }
        }

        return $mapa;
    }

    /**
     * Extrai o basename do script de um comando de hook do settings.json.
     * Ex: "powershell ... -File .claude/hooks/pii-redactor.ps1" → "pii-redactor.ps1"
     *     "node .claude/hooks/audit-creates-tasks.mjs"          → "audit-creates-tasks.mjs"
     */
    private function extrairBasenameDoComando(string $comando): ?string
    {
        if (preg_match('#\.claude/hooks/([A-Za-z0-9._-]+\.(?:ps1|mjs))#', $comando, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    // ───────────────────────── COLETOR (b) — CRONS ─────────────────────────

    /**
     * Parseia app/Console/Kernel.php: regex em $schedule->command('X')->FREQ.
     * Resolve o gatilho (expressão cron / dailyAt / weeklyOn / everyN...).
     *
     * Frágil a refactors do arquivo (ADR 0234 trade-off aceito): parse falho de
     * um comando vira alerta medium, não erro fatal. Só pega ->command('...');
     * ->job(...) e ->call(...) (closures) são ignorados (sem slug textual).
     *
     * @return array<string, array<string, mixed>>
     */
    public function coletarCrons(): array
    {
        $path = $this->repoPath('app/Console/Kernel.php');
        if (! is_file($path)) {
            return [];
        }

        $conteudo = (string) @file_get_contents($path);
        if ($conteudo === '') {
            return [];
        }

        $resultado = [];

        // Captura cada `->command('CMD ...')` + a cauda de chamadas encadeadas até
        // o `;` que fecha o statement do schedule. A cauda carrega a frequência.
        if (preg_match_all(
            "/->command\(\s*'([^']+)'\s*\)(.*?);/s",
            $conteudo,
            $matches,
            PREG_SET_ORDER
        ) === false) {
            return [];
        }

        foreach ($matches as $m) {
            $comandoCompleto = trim($m[1]);
            $cauda = $m[2];

            // Slug = primeiro token do comando (sem flags/args). Ex:
            // "jana:health-check --notify" → "jana:health-check"
            // "queue:work database --queue=whatsapp ..." → mantém só o comando base.
            $slug = $this->slugDoComandoCron($comandoCompleto);
            if ($slug === null) {
                continue;
            }

            $resultado[$slug] = [
                'tipo'            => 'cron',
                'gatilho'         => $this->resolverFrequenciaCron($cauda),
                'descricao'       => "Cron schedule: php artisan {$comandoCompleto}",
                'arquivo'         => 'app/Console/Kernel.php',
                'owner'           => null,
                'governed_by_adr' => null,
                'enabled'         => true,
            ];
        }

        return $resultado;
    }

    /**
     * Slug determinístico do comando cron. Comandos `queue:work` recebem sufixo
     * da queue pra não colidirem (há 2 no Kernel: whatsapp + whatsapp-history).
     */
    private function slugDoComandoCron(string $comandoCompleto): ?string
    {
        $partes = preg_split('/\s+/', $comandoCompleto);
        $base = $partes[0] ?? '';
        if ($base === '') {
            return null;
        }

        // queue:work database --queue=X → "queue:work:X" (evita colisão de slug).
        if ($base === 'queue:work' && preg_match('/--queue=([A-Za-z0-9_-]+)/', $comandoCompleto, $qm) === 1) {
            return 'queue:work:' . $qm[1];
        }

        return $base;
    }

    /**
     * Resolve a expressão de frequência a partir da cauda encadeada do schedule.
     * Cobre os formatos usados no Kernel.php do oimpresso.
     */
    private function resolverFrequenciaCron(string $cauda): string
    {
        // ->cron('EXPR') tem precedência (expressão crua).
        if (preg_match("/->cron\(\s*'([^']+)'\s*\)/", $cauda, $m) === 1) {
            return $m[1];
        }

        // ->dailyAt('HH:MM')
        if (preg_match("/->dailyAt\(\s*'([^']+)'\s*\)/", $cauda, $m) === 1) {
            return "dailyAt {$m[1]}";
        }

        // ->daily()->at('HH:MM')  /  ->mondays()->at(...)  /  ->fridays()->at(...)
        if (preg_match("/->(daily|mondays|fridays|sundays|saturdays|weekdays)\(\)\s*->at\(\s*'([^']+)'\s*\)/", $cauda, $m) === 1) {
            return "{$m[1]} at {$m[2]}";
        }

        // ->weeklyOn(DOW, 'HH:MM')
        if (preg_match("/->weeklyOn\(\s*(\d+)\s*,\s*'([^']+)'\s*\)/", $cauda, $m) === 1) {
            return "weeklyOn dia {$m[1]} as {$m[2]}";
        }

        // ->hourlyAt(N)
        if (preg_match('/->hourlyAt\(\s*(\d+)\s*\)/', $cauda, $m) === 1) {
            return "hourlyAt {$m[1]}";
        }

        // Frequências nomeadas sem argumento.
        $nomeadas = [
            'everyMinute'         => 'a cada 1 min',
            'everyFiveMinutes'    => 'a cada 5 min',
            'everyTenMinutes'     => 'a cada 10 min',
            'everyFifteenMinutes' => 'a cada 15 min',
            'everyThirtyMinutes'  => 'a cada 30 min',
            'hourly'              => 'a cada hora',
            'daily'               => 'diario',
            'weekly'              => 'semanal',
        ];
        foreach ($nomeadas as $metodo => $legivel) {
            if (preg_match('/->' . $metodo . '\(\)/', $cauda) === 1) {
                return $legivel;
            }
        }

        return '(frequencia nao reconhecida)';
    }

    // ──────────────────────── COLETOR (c) — ROTINAS ────────────────────────

    /**
     * Varre .claude/*.json com marcador "_automation_registry": true e lê
     * slug/tipo/gatilho/arquivo do próprio manifesto.
     *
     * @return array<string, array<string, mixed>>
     */
    public function coletarRotinas(): array
    {
        $base = $this->repoPath('.claude');
        if (! is_dir($base)) {
            return [];
        }

        $files = glob("$base/*.json") ?: [];
        $resultado = [];

        foreach ($files as $file) {
            $json = json_decode((string) @file_get_contents($file), true);
            if (! is_array($json)) {
                continue;
            }
            if (($json['_automation_registry'] ?? false) !== true) {
                continue;
            }

            $slug = $json['slug'] ?? null;
            if (! is_string($slug) || $slug === '') {
                continue;
            }

            // arquivo: o manifesto pode apontar pro check (ex hook .ps1); fallback
            // pro próprio manifesto se não declarar.
            $arquivo = $json['arquivo'] ?? $this->relativo($file);

            $resultado[$slug] = [
                'tipo'            => $json['tipo'] ?? 'routine',
                'gatilho'         => $json['gatilho'] ?? '(manifesto sem gatilho)',
                'descricao'       => $json['descricao'] ?? null,
                'arquivo'         => $arquivo,
                'owner'           => $json['owner'] ?? null,
                'governed_by_adr' => $json['governed_by_adr'] ?? null,
                'enabled'         => $json['enabled'] ?? true,
            ];
        }

        return $resultado;
    }

    // ─────────────────────────── DRIFT DETECTION ───────────────────────────

    /**
     * orphan_file: automação registrada em mcp_automations cujo slug NÃO foi
     * coletado nesta varredura (nenhuma fonte do filesystem o produz). Indica
     * arquivo removido sem desregistrar, ou slug renomeado.
     *
     * NOTA semântica: a ADR rotula "orphan_file" como "arquivo no FS, não
     * registrado". Implementação prática: o sync SEMPRE registra o que está no
     * FS (passo 2), então o único órfão observável pós-upsert é o inverso — row
     * no DB sem fonte no FS. Mantemos os 2 nomes da ADR (orphan_file +
     * missing_file) cobrindo as 2 pontas: missing_file = arquivo declarado sumiu
     * do disco; orphan_file = slug no DB sem nenhuma fonte que o reproduza.
     *
     * @param  array<int,string>  $slugsColetados
     * @return array<int,string>  slugs órfãos
     */
    private function detectarOrfaos(array $slugsColetados): array
    {
        $query = McpAutomation::query()->where('enabled', true);
        if (! empty($slugsColetados)) {
            $query->whereNotIn('slug', $slugsColetados);
        }

        return $query->pluck('slug')->all();
    }

    /**
     * missing_file: row enabled em mcp_automations cujo `arquivo` declarado NÃO
     * existe mais no filesystem (automação zumbi). Severity high.
     *
     * @param  array<int,string>  $arquivosColetados  (não usado diretamente —
     *                            checamos o filesystem real, fonte de verdade)
     * @return array<string,string>  slug → arquivo ausente
     */
    private function detectarAusentes(array $arquivosColetados): array
    {
        $ausentes = [];

        foreach (McpAutomation::query()->where('enabled', true)->cursor() as $auto) {
            // Crons vivem todos em Kernel.php (sempre presente) — não há "arquivo
            // ausente" por cron individual; pular pra evitar falso-positivo.
            if ($auto->tipo === 'cron') {
                continue;
            }

            $abs = $this->repoPath($auto->arquivo);
            if (! is_file($abs)) {
                $ausentes[$auto->slug] = $auto->arquivo;
            }
        }

        return $ausentes;
    }

    /**
     * Persiste alertas de drift em mcp_alertas_eventos (mesmo padrão de
     * StalenessDetectorService::alertCritical — DB::table()->insert() com
     * chave_idempotencia UNIQUE pra evitar spam diário).
     *
     * @param  array<int,string>     $orphanFiles
     * @param  array<string,string>  $missingFiles
     */
    private function alertarDrift(array $orphanFiles, array $missingFiles): int
    {
        $hoje = now()->format('Y-m-d');
        $inseridos = 0;

        // orphan: slug no DB sem fonte no FS — severity medium.
        foreach ($orphanFiles as $slug) {
            $chave = "automation_drift:orphan:{$slug}:{$hoje}";
            if ($this->alertaJaExiste($chave)) {
                continue;
            }

            DB::table('mcp_alertas_eventos')->insert([
                'user_id'            => null,
                'business_id'        => null, // registry global (infra de plataforma)
                'tipo'               => 'automation_drift',
                'severidade'         => 'medium',
                'titulo'             => "Automacao orfa: '{$slug}' registrada mas sem fonte no filesystem",
                'descricao'          => "A automacao '{$slug}' existe em mcp_automations mas nenhuma fonte "
                                        . '(.claude/hooks, Kernel.php, .claude/*.json) a reproduz na varredura. '
                                        . 'Arquivo removido sem desregistrar ou slug renomeado.',
                'chave_idempotencia' => $chave,
                'metadata'           => json_encode([
                    'slug'       => $slug,
                    'drift'      => 'orphan_file',
                    'detectado'  => $hoje,
                ]),
                'status'             => 'aberto',
                'criado_em'          => now(),
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
            $inseridos++;
        }

        // missing: arquivo declarado sumiu do disco — severity high.
        foreach ($missingFiles as $slug => $arquivo) {
            $chave = "automation_drift:missing:{$slug}:{$hoje}";
            if ($this->alertaJaExiste($chave)) {
                continue;
            }

            DB::table('mcp_alertas_eventos')->insert([
                'user_id'            => null,
                'business_id'        => null,
                'tipo'               => 'automation_drift',
                'severidade'         => 'high',
                'titulo'             => "Automacao zumbi: '{$slug}' registrada com arquivo ausente",
                'descricao'          => "A automacao '{$slug}' aponta para '{$arquivo}', que nao existe mais "
                                        . 'no filesystem. Registro zumbi — desregistrar ou restaurar o arquivo.',
                'chave_idempotencia' => $chave,
                'metadata'           => json_encode([
                    'slug'       => $slug,
                    'arquivo'    => $arquivo,
                    'drift'      => 'missing_file',
                    'detectado'  => $hoje,
                ]),
                'status'             => 'aberto',
                'criado_em'          => now(),
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
            $inseridos++;
        }

        return $inseridos;
    }

    private function alertaJaExiste(string $chave): bool
    {
        return DB::table('mcp_alertas_eventos')
            ->where('chave_idempotencia', $chave)
            ->exists();
    }

    // ─────────────────────────────── HELPERS ───────────────────────────────

    /** Path relativo ao repo, normalizado com separador `/`. */
    private function relativo(string $absolute): string
    {
        $root = $this->repoBasePath ?? base_path();
        $rel = str_replace('\\', '/', str_replace($root, '', $absolute));

        return ltrim($rel, '/');
    }

    /** Extrai a 1ª linha de comentário descritiva do header do hook (best-effort). */
    private function extrairDescricaoHook(string $file): ?string
    {
        $conteudo = @file_get_contents($file, false, null, 0, 600);
        if (! is_string($conteudo) || $conteudo === '') {
            return null;
        }

        foreach (preg_split('/\R/', $conteudo) as $linha) {
            $linha = trim($linha);
            // PowerShell: "# desc" · Node/JS: "// desc" ou "/* desc"
            if (preg_match('/^(#|\/\/|\/\*)\s*(.+)$/', $linha, $m) === 1) {
                $desc = trim(rtrim($m[2], '*/ '));
                if ($desc !== '' && ! str_starts_with($desc, '!')) {
                    return mb_substr($desc, 0, 250);
                }
            }
        }

        return null;
    }

    /** Procura "ADR NNNN" no header do arquivo pra preencher governed_by_adr. */
    private function extrairAdrDoHeader(string $file): ?string
    {
        $conteudo = @file_get_contents($file, false, null, 0, 800);
        if (! is_string($conteudo) || $conteudo === '') {
            return null;
        }

        if (preg_match('/ADR\s+(\d{3,4})/', $conteudo, $m) === 1) {
            return $m[1];
        }

        return null;
    }
}
