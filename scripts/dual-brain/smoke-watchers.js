/**
 * Smoke test — valida watchers v2 (HTTP poll) end-to-end.
 * Não chama POST /api/ads/route — só verifica que GET endpoints respondem.
 */

import 'dotenv/config';
import { RemoteGitWatcher } from './watchers/remote-git-watcher.js';
import { RemoteLogWatcher } from './watchers/remote-log-watcher.js';

const apiBase = (process.env.ADS_API_URL || '').replace(/\/api\/ads\/route$/, '/api/ads');
const apiKey  = process.env.ADS_API_KEY;
if (process.env.ALLOW_INSECURE_TLS === 'true') {
  process.env.NODE_TLS_REJECT_UNAUTHORIZED = '0';
}

console.log(`apiBase=${apiBase}`);

let commitCount = 0;
const git = new RemoteGitWatcher({
  apiUrl:    `${apiBase}/recent-commits`,
  apiKey,
  intervalMs: 1000,
  onCommit: async ({ sha, subject }) => {
    commitCount++;
    console.log(`  [git] ${sha.slice(0,8)} "${subject.slice(0,50)}"`);
  },
});

let errorCount = 0;
const log = new RemoteLogWatcher({
  apiUrl:    `${apiBase}/recent-errors`,
  apiKey,
  intervalMs: 1000,
  onError: async ({ line }) => {
    errorCount++;
    console.log(`  [log] ${line.slice(0,80)}…`);
  },
});

console.log('Inicializando watchers...');
await git.start();
await log.start();

console.log('Aguardando 3s para 1 tick (esperado: 0 eventos novos)...');
await new Promise(r => setTimeout(r, 3500));

console.log(`\nResultado: commits=${commitCount} errors=${errorCount}`);
console.log(commitCount === 0 && errorCount === 0
  ? '✓ Watchers OK (não reprocessaram histórico)'
  : '⚠ Watchers detectaram eventos durante boot — investigar offset');

git.stop();
log.stop();
process.exit(0);
