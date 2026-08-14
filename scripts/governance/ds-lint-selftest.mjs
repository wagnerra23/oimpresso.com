#!/usr/bin/env node
// @ts-check
// DS-LINT SELFTEST — controle-negativo das regras ds/* (as `no-restricted-syntax`
// de eslint.config.js com mensagem `ds/…`). QUEM VIGIA O VIGIA: prova que CADA
// regra MORDE seu alvo (bad-fixture → todas disparam) e NÃO falso-positiva no
// caminho canônico (good-fixture → 0 disparo ds/).
//
// POR QUE EXISTE (auditoria 2026-07-15): as regras ds/* são o gate required que
// impede hand-roll novo (tablist, cor crua, select nativo, pílula de status…).
// Mas elas não estavam no `gate-selftest.mjs` nem tinham RuleTester — logo
// "simplificar" um selector quebraria a regra EM SILÊNCIO e o ratchet
// (`eslint-baseline.mjs`) contaria 0 pra sempre (teatro). Este selftest fecha
// esse buraco: se uma regra deixar de morder, o CI fica vermelho.
//
// Roda: node scripts/governance/ds-lint-selftest.mjs   (exit 0 = todas mordem)
// Determinístico, sem rede. Usa o eslint do repo via --stdin (nenhum arquivo
// escrito no tree) com um stdin-filename que casa o files-glob do bloco ds.
//
// ── COBERTURA REGISTRY-BACKED (2026-08-14, chip G4) ────────────────────────────
// As mensagens das regras COMPONENT-SUBSTITUTE passaram a DERIVAR o alvo (componente
// + import_path) do `prototipo-ui/component-registry.json`, via `ds-lint-alvos.mjs`.
// Os blocos 3-6 abaixo travam esse desenho por MEDIÇÃO, não por prosa:
//   3. a tabela `ALVO_POR_REGRA` cobre exatamente as regras de `RULES` (1:1, sem drift);
//   4. a cobertura (quantas regras derivam) fica PINADA — número em teste, não em doc;
//   5. a mensagem que o ESLint realmente emitiu contém o `import_path` do registry
//      (prova de DERIVAÇÃO — texto fixo "igualzinho" não passaria no bloco 6);
//   6. controle-negativo: registry sem a entrada (ou com ela em `gap`) faz `alvo()`
//      LANÇAR — nada de fallback silencioso mandando importar de path inexistente.

import { spawnSync } from 'node:child_process';
import {
  ALVO_POR_REGRA,
  cobertura,
  criarAlvos,
  lerRegistry,
  alvo as alvoCanon,
} from '../../prototipo-ui/ds-lint-alvos.mjs';

// As 13 regras ds/* que o bloco `no-restricted-syntax` do eslint.config.js
// canoniza. Se adicionar/remover uma regra ds/ lá, atualize aqui + o fixture.
const RULES = [
  'no-native-radio',
  'no-native-checkbox',
  'no-native-select',
  'no-rounded-xl',
  'no-arbitrary-color',
  'no-raw-palette-color',
  'no-os-btn',
  'no-radix-item-empty-value',
  'no-db-jargon-in-ui',
  'no-inline-tablist',
  'no-inline-raw-color',
  'no-handrolled-combobox',
  'no-handrolled-status-pill',
];

// BAD-fixture: UMA violação por regra (mesma ordem do RULES, comentada).
const BAD = `import { SelectItem } from '@/Components/ui/select';
export default function Bad() {
  return (
    <div>
      <input type="radio" />
      <input type="checkbox" />
      <select></select>
      <div className="rounded-xl" />
      <div className="bg-[#fff]" />
      <div className="bg-stone-200" />
      <div className="os-btn" />
      <SelectItem value="" />
      <span>final_total</span>
      <div role="tablist" />
      <div style={{ color: '#fff' }} />
      <input role="combobox" aria-autocomplete="list" />
      <span className="rounded-full px-2 success-soft" />
    </div>
  );
}
`;

// GOOD-fixture: caminho canônico — NENHUMA violação ds/ deve disparar.
const GOOD = `export default function Ok() {
  return <div className="rounded-lg bg-muted text-foreground border-border">ok</div>;
}
`;

function lint(src) {
  const r = spawnSync(
    'npx',
    ['--no-install', 'eslint', '--stdin', '--stdin-filename',
     'resources/js/Pages/__ds_selftest__.tsx', '--format=json', '--max-warnings=999999'],
    { input: src, encoding: 'utf8', shell: true },
  );
  let json;
  try { json = JSON.parse(r.stdout); }
  catch {
    console.error('[erro] eslint não retornou JSON. stdout/stderr abaixo:');
    console.error(r.stdout);
    console.error(r.stderr);
    process.exit(2);
  }
  const msgs = (json[0] && json[0].messages) || [];
  return msgs.map((m) => m.message || '');
}

let fails = 0;

// 1. BAD-fixture: todas as regras têm que morder.
const bad = lint(BAD);
for (const rl of RULES) {
  const fired = bad.some((t) => t.includes('ds/' + rl));
  console.log((fired ? '[OK]  ' : '[FAIL] TEATRO — ') + 'ds/' + rl + (fired ? ' morde' : ' NÃO disparou no alvo'));
  if (!fired) fails++;
}

// 2. GOOD-fixture: caminho canônico não pode falso-positivar.
const good = lint(GOOD).filter((t) => t.includes('ds/'));
if (good.length) { console.log(`[FAIL] falso-positivo ds/ no good-fixture (${good.length}): ${good[0]}`); fails++; }
else console.log('[OK]  good-fixture sem falso-positivo ds/');

