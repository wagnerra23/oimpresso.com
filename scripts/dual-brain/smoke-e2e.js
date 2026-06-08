/**
 * Smoke e2e — força watcher a "ver" os 2 últimos commits como novos
 * e verifica que o ciclo completo (HTTP poll → triage → POST /api/ads/route)
 * grava decisions em mcp_dual_brain_decisions.
 */

import 'dotenv/config';
import { execSync } from 'node:child_process';
import { AdsClient } from './ads-client.js';
import { RemoteGitWatcher } from './watchers/remote-git-watcher.js';
import { triageCommit } from './triage.js';

const apiBase = (process.env.ADS_API_URL || '').replace(/\/api\/ads\/route$/, '/api/ads');
const apiKey  = process.env.ADS_API_KEY;
if (process.env.ALLOW_INSECURE_TLS === 'true') {
  process.env.NODE_TLS_REJECT_UNAUTHORIZED = '0';
}

const businessId = parseInt(process.env.DEFAULT_BUSINESS_ID || '1', 10);
const repoPath = process.env.REPO_PATH || 'D:/oimpresso.com';

// Pega o SHA 3 commits atrás como ponto de partida
const lastSha = execSync('git rev-parse HEAD~3', { cwd: repoPath, encoding: 'utf-8' }).trim();
console.log(`Forçando lastSha = HEAD~3 = ${lastSha.slice(0, 8)}`);

const client = new AdsClient({
  apiUrl:           process.env.ADS_API_URL,
  apiKey,
  healthUrl:        process.env.ADS_HEALTH_URL,
  allowInsecureTls: process.env.ALLOW_INSECURE_TLS === 'true',
});

let detected = 0;
let routed = 0;

const watcher = new RemoteGitWatcher({
  apiUrl:    `${apiBase}/recent-commits`,
  apiKey,
  intervalMs: 1000,
  onCommit: async ({ sha, subject, files }) => {
    detected++;
    const { eventType, domain } = triageCommit({ subject, files });
    console.log(`  detectado: ${sha.slice(0,8)} "${subject.slice(0,50)}" → ${eventType} (${domain})`);

    try {
      const decision = await client.route({
        eventType, domain, eventSource: 'brain_a',
        businessId, filesAffected: files.slice(0, 10),
        metadata: { sha, subject, source: 'smoke_e2e' },
      });
      routed++;
      console.log(`    → decision #${decision.decision_id} dest=${decision.destination} risk=${decision.risk_score}`);
    } catch (e) {
      console.error('    ✗ route falhou:', e.message);
    }
  },
});

// Inicializa watcher manualmente, sobrescreve lastSha, força tick
await watcher.start();
watcher.lastSha = lastSha;
console.log('Forçando tick...');
await watcher.tick();

console.log(`\nResultado: ${detected} commits detectados, ${routed} roteados.`);
console.log(routed >= 2 ? '✓ E2E completo: HTTP poll → triage → POST /route → grava decision' : '✗ Falha no ciclo e2e');

watcher.stop();
process.exit(routed >= 2 ? 0 : 1);
