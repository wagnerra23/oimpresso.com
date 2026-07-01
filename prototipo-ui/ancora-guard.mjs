#!/usr/bin/env node
// ancora-guard.mjs — CATRACA: o protótipo tem 1 lugar fixo e nunca troca de lugar.
//
// Origem: Wagner 2026-07-01 — "não pode trocar de lugar nunca. deve ser isso que fica errando".
// O bug (3 gerações: public/cowork-preview → prototipo-ui/cowork → Downloads/staging): a âncora do
// charter é FIXA (related_prototype/canon_source), mas o protótipo migrava de pasta; a âncora passava
// a apontar pro vazio → "tudo dá errado". Este gate torna "trocar de lugar" IMPOSSÍVEL sem quebrar o CI.
//
// Duas regras:
//   R1 — LUGAR PROIBIDO: charter não pode citar lugar velho/volátil (public/cowork-preview, erp-shell,
//        _cowork-handoff-staging, Downloads). O único endereço legítimo é prototipo-ui/cowork/.
//   R2 — ÂNCORA VIVA: todo path prototipo-ui/cowork/<arquivo> citado numa âncora tem que EXISTIR.
//
// Uso: node prototipo-ui/ancora-guard.mjs [--selftest]
// Exit: 0 = ok | 1 = violação | 2 = erro de uso

import { readFileSync, existsSync, readdirSync, statSync } from 'node:fs';
import { join, resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const REPO = resolve(HERE, '..');
const LUGAR_FIXO = 'prototipo-ui/cowork/';

// Lugares proibidos — citar qualquer um = protótipo trocou de lugar.
const PROIBIDOS = [
  { re: /public\/cowork-preview\//i, nome: 'public/cowork-preview/ (geração antiga apagada)' },
  { re: /erp-shell(-v2)?\//i, nome: 'erp-shell/ (shell antigo)' },
  { re: /_cowork-handoff-staging/i, nome: '_cowork-handoff-staging (bundle bruto, fora do git)' },
  { re: /Downloads\//i, nome: 'Downloads/ (staging local, volátil)' },
];

// Campos de charter que ancoram no protótipo.
const CAMPO_ANCORA = /^(related_prototype|canon_source):\s*(.+)$/i;

// Extrai um path prototipo-ui/cowork/xxx.(jsx|html|css|tsx) da string da âncora.
function extrairPathFixo(valor) {
  const m = valor.match(/prototipo-ui\/cowork\/[^\s`"'()]+\.(jsx|html|css|tsx)/i);
  return m ? m[0] : null;
}

// Núcleo testável: recebe [{rel, conteudo}] + existsFn(path)->bool. Devolve violações.
export function checarConteudos(items, existsFn) {
  const violacoes = [];
  for (const { rel, conteudo } of items) {
    conteudo.split(/\r?\n/).forEach((linha, i) => {
      const mc = linha.match(CAMPO_ANCORA);
      if (!mc) return;
      const [campo, valor] = [mc[1], mc[2].trim()];
      // R1 — lugar proibido
      const proib = PROIBIDOS.find((p) => p.re.test(valor));
      if (proib) {
        violacoes.push({ rel, linha: i + 1, campo, regra: 'R1',
          motivo: `aponta pra LUGAR PROIBIDO — ${proib.nome}. O único endereço legítimo é ${LUGAR_FIXO}` });
        return;
      }
      // R2 — âncora viva (só quando cita um path do lugar fixo)
      const p = extrairPathFixo(valor);
      if (p && !existsFn(p)) {
        violacoes.push({ rel, linha: i + 1, campo, regra: 'R2',
          motivo: `âncora aponta pra path INEXISTENTE — "${p}" não está no lugar fixo` });
      }
    });
  }
  return violacoes;
}

function walk(dir, out = []) {
  for (const e of readdirSync(dir)) {
    if (e === 'node_modules' || e.startsWith('.')) continue;
    const p = join(dir, e);
    const st = statSync(p);
    if (st.isDirectory()) walk(p, out);
    else if (e.endsWith('.charter.md')) out.push(p);
  }
  return out;
}

function modoReal() {
  const pagesDir = join(REPO, 'resources', 'js', 'Pages');
  if (!existsSync(pagesDir)) { console.error('[ancora-guard] resources/js/Pages não encontrado — worktree órfã?'); process.exit(2); }
  const charters = walk(pagesDir);
  const items = charters.map((ch) => ({
    rel: ch.replace(REPO, '').replace(/^[\\/]/, '').replace(/\\/g, '/'),
    conteudo: readFileSync(ch, 'utf8'),
  }));
  const violacoes = checarConteudos(items, (p) => existsSync(join(REPO, p)));
  if (violacoes.length === 0) {
    console.log(`[ancora-guard] OK — ${charters.length} charters · âncoras no lugar fixo (${LUGAR_FIXO}) e vivas.`);
    process.exit(0);
  }
  console.error(`[ancora-guard] ✗ ${violacoes.length} violação(ões) — protótipo fora do lugar fixo:`);
  for (const v of violacoes) console.error(`  ${v.regra} ${v.rel}:${v.linha} (${v.campo}) — ${v.motivo}`);
  process.exit(1);
}

function selftest() {
  const existsFn = (p) => p === 'prototipo-ui/cowork/oimpresso.com.html' || p === 'prototipo-ui/cowork/compras-page.jsx';
  const casos = [
    { nome: 'lugar fixo + existe → OK', itens: [{ rel: 'a.charter.md', conteudo: 'related_prototype: prototipo-ui/cowork/oimpresso.com.html (canon)' }], esperado: 0 },
    { nome: 'lugar PROIBIDO public/cowork-preview → 1', itens: [{ rel: 'b.charter.md', conteudo: 'related_prototype: public/cowork-preview/Oimpresso ERP - Chat.html' }], esperado: 1 },
    { nome: 'lugar PROIBIDO erp-shell → 1', itens: [{ rel: 'c.charter.md', conteudo: 'canon_source: public/cowork-preview/erp-shell/financeiro-telas-extras.jsx' }], esperado: 1 },
    { nome: 'lugar PROIBIDO staging Downloads → 1', itens: [{ rel: 'd.charter.md', conteudo: 'related_prototype: Downloads/_cowork-handoff-staging/x/oimpresso.com.html' }], esperado: 1 },
    { nome: 'lugar fixo mas INEXISTENTE → 1', itens: [{ rel: 'e.charter.md', conteudo: 'related_prototype: prototipo-ui/cowork/fantasma.jsx' }], esperado: 1 },
    { nome: 'texto-livre n/a sem path → OK', itens: [{ rel: 'f.charter.md', conteudo: 'related_prototype: n/a (F6 Soft wrapper — sem protótipo)' }], esperado: 0 },
  ];
  let falhou = false;
  for (const c of casos) {
    const n = checarConteudos(c.itens, existsFn).length;
    const ok = n === c.esperado;
    if (!ok) falhou = true;
    console.log(`  [${ok ? 'PASS' : 'FAIL'}] ${c.nome} → esperado ${c.esperado}, obtido ${n}`);
  }
  if (falhou) { console.error('SELFTEST FALHOU'); process.exit(1); }
  console.log('SELFTEST OK — R1 (lugar proibido) + R2 (âncora viva) mordem; texto-livre ignorado.');
  process.exit(0);
}

if (process.argv.includes('--selftest')) selftest();
else modoReal();
