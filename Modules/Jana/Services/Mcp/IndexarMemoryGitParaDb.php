<?php

namespace Modules\Jana\Services\Mcp;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;
use Modules\Jana\Services\Memoria\Contextual\ContextualizerService;
use Modules\Jana\Services\Memoria\Contextual\DocumentChunker;
use Symfony\Component\Yaml\Yaml;

/**
 * MEM-MCP-1.a (ADR 0053) — Sincroniza memory/ do filesystem (git) pra
 * mcp_memory_documents (DB cache governado).
 *
 * F1 KB expansion (2026-04-30): cobre agora além de decisions/sessions/SPEC:
 *   - memory/comparativos/X.md           → type=comparativo
 *   - memory/requisitos/M/adr/CAT/X.md   → type=adr (module=M, cat=arq|tech|ui)
 *   - memory/requisitos/M/RUNBOOK.md, ARCHITECTURE.md, GLOSSARY.md, etc
 *   - memory/requisitos/M/audits/X.md    → type=audit
 *   - memory/X.md raiz (00-overview..., CHANGELOG, INDEX...)
 *   - memory/requisitos/X.md raiz (BI.md, Boleto.md...)
 *
 * Idempotente: re-rodar com mesmos arquivos é no-op (sha igual).
 */
class IndexarMemoryGitParaDb
{
    /** Padrões para PII (regex BR — herda lógica de LaravelAiSdkDriver) */
    protected const PII_PATTERNS = [
        // CPF: 000.000.000-00 ou 00000000000  (pii-allowlist: exemplo de FORMATO do próprio redator, não é PII real)
        '/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/' => 'XXX.XXX.XXX-NN',
        // CNPJ: 00.000.000/0000-00 ou 00000000000000  (pii-allowlist: exemplo de FORMATO do próprio redator, não é PII real)
        '/\b\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}\b/' => 'XX.XXX.XXX/XXXX-NN',
        // Cartão de crédito (16 dígitos)
        '/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/' => '****-****-****-****',
    ];

    public function __construct(
        protected string $repoBasePath,
        protected string $reason = 'manual',
        protected ?int $userId = null,
        protected int $businessId = 1,
    ) {
    }

    /**
     * Executa indexação completa. Retorna contadores.
     *
     * @return array{indexados:int, atualizados:int, novos:int, removidos:int, redactions:int}
     */
    public function run(): array
    {
        // D9.a (Wave 18 SATURATION) — span indexação memória git→DB; reason+businessId.
        return OtelHelper::span('jana.mcp.indexar_memory', [
            'business_id' => $this->businessId,
            'reason' => $this->reason,
            'user_id' => $this->userId,
        ], fn () => $this->runInternal());
    }

    private function runInternal(): array
    {
        $stats = ['indexados' => 0, 'atualizados' => 0, 'novos' => 0, 'removidos' => 0, 'redactions' => 0];

        $arquivos = $this->coletarArquivos();
        $slugsVistos = [];

        foreach ($arquivos as $info) {
            $slug = $info['slug'];
            $slugsVistos[] = $slug;

            $resultado = $this->indexarArquivo($info);
            $stats['indexados']++;
            $stats['redactions'] += $resultado['redactions'];
            if ($resultado['novo']) {
                $stats['novos']++;
            } elseif ($resultado['atualizado']) {
                $stats['atualizados']++;
            }
        }

        // Soft-delete documentos que sumiram do filesystem
        $stats['removidos'] = McpMemoryDocument::whereNotIn('slug', $slugsVistos)->delete();

        Log::channel('copiloto-ai')->info('IndexarMemoryGitParaDb concluído', [
            'reason' => $this->reason,
            'stats'  => $stats,
        ]);

        return $stats;
    }

