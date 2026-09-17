#!/usr/bin/env node
// placar.mjs — PR-A6 do protocolo de export: o PLACAR de entrega de uma tela vira MEDIDA
// comentada no PR. `entregue X de Y · ausentes <classe> por <motivo>`.
//
// Doc: prototipo-ui/cowork/Wagner/COLAR-NO-CODE-AUTOMACAO-DO-PROTOCOLO.md (PR-A6)
// Insumo: governance/design/targets/<tela>.alvo.json (PR-A1, scripts/design-sync/alvo.mjs)
// Bite-test: scripts/qa/placar.test.mjs (exercita o CLI de FORA — §5 2026-07-30)
//
// ── O QUE ESTE GATE MEDE (e por que NÃO é presence-gate — LC-11) ─────────────────────────
// Ele NÃO procura "existe um comentário no PR". Isso mediria PRESENÇA e passaria verde com
// um comentário vazio. Ele COMPUTA o par entregue/alvo a partir do alvo medido e reprova
// pelo CONTEÚDO: seção cobrada sem motivo declarado · motivo fora do enum · declaração
// apontando pra seção que JÁ foi entregue (ponteiro podre) · declaração no arquivo errado.
// O comentário no PR é a SAÍDA do cálculo, nunca o predicado dele.
//
// ── ONDE A DECLARAÇÃO MORA, E POR QUE NÃO NO alvo.json ──────────────────────────────────
// O plano dizia `ausentes:` no `alvo.json`. Medido 2026-09-17: `alvo.mjs:201` reemite
// `ausentes: []` em TODA medida — declaração escrita ali é apagada no próximo `--alvo`, e o
// README de governance/design/targets/ diz que o arquivo "não se edita à mão". A declaração
// mora no INPUT versionado `<tela>.secoes.json`, na chave `_ausentes` — a convenção `_` que
// a sonda de alvo.mjs já ignora por construção (ALVO_PROBE_SOURCE: `if (id.startsWith('_'))`).
// `ausentes` não-vazio no alvo.json vira REPROVAÇÃO explícita, pro trap virar mensagem.
//
// ── O QUE ELE **NÃO** MEDE (escopo, pra verde não mentir) ────────────────────────────────
// FORMA (filhos · ordem das classes · tokens resolvidos) é do PR-A3 (`secao-check`), que
// ainda NÃO existe. Aqui "entregue" = a seção EXISTE no lado medido. Verde deste placar não
// diz que a forma confere — diz que a seção não sumiu e que toda ausência tem motivo.
//
// ── AS 3 MÉTRICAS, derivadas (nenhuma máquina nova) ─────────────────────────────────────
//   cobertura cumulativa  Σ entregue ÷ Σ alvo, sobre todos os alvos
//   reincidência          quantas seções cobradas por CADA motivo
//   retrabalho            seção que estava entregue e voltou a ausente — derivado do
//                         histórico git do próprio alvo.json (ls-tree + cat-file, imune ao
//                         mangling de `<ref>:<path>` no MSYS, §5 2026-08-23). Em clone RASO
//                         sai "não medi", nunca "0 retrabalho" (§5 2026-07-24 + 2026-07-29).
//
// ── MODOS ───────────────────────────────────────────────────────────────────────────────
//   (default)             placar de todos os alvos · --tela <slug> restringe a um
//   --render <arq.json>   compara contra uma medida do render (mesma forma do alvo)
//   --md                  markdown do comentário de PR (stdout)
//   --json                JSON (consumo por máquina)
//   --check               MORDE: exit 1 quando o placar não fecha
//   --dir <path>          diretório de alvos (default governance/design/targets)
//
// Exit: 0 fecha · 1 não fecha · 2 NÃO CONSEGUI MEDIR (alvo ilegível/ausente).
// O 2 é separado: "não medi" nunca pode se passar por "está são" (§5 2026-07-29).
//
// ── O PR-A8 (`--indice`) — IMPLEMENTADO em 2026-09-17 ───────────────────────────────────
// `--indice` é o placar da LISTA: mede um MÓDULO (threads de um playbook) em vez de uma tela.
// A flag mora aqui, como o A8 pede; a lógica em `scripts/qa/placar-indice.mjs`, que é o
// destino que a própria ponte original sugeria — mantém este arquivo legível e o diff honesto.
// Ele NÃO reusa `agregar()`/`emitirMd()`: aquelas leem `*.alvo.json` (seções de uma tela) e
// o índice é outra forma (threads + provas + `_saida`). Reusa a DOUTRINA — par entregue/alvo,
// enum fechado, exit 0/1/2, "não medi" separado de "falhou". Forçar a mesma função sobre duas
// formas diferentes seria acoplar por semelhança de nome, não por contrato.

