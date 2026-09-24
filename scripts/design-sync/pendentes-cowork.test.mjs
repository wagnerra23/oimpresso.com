#!/usr/bin/env node
// pendentes-cowork.test.mjs — o critério do sentido Code -> Cowork (ver o docblock do script).
//   BITE      arquivo fora do bundle · hash diferente do bundle -> pendente
//   RELEASE   hash igual ao bundle · `_ds/` · `.gitignore` -> não pendente
//   ENVIADO   registrado com o MESMO sha -> sai dos não-enviados; mudou depois -> volta
//   CANAL     o --plano só põe `cowork-inbox/` em `writes`; tela vai para `fora_do_canal`

import { calcularPendentes, eRecibo } from './pendentes-cowork.mjs';

let fails = 0;
const ok = (c, m) => { if (c) console.log(`  ✓ ${m}`); else { console.error(`  ✗ ${m}`); fails++; } };

const ativo = { files: [
  { path: 'clientes-page.jsx', sha256: 'aaa' },
  { path: 'cowork-inbox/p/playbook/00-INDICE.md', sha256: 'bbb' },
  { path: '_ds/x/y.css', sha256: 'ccc', role: 'preview-cache' },
] };
const arquivos = [
  { rel: 'clientes-page.jsx', sha: 'aaa' },                         // igual -> fora
  { rel: 'cowork-inbox/p/playbook/00-INDICE.md', sha: 'bbb2' },     // editado no repo -> pendente
  { rel: 'cowork-inbox/p/playbook/07-painel.md', sha: 'ddd' },      // restaurado no repo -> pendente
  { rel: 'cowork-inbox/p/playbook/_saida-01.md', sha: 'eee' },      // recibo -> pendente
  { rel: '_ds/x/y.css', sha: 'zzz' },                               // cache do DS -> fora
  { rel: '.gitignore', sha: 'ggg' },                                // guarda local -> fora
];

let p = calcularPendentes({ arquivos, ativo });
const rels = p.map((x) => x.rel);
ok(rels.includes('cowork-inbox/p/playbook/00-INDICE.md'), 'BITE: hash diferente do bundle é pendente (o caso #7866)');
ok(p.find((x) => x.rel.endsWith('00-INDICE.md'))?.motivo === 'difere-do-bundle', 'BITE: motivo difere-do-bundle');
ok(rels.includes('cowork-inbox/p/playbook/07-painel.md'), 'BITE: fora do bundle é pendente (o caso #7847)');
ok(p.find((x) => x.rel.endsWith('_saida-01.md'))?.recibo === true, 'BITE: recibo marcado como recibo');
ok(!rels.includes('clientes-page.jsx'), 'RELEASE: hash igual ao bundle não é pendente');
ok(!rels.includes('_ds/x/y.css'), 'RELEASE: _ds/ (dono = projeto DS) fica fora');
ok(!rels.includes('.gitignore'), 'RELEASE: .gitignore fica fora');
ok(p.length === 3, `CONTROLE: exatamente 3 pendentes (deu ${p.length})`);

p = calcularPendentes({ arquivos, ativo, enviados: { 'cowork-inbox/p/playbook/07-painel.md': { sha256: 'ddd' } } });
ok(p.find((x) => x.rel.endsWith('07-painel.md'))?.enviado === true, 'ENVIADO: mesmo sha registrado -> enviado');
p = calcularPendentes({ arquivos, ativo, enviados: { 'cowork-inbox/p/playbook/07-painel.md': { sha256: 'velho' } } });
ok(p.find((x) => x.rel.endsWith('07-painel.md'))?.enviado === false, 'ENVIADO: mudou depois do envio -> volta a não-enviado');

ok(eRecibo('cowork-inbox/p/playbook/_saida-06-bens.md'), 'recibo com sufixo -<tela> conta como recibo');
ok(!eRecibo('cowork-inbox/p/playbook/06-bens.md'), 'CONTROLE: thread não é recibo');

console.log(`\n${fails ? `${fails} FALHA(S)` : 'todos os casos passaram'}`);
process.exit(fails ? 1 : 0);