    /**
     * Coleta arquivos memory/* + raiz e classifica.
     *
     * @return array<array{slug:string, type:string, module:?string, path:string, full:string}>
     */
    protected function coletarArquivos(): array
    {
        $base = rtrim($this->repoBasePath, '/\\');
        $arquivos = [];

        // ADRs
        foreach (glob("$base/memory/decisions/*.md") as $file) {
            $name = basename($file, '.md');
            // Pula README, _INDEX, etc.
            if (str_starts_with($name, '_') || $name === 'README') continue;
            $arquivos[] = [
                'slug'   => $name,
                'type'   => 'adr',
                'module' => $this->detectarModulo($name),
                'path'   => "memory/decisions/$name.md",
                'full'   => $file,
            ];
        }

        // Sessions
        foreach (glob("$base/memory/sessions/*.md") as $file) {
            $name = basename($file, '.md');
            if (str_starts_with($name, '_') || $name === 'README') continue;
            $arquivos[] = [
                'slug'   => "session-$name",
                'type'   => 'session',
                'module' => $this->detectarModulo($name),
                'path'   => "memory/sessions/$name.md",
                'full'   => $file,
            ];
        }

        // Specs por módulo (memory/requisitos/{Modulo}/SPEC.md)
        foreach (glob("$base/memory/requisitos/*/SPEC.md") as $file) {
            $module = strtolower(basename(dirname($file)));
            $arquivos[] = [
                'slug'   => "spec-$module",
                'type'   => 'spec',
                'module' => $module,
                'path'   => "memory/requisitos/" . basename(dirname($file)) . "/SPEC.md",
                'full'   => $file,
            ];
        }

        // BRIEFINGs por módulo (memory/requisitos/{Modulo}/BRIEFING.md) — "estado
        // consolidado" do módulo, porta única (ADR 0270 D-2). Slug canônico
        // `briefing:<Modulo>` capitalizado, como o golden set recall-golden.yaml espera.
        // Sync gap descoberto 2026-07-04: estavam fora do índice → decisions-search/
        // kb-answer nunca achavam o estado consolidado do módulo (caso de uso #1 Larissa).
        foreach (glob("$base/memory/requisitos/*/BRIEFING.md") as $file) {
            $module = basename(dirname($file));
            $arquivos[] = [
                'slug'   => "briefing:$module",
                'type'   => 'briefing',
                'module' => strtolower($module),
                'path'   => "memory/requisitos/$module/BRIEFING.md",
                'full'   => $file,
            ];
        }

        // Arquivos raiz canônicos
        $raiz = [
            ['slug' => 'handoff',  'type' => 'handoff', 'module' => null, 'path' => 'memory/08-handoff.md',  'full' => "$base/memory/08-handoff.md"],
            ['slug' => 'current',  'type' => 'current', 'module' => null, 'path' => 'CURRENT.md',           'full' => "$base/CURRENT.md"],
            ['slug' => 'tasks',    'type' => 'tasks',   'module' => null, 'path' => 'TASKS.md',             'full' => "$base/TASKS.md"],
            ['slug' => 'claude',   'type' => 'reference', 'module' => null, 'path' => 'CLAUDE.md',          'full' => "$base/CLAUDE.md"],
            ['slug' => 'infra',    'type' => 'reference', 'module' => 'infra', 'path' => 'INFRA.md',         'full' => "$base/INFRA.md"],
            ['slug' => 'design',   'type' => 'reference', 'module' => null, 'path' => 'DESIGN.md',          'full' => "$base/DESIGN.md"],
        ];
        foreach ($raiz as $r) {
            if (file_exists($r['full'])) {
                $arquivos[] = $r;
            }
        }

        // ─── F1 KB expansion ──────────────────────────────────────────────

        // Comparativos Capterra-style
        foreach (glob("$base/memory/comparativos/*.md") as $file) {
            $name = basename($file, '.md');
            if (str_starts_with($name, '_')) continue; // skip _INDEX, _TEMPLATE
            $arquivos[] = [
                'slug'   => "comparativo-$name",
                'type'   => 'comparativo',
                'module' => $this->detectarModulo($name),
                'path'   => "memory/comparativos/$name.md",
                'full'   => $file,
            ];
        }

        // ADRs por módulo (memory/requisitos/{Modulo}/adr/{cat}/*.md)
        foreach (glob("$base/memory/requisitos/*/adr/*/*.md") as $file) {
            $name = basename($file, '.md');
            if (str_starts_with($name, '_') || $name === 'README') continue;
            $cat = strtolower(basename(dirname($file)));            // arq | tech | ui
            $moduleDir = basename(dirname(dirname(dirname($file)))); // capitalized
            $module = strtolower($moduleDir);
            $arquivos[] = [
                'slug'   => "adr-$module-$cat-$name",
                'type'   => 'adr',
                'module' => $module,
                'path'   => "memory/requisitos/$moduleDir/adr/$cat/$name.md",
                'full'   => $file,
            ];
        }

        // Docs por módulo: RUNBOOK, ARCHITECTURE, GLOSSARY, CHANGELOG, README
        $docsPorModulo = [
            'RUNBOOK'      => 'runbook',
            'ARCHITECTURE' => 'reference',
            'GLOSSARY'     => 'reference',
            'CHANGELOG'    => 'changelog',
            'README'       => 'reference',
            'COMPARATIVO_CONCORRENCIA' => 'comparativo',
        ];
        foreach (glob("$base/memory/requisitos/*/*.md") as $file) {
            $name = basename($file, '.md');
            if ($name === 'SPEC') continue; // já coberto acima
            if (!isset($docsPorModulo[$name])) continue;
            $moduleDir = basename(dirname($file));
            $module = strtolower($moduleDir);
            $arquivos[] = [
                'slug'   => strtolower($name) . "-$module",
                'type'   => $docsPorModulo[$name],
                'module' => $module,
                'path'   => "memory/requisitos/$moduleDir/$name.md",
                'full'   => $file,
            ];
        }

        // Audits por módulo (memory/requisitos/{Modulo}/audits/*.md)
        foreach (glob("$base/memory/requisitos/*/audits/*.md") as $file) {
            $name = basename($file, '.md'); // ex: 2026-04-22
            if (str_starts_with($name, '_')) continue;
            $moduleDir = basename(dirname(dirname($file)));
            $module = strtolower($moduleDir);
            $arquivos[] = [
                'slug'   => "audit-$module-$name",
                'type'   => 'audit',
                'module' => $module,
                'path'   => "memory/requisitos/$moduleDir/audits/$name.md",
                'full'   => $file,
            ];
        }

        // Memory/*.md raiz (00-user-profile..., CHANGELOG, INDEX, REQUISITOS_FUNCIONAIS_PONTO, etc)
        // Evita duplicar 08-handoff já coberto acima
        $raizSlugs = ['08-handoff'];
        foreach (glob("$base/memory/*.md") as $file) {
            $name = basename($file, '.md');
            if (in_array($name, $raizSlugs, true)) continue;
            if (str_starts_with($name, '_')) continue;
            $arquivos[] = [
                'slug'   => "memory-" . strtolower(str_replace(['_', ' '], '-', $name)),
                'type'   => 'reference',
                'module' => $this->detectarModulo($name),
                'path'   => "memory/$name.md",
                'full'   => $file,
            ];
        }

        // memory/requisitos/*.md raiz (BI.md, AiAssistance.md, AssetManagement.md, Boleto.md...)
        foreach (glob("$base/memory/requisitos/*.md") as $file) {
            $name = basename($file, '.md');
            if (str_starts_with($name, '_')) continue;
            $arquivos[] = [
                'slug'   => "module-overview-" . strtolower($name),
                'type'   => 'reference',
                'module' => strtolower($name),
                'path'   => "memory/requisitos/$name.md",
                'full'   => $file,
            ];
        }

        // ─── GAP #1 ingest-coverage (2026-05-29) ──────────────────────────
        //
        // Pastas cegas de ALTO VALOR e SEM PII de cliente que a whitelist de
        // globs acima ignorava. Sem isto, handoffs ("onde paramos" entre
        // sessões) e os 51 docs canônicos ex-auto-mem NUNCA chegavam ao MCP.
        //
        // ⚠️ EXCLUÍDO de propósito (LGPD): memory/clientes/** e memory/feedback/**
        // têm PII de cliente (email/telefone) que o redactor (só CPF/CNPJ/cartão)
        // NÃO cobre. Adicionar só quando o redactor cobrir email+telefone.

        // 1. Handoffs — "onde paramos" entre sessões (CRÍTICO p/ continuidade).
        //    memory/08-handoff.md já coletado acima como slug 'handoff' — não duplica
        //    (pasta handoffs/ tem nomes datados, sem colisão com '08-handoff').
        foreach (glob("$base/memory/handoffs/*.md") as $file) {
            $name = basename($file, '.md');
            if (str_starts_with($name, '_') || $name === 'README') continue;
            $arquivos[] = [
                'slug'   => "handoff-" . strtolower(str_replace(['_', ' '], '-', $name)),
                'type'   => 'handoff',
                'module' => $this->detectarModulo($name),
                'path'   => "memory/handoffs/$name.md",
                'full'   => $file,
            ];
        }

        // 2. Reference recursivo — 51+ docs canônicos ex-auto-mem (ADR 0061).
        foreach ($this->coletarRecursivo("$base/memory/reference", $base, 'reference', 'reference') as $info) {
            $arquivos[] = $info;
        }

        // 3. Sprints recursivo — dossiês de sprint (s3-constituicao, etc).
        foreach ($this->coletarRecursivo("$base/memory/sprints", $base, 'reference', 'sprint') as $info) {
            $arquivos[] = $info;
        }

        // 4. Governance recursivo — CONSTITUTION, ENFORCEMENT, TRUST-TIERS, design-requests/, etc.
        //    Cobre `memory/governance/design-requests/` = o Design Request Ledger (vereditos · Onda 3):
        //    REQ-NNN + LEDGER ficam consultáveis via memoria-search, READ-ONLY (git é SSOT · ADR 0061).
        //    Travado por IndexarMemoryGitParaDbColetarTest (não regredir a cobertura dos vereditos).
        foreach ($this->coletarRecursivo("$base/memory/governance", $base, 'reference', 'governance') as $info) {
            $arquivos[] = $info;
        }

        // 5. Audits na raiz (memory/audits/*.md — NÃO recursivo: subpastas como
        //    2026-05-pre-sales podem conter material sensível não auditado).
        foreach (glob("$base/memory/audits/*.md") as $file) {
            $name = basename($file, '.md');
            if (str_starts_with($name, '_') || $name === 'README') continue;
            $arquivos[] = [
                'slug'   => "audit-root-" . strtolower(str_replace(['_', ' '], '-', $name)),
                'type'   => 'audit',
                'module' => $this->detectarModulo($name),
                'path'   => "memory/audits/$name.md",
                'full'   => $file,
            ];
        }

        // 6. Design System recursivo — PT-01, PRE-MERGE-UI, adr/ui/*.
        foreach ($this->coletarRecursivo("$base/memory/requisitos/_DesignSystem", $base, 'reference', 'designsystem') as $info) {
            $arquivos[] = $info;
        }

        return $arquivos;
    }

