<?php

namespace Modules\Copiloto\Console\Commands;

use Illuminate\Console\Command;
use Modules\Copiloto\Entities\Mcp\McpToken;
use ZipArchive;

/**
 * Gera arquivo .dxt (Claude Desktop Extension) personalizado por membro do time.
 *
 * Uso:
 *   php artisan copiloto:mcp:generate-dxt --user-email=eliana@oimpresso.com.br
 *   php artisan copiloto:mcp:generate-dxt --all
 *
 * O .dxt gerado fica em storage/app/dxt/oimpresso-mcp-{nome}.dxt
 * Entregar via Vaultwarden — NUNCA por email ou slack público.
 */
class McpGenerateDxtCommand extends Command
{
    protected $signature = 'copiloto:mcp:generate-dxt
                            {--user-email= : Email do membro (gera 1 DXT)}
                            {--all         : Gera DXT para todos os membros com token ativo}
                            {--out=        : Pasta de saída (default: storage/app/dxt)}';

    protected $description = 'Gera arquivo .dxt (Claude Desktop Extension) com token MCP embutido';

    private string $mcpUrl;
    private string $bridgeJs;

    public function handle(): int
    {
        if (! class_exists(ZipArchive::class)) {
            $this->error('PHP ZipArchive não disponível. Instale a extensão php-zip.');
            return self::FAILURE;
        }

        $this->mcpUrl   = config('copiloto.mcp_url', 'https://mcp.oimpresso.com/api/mcp');
        $this->bridgeJs = $this->getBridgeJs();
        $outDir         = $this->option('out') ?: storage_path('app/dxt');

        if (! is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }

        if ($this->option('all')) {
            return $this->generateAll($outDir);
        }

        $email = $this->option('user-email');
        if (! $email) {
            $this->error('Informe --user-email=X ou use --all');
            return self::FAILURE;
        }

        return $this->generateForEmail($email, $outDir);
    }

