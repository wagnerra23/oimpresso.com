#!/usr/bin/env node
// block-ancora-velha.test.mjs — o gate MORDE e LIBERA certo.
//
// Cada caso é par: um que DEVE bloquear e um controle-negativo que DEVE passar. Gate que só
// tem fixture boa não prova nada — prova que não explodiu.
//
// Rode: node .claude/hooks/block-ancora-velha.test.mjs

import { decidir, frescor, declaraNa, pathDaAncora } from './block-ancora-velha.mjs';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';

let ok = 0;
let falhou = 0;

function t(nome, cond) {
  if (cond) { ok++; console.log('  ✓ ' + nome); }
  else { falhou++; console.error('  ✗ ' + nome); }
}

// ── sandbox: um repo de mentira com charter + espelho + ledger ────────────────────
const raiz = mkdtempSync(join(tmpdir(), 'ancora-velha-'));
const escrever = (rel, txt) => {
  const abs = join(raiz, rel);
  mkdirSync(dirname(abs), { recursive: true });
  writeFileSync(abs, txt);
};

escrever('prototipo-ui/cowork/tela-x.jsx', '// protótipo da tela X\n');
escrever(
  'resources/js/Pages/Mod/Tela.charter.md',
  '---\npage: /mod/tela\nrelated_prototype: prototipo-ui/cowork/tela-x.jsx\n---\n# charter\n',
);
escrever(
  'resources/js/Pages/Mod/Sem.charter.md',
  '---\npage: /mod/sem\nrelated_prototype: n/a (nasce do DS, sem protótipo)\n---\n# charter\n',
);

const ledger = (rodada) =>
  escrever('scripts/governance/.cowork-freshness-ledger.json', JSON.stringify([rodada]));

const editar = (rel) => decidir('Edit', { file_path: join(raiz, rel) }, raiz);

console.log('\nblock-ancora-velha — bite test\n');

// ── 1. MORDE: âncora na staleList ────────────────────────────────────────────────
ledger({ date: '2026-08-27', staleList: ['prototipo-ui/cowork/tela-x.jsx'], verified: [] });
const mordeu = editar('resources/js/Pages/Mod/Tela.tsx');
t('MORDE: âncora STALE bloqueia o Edit da tela', !!mordeu);
t('MORDE: a razão nomeia o arquivo da âncora', !!mordeu && mordeu.msg.includes('tela-x.jsx'));

// ── 1b. controle-negativo: a MESMA tela, com a âncora verificada ─────────────────
ledger({ date: '2026-08-27', staleList: [], verified: ['prototipo-ui/cowork/tela-x.jsx'] });
t('LIBERA: âncora verificada deixa passar', editar('resources/js/Pages/Mod/Tela.tsx') === null);

// ── 2. não-medido NÃO é velho (lápide §5 2026-07-29) ─────────────────────────────
ledger({ date: '2026-08-27', staleList: [], verified: [] });
t('LIBERA: âncora nunca medida avisa, não trava', editar('resources/js/Pages/Mod/Tela.tsx') === null);
t('LIBERA: sem ledger nenhum não trava', (() => {
  rmSync(join(raiz, 'scripts/governance/.cowork-freshness-ledger.json'), { force: true });
  return editar('resources/js/Pages/Mod/Tela.tsx') === null;
})());

// ── 3. hash divergente do registrado também é STALE ──────────────────────────────
ledger({
  date: '2026-08-27',
  staleList: [],
  verified: ['prototipo-ui/cowork/tela-x.jsx'],
  verifiedHash: { 'prototipo-ui/cowork/tela-x.jsx': 'hash-de-outro-conteudo' },
});
t('MORDE: hash local ≠ hash registrado = stale', !!editar('resources/js/Pages/Mod/Tela.tsx'));

// ── 4. quem NÃO deve ser tocado ──────────────────────────────────────────────────
ledger({ date: '2026-08-27', staleList: ['prototipo-ui/cowork/tela-x.jsx'], verified: [] });
t('LIBERA: tela que declara n/a nunca é bloqueada', editar('resources/js/Pages/Mod/Sem.tsx') === null);
t('LIBERA: arquivo fora de Pages/ não é alvo', decidir('Edit', { file_path: join(raiz, 'app/Http/Controllers/X.php') }, raiz) === null);
t('LIBERA: .charter.md não é alvo (só .tsx)', decidir('Edit', { file_path: join(raiz, 'resources/js/Pages/Mod/Tela.charter.md') }, raiz) === null);
t('LIBERA: Read não é alvo (só Edit/Write)', decidir('Read', { file_path: join(raiz, 'resources/js/Pages/Mod/Tela.tsx') }, raiz) === null);
t('LIBERA: tela sem charter irmão não trava', editar('resources/js/Pages/Mod/Orfa.tsx') === null);

// ── 5. helpers puros ─────────────────────────────────────────────────────────────
t('declaraNa reconhece n/a com parêntese', declaraNa('n/a (F6 Soft wrapper)'));
t('declaraNa reconhece n/a entre aspas', declaraNa('"n/a"'));
t('declaraNa NÃO confunde nome de arquivo', !declaraNa('prototipo-ui/cowork/na-tela.jsx'));
t('pathDaAncora aceita path completo', pathDaAncora('prototipo-ui/cowork/x.jsx') === 'prototipo-ui/cowork/x.jsx');
t('pathDaAncora resolve nome solto no lugar fixo', pathDaAncora('dash-legacy-page.jsx (PT-04)') === 'prototipo-ui/cowork/dash-legacy-page.jsx');
t('frescor sem rodada = sem-ledger', frescor('x', null, null) === 'sem-ledger');

rmSync(raiz, { recursive: true, force: true });

console.log(`\n${ok} ok · ${falhou} falhou\n`);
process.exit(falhou ? 1 : 0);
