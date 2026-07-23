#!/usr/bin/env node
// mapa-artefatos.mjs — gera a seção "máquinas por artefato" do MAPA-DE-ARTEFATOS.md
// DERIVA do governance/required-checks-baseline.json (fonte-única de "o que é required").
// Assim a tabela de máquinas NUNCA é escrita à mão — regenera quando um gate é
// promovido/demovido no baseline (ADR 0256: derivado+enforçado sobrevive; ADR 0345:
// ponteiro, não cópia). O catálogo máquina→artefato é semente curada (revisável no diff),
// no espírito do CORE_APP_MODULES do module-surface.mjs.
//
// Uso:
//   node scripts/governance/mapa-artefatos.mjs            # imprime a seção
//   node scripts/governance/mapa-artefatos.mjs --write    # grava no doc entre os marcadores
//   node scripts/governance/mapa-artefatos.mjs --check    # exit 1 se o doc está desatualizado

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..', '..');
const BASELINE = path.join(ROOT, 'governance', 'required-checks-baseline.json');
const DOC = path.join(ROOT, 'memory', 'requisitos', '_Governanca', 'MAPA-DE-ARTEFATOS.md');
const START = '<!-- MAQUINAS:INICIO (gerado por mapa-artefatos.mjs — NAO editar a mao) -->';
const END = '<!-- MAQUINAS:FIM -->';

// Catálogo curado: cada linha liga uma MÁQUINA ao ARTEFATO que ela toca.
// `contexto` = nome EXATO do job no branch protection (quando a máquina tem gate de CI).
//   null = não tem contexto de CI próprio (advisory local, ou step interno de outro job).
// O gerador cruza `contexto` com o baseline pra decidir required vs advisory — SEM hardcode.
const CATALOGO = [
  // Camada MÓDULO
  { artefato: 'SCOPE.md', maquina: 'scope-guard (bin/check-scope.php)', tipo: 'valida', contexto: null, comando: 'php bin/check-scope.php --strict' },
  { artefato: 'SUPERFICIE.md', maquina: 'module-surface.mjs', tipo: 'GERA + frescor', contexto: null, comando: 'node scripts/governance/module-surface.mjs <Mod> --write · --all --check' },
  { artefato: 'BRIEFING.md', maquina: 'briefing-code-staleness (cobertura)', tipo: 'valida existência', contexto: 'Modulo backend com BRIEFING (cobertura)', comando: 'node scripts/governance/briefing-code-staleness.mjs --strict-coverage' },
  { artefato: 'BRIEFING.md', maquina: 'briefing-code-staleness (frescor)', tipo: 'frescor', contexto: null, comando: 'node scripts/governance/briefing-code-staleness.mjs --strict' },
  { artefato: 'BRIEFING.md', maquina: 'memory-schema (briefing.schema.json)', tipo: 'valida forma', contexto: null, comando: 'bash scripts/validate-memory-schema.sh briefing' },
  { artefato: 'SPEC.md', maquina: 'memory-schema (spec.schema.json)', tipo: 'valida forma', contexto: 'SPEC (memory/requisitos/*/SPEC.md)', comando: 'bash scripts/validate-memory-schema.sh spec' },
  { artefato: 'SPEC.md', maquina: 'anchor-lint.mjs', tipo: 'valida âncora spec↔código', contexto: 'anchor-lint ADR 0273', comando: 'node scripts/governance/anchor-lint.mjs --check <SPEC>' },
  { artefato: 'SPEC.md', maquina: 'anchor entry/covers', tipo: 'valida âncora', contexto: 'anchor entry/covers gate', comando: 'node scripts/governance/anchor-lint.mjs --json' },
  { artefato: 'SPEC.md', maquina: 'doneness-lint.mjs', tipo: 'valida status×âncora', contexto: 'doneness-lint ADR 0302', comando: 'node scripts/governance/doneness-lint.mjs --check <SPEC>' },
  // Camada TELA
  { artefato: 'charter', maquina: 'memory-schema (charter.schema.json)', tipo: 'valida forma', contexto: 'Charter (resources/js/Pages/**/*.charter.md)', comando: 'bash scripts/validate-memory-schema.sh charter' },
  { artefato: 'charter', maquina: 'charter-live-signal.mjs', tipo: 'valida honestidade', contexto: 'charter status:live precisa de sinal de prod', comando: 'node scripts/governance/charter-live-signal.mjs --check <charter>' },
  { artefato: 'charter', maquina: 'anchor-content-check.mjs', tipo: 'valida âncora de design', contexto: 'Ancora de design nao-shell (F2/F6 required)', comando: 'node scripts/governance/anchor-content-check.mjs --check' },
  { artefato: 'charter', maquina: 'charter-refs.mjs', tipo: 'valida refs', contexto: null, comando: 'node scripts/governance/charter-refs.mjs --check' },
  { artefato: 'casos', maquina: 'casos-coverage-guard.mjs', tipo: 'valida trio + UC↔teste + frescor', contexto: 'Casos-coverage · ratchet (trio + rastreabilidade)', comando: 'npm run casos:check · npm run casos:report' },
  { artefato: 'scorecard', maquina: 'screen-grades-ratchet.mjs', tipo: 'valida (nota não desce)', contexto: null, comando: 'node scripts/qa/screen-grades-ratchet.mjs' },
  { artefato: 'mapa de tela', maquina: 'screen-coverage-map.mjs', tipo: 'GERA mapa + catraca', contexto: 'screen-coverage-gate', comando: 'npm run screen-coverage:report · --check' },
  // Transversais que defendem a integridade da estrutura
  { artefato: 'catalog.json (grafo IDP)', maquina: 'catalog-graph.mjs', tipo: 'GERA + frescor', contexto: null, comando: 'node scripts/governance/catalog-graph.mjs --write · --check' },
  { artefato: 'dicionário de domínio', maquina: 'domain-dict-guard.mjs', tipo: 'valida enum↔dicionário', contexto: 'Dominio-dict · ratchet (enum ⇔ dicionário)', comando: 'npm run dominio:check' },
  { artefato: 'qualquer .md canon', maquina: 'deadlink-gate.mjs', tipo: 'valida links doc↔doc', contexto: 'deadlink-gate (ratchet · integridade referencial)', comando: 'node scripts/governance/deadlink-gate.mjs' },
  { artefato: 'trio de tela NOVA', maquina: 'criar-tela.mjs', tipo: 'GERA (.tsx+charter+casos+e2e)', contexto: null, comando: 'node scripts/governance/criar-tela.mjs <Mod/Tela> <PT-0X>' },
];

