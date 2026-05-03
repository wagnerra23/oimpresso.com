/**
 * Brain A Daemon — System 1 do Dual Brain (ARQ-0002).
 *
 * Responsabilidades:
 *   - Monitorar git log e laravel.log em loop contínuo
 *   - Triage rule-based de cada evento → event_type canônico
 *   - Submeter ao Decision Router via POST /api/ads/route
 *
 * Custo: ~$0/mês (sem LLM nesta v1; Ollama vem na v2)
 * Disponibilidade: 24/7 enquanto a máquina estiver ligada
 */

import 'dotenv/config';
import { AdsClient } from './ads-client.js';
import { OllamaClient } from './ollama-client.js';
import { GitWatcher } from './watchers/git-watcher.js';
import { LogWatcher } from './watchers/log-watcher.js';
import { triageCommit, triageLogEntry } from './triage.js';

const env = (k, def) => process.env[k] ?? def;

async function main() {
  const client = new AdsClient({
    apiUrl:           env('ADS_API_URL'),
    apiKey:           env('ADS_API_KEY'),
    healthUrl:        env('ADS_HEALTH_URL'),
    allowInsecureTls: env('ALLOW_INSECURE_TLS') === 'true',
  });

  const businessId = parseInt(env('DEFAULT_BUSINESS_ID', '1'), 10);

  // Ollama opcional — se OLLAMA_HOST setado E health OK, usa LLM; senão fallback rule-based.
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

  // Triage híbrido: tenta Ollama, fallback para regex
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
    console.error('       Verifique: server Laravel rodando? ADS_API_URL correto?');
    process.exit(1);
  }
  console.log('[boot] ADS health OK');

  const gitWatcher = new GitWatcher({
    repoPath:    env('REPO_PATH'),
    intervalMs:  parseInt(env('GIT_POLL_INTERVAL_MS', '30000'), 10),
    onCommit: async ({ sha, subject, files }) => {
      const { eventType, domain } = await classifyCommit({ subject, files });
      console.log(`[git] ${sha.slice(0,8)} "${subject.slice(0,60)}" → ${eventType} (${domain})`);
      try {
        const decision = await client.route({
          eventType, domain, eventSource: 'brain_a',
          businessId, filesAffected: files.slice(0, 20),
          metadata: { sha, subject, source: 'git_watcher' },
        });
        console.log(`[ads] decision=${decision.destination} risk=${decision.risk_score} conf=${decision.confidence_score}`);
      } catch (e) {
        console.error('[ads] route falhou:', e.message);
      }
    },
  });

  const logWatcher = new LogWatcher({
    logPath:    env('LARAVEL_LOG_PATH'),
    intervalMs: parseInt(env('LOG_POLL_INTERVAL_MS', '5000'), 10),
    onError: async ({ line }) => {
      const t = await classifyLog({ line });
      if (!t) return;
      console.log(`[log] ERROR detectado → ${t.eventType} (${t.domain})`);
      try {
        const decision = await client.route({
          eventType: t.eventType, domain: t.domain, eventSource: 'brain_a',
          businessId, metadata: { line: line.slice(0, 300), source: 'log_watcher' },
        });
        console.log(`[ads] decision=${decision.destination} risk=${decision.risk_score}`);
      } catch (e) {
        console.error('[ads] route falhou:', e.message);
      }
    },
  });

  await gitWatcher.start();
  logWatcher.start();

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
