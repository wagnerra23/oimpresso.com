<?php

namespace Modules\Jana\Services\TaskRegistry;

use App\Util\OtelHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Entities\Mcp\McpComponent;
use Modules\Jana\Entities\Mcp\McpCycle;
use Modules\Jana\Entities\Mcp\McpEpic;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Entities\Mcp\McpTaskEvent;

/**
 * TaskRegistry parser (ADR 0070, supersedes ADR 0069).
 *
 * Fonte: memory/requisitos/<Mod>/SPEC.md
 *
 * Suporta frontmatter YAML opcional no topo do SPEC (defaults pra todas as USs):
 *
 *   ---
 *   project: COPI
 *   default_epic: COPI-EP-001
 *   default_component: BE
 *   default_cycle: CYCLE-01
 *   ---
 *
 * Formato esperado de cada US:
 *
 *   ### US-NFSE-001 · Pesquisa fiscal Tubarão
 *
 *   > owner: eliana · sprint: A · priority: p0 · estimate: 8h · status: todo
 *   > epic: COPI-EP-001 · cycle: CYCLE-01 · component: BE · type: bug
 *   > story_points: 5 · due: 2026-05-09 · labels: lgpd,perf
 *   > blocked_by: US-NFSE-000
 *
 *   - [ ] Confirmar SN-NFSe vs ABRASF
 *
 * Idempotente: rodar 2x sem mudança = 0 inserts/updates.
 * US deletada do SPEC vira status=cancelled (soft) em vez de DELETE.
 *
 * ADR 0144 (Bug #2 BUGS-MCP-SYNC-2026-05-13) — DB = canon, SPEC = template.
 * Para tasks já existentes no DB, o sync só atualiza campos descritivos
 * (title, description, labels, type, módulo, epic/cycle/component etc).
 * Campos de estado vivo (status, owner, sprint, priority) NUNCA são
 * sobrescritos pelo webhook — `tasks-update` é durável. Novas USs (ainda
 * não no DB) usam o SPEC pra valores iniciais normalmente.
 */
class TaskParserService
{
    /**
     * Regex pra heading de US: ###(#)? US-XXX-NNN[N][x] · Título
     *
     * O sufixo opcional `[a-z]?` captura o esquema de sub-letra (ex: US-WA-002b,
     * US-WA-010b) como parte do task_id. Sem ele, `\d{3,4}` parava no dígito e
     * `US-WA-002b` colapsava em `US-WA-002`, com o `b` vazando pro título
     * (`b · …`) e sobrescrevendo o id-base canônico — origem das colisões
     * WA-002/010/045 no spec_id_drift (incidente 2026-06-20). Só WhatsApp usa
     * o esquema hoje; ids sem sufixo seguem inalterados.
     *
     * Flag `u` (UTF-8) é OBRIGATÓRIA: sem ela a classe `[·\-:]` é bytewise e
     * casa só 1 dos 2 bytes do `·` (U+00B7 = C2 B7), deixando o B7 órfão grudar
     * no começo do título → vira `?` ao gravar no MySQL. Era a RAIZ dos 751
     * títulos `"? Listar Budget"` no cache `mcp_tasks` (incidente 2026-06-20).
     * Com `u`, o `·` é consumido inteiro e o título sai limpo.
     */
    public const US_HEADING_REGEX = '/^#{2,4}\s+(US-[A-Z0-9]+-\d{3,4}[a-z]?)\s*[·\-:]?\s*(.*)$/mu';

    /**
     * Campos de "estado vivo" — ADR 0144.
     * Webhook NUNCA sobrescreve estes em tasks já existentes no DB.
     * Mudança só via tool MCP `tasks-update` (auditada em mcp_task_events).
     */
    public const LIVE_STATE_FIELDS = ['status', 'owner', 'sprint', 'priority'];

