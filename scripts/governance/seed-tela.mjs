#!/usr/bin/env node
// seed-tela.mjs — EMPACOTADOR DE SEED (G1 do padrão "1 clique → sessão limpa por tela").
// Dado <Mod>/<Tela>, emite a "ordem de serviço" que seeda uma SESSÃO LIMPA isolada
// (cole num Agent(isolation:"worktree") ou numa sessão nova): charter + GAP-SPEC +
// visual_source + regras Tier 0 + skills que auto-disparam. Não arrasta contexto de
// outras telas (economia de token + fidelidade). Pareado com a skill `aplicar-prototipo`
// Fase 4 + ADR-proposta 2026-06-23-prototipo-ssot-unico-com-historico.
//
// Uso:
//   node scripts/governance/seed-tela.mjs Atendimento/CaixaUnificada
//   node scripts/governance/seed-tela.mjs resources/js/Pages/Cliente/Index.charter.md
//   node scripts/governance/seed-tela.mjs <alvo> --json
import { readFileSync, existsSync, readdirSync } from 'node:fs';
import { join, relative } from 'node:path';

const ROOT = process.cwd();
const arg = process.argv[2];
const asJson = process.argv.includes('--json');
if (!arg || arg.startsWith('--')) { console.error('uso: node scripts/governance/seed-tela.mjs <Mod>/<Tela> [--json]'); process.exit(2); }

// --- resolve o charter ---
function firstExisting(cands) { return cands.map(c => join(ROOT, c)).find(existsSync); }
const charterPath = firstExisting([
  arg,
  `resources/js/Pages/${arg}.charter.md`,
  `resources/js/Pages/${arg}/Index.charter.md`,
]);
if (!charterPath) { console.error(`✗ charter não encontrado pra "${arg}". Tente o caminho .charter.md direto, ou rode charter-write antes (não invente dados).`); process.exit(1); }
const relCharter = relative(ROOT, charterPath).replace(/\\/g, '/');
const txt = readFileSync(charterPath, 'utf8');

// --- parse frontmatter (line-based, sem deps) ---
const fm = (txt.match(/^---\n([\s\S]*?)\n---/) || [, ''])[1];
const field = (k) => { const m = fm.match(new RegExp(`^${k}:\\s*(.+)$`, 'm')); return m ? m[1].trim().replace(/^["']|["']$/g, '') : null; };
const component = field('component') || '(?)';
const visualSource = field('visual_source');
const visualSourceSha = field('visual_source_sha');
const parentModule = field('parent_module') || (relCharter.split('/')[3] || '?');
const status = field('status') || '?';
const permissao = field('permissao');
const relatedAdrsRaw = (fm.match(/^related_adrs:\s*(.+)$/m) || [])[1] || '';

// --- acha o GAP-SPEC (best-effort por nome normalizado) ---
const norm = (s) => s.toLowerCase().replace(/[^a-z0-9]/g, '');
const telaSlug = norm(arg.split('/').pop().replace(/\.charter\.md$/, ''));
function findGaps() {
  const base = join(ROOT, 'memory/requisitos');
  const hits = [];
  const walk = (d) => { if (!existsSync(d)) return; for (const e of readdirSync(d, { withFileTypes: true })) { const p = join(d, e.name); if (e.isDirectory()) walk(p); else if (/-gap\.md$/.test(e.name)) hits.push(relative(ROOT, p).replace(/\\/g, '/')); } };
  walk(base);
  return hits;
}
const gaps = findGaps();
const gap = gaps.find(g => norm(g).includes(telaSlug)) || null;

const skills = ['charter-first', 'preflight-modulo', 'multi-tenant-patterns', 'mwart-process', 'cowork-prototype-replication'];
const instrucao = `Aplica o GAP-SPEC na tela viva ${component}, parte por parte, seguindo o charter + Tier 0. Para no SCREENSHOT pro Wagner aprovar. Não inventa; gap incerto = pergunta.`;

if (asJson) {
  console.log(JSON.stringify({ tela: arg, component, charter: relCharter, visual_source: visualSource, visual_source_sha: visualSourceSha, gap, parent_module: parentModule, status, permissao, related_adrs: relatedAdrsRaw, skills, instrucao }, null, 2));
  process.exit(0);
}

const L = [];
L.push(`# SEED — ${arg}  ·  sessão limpa (1 ordem de serviço)`);
L.push(`> Cole numa sessão NOVA / \`Agent(isolation:"worktree")\`. Carrega SÓ esta tela — não arraste outras.`);
L.push('');
L.push('## Tela');
L.push(`- **Page (alvo vivo):** \`${component}\``);
L.push(`- **Charter (LER 1º — contrato+dados+decisões · charter-first Tier A):** \`${relCharter}\``);
L.push(`- **Fonte visual (build no cowork/):** ${visualSource ? '`' + visualSource + '`' : '_sem visual_source_'}${visualSourceSha ? ` @ \`${visualSourceSha.slice(0, 12)}\`` : ' _(sem sha — drift não rastreável; ver B1)_'}`);
L.push(`- **GAP-SPEC:** ${gap ? '`' + gap + '`' : '_pendente_ (rode a Fase 1 / mapeie antes; não invente)'}`);
L.push(`- status: ${status}${permissao ? ` · permissão: \`${permissao}\`` : ''}`);
L.push('');
L.push('## Regras (Tier 0 — irrevogáveis)');
L.push('- **Multi-tenant** `business_id` global scope (ADR 0093) — nunca vazar entre tenants.');
L.push('- **Estrutura SSOT:** `cowork/` = build-only; memória = canon (memory/ + MCP). Contrato: `prototipo-ui/COWORK-ESTRUTURA-E-TELAS.md`.');
L.push('- **Não inventar:** caminho/feature/dado incerto = `_pendente_`/pergunta (LICOES_F3).');
if (relatedAdrsRaw) L.push(`- **ADRs da tela:** ${relatedAdrsRaw}`);
L.push('');
L.push('## Skills (auto-disparam por path/intenção)');
L.push('- ' + skills.join(' · '));
L.push('');
L.push('## Ordem de serviço');
L.push(`> ${instrucao}`);
console.log(L.join('\n'));
