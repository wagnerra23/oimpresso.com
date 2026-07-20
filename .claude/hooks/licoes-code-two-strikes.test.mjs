#!/usr/bin/env node
// Teste do PORTE licoes-code-two-strikes.mjs (ex-.ps1). Deriva do CONTRATO (LICOES_CODE.md:
// erro reincidente ≥threshold sem gate = alarme), NÃO do .ps1. Advisory: SEMPRE exit 0.
// Rodar: node .claude/hooks/licoes-code-two-strikes.test.mjs

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { parseLicoes, classificar, semGate, threshold, formatBanner,
  parseTombstones, ledgerCitacoesSecao5, computeFrontier, reconcile, formatReconcile } from './licoes-code-two-strikes.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'licoes-code-two-strikes.mjs');
let fails = 0;
const check = (n, c) => { console.log((c ? '[OK]   ' : '[FAIL] ') + n); if (!c) fails++; };

const MD = `# Licoes
## LC-01 - Erro repetido sem defesa
**Ocorrências:** 3
**Gate:** none
## LC-02 - Ja tem gate
**Ocorrências:** 5
**Gate:** block-foo.mjs
## LC-03 - So uma vez
**Ocorrências:** 1
**Gate:** none
`;

// ── parser + classificador (puros) ───────────────────────────────────────────────
const licoes = parseLicoes(MD);
check('parseLicoes acha 3 licoes', licoes.length === 3);
check('parseLicoes le ocorrencias + gate', licoes[0].ocorr === 3 && licoes[0].gate === 'none' && licoes[1].gate === 'block-foo.mjs');
check('semGate: none/vazio sim, gate real nao', semGate('none') && semGate('') && semGate('-') && !semGate('block-foo.mjs'));
check('threshold default 2', threshold({}) === 2 && threshold({ OIMPRESSO_LICOES_THRESHOLD: '3' }) === 3);
const { alarme, watch } = classificar(licoes, 2);
check('alarme = LC-01 (3x sem gate)', alarme.length === 1 && alarme[0].id === 'LC-01');
check('watch = LC-03 (1x sem gate)', watch.length === 1 && watch[0].id === 'LC-03');
check('LC-02 (tem gate) fica de fora', !alarme.concat(watch).some((l) => l.id === 'LC-02'));
check('formatBanner cita PROMOVER + LC-01', /PROMOVER A DEFESA MECANICA/.test(formatBanner(alarme, watch, 2)) && /LC-01/.test(formatBanner(alarme, watch, 2)));
check('formatBanner vazio quando nada', formatBanner([], [], 2) === '');

// ── extensão: cobertura só-advisory = "sem defesa mecânica" (proposal two-strikes-cobre-processo) ──
check('semGate: advisory/parcial/insuficiente = sem defesa mecanica', semGate('advisory — nudge-x') && semGate('parcial: cobre so X') && semGate('insuficiente'));
check('semGate: nome de gate real com "(advisory,...)" NAO casa (so o prefixo declarado)', !semGate('mutation-gate (advisory, escopo v1)') && !semGate('block-foo.mjs'));
check('semGate: advisory-terminal/by-design/0224 NAO alarma (decisao final ADR 0224)', !semGate('advisory-terminal (0224) — nudge-x') && !semGate('advisory by-design: nudge-y') && !semGate('parcial (0224 terminal)'));
const MD2 = `# Licoes
## LC-90 - Classe de processo com gate advisory que vaza
**Ocorrências:** 5
**Gate:** advisory — nudge-diagnosis-without-evidence + warn-red-first
`;
const c2 = classificar(parseLicoes(MD2), 2);
check('classe so-advisory reincidente (5x) vira ALARME', c2.alarme.length === 1 && c2.alarme[0].id === 'LC-90');

// ── E2E: advisory SEMPRE exit 0 ──────────────────────────────────────────────────
const tmp = mkdtempSync(join(tmpdir(), 'licoes-'));
const p = join(tmp, 'LICOES_CODE.md');
writeFileSync(p, MD);
function run(env = {}) {
  return spawnSync(process.execPath, [HOOK], { encoding: 'utf8', env: { ...process.env, OIMPRESSO_LICOES_CODE_PATH: p, ...env } });
}
const r = run();
check('E2E: alarme → exit 0 + banner stdout', r.status === 0 && /LC-01/.test(r.stdout));
check('E2E: arquivo inexistente → exit 0 silencioso', (() => { const x = spawnSync(process.execPath, [HOOK], { encoding: 'utf8', env: { ...process.env, OIMPRESSO_LICOES_CODE_PATH: join(tmp, 'nope.md') } }); return x.status === 0 && !x.stdout.trim(); })());
check('E2E: threshold alto → LC-01 vira watch, exit 0', run({ OIMPRESSO_LICOES_THRESHOLD: '9' }).status === 0);

// ══ AUTO-FEED (proposal auto-feed-ledger) — reconciliação §5↔ledger, fixture boa/ruim ══
// Controle-negativo OBRIGATÓRIO: prova que o surface MORDE (fixture ruim) e NÃO avermelha
// o legítimo (fixture boa). Deriva do CONTRATO (declaração-de-recorrência não-contada), não
// da implementação.