    /**
     * Campos descritivos onde o git é canon (SPEC sobrescreve o DB no update).
     * Divergência DB↔SPEC nestes campos é APENAS detectada e contabilizada
     * (SDD C4) — git continua ganhando, sem mudar quem é canon. NÃO confundir
     * com LIVE_STATE_FIELDS (DB canon, nunca sobrescrito — ADR 0144).
     */
    public const DESCRITIVOS_DETECTAVEIS = ['title', 'description'];

    /** Classificador de âncora (fonte de done-ness, ADR 0302/0273) — injetável pra teste. */
    protected SpecAnchorClassifier $anchorClassifier;

    /**
     * Mapa task_id → veredito de âncora do último parseSpec (ADR 0337). Populado em
     * parseSpec, consumido no forward-close de syncAllInternal. Vive só em memória —
     * NUNCA entra no payload persistido (não é coluna de mcp_tasks).
     *
     * @var array<string, array{state: string, sha: ?string, paths: list<string>}>
     */
    protected array $anchorPorTask = [];

    public function __construct(?SpecAnchorClassifier $anchorClassifier = null)
    {
        $this->anchorClassifier = $anchorClassifier ?? new SpecAnchorClassifier();
    }

    /**
     * Parser SPEC + sync DB. Retorna relatório por módulo.
     *
     * @return array{tasks_processadas:int, inseridas:int, atualizadas:int, canceladas:int, fechadas_por_ancora:int, descritivos_divergentes:int, modulos:array<string,int>}
     */
    public function syncAll(?string $apenasModulo = null): array
    {
        // D9.a (Wave 18 SATURATION) — span sync SPEC→DB; cross-tenant admin op.
        return OtelHelper::span('jana.task_parser.sync_all', [
            'apenas_modulo' => $apenasModulo,
        ], fn () => $this->syncAllInternal($apenasModulo));
    }

