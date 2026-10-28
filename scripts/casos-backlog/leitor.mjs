// leitor.mjs — lê os pedidos de clientes e as decisões pendentes que vivem nos *.casos.md.
//
// Um PEDIDO é um caso de backlog ligado ao documento "Casos de uso por tela" pelo subitem
// `  - Pedido: P-NNN`. Ele pode estar em três lugares:
//   1. um bullet de backlog, em qualquer das grafias em uso (`**[BACKLOG] t**`, `**[BACKLOG]** t`,
//      `[BACKLOG] t`, `` `[BACKLOG]` **t** ``, `**[BACKLOG · ⬜ sem teste] t**`, ou bullet solto
//      dentro de uma seção "Backlog"). A primeira linha do bullet é a do autor e não é reescrita;
//      os subitens `Chave: valor` carregam os campos do documento.
//   2. um bloco de UC (`## UC-...`) com a linha `- **Pedido de origem:** P-NNN`: o pedido virou
//      caso de uso com id. Status ✅ no bloco = testado.
//   3. ausente: o pedido não está no git.
// Uma DECISÃO é `- **[DECISÃO] D-NN · pergunta**` com subitens, numa seção de decisões [W].
//
// O id P-NNN nunca casa o regex de UC (scripts/lib/uc-regex.mjs): backlog não vira contrato
// por acidente.
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { ucBlocksInCasos } from '../lib/uc-regex.mjs';
import { isPageScreenPath } from '../qa/page-path.mjs';

const RE_PEDIDO_SUB = /^\s{2,}- Pedido: (P-\d{3})\s*$/;
const RE_ORIGEM_UC = /^- \*\*Pedido de origem:\*\* (P-\d{3})/;
const RE_DECISAO = /^- \*\*\[DECISÃO\] (D-\d{2}) · (.+?)\*\*\s*$/;
const RE_SUB = /^\s{2,}- ([A-ZÀ-Úa-zà-ú ]+): ?(.*)$/;
const RE_UC_HEAD = /^#{2,3} (UC-[A-Z0-9-]+)\s*·\s*(.+)$/;
const RE_STATUS_VERDE = /^- \*\*Status:?\*\*:? *✅/;

export function arquivosCasos(raiz) {
  const out = execFileSync('git', ['ls-files', '*.casos.md'], { cwd: raiz, encoding: 'utf8' });
  return out.split('\n').filter(p => p && !p.startsWith('prototipo-ui/'));
}

/** Título legível a partir da primeira linha de um bullet de backlog, sem os marcadores. */
export function tituloDoBullet(linha) {
  let s = linha.replace(/^\s*- /, '');
  s = s.replace(/^\*\*\[BACKLOG[^\]]*\]\*\*\s*/, '').replace(/^\*\*\[BACKLOG[^\]]*\]\s*/, '**')
    .replace(/^`\[BACKLOG\]`\s*/, '').replace(/^\[BACKLOG[^\]]*\]\s*/, '');
  const corte = s.indexOf(' — ');
  if (corte > 0) s = s.slice(0, corte);
  s = s.replace(/`\[(V0|E0|T0)\]`/g, '').replace(/\*\*/g, '');
  s = s.replace(/\((pedido|comparação)[^)]*\)/gi, '');
  return s.replace(/\s+/g, ' ').trim();
}

export const marcasDe = linha => [...linha.matchAll(/\[(V0|E0|T0)\]/g)].map(m => m[1]);

const selecao = v => { const [tipo = '', texto = '', ...r] = v.split(' | '); return { tipo, texto, nota: r.join(' | ') }; };
const lista = v => (v ? v.split(',').map(x => x.trim()).filter(Boolean) : []);

function campos(subs) {
  const c = {};
  for (const [k, v] of subs) {
    if (k === 'Hoje') c.hoje = selecao(v);
    else if (k === 'Comunicação visual') c.cv = selecao(v);
    else if (k === 'Clientes') c.clientes = lista(v);
    else if (k === 'Etiquetas') c.tags = lista(v);
    else if (k === 'Âncora') c.ancora = v;
    else if (k === 'Fazer') c.fazer = v;
    else if (k === 'Aceite') c.aceite = v;
    else if (k === 'Destrava') c.destrava = lista(v);
    else if (k === 'Estado') c.estado = v;
    else if (k === 'Texto') c.texto = v;
    else if (k === 'Opção') {
      const i = v.indexOf(' — ');
      (c.opcoes ||= []).push(i > 0 ? { nome: v.slice(0, i), texto: v.slice(i + 3) } : { nome: v, texto: '' });
    }
  }
  return c;
}

