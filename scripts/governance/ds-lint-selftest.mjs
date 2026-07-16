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

import { spawnSync } from 'node:child_process';

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

console.log(
  fails
    ? `\n✗ ${fails} problema(s) — regra ds/* sem morder OU falso-positivo. TEATRO detectado.`
    : `\n✓ todas as ${RULES.length} regras ds/* mordem seu alvo (e sem falso-positivo no caminho canônico).`,
);
process.exit(fails ? 1 : 0);