    private function syncAllInternal(?string $apenasModulo = null): array
    {
        $base = base_path('memory/requisitos');
        if (! is_dir($base)) {
            return $this->relatorio(0, 0, 0, 0, 0, 0, []);
        }

        $reportadasNoSync = [];
        $inseridas = 0;
        $atualizadas = 0;
        $fechadasPorAncora = 0;
        $descritivosDivergentes = 0;
        $modulos = [];

        $iterator = new \DirectoryIterator($base);
        foreach ($iterator as $modDir) {
            if ($modDir->isDot() || ! $modDir->isDir()) {
                continue;
            }
            $module = $modDir->getFilename();
            if ($apenasModulo !== null && $module !== $apenasModulo) {
                continue;
            }
            $specPath = $modDir->getPathname() . '/SPEC.md';
            if (! is_file($specPath)) {
                continue;
            }

            $candidatos = $this->parseSpec($specPath, $module);
            $modulos[$module] = $candidatos->count();

            foreach ($candidatos as $cand) {
                $reportadasNoSync[] = $cand['task_id'];
                $existente = McpTask::where('task_id', $cand['task_id'])->first();

                if ($existente === null) {
                    // Task nova — usa SPEC integral (status inicial vem do SPEC)
                    McpTask::create($cand);
                    $inseridas++;
                } else {
                    if ($this->precisaAtualizar($existente, $cand)) {
                        // SDD C4 — detectar (não resolver) divergência SPEC↔DB em
                        // campos descritivos (title/description). git continua canon
                        // (preserva ADR 0144 zero-regressão de estado vivo); aqui só
                        // contabilizamos + logamos pra dar visibilidade ao drift.
                        if ($this->detectarDivergenciaDescritiva($existente, $cand)) {
                            $descritivosDivergentes++;
                        }

                        // Task existente — ADR 0144 — só atualiza campos descritivos.
                        // Estado vivo (status/owner/sprint/priority) é canônico no DB,
                        // mudança via `tasks-update`. SPEC vira template descritivo.
                        $updatePayload = $this->extrairCamposDescritivos($cand);
                        $this->logarSkipsDeEstadoVivo($existente, $cand);
                        $existente->update($updatePayload);
                        $atualizadas++;
                    }

                    // ADR 0337 (emenda cirúrgica à 0144) — forward-close por âncora
                    // verificada, INDEPENDENTE do update descritivo. O DB segue canon
                    // de estado vivo, MAS a âncora `**Implementado em:** ...verificado@sha`
                    // é a fonte de done-ness (ADR 0302/0273): carrega o veredito do git
                    // pro card quando o SPEC declara done + a âncora prova. Só fecha-pra-
                    // frente; nunca reabre, nunca toca owner/sprint/priority.
                    if ($this->fecharPorAncoraSeElegivel($existente, $cand)) {
                        $fechadasPorAncora++;
                    }
                }
            }
        }

        // Tasks que não apareceram no SPEC mais → cancelar (soft).
        // ADR 0070: NÃO cancelar tasks ad-hoc ou backfilled — só vivem no DB,
        // não são esperadas no SPEC.
        // ADR 0144: NÃO regredir `done` → `cancelled`. Estado terminal é canon
        // do DB; remover linha do SPEC depois de mergear PR é fluxo normal.
        $canceladas = 0;
        $query = McpTask::whereNotIn('status', ['cancelled', 'done'])
            ->where(function ($q) {
                $q->where('source_path', 'LIKE', 'memory/requisitos/%')
                  ->orWhereNull('source_path');
            });
        if ($apenasModulo !== null) {
            $query->where('module', $apenasModulo);
        }
        if (! empty($reportadasNoSync)) {
            $query->whereNotIn('task_id', $reportadasNoSync);
        }
        $orfas = $query->get();
        foreach ($orfas as $orfa) {
            $orfa->update(['status' => 'cancelled', 'parsed_at' => now()]);
            $canceladas++;
        }

        Log::channel('copiloto-ai')->info('TaskRegistry sync', [
            'modulos' => $modulos,
            'inseridas' => $inseridas,
            'atualizadas' => $atualizadas,
            'canceladas' => $canceladas,
            'fechadas_por_ancora' => $fechadasPorAncora,
            'descritivos_divergentes' => $descritivosDivergentes,
        ]);

        return $this->relatorio(count($reportadasNoSync), $inseridas, $atualizadas, $canceladas, $fechadasPorAncora, $descritivosDivergentes, $modulos);
    }

