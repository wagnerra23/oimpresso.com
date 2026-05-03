/**
 * Brain A Daemon — System 1 do Dual Brain (ARQ-0002).
 *
 * Topologia (ARQ-0011): roda em CT 100 Proxmox; consome eventos do app Hostinger
 * via HTTP poll (RemoteGitWatcher + RemoteLogWatcher). Em modo dev local, pode
 * usar watchers v1 que leem filesystem (LOCAL_MODE=true).
 *
 * Fluxo:
 *   poll /api/ads/recent-commits → triage (Ollama|regex) → POST /api/ads/route
 *   poll /api/ads/recent-errors  → triage (Ollama|regex) → POST /api/ads/route
 *
 * Custo: ~$0/mês em Brain A (Ollama ou regex). Brain B é processado via cron
 * Hostinger (php artisan ads:process-brain-b), não por este daemon.
 */

import 'dotenv/config';
import { AdsClient } from './ads-client.js';
import { OllamaClient } from './ollama-client.js';
import { RemoteGitWatcher } from './watchers/remote-git-watcher.js';
import { RemoteLogWatcher } from './watchers/remote-log-watcher.js';
import { GitWatcher } from './watchers/git-watcher.js';
import { LogWatcher } from './watchers/log-watcher.js';
import { triageCommit, triageLogEntry } from './triage.js';

const env = (k, def) => process.env[k] ?? def;

async function main() {
  const apiBase = (env('ADS_API_URL') || '').replace(/\/api\/ads\/route$/, '/api/ads');
  const apiKey  = env('ADS_API_KEY');
  const businessId = parseInt(env('DEFAULT_BUSINESS_ID', '1'), 10);
  const localMode  = env('LOCAL_MODE') === 'true';

  const client = new AdsClient({
    apiUrl:           env('ADS_API_URL'),
    apiKey,
    healthUrl:        env('ADS_HEALTH_URL'),
    allowInsecureTls: env('ALLOW_INSECURE_TLS') === 'true',
  });

  // Ollama opcional (mesma lógica de antes)
  let ollama = null;
  if (env('OLLAMA_HOST')) {
    const probe = new OllamaClient({
      host:  env('OLLAMA_HOST'),
      model: env('OLLAMA_MODEL', 'qwen2.5-coder:14b'),
    });
    const oh = await probe.health();
    if (oh.ok) {
      ollama = probe;
      console.log(`[boot] Ollama OK em ${env('OLLAMA_HOST')} (modelo: ${env('OLLAMA_MODEL', 'qwen2.5-coder:14b')})`);
    } else {
      console.warn(`[boot] Ollama indisponível (${oh.reason}) — usando triage rule-based`);
    }
  } else {
    console.log('[boot] OLLAMA_HOST não setado — usando triage rule-based');
  }

  async function classifyCommit({ subject, files }) {
    if (ollama) {
      const cls = await ollama.classify({ kind: 'commit', content: `${subject}\n\nfiles: ${files.slice(0,5).join(', ')}` });
      if (cls) return cls;
    }
    return triageCommit({ subject, files });
  }
  async function classifyLog({ line }) {
    if (ollama) {
      const cls = await ollama.classify({ kind: 'log', content: line });
      if (cls) return cls;
    }
    return triageLogEntry({ line });
  }

  // Health check inicial
  const h = await client.health();
  if (!h.ok) {
    console.error('[boot] ADS health check falhou:', h);
    console.error('       Verifique: ADS_API_URL/HEALTH_URL corretos? token válido?');
    process.exit(1);
  }
  console.log('[boot] ADS health OK');

  // Handlers compartilhados pelos dois modos (local e remoto)
  const onCommit = async ({ sha, subject, files }) => {
    const { eventType, domain } = await classifyCommit({ subject, files });
    console.log(`[git] ${sha.slice(0,8)} "${subject.slice(0,60)}" → ${eventType} (${domain})`);
    try {
      const decision = await client.route({
        eventType, domain, eventSource: 'brain_a',
        businessId, filesAffected: files.slice(0, 20),
        metadata: { sha, subject, source: localMode ? 'git_watcher' : 'remote_git_watcher' },
      });
      console.log(`[ads] decision=${decision.destination} risk=${decision.risk_score} conf=${decision.confidence_score}`);
    } catch (e) {
      console.error('[ads] route falhou:', e.message);
    }
  };

  const onError = async ({ line }) => {
    const t = await classifyLog({ line });
    if (!t) return;
    console.log(`[log] ERROR detectado → ${t.eventType} (${t.domain})`);
    try {
      const decision = await client.route({
        eventType: t.eventType, domain: t.domain, eventSource: 'brain_a',
        businessId, metadata: { line: line.slice(0, 300), source: localMode ? 'log_watcher' : 'remote_log_watcher' },
      });
      console.log(`[ads] decision=${decision.destination} risk=${decision.risk_score}`);
    } catch (e) {
      console.error('[ads] route falhou:', e.message);
    }
  };

  let gitWatcher, logWatcher;

  if (localMode) {
    console.log('[boot] LOCAL_MODE=true — usando watchers v1 (filesystem)');
    gitWatcher = new GitWatcher({
      repoPath:    env('REPO_PATH'),
      intervalMs:  parseInt(env('GIT_POLL_INTERVAL_MS', '30000'), 10),
      onCommit,
    });
    logWatcher = new LogWatcher({
      logPath:    env('LARAVEL_LOG_PATH'),
      intervalMs: parseInt(env('LOG_POLL_INTERVAL_MS', '5000'), 10),
      onError,
    });
  } else {
    console.log('[boot] modo remoto (default) — watchers v2 via HTTP poll');
    gitWatcher = new RemoteGitWatcher({
      apiUrl:    `${apiBase}/recent-commits`,
      apiKey,
      intervalMs: parseInt(env('GIT_POLL_INTERVAL_MS', '30000'), 10),
      onCommit,
    });
    logWatcher = new RemoteLogWatcher({
      apiUrl:    `${apiBase}/recent-errors`,
      apiKey,
      intervalMs: parseInt(env('LOG_POLL_INTERVAL_MS', '5000'), 10),
      onError,
    });
  }

  await gitWatcher.start();
  await logWatcher.start();

  console.log('[boot] Brain A operacional. Ctrl+C para parar.');

  process.on('SIGINT', () => {
    console.log('\n[shutdown] parando watchers…');
    gitWatcher.stop();
    logWatcher.stop();
    process.exit(0);
  });
}

main().catch(e => {
  console.error('[fatal]', e);
  process.exit(1);
});