import { readFileSync, readdirSync, existsSync } from 'node:fs';
import { dirname, resolve, join, basename, relative, isAbsolute } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync } from 'node:child_process';
import { agregarIndices, descobrirIndices, emitirMdIndice, emitirTextoIndice } from './placar-indice.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, '..', '..');
const DIR_PADRAO = join(ROOT, 'governance', 'design', 'targets');

const argv = process.argv.slice(2);
const flag = (n) => argv.includes(n);
const val = (n, d = null) => { const i = argv.indexOf(n); return i >= 0 && argv[i + 1] && !argv[i + 1].startsWith('--') ? argv[i + 1] : d; };

/** Enum fechado — motivo inventado é reprovação, não tolerância (plano PR-A6). */
export const MOTIVOS = ['sem endpoint', 'campo inexistente', 'decisão [W]'];

class NaoMedi extends Error {}

function lerJson(p) {
  try { return JSON.parse(readFileSync(p, 'utf8')); }
  catch (e) { throw new NaoMedi(`não consegui ler ${basename(p)}: ${e.message.slice(0, 120)}`); }
}

/** Seções de uma medida (alvo ou render) que EXISTEM no DOM medido. */
export function entreguesDe(medida) {
  const s = (medida && medida.secoes) || {};
  return new Set(Object.keys(s).filter((id) => !s[id].ausente));
}

/* ── o placar de UMA tela ───────────────────────────────────────────────────────────────── */
export function placarDeTela({ slug, alvo, declaracao, render = null }) {
  const ids = Object.keys((alvo && alvo.secoes) || {}).sort();
  if (!ids.length) throw new NaoMedi(`${slug}: alvo sem nenhuma seção — "0 de 0" não é 100%`);

  // Sem --render o lado medido é o próprio alvo (o que a sonda achou no espelho servido).
  // COM --render, o lado medido é o render e o alvo vira só o denominador.
  const lado = render ? entreguesDe(render) : entreguesDe(alvo);
  const fonte = render ? 'render' : 'alvo';

  const decl = (declaracao && declaracao._ausentes) || {};
  const entregue = [], cobradas = [], problemas = [];

  for (const id of ids) {
    if (lado.has(id)) {
      entregue.push(id);
      if (decl[id]) problemas.push({ tipo: 'declaracao-podre', id, msg: `"${id}" está ENTREGUE mas segue declarada em _ausentes — motivo apodrecido, remova a entrada` });
      continue;
    }
    const d = decl[id];
    if (!d) {
      problemas.push({ tipo: 'sem-motivo', id, msg: `"${id}" não está no ${fonte} e não tem motivo em _ausentes do ${slug}.secoes.json` });
      cobradas.push({ id, motivo: null, nota: null });
      continue;
    }
    const motivo = typeof d === 'string' ? d : d.motivo;
    if (!MOTIVOS.includes(motivo)) problemas.push({ tipo: 'motivo-invalido', id, msg: `"${id}": motivo ${JSON.stringify(motivo)} fora do enum — use ${MOTIVOS.map((m) => `"${m}"`).join(' · ')}` });
    cobradas.push({ id, motivo: motivo ?? null, nota: (typeof d === 'object' && d.nota) || null });
  }

  for (const id of Object.keys(decl)) {
    if (!ids.includes(id)) problemas.push({ tipo: 'declaracao-orfa', id, msg: `_ausentes declara "${id}", que não é seção deste alvo` });
  }
  const volatil = Array.isArray(alvo.ausentes) ? alvo.ausentes.length : (alvo.ausentes ? Object.keys(alvo.ausentes).length : 0);
  if (volatil) problemas.push({ tipo: 'declaracao-volatil', id: '-', msg: `${slug}.alvo.json traz "ausentes" preenchido — alvo.mjs REESCREVE esse campo a cada medida (alvo.mjs:201). Mova a declaração pra "_ausentes" do ${slug}.secoes.json` });

  return { slug, tela: (alvo && alvo.tela) || slug, fonte, alvoTotal: ids.length, entregue, cobradas, problemas };
}