    /**
     * Parseia 1 arquivo SPEC.md e retorna candidatas (sem persistir).
     *
     * @return Collection<int, array>
     */
    public function parseSpec(string $path, ?string $modulo = null): Collection
    {
        if (! is_file($path)) {
            return collect();
        }
        $modulo ??= basename(dirname($path));

        $conteudoBruto = file_get_contents($path);
        $sha = $this->headSha();

        // Extrai frontmatter YAML do topo (defaults globais do SPEC)
        [$globalDefaults, $conteudo] = $this->parseGlobalFrontmatter($conteudoBruto);

        // Resolve projeto/epic/cycle/component default
        $projectId   = isset($globalDefaults['project'])   ? $this->resolveProjectId($globalDefaults['project']) : null;
        $defaultEpic = isset($globalDefaults['default_epic'])      ? $this->resolveEpicId($globalDefaults['default_epic'], $projectId) : null;
        $defaultCycle = isset($globalDefaults['default_cycle'])    ? $this->resolveCycleId($globalDefaults['default_cycle'], $projectId) : null;
        $defaultComp  = isset($globalDefaults['default_component']) ? $this->resolveComponentId($globalDefaults['default_component'], $projectId) : null;

        preg_match_all(self::US_HEADING_REGEX, $conteudo, $matches, PREG_OFFSET_CAPTURE);
        if (empty($matches[1])) {
            return collect();
        }

        $candidatos = collect();
        $count = count($matches[1]);

        for ($i = 0; $i < $count; $i++) {
            $taskId = trim($matches[1][$i][0]);
            $title  = trim($matches[2][$i][0]);
            $offsetInicio = $matches[0][$i][1] + strlen($matches[0][$i][0]);
            $offsetFim    = $i + 1 < $count
                ? $matches[0][$i + 1][1]
                : strlen($conteudo);
            $bloco = substr($conteudo, $offsetInicio, $offsetFim - $offsetInicio);

            // ADR 0337 — veredito de âncora (fonte de done-ness, ADR 0302/0273) por
            // task, consumido pelo forward-close de syncAllInternal. NÃO entra no
            // payload persistido (vive só em memória). Path-existence contra o disco.
            $this->anchorPorTask[$taskId] = $this->anchorClassifier->classify(
                $bloco,
                static fn (string $p): bool => file_exists(base_path($p)),
            );

            $meta = $this->parseFrontmatterInline($bloco);
            $description = $this->extrairDescription($bloco);

            // Resolve epic/cycle/component da própria US (override do default)
            $epicId   = isset($meta['epic_key'])      ? $this->resolveEpicId($meta['epic_key'], $projectId)            : $defaultEpic;
            $cycleId  = isset($meta['cycle_key'])     ? $this->resolveCycleId($meta['cycle_key'], $projectId)          : $defaultCycle;
            $compId   = isset($meta['component_key']) ? $this->resolveComponentId($meta['component_key'], $projectId)  : $defaultComp;

            $candidatos->push([
                'task_id' => $taskId,
                'identifier' => $meta['identifier'] ?? null,
                'project_id' => $projectId,
                'epic_id' => $epicId,
                'cycle_id' => $cycleId,
                'component_id' => $compId,
                'module' => $modulo,
                'title' => $title !== '' ? $title : $taskId,
                'description' => $description,
                'status' => $meta['status'] ?? 'todo',
                'type' => $meta['type'] ?? 'story',
                'owner' => $meta['owner'] ?? null,
                'sprint' => $meta['sprint'] ?? null,
                'priority' => $meta['priority'] ?? 'p2',
                'estimate_h' => $meta['estimate_h'] ?? null,
                'story_points' => $meta['story_points'] ?? null,
                'estimate_unit' => $meta['estimate_unit'] ?? 'points',
                'estimate_value' => $meta['estimate_value'] ?? null,
                'due_date' => $meta['due_date'] ?? null,
                'labels' => $meta['labels'] ?? null,
                'custom_fields' => $meta['custom_fields'] ?? null,
                'blocked_by' => $meta['blocked_by'] ?? null,
                'source_path' => 'memory/requisitos/' . $modulo . '/SPEC.md#' . $taskId,
                'source_git_sha' => $sha,
                'parsed_at' => now(),
            ]);
        }

        return $candidatos;
    }