/** Lê um arquivo .casos.md e devolve { pedidos, decisoes }. `rel` é o path relativo à raiz. */
export function lerArquivo(md, rel) {
  const L = md.split('\n');
  const pedidos = [], decisoes = [];
  let uc = null;
  for (let i = 0; i < L.length; i++) {
    const l = L[i];
    const h = l.match(RE_UC_HEAD);
    if (h) { uc = { id: h[1], titulo: h[2].replace(/`[^`]*`/g, '').replace(/\s+/g, ' ').trim(), linha: i + 1 }; continue; }
    if (/^#{1,3} /.test(l)) { uc = null; continue; }
    const o = l.match(RE_ORIGEM_UC);
    if (o && uc) {
      let testado = false;
      for (let j = i + 1; j < L.length && !/^#{1,3} /.test(L[j]); j++) if (RE_STATUS_VERDE.test(L[j])) testado = true;
      pedidos.push({ id: o[1], arquivo: rel, linha: uc.linha, titulo: uc.titulo, uc: uc.id, estagio: testado ? 'testado' : 'uc', marcas: [] });
      continue;
    }
    if (!/^- \S/.test(l)) continue;
    const corpo = [];
    for (let j = i + 1; j < L.length && /^\s{2,}\S/.test(L[j]); j++) corpo.push(L[j]);
    const subs = corpo.map(x => x.match(RE_SUB)).filter(Boolean).map(m => [m[1].trim(), m[2]]);
    const dec = l.match(RE_DECISAO);
    if (dec) { decisoes.push({ id: dec[1], pergunta: dec[2], arquivo: rel, linha: i + 1, ...campos(subs) }); continue; }
    const p = corpo.map(x => x.match(RE_PEDIDO_SUB)).find(Boolean);
    if (!p) continue;
    const resto = l.indexOf(' — ') > 0 ? l.slice(l.indexOf(' — ') + 3) : '';
    const prosa = [resto, ...corpo.filter(x => !RE_SUB.test(x)).map(x => x.trim())].join(' ').trim();
    pedidos.push({ id: p[1], arquivo: rel, linha: i + 1, titulo: tituloDoBullet(l), marcas: marcasDe(l), texto: prosa, estagio: 'pedido', ...campos(subs) });
  }
  return { pedidos, decisoes };
}

/**
 * O contrato de cada tela: os casos de uso com id (`## UC-...`) de todos os .casos.md
 * versionados. O veredito não vem do arquivo: vem do manifesto de testes que o casos-gate
 * usa (scripts/casos-test-results.json), porque o status escrito pode estar velho.
 */
export function lerContratos(raiz) {
  let manifesto = {};
  try { manifesto = JSON.parse(fs.readFileSync(path.join(raiz, 'scripts/casos-test-results.json'), 'utf8')).ucs || {}; } catch { manifesto = {}; }
  const telas = [];
  // Mesmo recorte do casos-gate: só o casos.md ao lado de uma Page que o page-path reconhece.
  const deTela = rel => { const tsx = rel.replace(/\.casos\.md$/, '.tsx'); return isPageScreenPath(tsx) && fs.existsSync(path.join(raiz, tsx)); };
  for (const rel of arquivosCasos(raiz).filter(deTela)) {
    const md = fs.readFileSync(path.join(raiz, rel), 'utf8');
    const ucs = [];
    let desde = 0;
    // O parser de bloco é o do casos-gate (scripts/lib/uc-regex.mjs), não uma cópia.
    for (const { uc, block } of ucBlocksInCasos(md)) {
      const cabeca = block.split('\n')[0];
      const pos = md.indexOf(cabeca, desde);
      desde = pos + cabeca.length;
      const titulo = cabeca.replace(/^\S+/, '').replace(/`[^`]*`/g, '').replace(/\s+/g, ' ')
        .replace(/^[\s·:—-]+/, '').replace(/[\s·:—-]+$/, '').trim();
      const v = manifesto[uc];
      ucs.push({ id: uc, titulo, linha: md.slice(0, pos).split('\n').length, veredito: v ? v.verdict : 'sem-prova' });
    }
    if (ucs.length) telas.push({ arquivo: rel, ucs });
  }
  return telas;
}

/** Lê todos os .casos.md versionados. Pedido repetido é erro: um id, um lugar. */
export function lerRepositorio(raiz) {
  const pedidos = [], decisoes = [];
  for (const rel of arquivosCasos(raiz)) {
    const r = lerArquivo(fs.readFileSync(path.join(raiz, rel), 'utf8'), rel);
    pedidos.push(...r.pedidos); decisoes.push(...r.decisoes);
  }
  const vistos = new Map();
  for (const p of [...pedidos, ...decisoes]) {
    if (vistos.has(p.id)) throw new Error(`${p.id} aparece em dois lugares: ${vistos.get(p.id)} e ${p.arquivo}:${p.linha}`);
    vistos.set(p.id, `${p.arquivo}:${p.linha}`);
  }
  const ordem = (a, b) => a.id.localeCompare(b.id);
  return { pedidos: pedidos.sort(ordem), decisoes: decisoes.sort(ordem) };
}
