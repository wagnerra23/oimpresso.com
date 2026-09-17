#!/usr/bin/env node
// pedido.mjs — PR-A7 do protocolo de export: o PEDIDO DE SEÇÃO vira DERIVADO.
//
// Doc: prototipo-ui/cowork/Wagner/COLAR-NO-CODE-PROTOCOLO-COWORK-EXPORT.md §4 (4 blocos),
//      §4-bis (ancoragem dupla), §2-quater (sessão limpa / read-order).
// Aposenta: eu escrevendo o pedido à mão e a sessão seguinte descobrindo que ele estava
// incompleto DEPOIS de abrir (o "teste do estranho" falhando em produção).
//
// ── O QUE ESTE ARQUIVO **NÃO** É (LC-19: máquina paralela a dono existente) ───────────────
// Ele não mede nada e não resolve âncora sozinho. Cada bloco vem do dono que já existe:
//   ALVO    → governance/design/targets/<slug>.alvo.json  (dono: scripts/design-sync/alvo.mjs)
//   ÂNCORA  → resolveAncora()                             (dono: scripts/design/ancora.mjs)
//   REUSO   → <Tela>.design-spec.json                     (dono: scripts/design-spec-gen.mjs)
//   LEI     → charter frontmatter                         (dono: o charter da tela)
// O que ele acrescenta é a COSTURA: juntar os quatro numa folha auto-suficiente. Nenhum
// número é escrito aqui — se o número está errado, re-rode o dono dele (§5 2026-07-17).
//
// ── stdout POR DEFAULT, e isso é decisão, não preguiça ───────────────────────────────────
// Pedido é derivado dos 4 insumos; gravá-lo cria uma 5ª cópia que apodrece no dia seguinte
// (ADR 0256 · mesma doutrina do `--mapa` do alvo.mjs, que também nunca grava). `--out` existe
// pra colar no Issue, é opt-in, e RECUSA escrever sob `prototipo-ui/cowork/` — aquilo é
// espelho de leitura e edição minha ali some no próximo `--export-from` (ADR 0374).
//
// ── "bloco vazio REPROVA o pedido" (§4) é o aceite, e é mecânico ─────────────────────────
// O bloco B.dados NÃO é derivável do protótipo: por §3-bis ele exige ler o `main` (Model /
// Service / coluna real). Então ele mora no `secoes.json` — ao lado do alvo, versionado, no
// mesmo arquivo que o A1 já consome — na chave `dado` de cada seção. Sem ela o pedido **não
// sai** (exit 1) e o script diz onde preencher. Inventar dado aqui seria a lápide de
// 2026-06-05 (derivar do lugar errado) com passos extras.
// A chave é INERTE pro A1: a sonda dele lê só `.seletor` e `.campos` — provado por bite-test
// no `--selftest`, rodando a string real `ALVO_PROBE_SOURCE` com e sem `dado` (LC-22: não se
// muda artefato que a máquina lê sem RODAR a máquina com a mudança aplicada).
//
// ── DEPENDÊNCIA DECLARADA: o PR-A6 (placar) ─────────────────────────────────────────────
// O item 6 do bloco D é o PLACAR, e o dono dele é `scripts/qa/placar.mjs` (PR-A6). Este
// arquivo NÃO o implementa: seria máquina paralela ao dono (LC-19). Se ele existe na árvore
// é `existsSync` quem responde, A CADA GERAÇÃO (`placarComo()`) — e de propósito nenhuma
// linha aqui afirma esse estado em presente, porque afirmação de estado apodrece no primeiro
// merge (LC-10). Ausente ⇒ o pedido diz "à MÃO" e nomeia o A6; presente ⇒ imprime o comando,
// sem ninguém editar nada. O PR-A8 estende os dois — `placar --indice` e `--thread NN` aqui.
//
// Uso:
//   node scripts/design-sync/pedido.mjs --tela <Mod/Tela> --secao <id> [--onda <n.s>] [--out <arq.md>]
//   node scripts/design-sync/pedido.mjs --tela <Mod/Tela> --secoes      # lista as seções medidas
//   node scripts/design-sync/pedido.mjs --selftest
//
// Exit: 0 pedido completo · 1 REPROVADO (bloco vazio / ponteiro podre) · 2 NÃO MEDI (insumo ausente).
// O 2 é separado de propósito: "não tenho o alvo" nunca pode se passar por "o pedido está ruim"
// (§5 2026-07-29 — não-medi não colapsa em veredito).