    /**
     * Extrai frontmatter YAML do topo do arquivo se presente.
     *
     * @return array{0:array<string,string>, 1:string} [defaults, conteudoSemFrontmatter]
     */
    protected function parseGlobalFrontmatter(string $conteudo): array
    {
        if (! preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $conteudo, $m)) {
            return [[], $conteudo];
        }
        $defaults = [];
        foreach (preg_split('/\r?\n/', trim($m[1])) as $linha) {
            if (preg_match('/^([a-z_]+)\s*:\s*(.+)$/i', trim($linha), $kv)) {
                $defaults[strtolower(trim($kv[1]))] = trim($kv[2], "\"' \t");
            }
        }
        $conteudoSem = substr($conteudo, strlen($m[0]));
        return [$defaults, $conteudoSem];
    }

    /**
     * Parseia linhas tipo:
     *   > owner: eliana · sprint: A · priority: p0 · estimate: 8h · status: todo
     *   > epic: COPI-EP-001 · cycle: CYCLE-01 · component: BE · type: bug
     *   > story_points: 5 · due: 2026-05-09 · labels: lgpd,perf
     *   > blocked_by: US-NFSE-001
     *   > identifier: COPI-123
     */
    protected function parseFrontmatterInline(string $bloco): array
    {
        $meta = [];
        if (! preg_match_all('/^>\s*(.+)$/m', $bloco, $linhas)) {
            return $meta;
        }
        foreach ($linhas[1] as $linha) {
            $partes = preg_split('/\s*·\s*|\s*\|\s*/', $linha);
            foreach ($partes as $par) {
                if (! preg_match('/^([a-z_]+)\s*[:=]\s*(.+)$/i', trim($par), $m)) {
                    continue;
                }
                $key = strtolower(trim($m[1]));
                $val = trim($m[2]);
                if ($val === '—' || $val === '-' || $val === '') {
                    continue;
                }
                switch ($key) {
                    case 'owner':
                        $meta['owner'] = strtolower($val);
                        break;
                    case 'sprint':
                        $meta['sprint'] = $val;
                        break;
                    case 'priority':
                        $val = strtolower($val);
                        if (in_array($val, McpTask::PRIORITIES, true)) {
                            $meta['priority'] = $val;
                        }
                        break;
                    case 'status':
                        $val = strtolower($val);
                        if (in_array($val, McpTask::STATUSES, true)) {
                            $meta['status'] = $val;
                        }
                        break;
                    case 'type':
                        $val = strtolower($val);
                        if (in_array($val, McpTask::TYPES, true)) {
                            $meta['type'] = $val;
                        }
                        break;
                    case 'estimate':
                    case 'estimate_h':
                    case 'estimativa':
                        if (preg_match('/(\d+(?:\.\d+)?)/', $val, $mm)) {
                            $meta['estimate_h'] = (float) $mm[1];
                            $meta['estimate_unit'] = 'hours';
                            $meta['estimate_value'] = (float) $mm[1];
                        }
                        break;
                    case 'story_points':
                    case 'pontos':
                        if (is_numeric($val)) {
                            $meta['story_points'] = (float) $val;
                            $meta['estimate_unit'] = 'points';
                            $meta['estimate_value'] = (float) $val;
                        }
                        break;
                    case 'due':
                    case 'due_date':
                    case 'prazo':
                        try {
                            $meta['due_date'] = \Carbon\Carbon::parse($val);
                        } catch (\Throwable) {
                            // formato inválido — ignora
                        }
                        break;
                    case 'labels':
                    case 'tags':
                        $list = array_values(array_filter(array_map('trim', explode(',', $val))));
                        if (! empty($list)) {
                            $meta['labels'] = $list;
                        }
                        break;
                    case 'identifier':
                        if (preg_match('/^[A-Z]+-\d+$/', $val)) {
                            $meta['identifier'] = $val;
                        }
                        break;
                    case 'epic':
                        $meta['epic_key'] = $val;
                        break;
                    case 'cycle':
                        $meta['cycle_key'] = $val;
                        break;
                    case 'component':
                        $meta['component_key'] = $val;
                        break;
                    case 'blocked_by':
                    case 'depends_on':
                        $ids = preg_split('/[\s,]+/', $val);
                        $ids = array_filter($ids, fn ($x) => preg_match('/^US-/i', $x));
                        if (! empty($ids)) {
                            $meta['blocked_by'] = array_values(array_map('strtoupper', $ids));
                        }
                        break;
                    default:
                        // chave não-canônica → custom field
                        $meta['custom_fields'][$key] = $val;
                }
            }
        }
        return $meta;
    }

    /** Description = primeiro bloco não-frontmatter, máx 1000 chars. */
    protected function extrairDescription(string $bloco): string
    {
        $linhas = explode("\n", $bloco);
        $body = [];
        foreach ($linhas as $l) {
            $trimmed = trim($l);
            if ($trimmed === '' || str_starts_with($trimmed, '>')) {
                if (! empty($body)) {
                    continue;
                }
                continue;
            }
            $body[] = $l;
            if (count($body) > 30) {
                break;
            }
        }
        return mb_substr(trim(implode("\n", $body)), 0, 1000);
    }

    /**
     * Determina se a task precisa de update — só considera campos descritivos
     * (ADR 0144). Mudanças em status/owner/sprint/priority NO SPEC são
     * ignoradas (DB é canônico pra estado vivo).
     */
    protected function precisaAtualizar(McpTask $existente, array $cand): bool
    {
        // Campos escalares descritivos — note que status/owner/sprint/priority
        // foram REMOVIDOS desta lista intencionalmente (ADR 0144).
        $campos = [
            'title', 'description', 'estimate_h', 'source_path', 'identifier',
            'project_id', 'epic_id', 'cycle_id', 'component_id',
            'type', 'story_points', 'estimate_unit', 'estimate_value',
        ];
        foreach ($campos as $campo) {
            if ((string) ($existente->{$campo} ?? '') !== (string) ($cand[$campo] ?? '')) {
                return true;
            }
        }
        // Comparar arrays descritivos (labels, blocked_by, custom_fields)
        foreach (['labels', 'blocked_by', 'custom_fields'] as $jsonField) {
            if (json_encode($existente->{$jsonField} ?? null) !== json_encode($cand[$jsonField] ?? null)) {
                return true;
            }
        }
        // due_date
        $existDue = $existente->due_date?->toDateString();
        $candDue = $cand['due_date'] instanceof \Carbon\Carbon ? $cand['due_date']->toDateString() : null;
        if ($existDue !== $candDue) {
            return true;
        }
        return false;
    }

    /**
     * Filtra do payload da SPEC só os campos descritivos (ADR 0144).
     * Remove status/owner/sprint/priority — esses são canônicos no DB.
     * `parsed_at` e `source_git_sha` continuam atualizando (são metadata do sync).
     */
    protected function extrairCamposDescritivos(array $cand): array
    {
        $remover = self::LIVE_STATE_FIELDS;
        return array_diff_key($cand, array_flip($remover));
    }

    /**
     * Loga quando o sync teria sobrescrito estado vivo mas foi pulado.
     * Ajuda auditoria — se SPEC.md tem `status: todo` mas DB tem `status: done`,
     * registra qual divergência foi preservada e por quê (ADR 0144).
     */
    protected function logarSkipsDeEstadoVivo(McpTask $existente, array $cand): void
    {
        $skipsDetectados = [];
        foreach (self::LIVE_STATE_FIELDS as $field) {
            $valorDb = $existente->{$field};
            $valorSpec = $cand[$field] ?? null;

            // Normaliza pra string pra comparação estável
            $vDb = $valorDb === null ? null : (string) $valorDb;
            $vSpec = $valorSpec === null ? null : (string) $valorSpec;

            if ($vDb !== $vSpec) {
                $skipsDetectados[$field] = [
                    'db' => $vDb,
                    'spec' => $vSpec,
                ];
            }
        }

        if (! empty($skipsDetectados)) {
            Log::channel('copiloto-ai')->info('TaskParser preservou estado vivo DB (ADR 0144)', [
                'task_id' => $existente->task_id,
                'preservados' => $skipsDetectados,
                'fonte' => 'webhook-sync',
            ]);
        }
    }

    /**
     * Detecta (NÃO resolve) divergência DB↔SPEC em campos descritivos
     * (title/description) — SDD C4.
     *
     * git continua canon: o update logo a seguir vai sobrescrever o DB com o
     * valor do SPEC normalmente (não muda quem ganha, preserva ADR 0144 que só
     * blinda estado vivo status/owner/sprint/priority). Esta verificação só dá
     * VISIBILIDADE ao drift — loga + alimenta o contador `descritivos_divergentes`.
     *
     * @return bool true se title OU description divergem
     */
    protected function detectarDivergenciaDescritiva(McpTask $existente, array $cand): bool
    {
        $divergencias = [];
        foreach (self::DESCRITIVOS_DETECTAVEIS as $field) {
            $vDb = (string) ($existente->{$field} ?? '');
            $vSpec = (string) ($cand[$field] ?? '');
            if ($vDb !== $vSpec) {
                $divergencias[$field] = [
                    'db' => mb_substr($vDb, 0, 120),
                    'spec' => mb_substr($vSpec, 0, 120),
                ];
            }
        }

        if (empty($divergencias)) {
            return false;
        }

        Log::channel('copiloto-ai')->info('TaskParser detectou divergência descritiva SPEC↔DB (SDD C4)', [
            'task_id' => $existente->task_id,
            'campos' => array_keys($divergencias),
            'divergencias' => $divergencias,
            'canon' => 'git', // git vence o update — só registramos o drift
        ]);

        return true;
    }

    /** Cache em-memória pra evitar query repetida no mesmo sync. */
    protected array $cacheProjetos = [];
    protected array $cacheEpics = [];
    protected array $cacheCycles = [];
    protected array $cacheComponents = [];

    protected function resolveProjectId(string $key): ?int
    {
        $key = strtoupper(trim($key));
        if (isset($this->cacheProjetos[$key])) {
            return $this->cacheProjetos[$key];
        }
        $id = McpProject::where('key', $key)->value('id');
        return $this->cacheProjetos[$key] = $id ? (int) $id : null;
    }

    protected function resolveEpicId(string $key, ?int $projectId): ?int
    {
        if (! $projectId) return null;
        $cacheKey = $projectId . ':' . $key;
        if (isset($this->cacheEpics[$cacheKey])) {
            return $this->cacheEpics[$cacheKey];
        }
        $id = McpEpic::where('project_id', $projectId)->where('key', $key)->value('id');
        return $this->cacheEpics[$cacheKey] = $id ? (int) $id : null;
    }

    protected function resolveCycleId(string $key, ?int $projectId): ?int
    {
        if (! $projectId) return null;
        $cacheKey = $projectId . ':' . $key;
        if (isset($this->cacheCycles[$cacheKey])) {
            return $this->cacheCycles[$cacheKey];
        }
        $id = McpCycle::where('project_id', $projectId)->where('key', $key)->value('id');
        return $this->cacheCycles[$cacheKey] = $id ? (int) $id : null;
    }

    protected function resolveComponentId(string $key, ?int $projectId): ?int
    {
        if (! $projectId) return null;
        $cacheKey = $projectId . ':' . $key;
        if (isset($this->cacheComponents[$cacheKey])) {
            return $this->cacheComponents[$cacheKey];
        }
        $id = McpComponent::where('project_id', $projectId)->where('key', $key)->value('id');
        return $this->cacheComponents[$cacheKey] = $id ? (int) $id : null;
    }

    protected function headSha(): ?string
    {
        $headFile = base_path('.git/HEAD');
        if (! is_file($headFile)) {
            return null;
        }
        $head = trim((string) file_get_contents($headFile));
        if (str_starts_with($head, 'ref: ')) {
            $refPath = base_path('.git/' . substr($head, 5));
            if (is_file($refPath)) {
                return substr(trim((string) file_get_contents($refPath)), 0, 40);
            }
            return null;
        }
        return $head !== '' ? substr($head, 0, 40) : null;
    }

    protected function relatorio(int $processadas, int $ins, int $upd, int $can, int $fechadasAncora, int $descritivosDivergentes, array $modulos): array
    {
        return [
            'tasks_processadas' => $processadas,
            'inseridas' => $ins,
            'atualizadas' => $upd,
            'canceladas' => $can,
            'fechadas_por_ancora' => $fechadasAncora,
            'descritivos_divergentes' => $descritivosDivergentes,
            'modulos' => $modulos,
        ];
    }

    /**
     * ADR 0337 — decide + aplica o forward-close por âncora. Retorna true se fechou.
     *
     * Gatilho (TODAS obrigatórias, fail-closed) via {@see deveFecharPorAncora()}:
     *   1. card ainda ATIVO (não done/cancelled);
     *   2. SPEC declara `status: done` (decisão humana explícita — 1 dos 2 sinais);
     *   3. âncora `anchored_ok` COM sha (ADR 0273/0302 — prova verificável no disco).
     *
     * NUNCA reabre, NUNCA toca owner/sprint/priority. Fecho DIRETO (contorna a FSM,
     * igual ao cancel-de-órfãs logo abaixo) — o PR já passou pelo review real no git;
     * forçar doing→review→done fabricaria eventos falsos. Evento de auditoria honesto
     * + `completed_at` + `acceptance_ref` (do SHA) preservam a rastreabilidade (não
     * dispara o R-B "done sem acceptance" do TasksReconciler).
     */
    protected function fecharPorAncoraSeElegivel(McpTask $existente, array $cand): bool
    {
        $taskId = (string) ($cand['task_id'] ?? '');
        $ancora = $this->anchorPorTask[$taskId] ?? null;

        if (! $this->deveFecharPorAncora(
            (string) $existente->status,
            $cand['status'] ?? null,
            $ancora['state'] ?? null,
            $ancora['sha'] ?? null,
        )) {
            return false;
        }

        $sha = (string) $ancora['sha'];
        $paths = implode(' · ', $ancora['paths'] ?? []);
        $de = (string) $existente->status;

        // Preserva acceptance_ref humano se já houver; senão deriva da âncora.
        $acceptance = $existente->getAttribute('acceptance_ref');
        if (! is_string($acceptance) || trim($acceptance) === '') {
            $acceptance = "âncora verificada@{$sha}"
                . ($paths !== '' ? " · {$paths}" : '')
                . ' (forward-close ADR 0337/0302)';
        }

        $existente->status = 'done';
        if ($existente->completed_at === null) {
            $existente->completed_at = now();
        }
        $existente->acceptance_ref = $acceptance;
        $existente->save();

        McpTaskEvent::log(
            taskId: $existente->task_id,
            eventType: 'status_changed',
            from: $de,
            to: 'done',
            author: 'webhook-sync',
            note: "Forward-close por âncora verificada@{$sha} (ADR 0337, emenda 0144): "
                . 'SPEC declara done + âncora anchored_ok. Estado vivo carregado do git pro card.',
        );

        Log::channel('copiloto-ai')->info('TaskParser forward-close por âncora (ADR 0337)', [
            'task_id' => $existente->task_id,
            'de' => $de,
            'para' => 'done',
            'sha' => $sha,
            'paths' => $ancora['paths'] ?? [],
        ]);

        return true;
    }

    /**
     * Núcleo PURO do gatilho de forward-close (ADR 0337) — sem I/O, determinístico,
     * testável sem DB (espelha o padrão de núcleo puro do TasksReconciler::analisar).
     *
     * @param  string  $dbStatus     status atual do card no DB.
     * @param  ?string $specStatus   status declarado no SPEC (`status:` do blockquote).
     * @param  ?string $anchorState  estado da âncora (SpecAnchorClassifier::classify).
     * @param  ?string $anchorSha    sha `verificado@` da âncora (null se não-verificada).
     */
    public function deveFecharPorAncora(string $dbStatus, ?string $specStatus, ?string $anchorState, ?string $anchorSha): bool
    {
        // 1. card ainda ativo (nunca reabre done/cancelled — preserva estado terminal do DB)
        if (in_array($dbStatus, ['done', 'cancelled'], true)) {
            return false;
        }
        // 2. SPEC declara done — decisão humana explícita (1 dos 2 sinais; sozinho o
        //    `status:` NÃO basta, ADR 0144 desconfia dele — por isso exigimos a âncora)
        if ($specStatus !== 'done') {
            return false;
        }
        // 3. âncora verificada anchored_ok com sha (ADR 0273/0302 — prova no disco)
        return $anchorState === 'anchored_ok' && is_string($anchorSha) && $anchorSha !== '';
    }
}
