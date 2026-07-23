<?php

namespace Modules\ADS\Tools;

use Modules\ADS\Contracts\Tool;

/**
 * Tool de ESCRITA — paranóica em segurança.
 *
 * Múltiplas camadas de defesa:
 *   1. Path whitelist hardcoded (Modules/, resources/js/Pages/, memory/)
 *   2. Path blacklist (.env, config/, app/Console/Kernel.php, vendor/, .git/)
 *   3. Extensão allowlist (.php, .tsx, .ts, .md, .json, .blade.php, .css)
 *   4. Tamanho máximo 50KB por escrita
 *   5. Refuse to overwrite arquivos com size > 100KB (legacy intocável)
 *   6. Refuse to write absolute paths fora de base_path()
 *   7. Refuse to write paths com .. (path traversal)
 *
 * Wagner aprova ANTES da execução via UI. Tool não executa direto pelo agente.
 */
class WriteFileTool implements Tool
{
    public function name(): string { return 'write_file'; }
    public function category(): string { return 'escrita'; }
    public function isReadOnly(): bool { return false; }

    /** Diretórios permitidos (relativos a base_path) */
    private const ALLOWED_PREFIXES = [
        'Modules/',
        'resources/js/Pages/',
        'resources/js/Components/',
        'memory/sessions/',
        'memory/requisitos/',
        'memory/decisions/',
        'memory/research/comparativos/',
        'lang/',
    ];

    /** Caminhos NUNCA escritos — mesmo dentro dos prefixos permitidos */
    private const BLOCKED_PATHS = [
        '.env',
        'config/',
        'app/Console/Kernel.php',
        'app/Http/Kernel.php',
        'app/Exceptions/Handler.php',
        'bootstrap/app.php',
        'vendor/',
        '.git/',
        'storage/logs/',
        'public/index.php',
    ];

    /** Extensões permitidas */
    private const ALLOWED_EXTENSIONS = [
        'php', 'tsx', 'ts', 'jsx', 'js', 'md', 'json', 'css', 'yml', 'yaml', 'txt',
    ];

    private const MAX_BYTES = 50 * 1024;          // 50KB por escrita
    private const MAX_OVERWRITE_BYTES = 100 * 1024; // não sobrescreve arquivo > 100KB

    public function description(): string
    {
        return 'Escreve conteúdo em arquivo dentro do whitelist (Modules/, resources/js/Pages/, '
             . 'resources/js/Components/, memory/, lang/). Path traversal, .env, config/, vendor/, '
             . '.git/ bloqueados. Máx 50KB por escrita. Wagner aprova antes da execução.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path'    => ['type' => 'string', 'description' => 'Path relativo a base_path()'],
                'content' => ['type' => 'string', 'description' => 'Conteúdo completo do arquivo'],
                'mode'    => ['type' => 'string', 'enum' => ['create', 'overwrite'], 'default' => 'create'],
            ],
            'required' => ['path', 'content'],
        ];
    }

    public function execute(array $input): array
    {
        $path = $input['path'] ?? '';
        $content = $input['content'] ?? '';
        $mode = $input['mode'] ?? 'create';

        // Validação 0: per-user scope (camada NOVA — caso Maiara)
        $userId = $input['_user_id'] ?? auth()->id();
        if ($userId !== null) {
            $scope = app(\Modules\ADS\Services\UserScopeService::class);
            if (! $scope->canWriteToPath((int) $userId, $path)) {
                return [
                    'ok'     => false,
                    'output' => null,
                    'error'  => 'user_scope_denied',
                    'message' => "User #{$userId} sem permissão de escrita no módulo extraído de '{$path}'. Wagner precisa autorizar via /ads/admin/team-scopes.",
                ];
            }
        }

        // Validação 1: path obrigatório
        if (empty($path)) {
            return ['ok' => false, 'output' => null, 'error' => 'path_required'];
        }

        // Validação 2: sem path traversal
        if (str_contains($path, '..')) {
            return ['ok' => false, 'output' => null, 'error' => 'path_traversal_blocked'];
        }

        // Validação 3: path relativo (não absoluto)
        if (str_starts_with($path, '/') || preg_match('/^[A-Z]:[\\\\\\/]/i', $path)) {
            return ['ok' => false, 'output' => null, 'error' => 'absolute_path_blocked'];
        }

        // Normaliza separadores
        $path = str_replace('\\', '/', $path);

        // Validação 4: prefixo permitido
        $prefixOk = false;
        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $prefixOk = true;
                break;
            }
        }
        if (! $prefixOk) {
            return [
                'ok'     => false,
                'output' => null,
                'error'  => 'path_not_in_whitelist',
                'message' => 'Permitido apenas: ' . implode(', ', self::ALLOWED_PREFIXES),
            ];
        }

        // Validação 5: path bloqueado
        foreach (self::BLOCKED_PATHS as $blocked) {
            if (str_starts_with($path, $blocked) || $path === rtrim($blocked, '/')) {
                return ['ok' => false, 'output' => null, 'error' => 'path_in_blocklist', 'message' => "Bloqueado: {$blocked}"];
            }
        }

        // Validação 6: extensão permitida (com tratamento .blade.php)
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if (str_ends_with($path, '.blade.php')) {
            $ext = 'php';
        }
        if (! in_array(strtolower($ext), self::ALLOWED_EXTENSIONS, true)) {
            return [
                'ok'     => false,
                'output' => null,
                'error'  => 'extension_not_allowed',
                'message' => 'Permitido: ' . implode(', ', self::ALLOWED_EXTENSIONS),
            ];
        }

        // Validação 7: tamanho do conteúdo
        $contentBytes = strlen($content);
        if ($contentBytes > self::MAX_BYTES) {
            return [
                'ok' => false, 'output' => null,
                'error' => 'content_too_large',
                'message' => sprintf('Máx %d bytes, recebido %d', self::MAX_BYTES, $contentBytes),
            ];
        }

        $absolutePath = base_path($path);

        // Validação 8: arquivo existe? respeita modo
        if (file_exists($absolutePath)) {
            if ($mode === 'create') {
                return ['ok' => false, 'output' => null, 'error' => 'file_exists_use_overwrite_mode'];
            }
            $existingSize = filesize($absolutePath);
            if ($existingSize > self::MAX_OVERWRITE_BYTES) {
                return [
                    'ok' => false, 'output' => null,
                    'error' => 'existing_file_too_large_to_overwrite',
                    'message' => "Arquivo {$existingSize} bytes > limite {$this->bytesToHuman(self::MAX_OVERWRITE_BYTES)}",
                ];
            }
        }

        // Validação 9: cria diretório pai se não existe (apenas dentro do whitelist)
        $dir = dirname($absolutePath);
        if (! is_dir($dir)) {
            if (! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
                return ['ok' => false, 'output' => null, 'error' => 'cannot_create_directory'];
            }
        }

        // Execução real
        $bytesWritten = @file_put_contents($absolutePath, $content);
        if ($bytesWritten === false) {
            return ['ok' => false, 'output' => null, 'error' => 'write_failed'];
        }

        return [
            'ok' => true,
            'output' => [
                'path'         => $path,
                'absolute'     => $absolutePath,
                'bytes_written' => $bytesWritten,
                'mode'         => $mode,
                'created'      => $mode === 'create',
                'overwritten'  => $mode === 'overwrite',
            ],
            'error' => null,
        ];
    }

    private function bytesToHuman(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / (1024 * 1024), 1) . ' MB';
    }
}
