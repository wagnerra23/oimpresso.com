#!/usr/bin/env node
// pendentes-cowork.test.mjs — o critério do sentido Code -> Cowork (ver o docblock do script).
//   BITE      arquivo fora do bundle · hash diferente do bundle -> pendente
//   RELEASE   hash igual ao bundle · `_ds/` · `.gitignore` -> não pendente
//   ENVIADO   registrado com o MESMO sha -> sai dos não-enviados; mudou depois -> volta
//   CANAL     o --plano só põe `cowork-inbox/` em `writes`; tela vai para `fora_do_canal`
//   COPIA     2º projeto: pendente = nunca enviado ou git mudou desde o envio (sem bundle)
//   CONFERIR  lido de volta × git: IGUAL / DIFERENTE / FORA-DO-GIT; CLI não registra nada se algo diverge

import { calcularPendentes, calcularPendentesCopia, compararLidos, eRecibo, DESTINOS } from './pendentes-cowork.mjs';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

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

// ── COPIA ──
let c = calcularPendentesCopia({ arquivos });
ok(c.length === 4, `COPIA: sem envio nenhum, tudo dentro do escopo é pendente (deu ${c.length})`);
ok(c.every((x) => x.motivo === 'nunca-enviado'), 'COPIA: motivo nunca-enviado');
c = calcularPendentesCopia({ arquivos, enviados: { 'clientes-page.jsx': { sha256: 'aaa' }, 'cowork-inbox/p/playbook/07-painel.md': { sha256: 'velho' } } });
ok(!c.some((x) => x.rel === 'clientes-page.jsx'), 'COPIA: enviado com o mesmo sha sai da lista');
ok(c.find((x) => x.rel.endsWith('07-painel.md'))?.motivo === 'mudou-desde-o-envio', 'COPIA: git mudou depois do envio -> volta com motivo próprio');
ok(DESTINOS.copia.enviados !== DESTINOS.w.enviados, 'COPIA: estado de envio separado do projeto w (as contas não se pisam)');
ok(DESTINOS.copia.projectId && DESTINOS.copia.projectId !== DESTINOS.w.projectId, 'COPIA: projectId vem do painel e difere do w');

// ── CONFERIR (puro) ──
const doGit = new Map([['a.jsx', 's1'], ['b.jsx', 's2']]);
const v = compararLidos([{ rel: 'a.jsx', sha: 's1' }, { rel: 'b.jsx', sha: 'xx' }, { rel: 'z.jsx', sha: 's9' }], doGit);
ok(v[0].veredito === 'IGUAL' && v[1].veredito === 'DIFERENTE' && v[2].veredito === 'FORA-DO-GIT', 'CONFERIR: IGUAL / DIFERENTE / FORA-DO-GIT');

// ── CONFERIR (CLI de fora, sem tocar o estado do repo) ──
const script = join(dirname(fileURLToPath(import.meta.url)), 'pendentes-cowork.mjs');
const estado = join(dirname(fileURLToPath(import.meta.url)), 'state', 'enviados-cowork-copia.json');
const lerEstado = () => { try { return readFileSync(estado, 'utf8'); } catch { return null; } };
const antes = lerEstado();
const tmp = mkdtempSync(join(tmpdir(), 'pc-test-'));
try {
  const vazio = join(tmp, 'vazio'); mkdirSync(vazio);
  let r = spawnSync(process.execPath, [script, '--conferir', vazio, '--projeto', 'copia'], { encoding: 'utf8' });
  ok(r.status === 2, `CONFERIR CLI: diretório vazio é NÃO MEDI (exit 2, deu ${r.status})`);
  const ruim = join(tmp, 'ruim'); mkdirSync(ruim);
  writeFileSync(join(ruim, 'app.jsx'), '// conteúdo que não é o do git\n');
  r = spawnSync(process.execPath, [script, '--conferir', ruim, '--projeto', 'copia'], { encoding: 'utf8' });
  ok(r.status === 1 && /DIFERENTE/.test(r.stdout), `CONFERIR CLI: leitura divergente reprova (exit 1, deu ${r.status})`);
  ok(lerEstado() === antes, 'CONFERIR CLI: divergência NÃO registra envio (estado intacto)');
} finally {
  rmSync(tmp, { recursive: true, force: true });
  // Se um mutante registrou (o assert acima já reprovou), desfaz: o teste não deixa estado falso.
  if (lerEstado() !== antes) { if (antes === null) rmSync(estado, { force: true }); else writeFileSync(estado, antes); }
}

// ── CONFERIR copia GRAVA o ledger do espelho ([W] 2026-09-29: "a maiara deveria poder fazer isso") ──
// Leitura de volta idêntica ao git, vinda do projeto-cópia, tem de virar rodada no ledger que o
// check "espelho — mexeu depois de verificar" lê, marcada com o projeto. Mutante que volte a
// restringir o ledger ao `w` derruba este assert. Ledger e estado são restaurados no fim.
{
  const raiz = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
  const ledger = join(raiz, 'scripts', 'governance', '.cowork-freshness-ledger.json');
  const ledgerAntes = (() => { try { return readFileSync(ledger, 'utf8'); } catch { return null; } })();
  const estadoAntes = lerEstado();
  const rel = 'manufacturing-page.css';
  const tmp2 = mkdtempSync(join(tmpdir(), 'pc-ledger-'));
  try {
    writeFileSync(join(tmp2, rel), readFileSync(join(raiz, 'prototipo-ui', 'cowork', 'Wagner', rel)));
    const r = spawnSync(process.execPath, [script, '--conferir', tmp2, '--projeto', 'copia'], { encoding: 'utf8' });
    const entradas = JSON.parse(readFileSync(ledger, 'utf8'));
    const ult = entradas[entradas.length - 1];
    ok(r.status === 0, `CONFERIR copia: leitura igual ao git sai 0 (deu ${r.status}) ${r.stderr || ''}`);
    ok(ult?.projetoCowork === 'copia' && (ult.verified || []).includes(rel),
      'CONFERIR copia: grava rodada no ledger do espelho, com projetoCowork=copia e o arquivo verificado');
  } finally {
    rmSync(tmp2, { recursive: true, force: true });
    if (ledgerAntes === null) rmSync(ledger, { force: true }); else writeFileSync(ledger, ledgerAntes);
    if (lerEstado() !== estadoAntes) { if (estadoAntes === null) rmSync(estado, { force: true }); else writeFileSync(estado, estadoAntes); }
  }
}

console.log(`\n${fails ? `${fails} FALHA(S)` : 'todos os casos passaram'}`);
process.exit(fails ? 1 : 0);
