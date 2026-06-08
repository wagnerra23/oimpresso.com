/**
 * Smoke test — valida endpoint POST /api/ads/route end-to-end sem watchers.
 * Submete 3 eventos sintéticos e imprime resposta.
 */

import 'dotenv/config';
import { AdsClient } from './ads-client.js';

const client = new AdsClient({
  apiUrl:           process.env.ADS_API_URL,
  apiKey:           process.env.ADS_API_KEY,
  healthUrl:        process.env.ADS_HEALTH_URL,
  allowInsecureTls: process.env.ALLOW_INSECURE_TLS === 'true',
});

const businessId = parseInt(process.env.DEFAULT_BUSINESS_ID || '1', 10);

const cases = [
  { eventType: 'lang_file_pt_br',  domain: 'Copiloto', desc: 'Trivial — esperado brain_b (conf inicial 0.5)' },
  { eventType: 'env_production',   domain: 'Infra',    desc: 'Crítico — esperado blocked' },
  { eventType: 'db_schema_change', domain: 'NFSe',     desc: 'Médio — esperado brain_b' },
];

console.log('Health:', await client.health());

for (const c of cases) {
  try {
    const r = await client.route({
      eventType:    c.eventType,
      domain:       c.domain,
      eventSource:  'wagner',
      businessId,
      metadata:     { source: 'smoke_test', desc: c.desc },
    });
    console.log(`✓ ${c.eventType.padEnd(25)} → ${r.destination.padEnd(15)} risk=${r.risk_score} conf=${r.confidence_score} policy=${r.policy_applied}`);
  } catch (e) {
    console.error(`✗ ${c.eventType}: ${e.message}`);
  }
}