// §5 com: um tombstone FORA da §5 (deve ser ignorado), 3 datas que o ledger cita, 1
// recorrência DECLARADA pós-frontier (deve surfaçar), 1 neutro pós-frontier (cauda).
const PROIB = `# proibicoes
### 2026-01-01 — FORA da secao 5, tem "reincidência" mas NAO conta
corpo com reincidência aqui fora do escopo.
## Ideias avaliadas e DESCARTADAS — nao re-propor (regressoes)
### 2026-07-15 — lapide quinze
corpo neutro.
### 2026-07-16 — lapide dezesseis
corpo neutro.
### 2026-07-17 — lapide dezessete
corpo neutro.
### 2026-07-19 — lapide dezenove nova
corpo que declara reincidência da mesma família.
### 2026-07-21 — lapide vinte-e-um sem marca
corpo totalmente neutro.
## Sempre fazer
fim
`;
// Ledger cujo recibo cobre 07-15..17. O Ref tem uma data-metadata (07-25) que NAO pode
// virar recibo (o bug que o dry-run pegou). LC-06 tem Ocorrências SEM "§5" → excluída.
const LEDGER = `# Licoes
## LC-08 - fonte errada
- **Ocorrências:** 3   (proibicoes §5: 07-15 a · 07-16 b · 07-17 c)
- **Gate:** advisory
- **Ref:** raio-X 2026-07-25 (metadata, NAO e recibo)
## LC-06 - eyeball
- **Ocorrências:** 2   (strike 1 = 2026-07-06; strike 2 = 2026-07-07)
- **Gate:** design-diff.mjs
`;

// parseTombstones: escopo §5 (exclui o 01-01) + detecção de marcador
const tombs = parseTombstones(PROIB);
check('parseTombstones escopa §5 (01-01 fora fica DE FORA)', tombs.length === 5 && !tombs.some((t) => t.date === '2026-01-01'));
check('parseTombstones marca só quem declara recorrência', tombs.filter((t) => t.marker).length === 1 && tombs.find((t) => t.date === '2026-07-19').marker);

// ledgerCitacoesSecao5: SÓ a linha Ocorrências que declara §5; Ref-metadata e LC-06 fora
const cit = ledgerCitacoesSecao5(LEDGER);
check('ledgerCitacoes lê só Ocorrências §5 (Ref 07-25 e LC-06 07-06/07 FORA)',
  cit.byLc.length === 1 && cit.allMmdd.sort().join(',') === '07-15,07-16,07-17');
check('computeFrontier = max data resolvida (07-17, NAO 07-25 do Ref)', computeFrontier(cit.allMmdd, tombs) === '2026-07-17');

// reconcile — BITE: recorrência declarada pós-frontier não-contada é surfaçada
const rec = reconcile(LEDGER, PROIB);
check('reconcile frontier 07-17 + recibos 3/3 + 0 pendurado', rec.frontier === '2026-07-17' && rec.recibosOk === 3 && rec.recibosTotal === 3 && rec.dangling.length === 0);
check('reconcile MORDE: 07-19 (declarado) surfaça; 07-21 (neutro) vira cauda', rec.surfacedTotal === 1 && rec.surfaced[0].date === '2026-07-19' && rec.semMarcadorPosFrontier === 1);
check('formatReconcile cita o item surfaçado', /2026-07-19/.test(formatReconcile(rec)) && /recorrencia/i.test(formatReconcile(rec)));

// reconcile — GOOD: ledger cujo frontier cobre tudo → 0 surface, banner vazio (não avermelha)
const LEDGER_CLEAN = `# Licoes
## LC-08 - x
- **Ocorrências:** 4   (proibicoes §5: 07-15 · 07-19 z)
- **Gate:** advisory
`;
const recClean = reconcile(LEDGER_CLEAN, PROIB);
check('reconcile GOOD: frontier cobre 07-19 → 0 surface + 0 pendurado', recClean.frontier === '2026-07-19' && recClean.surfacedTotal === 0 && recClean.dangling.length === 0);
check('formatReconcile vazio quando limpo (silêncio, como o banner two-strikes)', formatReconcile(recClean) === '');

// reconcile — BITE S2: recibo pendurado (data citada sem lápide na §5)
const LEDGER_DANG = `# Licoes
## LC-09 - z
- **Ocorrências:** 1   (proibicoes §5: 07-14 inexistente)
- **Gate:** none
`;
const recDang = reconcile(LEDGER_DANG, PROIB);
check('reconcile MORDE S2: recibo 07-14 sem lápide → pendurado', recDang.dangling.length === 1 && recDang.dangling[0].mmdd === '07-14' && recDang.recibosOk === 0);
check('formatReconcile mostra recibo PENDURADO', /PENDURADO/.test(formatReconcile(recDang)) && /07-14/.test(formatReconcile(recDang)));

// temp-dir safe: proibicoes vazio/ausente → reconcile não quebra, banner vazio
check('reconcile temp-dir-safe (proibicoes vazio → 0 surface, banner vazio)', formatReconcile(reconcile(LEDGER, '')) === '');

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — parseia/classifica LICOES + reconcilia §5↔ledger (surface bite + good), advisory exit 0.');
process.exit(fails ? 1 : 0);