function loadRequired() {
  const b = JSON.parse(fs.readFileSync(BASELINE, 'utf8'));
  const ctx = [
    ...(b.classic_protection?.contexts || []),
    ...(b.rulesets?.contexts || []),
  ];
  return new Set(ctx);
}

function render() {
  const required = loadRequired();
  const artefatos = [...new Set(CATALOGO.map((c) => c.artefato))];

  // Auto-detecção de drift do próprio catálogo: contexto que EU digo ser gate
  // mas sumiu do baseline (foi demovido) — avisa em vez de mentir.
  const drift = CATALOGO
    .filter((c) => c.contexto && !required.has(c.contexto))
    .map((c) => c.contexto);

  const lines = [];
  lines.push(START);
  lines.push('');
  lines.push('> ⚙️ **Seção gerada** por `scripts/governance/mapa-artefatos.mjs` a partir de');
  lines.push('> [`governance/required-checks-baseline.json`](../../../governance/required-checks-baseline.json).');
  lines.push('> NÃO edite à mão. Regenerar: `node scripts/governance/mapa-artefatos.mjs --write`.');
  lines.push('> A coluna **Gate** deriva do baseline — se um gate é promovido/demovido, re-rode.');
  lines.push('');

  for (const art of artefatos) {
    lines.push(`### \`${art}\``);
    lines.push('');
    lines.push('| Máquina | Faz | Gate (do baseline) | Como rodar |');
    lines.push('|---|---|---|---|');
    for (const c of CATALOGO.filter((x) => x.artefato === art)) {
      const isReq = c.contexto && required.has(c.contexto);
      const gate = isReq ? `🔒 **required** — \`${c.contexto}\`` : (c.contexto ? `⚠️ advisory — \`${c.contexto}\`` : '⚠️ advisory (sem gate de CI próprio)');
      lines.push(`| \`${c.maquina}\` | ${c.tipo} | ${gate} | \`${c.comando}\` |`);
    }
    lines.push('');
  }

  const reqCount = CATALOGO.filter((c) => c.contexto && required.has(c.contexto)).length;
  const total = CATALOGO.length;
  lines.push(`**Resumo:** ${reqCount} das ${total} máquinas catalogadas têm gate 🔒 required (bloqueia merge); o resto é ⚠️ advisory (avisa, não bloqueia).`);
  lines.push('');
  if (drift.length) {
    lines.push(`> ⚠️ **Drift de catálogo:** ${drift.length} contexto(s) que este gerador esperava required sumiram do baseline — revisar \`CATALOGO\` em \`mapa-artefatos.mjs\`: ${drift.map((d) => `\`${d}\``).join(', ')}.`);
    lines.push('');
  }
  lines.push(END);
  return lines.join('\n');
}

function splice(doc, section) {
  const i = doc.indexOf(START);
  const j = doc.indexOf(END);
  if (i === -1 || j === -1) {
    throw new Error(`Marcadores ${START} / ${END} não encontrados em ${DOC}`);
  }
  return doc.slice(0, i) + section + doc.slice(j + END.length);
}

const mode = process.argv[2];
const section = render();

if (mode === '--write') {
  const doc = fs.readFileSync(DOC, 'utf8');
  fs.writeFileSync(DOC, splice(doc, section), 'utf8');
  console.log(`✅ Seção de máquinas regravada em ${path.relative(ROOT, DOC)}`);
} else if (mode === '--check') {
  const doc = fs.readFileSync(DOC, 'utf8');
  const want = splice(doc, section);
  if (want !== doc) {
    console.error('❌ MAPA-DE-ARTEFATOS.md desatualizado. Rode: node scripts/governance/mapa-artefatos.mjs --write');
    process.exit(1);
  }
  console.log('✅ MAPA-DE-ARTEFATOS.md em dia.');
} else {
  console.log(section);
}