    private function generateAll(string $outDir): int
    {
        $tokens = McpToken::with('user')
            ->whereNull('expires_at')
            ->orWhere('expires_at', '>', now())
            ->get();

        if ($tokens->isEmpty()) {
            $this->warn('Nenhum token ativo encontrado.');
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($tokens as $token) {
            $path = $this->buildDxt($token->user->name, $token->raw_token_hint ?? '', $outDir);
            if ($path) {
                $this->line("✅ {$token->user->name} → {$path}");
                $count++;
            }
        }

        $this->info("{$count} DXT(s) gerado(s) em {$outDir}");
        $this->warn('Entregue via Vaultwarden — NUNCA por email ou Slack público.');
        return self::SUCCESS;
    }

    private function generateForEmail(string $email, string $outDir): int
    {
        $user = \App\User::where('email', $email)->first();

        if (! $user) {
            $this->error("User com email {$email} não encontrado.");
            return self::FAILURE;
        }

        // Gera token novo raw para embutir no DXT
        $raw   = 'mcp_' . bin2hex(random_bytes(32));
        McpToken::create([
            'user_id'    => $user->id,
            'token_hash' => hash('sha256', $raw),
            'note'       => "DXT gerado via artisan — " . now()->toDateTimeString(),
            'expires_at' => null,
        ]);

        $path = $this->buildDxt($user->name, $raw, $outDir);

        if (! $path) {
            $this->error('Falha ao gerar o arquivo DXT.');
            return self::FAILURE;
        }

        $this->info("✅ DXT gerado: {$path}");
        $this->line("Token embutido: {$raw}");
        $this->warn('Entregue via Vaultwarden — o token aparece 1× só e foi gravado no DB.');
        return self::SUCCESS;
    }

    private function buildDxt(string $name, string $token, string $outDir): string|false
    {
        $slug     = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
        $fileName = "oimpresso-mcp-{$slug}.dxt";
        $filePath = $outDir . DIRECTORY_SEPARATOR . $fileName;

        $manifest = json_encode([
            'dxt_version'  => '0.1',
            'name'         => "oimpresso-mcp-{$slug}",
            'display_name' => "Oimpresso MCP - {$name}",
            'version'      => '1.0.0',
            'description'  => "Acesso MCP ao Oimpresso ERP - memória, ADRs, sessões, decisões. Token pessoal de {$name}.",
            'author'       => [
                'name'  => 'Oimpresso ERP',
                'email' => 'wagner@oimpresso.com',
                'url'   => 'https://oimpresso.com',
            ],
            'server' => [
                'type'        => 'node',
                'entry_point' => 'server/index.js',
                'mcp_config'  => [
                    'command' => 'node',
                    'args'    => ['${__dirname}/server/index.js'],
                    'env'     => [
                        'MCP_URL'           => $this->mcpUrl,
                        'MCP_AUTHORIZATION' => "Bearer {$token}",
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $zip = new ZipArchive();
        if ($zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $zip->addFromString('manifest.json', $manifest);
        $zip->addFromString('server/index.js', $this->bridgeJs);
        $zip->close();

        return $filePath;
    }

    private function getBridgeJs(): string
    {
        // Bridge idêntico ao scripts/generate-dxt.js (Node 18+ fetch nativo)
        return <<<'JS'
#!/usr/bin/env node
// Oimpresso MCP DXT — bridge stdio<=>HTTP nativo (Node 18+ fetch).
const fs = require('fs');
const path = require('path');
const os = require('os');

const LOG = path.join(os.tmpdir(), 'oimpresso-mcp-debug.log');
function log(msg) { try { fs.appendFileSync(LOG, `[${new Date().toISOString()}] ${msg}\n`); } catch {} }

const url  = process.env.MCP_URL;
const auth = process.env.MCP_AUTHORIZATION;

log('========== START ==========');
log(`platform=${process.platform} node=${process.version}`);
log(`url=${url || '<MISSING>'} auth=${auth ? '<SET>' : '<MISSING>'}`);

if (!url || !auth) { log('FATAL env'); process.exit(1); }
if (typeof fetch !== 'function') { log('FATAL: fetch ausente — Node < 18'); process.exit(1); }

let sessionId = null;
let buffer = '';

async function postOne(line) {
  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json, text/event-stream',
    'Authorization': auth,
  };
  if (sessionId) headers['Mcp-Session-Id'] = sessionId;
  let res;
  try {
    res = await fetch(url, { method: 'POST', headers, body: line });
  } catch (e) {
    log(`fetch error: ${e.message}`);
    try {
      const msg = JSON.parse(line);
      if (msg.id !== undefined) {
        process.stdout.write(JSON.stringify({ jsonrpc: '2.0', id: msg.id, error: { code: -32603, message: 'Bridge fetch error: ' + e.message } }) + '\n');
      }
    } catch {}
    return;
  }
  const newSession = res.headers.get('mcp-session-id');
  if (newSession && newSession !== sessionId) { sessionId = newSession; log(`session=${sessionId}`); }
  if (res.status === 202 || res.status === 204) { log(`-> ${res.status} (no body)`); return; }
  const ct = (res.headers.get('content-type') || '').toLowerCase();
  if (ct.includes('text/event-stream') && res.body) {
    const reader = res.body.getReader();
    const decoder = new TextDecoder('utf-8');
    let sseBuf = '';
    try {
      while (true) {
        const { value, done } = await reader.read();
        if (done) break;
        sseBuf += decoder.decode(value, { stream: true });
        let evEnd;
        while ((evEnd = sseBuf.indexOf('\n\n')) >= 0) {
          const ev = sseBuf.slice(0, evEnd);
          sseBuf = sseBuf.slice(evEnd + 2);
          for (const evLine of ev.split('\n')) {
            if (evLine.startsWith('data:')) { const data = evLine.slice(5).trim(); if (data) process.stdout.write(data + '\n'); }
          }
        }
      }
    } catch (e) { log(`SSE read error: ${e.message}`); }
  } else {
    const text = await res.text();
    if (text) process.stdout.write(text.endsWith('\n') ? text : text + '\n');
  }
}

process.stdin.setEncoding('utf-8');
process.stdin.on('data', (chunk) => {
  buffer += chunk;
  let nl;
  while ((nl = buffer.indexOf('\n')) >= 0) {
    const line = buffer.slice(0, nl).replace(/\r$/, '').trim();
    buffer = buffer.slice(nl + 1);
    if (!line) continue;
    postOne(line).catch((e) => log(`postOne uncaught: ${e.message}`));
  }
});
process.stdin.on('end', () => { log('stdin ended'); process.exit(0); });
process.stdin.on('error', (e) => { log(`stdin error: ${e.message}`); process.exit(1); });
log('bridge listening');
JS;
    }
}