// 3. A tabela de cobertura cobre EXATAMENTE as regras de RULES (1:1, nos dois sentidos).
const naTabela = Object.keys(ALVO_POR_REGRA);
const faltaNaTabela = RULES.filter((r) => !naTabela.includes(r));
const sobraNaTabela = naTabela.filter((r) => !RULES.includes(r));
if (faltaNaTabela.length || sobraNaTabela.length) {
  console.log(`[FAIL] ALVO_POR_REGRA fora de sincronia — falta: [${faltaNaTabela}] sobra: [${sobraNaTabela}]`);
  fails++;
} else {
  console.log(`[OK]  ALVO_POR_REGRA cobre as ${RULES.length} regras ds/* (1:1)`);
}

// 4. COBERTURA PINADA — o número vive aqui, não em prosa que apodrece.
// Mudou? Então mudou o desenho: atualize conscientemente (e diga por quê no PR).
const COBERTURA_ESPERADA = { derivado: 5, parcial: 1, proprio: 7, comRegistry: 6, entradasCitadas: 8 };
const c = cobertura();
const obtida = {
  derivado: c.derivado,
  parcial: c.parcial,
  proprio: c.proprio,
  comRegistry: c.comRegistry,
  entradasCitadas: c.entradasCitadas.length,
};
if (c.totalRegras !== RULES.length) {
  console.log(`[FAIL] cobertura().totalRegras=${c.totalRegras} ≠ RULES.length=${RULES.length}`);
  fails++;
}
if (JSON.stringify(obtida) !== JSON.stringify(COBERTURA_ESPERADA)) {
  console.log(`[FAIL] cobertura mudou — esperado ${JSON.stringify(COBERTURA_ESPERADA)}, obtido ${JSON.stringify(obtida)}`);
  fails++;
} else {
  console.log(
    `[OK]  cobertura registry-backed: ${c.comRegistry}/${c.totalRegras} regras ` +
    `(${c.derivado} derivado + ${c.parcial} parcial) · ${c.entradasCitadas.length}/${c.totalEntradasRegistry} entradas do registry citadas`,
  );
}

// 5. DERIVAÇÃO REAL: a mensagem que o ESLint emitiu tem que conter a FORMA COMPLETA do
// alvo — `<Componente> (import_path)`, parêntese de fechamento incluso — como está HOJE
// no registry. Conferir só o `import_path` solto NÃO serve: `includes('@/Components/ui/
// select')` casa dentro de `@/Components/ui/select-legado` e o bite-test passa verde com
// a mensagem já desancorada (medido nesta sessão — é o presence-gate por substring do §5
// 2026-07-26). A forma fechada por `)` é exata.
for (const [regra, spec] of Object.entries(ALVO_POR_REGRA)) {
  if (!spec.registry.length) continue;
  const msg = bad.find((t) => t.startsWith('ds/' + regra));
  if (!msg) { console.log(`[FAIL] ds/${regra} não disparou no bad-fixture — não dá pra conferir o alvo`); fails++; continue; }
  const faltando = spec.registry.filter((nome) => !msg.includes(alvoCanon(nome)));
  if (faltando.length) {
    console.log(`[FAIL] ds/${regra} não cita o alvo do registry pra: ${faltando.map((n) => alvoCanon(n)).join(' · ')}`);
    fails++;
  } else {
    console.log(`[OK]  ds/${regra} cita o alvo do registry (${spec.registry.map((n) => alvoCanon(n)).join(' · ')})`);
  }
}

// 6. CONTROLE-NEGATIVO — registry sem a entrada (ou com ela em `gap`) NÃO pode virar
// texto fixo silencioso: `alvo()` tem que lançar. Fallback aqui seria a mensagem
// mandando importar de um path que ninguém verificou (proibicoes §5 2026-07-29).
const reg = lerRegistry();
const semButton = { ...reg, entries: reg.entries.filter((e) => e.componente_react !== 'Button') };
const gapButton = {
  ...reg,
  entries: reg.entries.map((e) => (e.componente_react === 'Button' ? { ...e, status: 'gap' } : e)),
};
for (const [rotulo, doc] of [['entrada REMOVIDA', semButton], ['entrada em status gap', gapButton]]) {
  let lancou = false;
  try { criarAlvos(doc).alvo('Button'); } catch { lancou = true; }
  if (lancou) console.log(`[OK]  controle-negativo (${rotulo}): alvo() lança em vez de inventar path`);
  else { console.log(`[FAIL] controle-negativo (${rotulo}): alvo() NÃO lançou — fallback silencioso`); fails++; }
}
// Controle-POSITIVO do mesmo mecanismo: no registry real, resolve.
try {
  const s = criarAlvos(reg).alvo('Button');
  if (s.includes('@/Components/ui/button')) console.log('[OK]  controle-positivo: alvo("Button") resolve pelo registry real');
  else { console.log(`[FAIL] controle-positivo: alvo("Button") devolveu "${s}"`); fails++; }
} catch (e) { console.log(`[FAIL] controle-positivo: alvo("Button") lançou — ${e.message}`); fails++; }

console.log(
  fails
    ? `\n✗ ${fails} problema(s) — regra ds/* sem morder, falso-positivo, ou alvo desancorado do registry. TEATRO detectado.`
    : `\n✓ todas as ${RULES.length} regras ds/* mordem seu alvo (sem falso-positivo) e as ${c.comRegistry} registry-backed citam o import_path vindo do registry.`,
);
process.exit(fails ? 1 : 0);