import { readFileSync, existsSync, writeFileSync, readdirSync } from 'node:fs';
import { dirname, resolve, join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { resolveAncora, frontmatter } from '../design/ancora.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, '..', '..');
const DIR_ALVOS = join(ROOT, 'governance', 'design', 'targets');

const argv = process.argv.slice(2);
const flag = (n) => argv.includes(n);
const val = (n, d = null) => { const i = argv.indexOf(n); return i >= 0 && argv[i + 1] && !argv[i + 1].startsWith('--') ? argv[i + 1] : d; };
const rel = (p) => relative(ROOT, p).split('\\').join('/');

class NaoMedi extends Error {}
class Reprova extends Error {}

/* ── READ-ORDER (§2-quater) — um dono, os outros apontam ─────────────────────────────────
   Os 5 fixos; os 3 derivados da tela entram em `readOrder()`. Ponteiro que não resolve em
   arquivo REPROVA o pedido: read-order que manda ler o que não existe é instrução de
   desistência disfarçada de completude (§5 2026-09-01). */
export const READ_ORDER_FIXO = [
  ['como se opera', 'memory/reference/prototipo-ui/COWORK-ESTRUTURA-E-TELAS.md'],
  ['frescor prod × protótipo', 'memory/reference/prototipo-ui/FRESCOR-PRODUCAO-vs-PROTOTIPO.md'],
  ['pré-flight da tela', 'memory/reference/prototipo-ui/PRE-FLIGHT-TELA.md'],
  ['o erro catalogado', 'memory/proibicoes.md'],
  ['o erro catalogado (design)', 'memory/LICOES_CC.md'],
  ['o ACERTO catalogado (não refazer)', 'prototipo-ui/cowork/Wagner/COLAR-NO-CODE-ACERTOS-E-LICOES.md'],
];

/** Read-order completo: os fixos + charter/casos/paridade da tela. */
export function readOrder({ charter = null, modulo = null, raiz = ROOT } = {}) {
  const itens = READ_ORDER_FIXO.map(([o, p]) => ({ oque: o, path: p }));
  if (charter) {
    itens.push({ oque: 'a LEI da tela', path: charter });
    const casos = charter.replace(/\.charter\.md$/, '.casos.md');
    if (existsSync(join(raiz, casos))) itens.push({ oque: 'o contrato UC da tela', path: casos });
  }
  if (modulo) {
    const dir = join(raiz, 'memory', 'requisitos', modulo);
    if (existsSync(dir)) {
      for (const f of readdirSync(dir).filter((f) => f.startsWith('PARIDADE-')).sort()) {
        itens.push({ oque: 'ondas passadas (ordem · veredito · ausentes)', path: `memory/requisitos/${modulo}/${f}` });
      }
    }
  }
  return itens.map((i) => ({ ...i, existe: existsSync(join(raiz, i.path)) }));
}

/* ── insumos ─────────────────────────────────────────────────────────────────────────────── */
/** Casa `Mod/Tela` com o arquivo de alvo, ignorando separadores (`-`/`--`/`/`). */
export function acharAlvo(tela, dir = DIR_ALVOS) {
  const chave = (s) => s.toLowerCase().replace(/[^a-z0-9]/g, '');
  if (!existsSync(dir)) return null;
  const alvo = readdirSync(dir).find((f) => f.endsWith('.alvo.json') && chave(f.replace('.alvo.json', '')) === chave(tela));
  return alvo ? join(dir, alvo) : null;
}

function lerJson(p) { return JSON.parse(readFileSync(p, 'utf8')); }

function shaCurto() {
  const r = spawnSync('git', ['rev-parse', '--short', 'HEAD'], { cwd: ROOT, encoding: 'utf8' });
  return r.status === 0 ? r.stdout.trim() : 'sem-git';
}

/* ── montagem ────────────────────────────────────────────────────────────────────────────── */
const T = { roxo: 'oklch(0.55 0.15 295) light · oklch(0.70 0.15 295) dark' };

/** Monta o pedido. Puro o bastante pra ter bite-test (recebe os insumos já lidos). */
export function montar({ tela, secao, onda, alvo, secoes, ancora, spec, hoje, sha }) {
  const spec_ = secoes[secao];
  if (!spec_) throw new Reprova(`seção "${secao}" não está no secoes.json — há: ${Object.keys(secoes).filter((k) => !k.startsWith('_')).join(' · ')}`);
  const med = alvo.secoes?.[secao];
  if (!med) throw new NaoMedi(`seção "${secao}" não foi medida no alvo.json — re-rode o A1 (npm run alvo:medir)`);
  if (med.ausente) throw new NaoMedi(`seção "${secao}" saiu AUSENTE do DOM na medição — o seletor não casa; re-colha com npm run alvo:mapa`);

  const dado = (typeof spec_ === 'object' && spec_.dado) || null;
  if (!dado) {
    throw new Reprova(
      `bloco B.dados VAZIO pra "${secao}" — e bloco vazio reprova o pedido (§4).\n` +
      `  Preencha: governance/design/targets/<slug>.secoes.json → "${secao}".dado\n` +
      '  O que entra: Model/Service/coluna REAL do main (§3-bis manda LER o main pra este bloco).\n' +
      '  Sem fonte pro slot ⇒ escreva o motivo ("sem endpoint" / "coluna inexistente"): vira "—" na tela + placar.\n' +
      '  Não invente aqui — derivar dado do protótipo é a lápide §5 2026-06-05.');
  }

  const fm = ancora.fm || {};
  const mod = tela.split('/')[0];
  const layout = ancora.ancoras?.[0];
  const outras = Object.keys(secoes).filter((k) => !k.startsWith('_') && k !== secao);
  const ausentes = (alvo.ausentes || []).filter((a) => typeof a !== 'object' || !a.secao || a.secao === secao);
  const est = Object.entries(med.estilo || {}).map(([k, v]) => `${k}=${v}`).join(' · ');
  const comps = spec ? [...(spec.components?.ui || []), ...(spec.components?.shared || [])] : null;

  const L = [];
  const p = (s = '') => L.push(s);
  p(`EXPORT ${mod} · ONDA ${onda} — seção ${secao} (${med.seletor})`);
  p(`> DERIVADO por \`scripts/design-sync/pedido.mjs\` em ${hoje} · repo @ ${sha}. Não editar: re-rode o comando.`);
  p(`> Auto-suficiente por construção (§2-quater): alvo · âncora · dado · aceite estão AQUI. Se faltar algo, a falha é do pedido.`);
  p();
  p('## 0 · READ-ORDER — leia nesta ordem, do `main`, nunca de cópia');
  readOrder({ charter: ancora.charter, modulo: mod }).forEach((i, n) => p(`${n + 1}. \`${i.path}\` — ${i.oque}`));
  p(`${readOrder({ charter: ancora.charter, modulo: mod }).length + 1}. **este pedido** + os arquivos da âncora abaixo (§3-bis: 2-4, no momento da onda).`);
  p();
  p('```');
  p('A · IDENTIDADE — ANCORAGEM DUPLA (§4-bis)');
  p(`  alvo (layout)   : ${layout ? `${layout.valor}   [${layout.tipo}]` : '⛔ SEM ÂNCORA — não invente; registre ou pergunte'}`);
  p(`  âncora (código) : ${ancora.telaViva || '⛔ charter sem component'}`);
  p(`  spec derivada   : ${spec ? `${spec.path.replace(/\.tsx$/, '.design-spec.json')} (nunca editar à mão)` : 'ausente — rode `npm run design-spec:write-all`'}`);
  p(`  charter (lei)   : ${ancora.charter} · status ${fm.status || '?'} · permissão ${fm.permissao || '—'}`);
  p(`  rota destino    : ${fm.page || '?'} · Page ${tela}`);
  // arquétipo/persona do §4 NÃO são deriváveis de insumo nenhum — então o pedido aponta onde
  // estão em vez de chutar. "O que não foi lido no turno não entra preenchido" (§3-bis).
  p(`  arquétipo/persona: não derivável — §Mission do charter (audiência primária) + vocabulário do §3`);
  p(`  veredito final  : design-diff --compare --check nos DOIS renders (T7) — nunca no olho (LC-06)`);
  p();
  p('B · NÃO INVENTAR (reusar, não recriar)');
  p(`  componentes   : ${comps && comps.length ? comps.join(', ') : '@/Components/ui (REGISTRY) — nunca hand-roll'}`);
  p(`  hooks         : ${spec?.hooks?.length ? spec.hooks.join(', ') : '—'}`);
  p(`  CSS           : bundle do módulo — ZERO CSS novo, zero utilitária de cor/espaço no que o bundle cobre`);
  p(`  tokens        : roxo ${T.roxo} · zero hex cru`);
  p(`  dados         : ${dado}`);
  p(`  copy          : literal do protótipo · PT-BR · sentence case`);
  p();
  p(`C · ALVO MEDIDO — o que REPROVA (de ${rel(DIR_ALVOS)}/…, medido em ${alvo.url || '?'}, tema ${alvo.base?.theme || '?'})`);
  p(`  seletor       : ${med.seletor}`);
  p(`  estrutural    : ${med.nos} nó(s) · ${med.filhos} filhos NESTA ordem: [${(med.ordemClasses || []).join(', ')}]`);
  p(`  estilo        : ${est || '—'}`);
  p(`  caixa         : ${med.rect?.w}×${med.rect?.h}px · truncado: ${med.truncado}`);
  p(`  proveniência  : __oiLazyDone + ${alvo.quieto_ms ?? 400}ms quieto${alvo.aguardou_sumir ? ` + aguardou sumir "${alvo.aguardou_sumir}"` : ''} · ${alvo.nos_totais} nós na página`);
  p(`  ausentes      : ${ausentes.length ? ausentes.map((a) => (typeof a === 'object' ? `${a.classe || a.nome} por ${a.motivo}` : a)).join(' · ') : 'nenhum declarado'}`);
  p();
  p('D · COMO VALIDAR (recibo do PR — §4)');
  p('  1 contagem e ORDEM batem com o alvo acima');
  p('  2 cada comportamento com o teste que o prova');
  p('  3 node scripts/design/design-diff.mjs --compare <prod.json> <design.json> --check → 0 DIVERGE(bug) neste seletor');
  p('    ⚠️ o --check fala em 3 códigos: 0 igual · 1 divergiu · 2 NÃO MEDI. O 2 NÃO é divergência —');
  p('       é o design vindo de espelho cuja fidelidade não está provada. Feche a rodada primeiro:');
  p('       node scripts/governance/cowork-mirror-freshness.mjs --ledger');
  p('  4 screenshot prod autenticado · dark · 1280px');
  p(`  5 ${(ancora.charter || '').replace(/\.charter\.md$/, '.casos.md')} com ≥1 UC da seção citado por teste — MESMO PR`);
  p(`  6 PLACAR no corpo do PR: "entregue X de Y · ausentes <nome> por <motivo>"  ${placarComo()}`);
  p('  7 bloco de contrato destilado no charter — MESMO PR');
  p('  8 github.md: linha do ciclo + "bundle regenerado (<data> · N arquivos)"');
  p('  9 ACERTOS: bloco do ciclo em COLAR-NO-CODE-ACERTOS-E-LICOES.md (≥1 acerto com sha OU "nenhum medido")');
  p();
  p(`ESCOPO FECHADO: só "${secao}". NÃO TOCAR: ${outras.length ? outras.join(' · ') : '(nenhuma vizinha medida)'} · shell · bundle CSS.`);
  p('PARAR SE: a âncora não abrir · o dado acima não existir no main · o alvo divergir do que você mede.');
  p('  Nesse caso a saída é PERGUNTAR — não é escolher. (§5 2026-07-16: anti-padrão inventado parece canon.)');
  p('```');
  return L.join('\n') + '\n';
}

// Por que o item 3 gasta 3 linhas com exit code: quem executa a onda toma o `exit 2` do guarda
// de frescor e lê como "o gate quebrou" ou, pior, como "divergiu" — e aí conserta a tela em vez
// de fechar a rodada do espelho. Deliberadamente NÃO se restateia aqui a CONDIÇÃO do guarda
// (medida 2026-09-17 em `design-diff.mjs:1267` — só morde com design de espelho local; prod×prod
// segue livre): a condição é do dono e pode mudar, e restatear comportamento alheio apodrece
// (§5 2026-07-17). O que o pedido carrega é o vocabulário 0/1/2, que é convenção do repo inteiro,
// + o comando do dono da rodada. Chegou por aviso de sessão irmã e foi MEDIDO antes de propagar
// (§5 2026-07-26: correção de peer é hipótese a testar, não patch a aplicar).

/** O item 6 depende do PR-A6 (`scripts/qa/placar.mjs`) — derivado, nunca afirmado de cor. */
function placarComo(raiz = ROOT) {
  return existsSync(join(raiz, 'scripts', 'qa', 'placar.mjs'))
    ? '→ `node scripts/qa/placar.mjs` (PR-A6)'
    : '→ à MÃO: o PR-A6 (`scripts/qa/placar.mjs`, que comentaria isto no PR) ainda não existe nesta árvore';
}

/* ── CLI ─────────────────────────────────────────────────────────────────────────────────── */
async function gerar(tela, secao) {
  const arqAlvo = acharAlvo(tela);
  if (!arqAlvo) throw new NaoMedi(`sem alvo medido pra "${tela}" em ${rel(DIR_ALVOS)} — rode o PR-A1 antes (npm run alvo:medir). Há: ${existsSync(DIR_ALVOS) ? readdirSync(DIR_ALVOS).filter((f) => f.endsWith('.alvo.json')).join(' · ') || '(nenhum)' : '(diretório ausente)'}`);
  const arqSecoes = arqAlvo.replace('.alvo.json', '.secoes.json');
  if (!existsSync(arqSecoes)) throw new NaoMedi(`alvo existe mas ${rel(arqSecoes)} não — o alvo não é re-executável sem ele (README do diretório)`);

  const alvo = lerJson(arqAlvo);
  const secoes = lerJson(arqSecoes);
  if (!secao) {
    const ids = Object.keys(secoes).filter((k) => !k.startsWith('_'));
    console.log(`seções medidas de ${tela} (${ids.length}):`);
    for (const id of ids) {
      const s = secoes[id];
      const temDado = typeof s === 'object' && s.dado;
      console.log(`  ${temDado ? 'ok ' : 'B! '}${id.padEnd(18)} ${typeof s === 'string' ? s : s.seletor}${temDado ? '' : '   ← sem .dado: o pedido REPROVA'}`);
    }
    return 0;
  }

  const ancora = await resolveAncora(tela, { repoRoot: ROOT });
  if (!ancora.ok) throw new NaoMedi(`${ancora.motivo} (query "${tela}")`);
  // o frontmatter do charter escolhido — a lei da tela, lida no turno
  ancora.fm = frontmatter(readFileSync(join(ROOT, ancora.charter), 'utf8'));

  const podres = readOrder({ charter: ancora.charter, modulo: tela.split('/')[0] }).filter((i) => !i.existe);
  if (podres.length) throw new Reprova(`read-order aponta pra arquivo que NÃO existe: ${podres.map((i) => i.path).join(' · ')} — conserte o ponteiro antes de gerar pedido`);

  let spec = null;
  if (ancora.telaViva) {
    const p = join(ROOT, ancora.telaViva.replace(/\.tsx$/, '.design-spec.json'));
    if (existsSync(p)) spec = { ...lerJson(p), path: ancora.telaViva };
  }

  const txt = montar({
    tela, secao, onda: val('--onda', '?'), alvo, secoes, ancora, spec,
    hoje: new Date().toISOString().slice(0, 10), sha: shaCurto(),
  });

  const out = val('--out');
  if (out) {
    if (/prototipo-ui[\\/]cowork[\\/]/.test(resolve(out))) throw new Reprova('--out sob prototipo-ui/cowork/ é RECUSADO: aquilo é espelho de leitura e some no próximo --export-from (ADR 0374)');
    writeFileSync(resolve(out), txt);
    console.log(`pedido gravado: ${out}`);
  } else {
    process.stdout.write(txt);
  }
  return 0;
}

/* ── selftest ────────────────────────────────────────────────────────────────────────────── */
/** A sonda REAL do A1, lida do fonte dele — `import` está fora de questão: `alvo.mjs` é
 *  CLI-only (o corpo chama `main().then(process.exit)`, e importar mata este processo — o
 *  mesmo motivo pelo qual ele próprio consome o `design-diff` por subprocesso). Ler o texto
 *  do dono não é cópia: se ele mudar a sonda, este bite-test passa a exercitar a nova. */
export function sondaDoAlvo(raiz = HERE) {
  const src = readFileSync(join(raiz, 'alvo.mjs'), 'utf8');
  const m = src.match(/export const ALVO_PROBE_SOURCE = `([\s\S]*?)`;/);
  if (!m) throw new Error('não achei ALVO_PROBE_SOURCE em alvo.mjs — o bite-test do LC-22 ficaria MUDO');
  if (m[1].includes('${')) throw new Error('ALVO_PROBE_SOURCE passou a interpolar — este extrator não serve mais');
  return new Function('return `' + m[1] + '`')(); // mesma desescapa que o parser faz no fonte
}

async function selftest() {
  const checks = [];
  const ok = (nome, cond, detalhe = '') => checks.push({ nome, ok: !!cond, detalhe });

  ok('acharAlvo casa Mod/Tela com o arquivo (ignora -/--//)',
    acharAlvo('Jana/Index') && acharAlvo('Jana/Index') === acharAlvo('jana--index'));
  ok('acharAlvo não inventa alvo pra tela inexistente', acharAlvo('Nao/Existe') === null);

  const ro = readOrder({ charter: 'resources/js/Pages/Jana/Index.charter.md', modulo: 'Jana' });
  ok('read-order: todo ponteiro resolve em arquivo (controle positivo)',
    ro.every((i) => i.existe), ro.filter((i) => !i.existe).map((i) => i.path).join(' · '));
  ok('read-order puxa charter + casos + PARIDADE da tela', ro.length > READ_ORDER_FIXO.length);

  // bloco B vazio REPROVA — com controle negativo (com `dado`, passa)
  const base = {
    tela: 'Jana/Index', secao: 's', onda: '1.1', hoje: '2026-01-01', sha: 'abc1234',
    alvo: { url: 'u', base: { theme: 'dark' }, secoes: { s: { seletor: '.s', nos: 1, filhos: 2, ordemClasses: ['a', 'b'], estilo: {}, rect: { w: 1, h: 1 }, truncado: false } } },
    ancora: { ok: true, charter: 'resources/js/Pages/Jana/Index.charter.md', telaViva: 'resources/js/Pages/Jana/Index.tsx', ancoras: [{ tipo: 't', valor: 'v' }], fm: {} },
    spec: null,
  };
  let rc = null;
  try { montar({ ...base, secoes: { s: { seletor: '.s' } } }); rc = 'passou'; } catch (e) { rc = e instanceof Reprova ? 'reprovou' : 'outro'; }
  ok('bloco B.dados vazio REPROVA o pedido (§4)', rc === 'reprovou', `rc=${rc}`);
  const txt = montar({ ...base, secoes: { s: { seletor: '.s', dado: 'Meta::where(ativo) + ApuracaoService' } } });
  ok('com .dado o pedido SAI e responde as 4 perguntas',
    /A · IDENTIDADE/.test(txt) && /ApuracaoService/.test(txt) && /2 filhos NESTA ordem/.test(txt) && /D · COMO VALIDAR/.test(txt));
  ok('2 gerações no mesmo dia dão texto idêntico (derivado, não retrato)',
    txt === montar({ ...base, secoes: { s: { seletor: '.s', dado: 'Meta::where(ativo) + ApuracaoService' } } }));

  // LC-22: a chave `dado` é INERTE pra sonda do A1 — rodando a string REAL dele num DOM stub.
  const filho = { getAttribute: () => 'item b', tagName: 'DIV' };
  const el = { children: [filho], getAttribute: () => 'raiz', tagName: 'SECTION', scrollWidth: 10, clientWidth: 10, getBoundingClientRect: () => ({ width: 7, height: 3 }) };
  const rodar = (cfg) => new Function('document', 'getComputedStyle', 'window', `return ${sondaDoAlvo()}`)(
    { querySelectorAll: () => [el] }, () => ({ display: 'flex' }), { __ALVO_SECOES: cfg });
  const semDado = rodar({ s: { seletor: '.s' } });
  ok('CONTROLE POSITIVO: a sonda extraída de fato MEDE (se o extrator pegasse lixo, isto cai)',
    semDado.s?.nos === 1 && semDado.s.filhos === 1 && semDado.s.ordemClasses[0] === 'item' && semDado.s.rect.w === 7,
    JSON.stringify(semDado.s));
  const comDado = { s: { seletor: '.s', dado: 'qualquer coisa' } };
  ok('LC-22: `dado` no secoes.json NÃO muda o que o alvo.mjs mede (bite-test na sonda real)',
    JSON.stringify(semDado) === JSON.stringify(rodar(comDado)));
  // MUTAÇÃO: se a sonda passasse a ler `dado`, o check acima TEM de cair. Sem isto ele seria
  // carimbo — verde compatível com "não mede nada" (§5 2026-09-05, bite-test por contagem).
  const mut = join(tmpdir(), `pedido-mut-${process.pid}`);
  mkdirSync(mut, { recursive: true });
  writeFileSync(join(mut, 'alvo.mjs'), readFileSync(join(HERE, 'alvo.mjs'), 'utf8')
    .replace('truncado: el.scrollWidth > el.clientWidth + 2,', 'truncado: el.scrollWidth > el.clientWidth + 2, dado: spec.dado,'));
  const rodarMut = (cfg) => new Function('document', 'getComputedStyle', 'window', `return ${sondaDoAlvo(mut)}`)(
    { querySelectorAll: () => [el] }, () => ({ display: 'flex' }), { __ALVO_SECOES: cfg });
  ok('…e MORDE: sonda mutada pra ler `dado` faz o check acima cair',
    JSON.stringify(rodarMut({ s: { seletor: '.s' } })) !== JSON.stringify(rodarMut(comDado)));

  for (const c of checks) console.log(`${c.ok ? 'ok  ' : 'X   '}${c.nome}${c.detalhe ? ' — ' + c.detalhe : ''}`);
  const falhou = checks.filter((c) => !c.ok).length;
  console.log(`\n${checks.length - falhou}/${checks.length} ok`);
  return falhou ? 1 : 0;
}

async function main() {
  if (flag('--selftest')) return selftest();
  const tela = val('--tela');
  if (!tela) { console.error('uso: --tela <Mod/Tela> [--secao <id> | --secoes] [--onda <n.s>] [--out <arq.md>] | --selftest'); return 2; }
  return gerar(tela, flag('--secoes') ? null : val('--secao'));
}

main()
  .then((rc) => process.exit(rc))
  .catch((e) => {
    const naoMedi = e instanceof NaoMedi;
    console.error(`${naoMedi ? 'NÃO MEDI' : 'REPROVADO'}: ${e.message}`);
    process.exit(naoMedi ? 2 : 1);
  });
