<?php

namespace Modules\Copiloto\Services\Mcp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Copiloto\Entities\Mcp\McpMemoryDocument;

/**
 * MEM-MCP-1.a (ADR 0053) — Sincroniza memory/ do filesystem (git) pra
 * mcp_memory_documents (DB cache governado).
 *
 * Workflow:
 *   1. Scaneia diretórios memory/decisions/, memory/sessions/, memory/requisitos/
 *      + arquivos raiz CURRENT.md, TASKS.md, memory/08-handoff.md
 *   2. Pra cada arquivo:
 *      - parseia frontmatter YAML (slug, type, module, scope_required)
 *      - PII redactor (regex CPF/CNPJ — ADR 0030)
 *      - calcula git_sha do commit que toca o arquivo
 *      - UPSERT por slug (UPDATE move row antiga pra _history)
 *   3. Soft-delete documentos que sumiram do filesystem
 *
 * Idempotente: re-rodar com mesmos arquivos é no-op (sha igual).
 */
class IndexarMemoryGitParaDb
{
    /** Padrões para PII (regex BR — herda lógica de LaravelAiSdkDriver) */
    protected const PII_PATTERNS = [
        // CPF: 000.000.000-00 ou 00000000000
        '/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/' => 'XXX.XXX.XXX-NN',
        // CNPJ: 00.000.000/0000-00 ou 00000000000000
        '/\b\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}\b/' => 'XX.XXX.XXX/XXXX-NN',
        // Cartão de crédito (16 dígitos)
        '/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/' => '****-****-****-****',
    ];

    public function __construct(
        protected string $repoBasePath,
        protected string $reason = 'manual',
        protected ?int $userId = null,
    ) {
    }

    /**
     * Executa indexação completa. Retorna contadores.
     *
     * @return array{indexados:int, atualizados:int, novos:int, removidos:int, redactions:int}
     */
    public function run(): array
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

        // Git SHA do último commit que toca o arquivo (best-effort, falha silente)
        $gitSha = $this->lerGitSha($info['path']);

        // UPSERT
        $doc = McpMemoryDocument::firstOrNew(['slug' => $info['slug']]);
        $novo = ! $doc->exists;
        $contentMudou = $doc->content_md !== $contentRedacted || $doc->git_sha !== $gitSha;

        $atributos = [
            'type'                 => $info['type'],
            'module'               => $info['module'],
            'title'                => $title,
            'content_md'           => $contentRedacted,
            'scope_required'       => $scopeRequired,
            'admin_only'           => $adminOnly,
            'metadata'             => $frontmatter,
            'git_sha'              => $gitSha,
            'git_path'             => $info['path'],
            'pii_redactions_count' => $piiCount,
            'indexed_at'           => now(),
        ];

        if ($novo) {
            McpMemoryDocument::create(array_merge(['slug' => $info['slug']], $atributos));
            return ['novo' => true, 'atualizado' => false, 'redactions' => $piiCount];
        }

        if ($contentMudou) {
            DB::transaction(function () use ($doc, $atributos) {
                $doc->snapshotEAtualizar($atributos, $this->userId, $this->reason);
            });
            return ['novo' => false, 'atualizado' => true, 'redactions' => $piiCount];
        }

        // Sem mudança — só atualiza indexed_at
        $doc->update(['indexed_at' => now()]);
        return ['novo' => false, 'atualizado' => false, 'redactions' => $piiCount];
    }

    /**
     * Parse YAML frontmatter simples (entre --- ... ---).
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

        // Parser YAML minimalista (key: value, suporta string/bool/int)
        $fm = [];
        foreach (explode("\n", $yaml) as $linha) {
            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*:\s*(.+)$/', trim($linha), $kv)) {
                $val = trim($kv[2], " \t\"'");
                $fm[$kv[1]] = match (strtolower($val)) {
                    'true'  => true,
                    'false' => false,
                    'null'  => null,
                    default => is_numeric($val) ? (str_contains($val, '.') ? (float) $val : (int) $val) : $val,
                };
            }
        }

        return ['frontmatter' => $fm, 'body' => $body];
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
     * Best-effort: pega SHA do último commit que toca o arquivo.
     */
    protected function lerGitSha(string $relativePath): ?string
    {
        $cmd = sprintf(
            'git -C %s log -n 1 --format=%%H -- %s 2>/dev/null',
            escapeshellarg($this->repoBasePath),
            escapeshellarg($relativePath)
        );
        $sha = trim((string) @shell_exec($cmd));
        return $sha !== '' ? $sha : null;
    }
}