    /**
     * GAP #1 ingest-coverage — coleta RECURSIVA de *.md numa subárvore.
     *
     * glob() do PHP não recursa em subdiretórios, então pastas como
     * memory/reference/, memory/sprints/ e memory/requisitos/_DesignSystem/adr/ui/
     * ficavam cegas. Usa RecursiveDirectoryIterator + RecursiveIteratorIterator
     * (SKIP_DOTS) — pattern canônico PHP 8.4 — pra varrer toda a subárvore.
     *
     * Pula arquivos `_*` (templates/índices) e README. Slug único derivado do
     * caminho relativo (ex: reference/adr/ui/foo.md → 'reference-adr-ui-foo')
     * pra não colidir entre subpastas homônimas.
     *
     * @return list<array{slug:string, type:string, module:?string, path:string, full:string}>
     */
    protected function coletarRecursivo(string $dir, string $base, string $type, string $slugPrefix): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $arquivos = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'md') {
                continue;
            }

            $name = $file->getBasename('.md');
            if (str_starts_with($name, '_') || $name === 'README') {
                continue;
            }

            // Caminho relativo POSIX a partir do diretório-raiz da subárvore.
            $full = $file->getPathname();
            $rel = ltrim(str_replace('\\', '/', substr($full, strlen($dir))), '/'); // ex: adr/ui/foo.md
            $relSemExt = preg_replace('/\.md$/i', '', $rel);

            // Slug determinístico: prefixo + caminho-relativo slugificado.
            $slugTail = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $relSemExt));
            $slugTail = trim((string) $slugTail, '-');
            $slug = "$slugPrefix-$slugTail";

            // git_path POSIX relativo ao repo (consistente com glob branches acima).
            $gitPath = ltrim(str_replace('\\', '/', substr($full, strlen($base))), '/');

            $arquivos[] = [
                'slug'   => $slug,
                'type'   => $type,
                'module' => $this->detectarModulo($rel),
                'path'   => $gitPath,
                'full'   => $full,
            ];
        }

        return $arquivos;
    }

    /**
     * @return array{novo:bool, atualizado:bool, redactions:int}
     */
    protected function indexarArquivo(array $info): array
    {
        $conteudo = file_get_contents($info['full']);
        if ($conteudo === false) {
            return ['novo' => false, 'atualizado' => false, 'redactions' => 0];
        }

        ['frontmatter' => $frontmatter, 'body' => $body] = $this->parseFrontmatter($conteudo);

        // PII redaction
        ['redacted' => $contentRedacted, 'count' => $piiCount] = $this->redactarPii($body);

        // Inferir título e scope
        $title = $frontmatter['name'] ?? $frontmatter['title'] ?? $this->inferirTitulo($body, $info['slug']);
        $scopeRequired = $frontmatter['scope_required'] ?? $this->inferirScopeRequired($info);
        $adminOnly = (bool) ($frontmatter['admin_only'] ?? false);

        // Sanitização UTF-8 — protege contra !!binary YAML / BOM / chars inválidos
        // gerados pelo mcp:adr:migrar-frontmatter inferindo título de body com BOM.
        // Sem isso, json_encode falha "Malformed UTF-8 characters" ao salvar metadata.
        $title = $this->sanitizarUtf8($title);
        $frontmatter = $this->sanitizarUtf8Recursivo($frontmatter);

        // Git SHA do último commit que toca o arquivo (best-effort, falha silente)
        $gitSha = $this->lerGitSha($info['path']);

        // GAP D3 #1 — Contextual Retrieval Anthropic (2024-09-19 / oimpresso 2026-05-15).
        //   Quando feature flag `copiloto.contextual_retrieval.enabled` = true,
        //   gera contexto curto (50-100 tokens) descrevendo doc de origem e
        //   PREPENDA ao content antes de embedding/BM25. Reduz -49% failed
        //   retrievals (-67% combinado com reranking).
        //   Custo: ~$1.02 / 1M tokens cached. Operacional pra ~1.500 docs.
        //   Default OFF — backfill via `php artisan jana:contextualize-backfill`.
        ['context' => $contextualContext, 'indexed' => $contextualIndexed]
            = $this->aplicarContextualRetrieval($contentRedacted);

        // UPSERT — inclui soft-deleted (withTrashed) pra recuperar docs que
        // foram soft-removidos por glob miss em sync anterior e reapareceram
        // (ex: arquivo renomeado pro path antigo, .gitignore mudou, glob expandido).
        // Sem withTrashed, firstOrNew ignora soft-deleted → create() viola UNIQUE
        // (slug). Detectado em prod 2026-05-21: SQLSTATE[23000] 1062 Duplicate
        // entry 'session-2026-05-13-agents-canonicos-meta-degradacao'.
        $doc = McpMemoryDocument::withTrashed()->firstOrNew(['slug' => $info['slug']]);
        $novo = ! $doc->exists;
        $estavaSoftDeletado = ! $novo && $doc->trashed();

        $tipadas = $this->extrairColunasTipadas($frontmatter, $piiCount);

        // Bug fix US-COPI-078 backfill: detectar mudança no FRONTMATTER mesmo
        // quando body não muda (caso típico: ADR antigo ganhou frontmatter
        // YAML pelo `mcp:adr:migrar-frontmatter` mas body permaneceu idêntico).
        // Sem isso, sync ignora a atualização e colunas tipadas ficam NULL.
        $metadataMudou = $doc->exists && (
            ($doc->status ?? null) !== ($tipadas['status'] ?? null) ||
            ($doc->authority ?? null) !== ($tipadas['authority'] ?? null) ||
            json_encode($doc->supersedes ?? null) !== json_encode($tipadas['supersedes'] ?? null) ||
            json_encode($doc->superseded_by ?? null) !== json_encode($tipadas['superseded_by'] ?? null)
        );

        $contentMudou = $doc->content_md !== $contentRedacted
            || $doc->git_sha !== $gitSha
            || $metadataMudou;

        $atributos = array_merge([
            'business_id'          => $this->businessId,
            'type'                 => $info['type'],
            'module'               => $frontmatter['module'] ?? $info['module'],
            'title'                => $title,
            'content_md'           => $contentRedacted,
            'scope_required'       => $scopeRequired,
            'admin_only'           => $adminOnly,
            'metadata'             => $frontmatter,
            'git_sha'              => $gitSha,
            'git_path'             => $info['path'],
            'pii_redactions_count' => $piiCount,
            'indexed_at'           => now(),
        ], $tipadas);

        // GAP D3 #1 — Adiciona colunas Contextual Retrieval (se schema migrado).
        // Idempotente: schema legado sem essas colunas ignora gracefully.
        if ($contextualIndexed) {
            $atributos['contextual_context']  = $contextualContext;
            $atributos['contextual_indexed']  = true;
            $atributos['contextualized_at']   = now();
        }

        if ($novo) {
            McpMemoryDocument::create(array_merge(['slug' => $info['slug']], $atributos));
            return ['novo' => true, 'atualizado' => false, 'redactions' => $piiCount];
        }

        // Soft-deleted row reapareceu — restaura e força update (mudou ou não).
        // Contabilizado como 'atualizado' (mais útil pro operador que 'novo' falso).
        if ($estavaSoftDeletado) {
            DB::transaction(function () use ($doc, $atributos) {
                $doc->restore();
                $doc->snapshotEAtualizar($atributos, $this->userId, $this->reason);
            });
            return ['novo' => false, 'atualizado' => true, 'redactions' => $piiCount];
        }

        if ($contentMudou) {
            DB::transaction(function () use ($doc, $atributos) {
                $doc->snapshotEAtualizar($atributos, $this->userId, $this->reason);
            });
            return ['novo' => false, 'atualizado' => true, 'redactions' => $piiCount];
        }

        // Sem mudança — só atualiza indexed_at, SEM disparar Scout observer.
        // withoutSyncingToSearch garante que Ollama não re-embeda doc inalterado.
        // Sem isso, update() dispara Eloquent 'updated' event → Scout → 383 embeddings por sync.
        McpMemoryDocument::withoutSyncingToSearch(fn () => $doc->update(['indexed_at' => now()]));
        return ['novo' => false, 'atualizado' => false, 'redactions' => $piiCount];
    }

    /**
     * Parse YAML frontmatter (entre --- ... ---) usando symfony/yaml.
     *
     * MEM-KB-3 / F1 — substitui parser minimalista linha-a-linha por parser
     * completo (suporta listas, datas ISO, strings com `:`, nesting). Garante
     * que `tags`, `supersedes`, `superseded_by`, `related` e `decided_by` venham
     * como array PHP, não string.
     *
     * @return array{frontmatter:array, body:string}
     */
    protected function parseFrontmatter(string $conteudo): array
    {
        if (! preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $conteudo, $m)) {
            return ['frontmatter' => [], 'body' => $conteudo];
        }

        $yaml = $m[1];
        $body = $m[2];

        try {
            $fm = Yaml::parse($yaml, Yaml::PARSE_DATETIME) ?? [];
            if (! is_array($fm)) {
                $fm = [];
            }
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->warning('YAML frontmatter inválido — ignorando', [
                'erro' => $e->getMessage(),
                'yaml' => substr($yaml, 0, 200),
            ]);
            $fm = [];
        }

        return ['frontmatter' => $fm, 'body' => $body];
    }

    /**
     * Extrai colunas tipadas do frontmatter pra colunas do mcp_memory_documents.
     * MEM-KB-3 / F1.
     *
     * @return array<string, mixed>
     */
    protected function extrairColunasTipadas(array $fm, int $piiCount): array
    {
        $supersedes = array_unique(array_merge(
            (array) ($fm['supersedes'] ?? []),
            (array) ($fm['supersedes_partially'] ?? []),
        ));

        $decidedAt = $fm['decided_at'] ?? null;
        if ($decidedAt instanceof \DateTimeInterface) {
            $decidedAt = $decidedAt->format('Y-m-d');
        }

        return [
            'status'        => $this->normalizarEnum($fm['status'] ?? null, ['rascunho', 'proposto', 'aceito', 'recusado', 'deprecated', 'superseded']),
            'authority'     => $this->normalizarEnum($fm['authority'] ?? null, ['canonical', 'reference', 'exploratory']),
            'lifecycle'     => $this->normalizarEnum($fm['lifecycle'] ?? null, ['ativo', 'arquivado', 'substituido']),
            'quarter'       => is_string($fm['quarter'] ?? null) ? $fm['quarter'] : null,
            'decided_at'    => $decidedAt,
            'decided_by'    => isset($fm['decided_by']) ? array_values((array) $fm['decided_by']) : null,
            'tags'          => isset($fm['tags']) ? array_values((array) $fm['tags']) : null,
            'supersedes'    => $supersedes ? array_values($supersedes) : null,
            'superseded_by' => isset($fm['superseded_by']) ? array_values((array) $fm['superseded_by']) : null,
            'related'       => isset($fm['related']) ? array_values((array) $fm['related']) : null,
            'has_pii'       => (bool) ($fm['pii'] ?? ($piiCount > 0)),
        ];
    }

    protected function normalizarEnum(mixed $valor, array $permitidos): ?string
    {
        if (! is_string($valor)) {
            return null;
        }
        $v = strtolower(trim($valor));
        return in_array($v, $permitidos, true) ? $v : null;
    }

    /**
     * Aplica PII redactor sobre o body. Returns texto redactado + contagem.
     *
     * @return array{redacted:string, count:int}
     */
    protected function redactarPii(string $texto): array
    {
        $count = 0;
        $redacted = $texto;

        foreach (self::PII_PATTERNS as $pattern => $replacement) {
            $redacted = preg_replace_callback($pattern, function () use (&$count, $replacement) {
                $count++;
                return $replacement;
            }, $redacted);
        }

        return ['redacted' => $redacted, 'count' => $count];
    }

    /**
     * Sanitiza string pra UTF-8 válido. Remove bytes inválidos que quebram
     * json_encode (!!binary do YAML, BOM, caracteres de controle).
     */
    protected function sanitizarUtf8(?string $s): string
    {
        if ($s === null || $s === '') return '';
        // Remove BOM UTF-8 e BOMs UTF-16
        $s = preg_replace('/^(\xEF\xBB\xBF|\xFF\xFE|\xFE\xFF)/', '', $s);
        // Substitui bytes UTF-8 inválidos por '?' usando iconv
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
        return $clean !== false ? $clean : '';
    }

    /**
     * Aplica sanitizarUtf8 recursivamente em arrays (frontmatter aninhado).
     */
    protected function sanitizarUtf8Recursivo(array $arr): array
    {
        $out = [];
        foreach ($arr as $k => $v) {
            $key = is_string($k) ? $this->sanitizarUtf8($k) : $k;
            if (is_string($v)) {
                $out[$key] = $this->sanitizarUtf8($v);
            } elseif (is_array($v)) {
                $out[$key] = $this->sanitizarUtf8Recursivo($v);
            } else {
                $out[$key] = $v;
            }
        }
        return $out;
    }

    /**
     * Tenta extrair título do conteúdo se não tem frontmatter.
     */
    protected function inferirTitulo(string $body, string $slug): string
    {
        if (preg_match('/^#\s+(.+)$/m', $body, $m)) {
            return trim($m[1]);
        }
        return $slug;
    }

    /**
     * Heurística pra inferir scope_required quando não está no frontmatter.
     */
    protected function inferirScopeRequired(array $info): ?string
    {
        // ADR 0030 (credenciais) e similares devem ser admin-only por padrão
        if (str_contains($info['slug'], 'credenciais') || str_contains($info['slug'], 'secret')) {
            return 'copiloto.mcp.admin';
        }
        // Audit fica admin-only
        if ($info['type'] === 'session' && str_contains($info['slug'], 'audit')) {
            return 'copiloto.mcp.audit.read';
        }
        return null; // pública pra autenticados
    }

    /**
     * Detecta módulo pelo prefixo do slug ou caminho.
     */
    protected function detectarModulo(string $name): ?string
    {
        $modulos = ['copiloto', 'financeiro', 'pontowr2', 'memcofre', 'cms', 'officeimpresso', 'connector', 'grow'];
        $lower = strtolower($name);
        foreach ($modulos as $m) {
            if (str_contains($lower, $m)) {
                return $m;
            }
        }
        return null;
    }

    /**
     * GAP D3 #1 — Contextual Retrieval Anthropic.
     *
     * Quando feature flag `copiloto.contextual_retrieval.enabled` = true,
     * gera contexto curto descrevendo o doc e PREPENDA ao body (cheia tabela
     * `mcp_memory_documents.contextual_context`). Hybrid retrieval depois
     * pega esse contexto pra desambiguar matches.
     *
     * Estratégia "concat-first" (Anthropic blog recommendation):
     *   - Doc curto (≤max_chunk_chars) → 1 chunk único, gera 1 contexto.
     *   - Doc longo → chunkeia + gera contexto por chunk, concatena ordenado.
     *
     * Erro/timeout → graceful degradation (sem contexto, mantém comportamento legado).
     *
     * @return array{context:?string, indexed:bool}
     */
    protected function aplicarContextualRetrieval(string $bodyRedacted): array
    {
        $enabled = (bool) config('copiloto.contextual_retrieval.enabled', false);
        if (! $enabled) {
            return ['context' => null, 'indexed' => false];
        }

        try {
            /** @var ContextualizerService $svc */
            $svc = app(ContextualizerService::class);
            /** @var DocumentChunker $chunker */
            $chunker = app(DocumentChunker::class);

            $maxChars = (int) config('copiloto.contextual_retrieval.max_chunk_chars', 3200);
            $maxDocChars = (int) config('copiloto.contextual_retrieval.max_doc_chars', 200_000);

            // Edge case: doc > limite (200 KB ~ 50k tokens) — pula contextualização
            // (Anthropic API rejeita request > context window do model).
            if (strlen($bodyRedacted) > $maxDocChars) {
                Log::channel('copiloto-ai')->info('ContextualRetrieval: doc oversize, pulando', [
                    'doc_chars' => strlen($bodyRedacted),
                    'limit' => $maxDocChars,
                ]);

                return ['context' => null, 'indexed' => false];
            }

            $chunks = $chunker->chunk($bodyRedacted, $maxChars);
            if (empty($chunks)) {
                return ['context' => null, 'indexed' => false];
            }

            $contextos = $svc->contextualizeBatch($bodyRedacted, $chunks);

            // Concat contextos preservando ordem dos chunks.
            $contextoFinal = collect($chunks)
                ->map(fn ($chunk) => trim((string) ($contextos[sha1($chunk)] ?? '')))
                ->filter(fn ($s) => $s !== '')
                ->implode("\n");

            if ($contextoFinal === '') {
                return ['context' => null, 'indexed' => false];
            }

            return ['context' => $contextoFinal, 'indexed' => true];
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->warning('ContextualRetrieval falha graceful', [
                'erro' => $e->getMessage(),
            ]);

            return ['context' => null, 'indexed' => false];
        }
    }

    /**
     * Best-effort: pega SHA do último commit que toca o arquivo.
     * Hostinger shared hosting tem shell_exec disabled — degrada gracioso.
     */
    protected function lerGitSha(string $relativePath): ?string
    {
        if (!function_exists('shell_exec') || in_array('shell_exec', explode(',', (string) ini_get('disable_functions')))) {
            return null;
        }
        $cmd = sprintf(
            'git -C %s log -n 1 --format=%%H -- %s 2>/dev/null',
            escapeshellarg($this->repoBasePath),
            escapeshellarg($relativePath)
        );
        try {
            $sha = trim((string) @shell_exec($cmd));
            return $sha !== '' ? $sha : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