/* ── retrabalho: seção reaberta (histórico git do alvo) ─────────────────────────────────── */
export function reaberturasEntre(anterior, agora) {
  return [...anterior].filter((id) => !agora.has(id)).sort();
}

export function retrabalhoDe(caminhoRelativo, { root = ROOT } = {}) {
  // Alvo fora do repo não tem histórico AQUI — devolver "0 reaberturas" seria afirmar
  // saúde a partir de não-medição (§5 2026-07-29), e o git sairia vazio em silêncio.
  // `isAbsolute` além do `..`: em OUTRO DRIVE no Windows, `relative()` devolve o caminho
  // ABSOLUTO (`C:/...`), não `..` — testar só o `..` deixava o git vazar um `fatal:` no log
  // do CI (§5 2026-08-07: literal de path não se comporta igual nas duas plataformas).
  if (caminhoRelativo.startsWith('..') || isAbsolute(caminhoRelativo)) return { medido: false, motivo: 'alvo fora do repositório (sem histórico git pra medir)', reaberturas: [] };
  // stderr em `pipe`, nunca herdado: mensagem de git não é saída do placar.
  const git = (args) => execFileSync('git', args, { cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'], env: { ...process.env, MSYS_NO_PATHCONV: '1' } }).trim();
  try {
    // §5 2026-07-24: em clone RASO o histórico é o piso, não a história — não invente zero.
    if (git(['rev-parse', '--is-shallow-repository']) === 'true') return { medido: false, motivo: 'clone raso (precisa de fetch-depth 0 pra medir)', reaberturas: [] };
    const shas = git(['log', '--format=%H', '--', caminhoRelativo]).split('\n').filter(Boolean).reverse();
    if (shas.length < 2) return { medido: true, reaberturas: [] };
    const reaberturas = [];
    let anterior = null;
    for (const sha of shas) {
      // ls-tree + cat-file: imune ao mangling de `<ref>:<path>` no Git Bash (§5 2026-08-23).
      const linha = git(['ls-tree', sha, '--', caminhoRelativo]);
      if (!linha) { anterior = null; continue; }
      const blob = linha.split(/\s+/)[2];
      let medida;
      try { medida = JSON.parse(git(['cat-file', '-p', blob])); } catch { anterior = null; continue; }
      const agora = entreguesDe(medida);
      if (anterior) for (const id of reaberturasEntre(anterior, agora)) reaberturas.push({ id, sha: sha.slice(0, 9) });
      anterior = agora;
    }
    return { medido: true, reaberturas };
  } catch (e) { return { medido: false, motivo: `git indisponível: ${e.message.slice(0, 80)}`, reaberturas: [] }; }
}

/* ── agregação (o PR-A8 reusa esta função pro --indice) ─────────────────────────────────── */
export function agregar(dir, { tela = null, render = null } = {}) {
  if (!existsSync(dir)) throw new NaoMedi(`diretório de alvos não existe: ${dir}`);
  let arquivos = readdirSync(dir).filter((f) => f.endsWith('.alvo.json')).sort();
  if (tela) {
    const esperado = `${tela.replace(/[^a-z0-9-]/gi, '-').toLowerCase()}.alvo.json`;
    arquivos = arquivos.filter((f) => f === esperado);
    if (!arquivos.length) throw new NaoMedi(`--tela ${tela}: ${esperado} não existe em ${dir}`);
  }
  // Corpus vazio NÃO é 100%: "entregue 0 de 0" é o verde vazio clássico (§5 2026-08-04).
  if (!arquivos.length) throw new NaoMedi(`nenhum *.alvo.json em ${dir} — "0 de 0" não é placar`);

  const medidaRender = render ? lerJson(render) : null;
  const telas = arquivos.map((f) => {
    const slug = f.replace(/\.alvo\.json$/, '');
    const secoes = join(dir, `${slug}.secoes.json`);
    const p = placarDeTela({ slug, alvo: lerJson(join(dir, f)), declaracao: existsSync(secoes) ? lerJson(secoes) : null, render: medidaRender });
    p.temDeclaracao = existsSync(secoes);
    p.retrabalho = retrabalhoDe(relative(ROOT, join(dir, f)).split('\\').join('/'));
    return p;
  });

  const somaEntregue = telas.reduce((n, t) => n + t.entregue.length, 0);
  const somaAlvo = telas.reduce((n, t) => n + t.alvoTotal, 0);
  const reincidencia = {};
  for (const t of telas) for (const c of t.cobradas) { const k = c.motivo || '(sem motivo)'; reincidencia[k] = (reincidencia[k] || 0) + 1; }
  const naoMedidos = telas.filter((t) => !t.retrabalho.medido);
  return {
    telas, somaEntregue, somaAlvo,
    cobertura: somaAlvo ? Math.round((somaEntregue / somaAlvo) * 1000) / 10 : 0,
    reincidencia,
    problemas: telas.flatMap((t) => t.problemas.map((p) => ({ ...p, slug: t.slug }))),
    reaberturas: telas.flatMap((t) => t.retrabalho.reaberturas.map((r) => ({ ...r, slug: t.slug }))),
    retrabalhoMedido: naoMedidos.length === 0,
    retrabalhoMotivo: naoMedidos.length ? naoMedidos[0].retrabalho.motivo : null,
  };
}

/* ── saídas ─────────────────────────────────────────────────────────────────────────────── */
const linhaTela = (t) => {
  const aus = t.cobradas.length
    ? ' · ausentes ' + t.cobradas.map((c) => `${c.id} por ${c.motivo ? '`' + c.motivo + '`' : '**(sem motivo)**'}`).join(' · ')
    : '';
  return `**${t.tela}** · entregue ${t.entregue.length} de ${t.alvoTotal}${aus}`;
};

export function emitirMd(r) {
  const L = ['<!-- placar-de-tela -->', '## Placar de tela — PR-A6', ''];
  for (const t of r.telas) L.push(`- ${linhaTela(t)}  <sub>fonte: ${t.fonte}</sub>`);
  L.push('', `**Cobertura cumulativa:** ${r.somaEntregue} de ${r.somaAlvo} (${r.cobertura}%)`);
  const rein = Object.entries(r.reincidencia).sort((a, b) => b[1] - a[1]);
  L.push(`**Reincidência por motivo:** ${rein.length ? rein.map(([m, n]) => `${m} ×${n}`).join(' · ') : '— nenhuma ausência'}`);
  const retr = !r.retrabalhoMedido ? `⚠️ não medido — ${r.retrabalhoMotivo}`
    : r.reaberturas.length ? r.reaberturas.map((x) => `${x.slug}/${x.id} @${x.sha}`).join(' · ')
      : 'nenhuma';
  L.push(`**Retrabalho (seção reaberta):** ${retr}`);
  if (r.problemas.length) {
    L.push('', '### ❌ O placar não fecha', '');
    for (const p of r.problemas) L.push(`- \`${p.slug}\` — ${p.msg}`);
    L.push('', `Cada ausência precisa de motivo em \`_ausentes\` do \`<tela>.secoes.json\`: ${MOTIVOS.map((m) => '`' + m + '`').join(' · ')}.`);
  }
  L.push('', '<sub>Forma (filhos · ordem · tokens) é do PR-A3 `secao-check`, que ainda não existe — verde aqui não afirma fidelidade de forma.</sub>');
  return L.join('\n');
}

function emitirTexto(r) {
  for (const t of r.telas) console.log(linhaTela(t).replace(/\*\*|`/g, ''));
  console.log(`cobertura cumulativa: ${r.somaEntregue} de ${r.somaAlvo} (${r.cobertura}%)`);
  for (const p of r.problemas) console.error(`  X ${p.slug} — ${p.msg}`);
}

/* ── CLI ────────────────────────────────────────────────────────────────────────────────── */
function main() {
  // PR-A8 — placar da LISTA. `--indice <00-INDICE.md>` para um módulo, `--todos` para somar,
  // `--thread NN` recorta uma thread (é o que o `/onda --thread` do A7 consome).
  if (flag('--indice') || flag('--todos')) {
    const raiz = resolve(val('--root', ROOT));
    const um = val('--indice');
    const r = agregarIndices(raiz, um ? [um.split('\\').join('/')] : descobrirIndices(raiz), { thread: val('--thread') });
    if (flag('--json')) console.log(JSON.stringify(r, null, 2));
    else if (flag('--md')) console.log(emitirMdIndice(r));
    else emitirTextoIndice(r, { proximo: flag('--proximo') });
    // Só o que FALHOU morde. Não-medição é reportada e NÃO reprova — acusar o inocente por
    // falta de instrumento é LC-33 (§5 2026-09-03), e o eixo já sai fail-closed no `feito`.
    if (flag('--check') && !r.fecha) {
      console.error(`\nplacar da lista NÃO fecha: ${r.somaFeito} de ${r.somaTotal} thread(s) entregues.`);
      return 1;
    }
    return 0;
  }

  const r = agregar(val('--dir', DIR_PADRAO), { tela: val('--tela'), render: val('--render') });
  if (flag('--json')) console.log(JSON.stringify(r, null, 2));
  else if (flag('--md')) console.log(emitirMd(r));
  else emitirTexto(r);

  if (flag('--check') && r.problemas.length) {
    console.error(`\nplacar NÃO fecha: ${r.problemas.length} problema(s) de declaração. Motivos válidos: ${MOTIVOS.join(' · ')}.`);
    return 1;
  }
  return 0;
}

// Só roda o CLI quando ESTE arquivo é o ponto de entrada. Sem esta guarda o `import()` de
// um teste dispara main() e mata o processo do teste — que passaria por NÃO-EXECUÇÃO (LC-13).
// Medido 2026-09-17: com o import nu, 3 asserts do placar.test.mjs nunca rodavam e a suíte
// ainda saía "24/24 ok". É o mesmo trap que alvo.mjs documenta sobre o design-diff (CLI-only).
if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try { process.exit(main()); }
  // `e.naoMedi` é o contrato com placar-indice.mjs, que tem a própria classe NaoMedi:
  // `instanceof` não cruza módulos, e sem isto um "não medi" do A8 sairia 1 (= "não fecha"),
  // que é exatamente colapsar não-medição num estado do objeto (§5 2026-07-29).
  catch (e) { const nm = e instanceof NaoMedi || e?.naoMedi === true; console.error(`${nm ? 'NÃO MEDI' : 'FALHOU'}: ${e.message}`); process.exit(nm ? 2 : 1); }
}
