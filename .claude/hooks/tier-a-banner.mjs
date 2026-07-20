#!/usr/bin/env node
// tier-a-banner.mjs — SessionStart (PORTE cross-plataforma do tier-a-banner.ps1).
// Imprime o banner das Skills Tier A (nucleo seguranca/disciplina) + auto-trigger.
// Ver ADR 0094 (Constituicao v2) + ADR 0095 (Skills tiers) + ADR 0225 (recalibracao 4.8).
//
// ── POR QUE .mjs (US-GOV-052) ─ o .ps1 so roda no Windows do [W]; no Mac/Linux (time MCP)
// o `powershell -File` evapora em silencio -> o time abre sessao sem o banner. Node roda em
// todo OS. Supersede tier-a-banner.ps1.
//
// ── DESIGN: Opcao A (estatico), DE-NUMERADO (adversario 2026-07-20) ──────────────────
// O gerador scripts/governance/skills-index-generate.mjs ASSERTA a PRESENCA de cada slug de
// nucleo neste arquivo (banner.includes(slug)) — nao o numero. Por isso mantemos os slugs
// LITERAIS (a catraca bite-6 do gerador barra se um slug de nucleo sumir do banner) e
// REMOVEMOS os contadores "5"/"6" (numero que outro sistema sabe melhor — o frontmatter das
// skills + o bloco AUTO:SKILLS do CLAUDE.md; proibicoes.md §5 2026-07-17 "nao restatear numero").
// Um port DERIVADO (ler frontmatter em runtime) foi pesado e recusado: adicionaria parse de
// arquivo a cada SessionStart num banner decorativo (fail-open fragil) e forcaria cirurgia no
// gerador — o ganho nao paga. O dono da lista viva segue sendo o gerador; este banner e uma
// lembranca estatica cujos unicos itens driftaveis (os slugs) o gerador guarda.
//
// Selftest: node .claude/hooks/tier-a-banner.mjs --selftest

import { spawnSync } from 'node:child_process';
import { pathToFileURL } from 'node:url';

// ASCII puro (igual ao .ps1 origem) — diff minimo no que o time ve; so os numeros saem.
export function banner() {
  return [
    '',
    '=== CONSTITUICAO v2 - SKILLS TIER A (nucleo) + AUTO-TRIGGER (ADR 0225) ===',
    '',
    '  TIER A (seguranca/LGPD/disciplina - sempre relevantes):',
    '  - multi-tenant-patterns      - business_id global scope Tier 0 IRREVOGAVEL',
    '  - commit-discipline          - 1 PR = 1 intent, <=300 linhas, sem PII',
    '  - incident-done-checklist    - DoD smoke real ANTES de declarar pronto (R1)',
    '  - memory-first-secret-search - consultar _INDEX-SECRETS ANTES de buscar token',
    '  - hostinger-dns-autonomy     - nao escalar acao automatizavel pro Wagner',
    '',
    '  AUTO-TRIGGER (Tier B - disparam por path/intencao, ADR 0225):',
    '  - brief-first               - brief-fetch (conveniencia inicio sessao)',
    '  - mcp-first                 - tools MCP antes de filesystem',
    '  - mwart-process / -comparative - dispara em Edit Pages/*.tsx',
    '  - charter-first             - dispara ao editar tsx com .charter.md',
    '  - preflight-modulo          - dispara em Edit Modules/<X>/ (+ hook + proibicoes)',
    '',
    '  PROTOCOLO WAGNER: doc memory/reference/PROTOCOLO-WAGNER-SEMPRE.md (on-demand)',
    '    R1 smoke real + R10 aprovacao humana = Tier 0 duro (memory/proibicoes.md)',
    '',
    '  DORMENTE: ads-route (ativa quando S5 entregar decide ~jul/2026)',
    '',
    '  ADRs canon: 0094 Constituicao v2 | 0095 Skills tiers | 0225 recalibracao 4.8 | 0104 MWART',
    '  Health: php artisan jana:health-check 5 checks SQL diarios',
    '',
    '=== INVIOLAVEL (Tier 0 sem ADR mae nova) ===',
    '  X business_id global scope ADR 0093',
    '  X Hostinger != CT 100 runtime ADR 0062',
    '  X ZERO auto-mem privada ADR 0061',
    '  X ADRs CANON sao append-only',
    '',
  ].join('\n');
}

function main() {
  try { process.stdout.write(banner() + '\n'); } catch { /* fail-open */ }
  process.exit(0);
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./tier-a-banner.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [test.pathname], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
