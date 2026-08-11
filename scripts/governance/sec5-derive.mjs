#!/usr/bin/env node
// @ts-check
/**
 * sec5-derive.mjs — o §5 do `memory/proibicoes.md` passa a ser DERIVADO.
 *
 * ── O PROBLEMA (medido 2026-08-11, não estimado) ────────────────────────────────────
 * `CLAUDE.md:76` faz `@memory/proibicoes.md` → o arquivo INTEIRO entra em TODA sessão.
 * Os corpos das lápides §5 somavam **349.215 chars = 83,9% do arquivo**, crescendo
 * **1,55 lápide/dia** há 67 dias, com `teto_declarado: null` (medida do `lapide-recheck`).
 * O próprio `lapide-recheck.mjs:209` já registrava "~106k tokens em TODA sessão".
 *
 * ── A SEPARAÇÃO (por que ESTA linha, e não outra) ───────────────────────────────────
 * Medido no corpus real: o campo **"O limite"** — a regra positiva, o que NÃO fazer — é
 * **22,5%** dos corpos (78.558 chars); o resto (o que foi tentado · por que caiu ·
 * evidência · recibos) é **77,5%** (270.657 chars). O limite é a parte que PREVINE; o
 * resto é arqueologia, valiosa quando alguém investiga e dispensável em toda sessão.
 * Extração é mecânica: 103 das 105 entradas têm o campo, em 3 formatos consistentes.
 *
 * ── POR QUE DERIVADO, E NÃO "DOIS LUGARES PRA ESCREVER" ─────────────────────────────
 * Espelhar à mão o limite em dois arquivos garante drift no primeiro esquecimento
 * (ADR 0256: *derivado+enforçado sobrevive; escrito+lembrado apodrece*). Então:
 *   FONTE   = memory/licoes-rejeitadas.md  (lápides COMPLETAS, append-only Tier 0)
 *   DERIVADO= região §5 de memory/proibicoes.md (headers + só o bloco "O limite")
 * Escreve-se num lugar só. `--check` no CI impede o derivado de drifar da fonte.
 *
 * ── O QUE ESTE SCRIPT NÃO É (as lápides §5 que ele poderia virar, e não vira) ────────
 *   • NÃO apaga lápide: `--migrate` MOVE o corpo integral pra fonte; nada é perdido, e
 *     o git preserva a origem. Append-only Tier 0 continua valendo NA FONTE.
 *   • NÃO é presence-gate: `--check` compara o TEXTO derivado byte a byte contra o que
 *     está no arquivo — não checa "campo presente / seção não-vazia" (§5 07-01/07-09/07-16).
 *   • NÃO é campo auto-declarado: nada de `verificado_em`/`gerado_em` que o agente escreve
 *     pra silenciar alarme (§5 07-01/07-09). Re-deriva da fonte a cada corrida.
 *   • NÃO agrega nota nem conta "cobertura": o §5 não tem métrica de qualidade aqui.
 *
 * USO (raiz do repo):
 *   node scripts/governance/sec5-derive.mjs --migrate   # one-shot: extrai a fonte do §5 atual
 *   node scripts/governance/sec5-derive.mjs --check     # CI: derivado == fonte? (exit 1 se drift)
 *   node scripts/governance/sec5-derive.mjs --write     # regrava o §5 derivado
 *   node scripts/governance/sec5-derive.mjs --selftest
 */
import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { resolve, dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const REPO_ROOT = process.env.SEC5_ROOT
  ? resolve(process.env.SEC5_ROOT)
  : resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');

const PROIBICOES = join(REPO_ROOT, 'memory', 'proibicoes.md');
const FONTE = join(REPO_ROOT, 'memory', 'licoes-rejeitadas.md');

const H_SEC5 = /^##\s+Ideias avaliadas e DESCARTADAS/i;
const H_LAPIDE = /^### 20\d\d-\d\d-\d\d/;
const H_LIMITE = /^- \*\*O limite/;
const BULLET_TOPO = /^- \*\*/;

/**
 * Recorta a região §5 de um texto: [inicio, fim) em índices de linha.
 * Fim = próximo cabeçalho `## ` (nunca `###`), ou EOF.
 * @returns {{ start: number, end: number } | null}
 */
export function regiaoSec5(linhas) {
  const start = linhas.findIndex((l) => H_SEC5.test(l));
  if (start === -1) return null;
  let end = linhas.length;
  for (let i = start + 1; i < linhas.length; i++) {
    if (/^## /.test(linhas[i])) { end = i; break; }
  }
  return { start, end };
}

/**
 * Quebra um bloco de linhas em [preambulo, lapides[]].
 * Lápide = do `### <data>` até o próximo `### <data>` (ou fim).
 * @returns {{ preambulo: string[], lapides: {head: string, body: string[]}[] }}
 */
export function partir(linhas) {
  const preambulo = [];
  const lapides = [];
  let cur = null;
  for (const l of linhas) {
    if (H_LAPIDE.test(l)) { if (cur) lapides.push(cur); cur = { head: l, body: [] }; }
    else if (cur) cur.body.push(l);
    else preambulo.push(l);
  }
  if (cur) lapides.push(cur);
  return { preambulo, lapides };
}

/**
 * Extrai TODOS os blocos "O limite" do corpo: de cada bullet `- **O limite` até o
 * próximo bullet de topo `- **` (exclusivo) ou fim.
 *
 * ⚠️ PLURAL de propósito, e isto foi MEDIDO (2026-08-11, `--audit`): a lápide
 * `2026-08-03` tem DOIS eixos e portanto DOIS limites. A 1a versão deste extrator
 * pegava só o primeiro `findIndex` e comia o segundo — e o invariante de não-perda,
 * que conferia só a 1a linha por lápide, não via. Uma lápide pode ter N limites;
 * quem assume 1 perde regra em silêncio.
 *
 * Devolve [] quando a lápide não tem o campo — 3 casos reais (2 emendas + a tabela
 * de claims refutadas), que por isso caem inteiras no derivado (ver `derivar`).
 * @returns {string[]} linhas de todos os blocos, concatenadas na ordem original
 */
export function extrairLimite(body) {
  const blocos = [];
  for (let i = 0; i < body.length; i++) {
    if (!H_LIMITE.test(body[i])) continue;
    let e = body.length;
    for (let j = i + 1; j < body.length; j++) if (BULLET_TOPO.test(body[j])) { e = j; break; }
    const bloco = body.slice(i, e);
    while (bloco.length && bloco[bloco.length - 1].trim() === '') bloco.pop();
    blocos.push(bloco);
    i = e - 1;
  }
  return blocos.flatMap((b, i) => (i === 0 ? b : ['', ...b]));
}

/** Só as PRIMEIRAS linhas de cada bloco de limite — chaves do invariante de não-perda. */
export function chavesLimite(body) {
  return body.filter((l) => H_LIMITE.test(l));
}

const AVISO = [
  '',
  '> ⚠️ **Esta seção é DERIVADA — não edite aqui.** A fonte é',
  '> [`memory/licoes-rejeitadas.md`](licoes-rejeitadas.md) (append-only Tier 0), que carrega cada',
  '> lápide COMPLETA: *o que foi tentado · por que caiu · o limite · evidência*. Abaixo fica só o',
  '> **limite** de cada uma — a regra que previne. Mesma ordem, mesmos cabeçalhos: procure lá pelo',
  '> `### <data> — <título>` para o contexto inteiro (é o que você quer ao INVESTIGAR uma lápide;',
  '> o limite basta para NÃO reincidir).',
  '>',
  '> Regerar: `node scripts/governance/sec5-derive.mjs --write` · o CI roda `--check`.',
  '',
];

/** Monta o texto do §5 derivado a partir do texto da FONTE. */
export function derivar(fonteTexto) {
  const { preambulo, lapides } = partir(fonteTexto.split('\n'));
  // o preâmbulo da fonte já traz o cabeçalho `## Ideias avaliadas...` + a doutrina
  const out = [...trimBordas(preambulo), ...AVISO];
  for (const L of lapides) {
    const lim = extrairLimite(L.body);
    out.push(L.head, '');
    // sem campo "O limite" (emendas/tabela): preserva o corpo inteiro — encolher
    // uma entrada que não tem a parte que previne seria perder a própria regra.
    out.push(...(lim.length ? lim : trimBordas(L.body)), '');
  }
  return out.join('\n').replace(/\n{3,}/g, '\n\n').trimEnd() + '\n';
}

function trimBordas(arr) {
  const a = [...arr];
  while (a.length && a[0].trim() === '') a.shift();
  while (a.length && a[a.length - 1].trim() === '') a.pop();
  return a;
}

/** Substitui a região §5 de `proibicoesTexto` pelo `novoSec5`. */
export function aplicar(proibicoesTexto, novoSec5) {
  const linhas = proibicoesTexto.split('\n');
  const r = regiaoSec5(linhas);
  if (!r) throw new Error('região §5 não encontrada em proibicoes.md');
  const antes = linhas.slice(0, r.start);
  const depois = linhas.slice(r.end);
  return [...antes, ...novoSec5.split('\n'), ...depois].join('\n');
}

/**
 * INVARIANTE DE NÃO-PERDA: todo bullet `- **O limite` da fonte tem que aparecer,
 * caractere a caractere, no derivado. É a garantia de que a separação não come a
 * parte que PREVINE — a única coisa que o derivado existe para carregar.
 * Sem isso o `--check` só provaria "o derivado é o que o gerador cospe", que é
 * tautológico (§5 2026-06-05: asserção derivada da implementação).
 * @returns {{ ok: boolean, faltando: string[], totalFonte: number }}
 */
export function conferirNaoPerda(fonteTexto, derivadoTexto) {
  const { lapides } = partir(fonteTexto.split('\n'));
  const faltando = [];
  let totalFonte = 0;
  const alvo = derivadoTexto.replace(/\s+/g, ' ');
  for (const L of lapides) {
    // TODAS as chaves — uma lápide pode ter N limites (ver `extrairLimite`)
    for (const chave of chavesLimite(L.body)) {
      totalFonte++;
      if (!alvo.includes(chave.replace(/\s+/g, ' ').trim())) {
        faltando.push(`${L.head.slice(0, 70)}  →  ${chave.slice(0, 60)}`);
      }
    }
  }
  return { ok: faltando.length === 0, faltando, totalFonte };
}

/** Diagnóstico: lápides cuja contagem de bullets `- **O limite` != 1. */
export function auditar(fonteTexto) {
  const { lapides } = partir(fonteTexto.split('\n'));
  return lapides
    .map((L) => ({ head: L.head, n: L.body.filter((x) => H_LIMITE.test(x)).length }))
    .filter((x) => x.n !== 1);
}

/** Recorta o §5 atual de proibicoes.md como texto (usado por --migrate e --check). */
export function sec5Atual(proibicoesTexto) {
  const linhas = proibicoesTexto.split('\n');
  const r = regiaoSec5(linhas);
  if (!r) throw new Error('região §5 não encontrada em proibicoes.md');
  return linhas.slice(r.start, r.end).join('\n').trimEnd() + '\n';
}

// ─────────────────────────────── CLI ───────────────────────────────
function selftest() {
  const FONTE_FIX = [
    '## Ideias avaliadas e DESCARTADAS — teste',
    '',
    '> doutrina do preâmbulo.',
    '',
    '### 2026-01-01 — Ideia A',
    '- **O que foi tentado:** blá longo de arqueologia.',
    '- **Por que caiu:** medido, com recibo enorme.',
    '- **O limite (variante também proibida):** não faça A nem A-linha.',
    '- **Nota extra:** rodapé.',
    '',
    '### 2026-01-02 — Emenda sem limite',
    '- **O que foi tentado:** só contexto, sem campo de limite.',
    '',
    // caso REAL (2026-08-03): lápide de DOIS eixos → DOIS limites. A 1a versão do
    // extrator comia o segundo, e o invariante de não-perda não via (conferia só a
    // 1a linha por lápide). Este par é o bite-test dessa regressão.
    '### 2026-01-03 — Lápide de dois eixos',
    '- **Eixo 1 — o que foi tentado:** arqueologia do eixo 1.',
    '- **O limite (variante também proibida):** não faça B (eixo um).',
    '- **Eixo 2 — o que foi tentado:** arqueologia do eixo 2.',
    '- **O limite (variante também proibida):** não faça C (eixo dois).',
    '',
  ].join('\n');

  const der = derivar(FONTE_FIX);
  const t = [];
  t.push(['preserva o cabeçalho da seção', der.includes('## Ideias avaliadas e DESCARTADAS — teste')]);
  t.push(['preserva o preâmbulo/doutrina', der.includes('doutrina do preâmbulo')]);
  t.push(['preserva TODOS os cabeçalhos de lápide', der.includes('### 2026-01-01 — Ideia A') && der.includes('### 2026-01-02 — Emenda sem limite')]);
  t.push(['MANTÉM o limite', der.includes('não faça A nem A-linha')]);
  t.push(['CORTA a arqueologia', !der.includes('blá longo de arqueologia') && !der.includes('recibo enorme')]);
  t.push(['CORTA rodapé fora do limite', !der.includes('rodapé')]);
  // CN: entrada SEM campo "O limite" não pode ser esvaziada — cairia fora a única regra dela
  t.push(['CN: sem campo "O limite" → corpo preservado', der.includes('só contexto, sem campo de limite')]);
  t.push(['aponta a fonte', der.includes('licoes-rejeitadas.md')]);
  t.push(['avisa que é derivado', /DERIVADA — não edite aqui/.test(der)]);
  // idempotência: derivar do derivado não pode encolher de novo o que já é limite
  t.push(['BITE: derivado é estável (idempotente)', derivar(der).includes('não faça A nem A-linha')]);
  // BITE da regressão real (2026-08-03): lápide com 2 limites — AMBOS têm que ir
  t.push(['BITE: lápide com DOIS limites leva os dois', der.includes('não faça B (eixo um)') && der.includes('não faça C (eixo dois)')]);
  t.push(['BITE: e ainda corta a arqueologia dos dois eixos', !der.includes('arqueologia do eixo 1') && !der.includes('arqueologia do eixo 2')]);
  // o invariante de não-perda tem que ENXERGAR os 2 (senão é carimbo)
  t.push(['não-perda conta TODOS os limites (3, não 2)', conferirNaoPerda(FONTE_FIX, der).totalFonte === 3]);
  // BITE do invariante: derivado mutilado (sem o 2o limite) tem que ser REPROVADO
  const mutilado = der.replace('não faça C (eixo dois)', 'sumiu');
  t.push(['BITE: não-perda REPROVA derivado sem um dos limites', conferirNaoPerda(FONTE_FIX, mutilado).ok === false]);
  t.push(['CN: não-perda APROVA derivado íntegro', conferirNaoPerda(FONTE_FIX, der).ok === true]);
  // aplicar() troca só a região
  const PROIB = ['# Proibições', '', '## Antes', 'x', '', '## Ideias avaliadas e DESCARTADAS — teste', 'velho', '', '## Depois', 'y', ''].join('\n');
  const ap = aplicar(PROIB, derivar(FONTE_FIX));
  t.push(['aplicar preserva seções vizinhas', ap.includes('## Antes') && ap.includes('## Depois') && ap.includes('\ny')]);
  t.push(['aplicar remove o §5 antigo', !ap.includes('\nvelho')]);
  // BITE do --check: drift é detectado
  t.push(['BITE: --check detecta drift', sec5Atual(ap).trimEnd() !== derivar(FONTE_FIX).replace('não faça A', 'MUTADO').trimEnd()]);

  let ok = 0;
  for (const [nome, passou] of t) { console.log(`  ${passou ? '✓' : '✗'} ${nome}`); if (passou) ok++; }
  console.log(`\n${ok === t.length ? 'SELFTEST OK' : 'SELFTEST FALHOU'} — ${ok}/${t.length}`);
  return ok === t.length ? 0 : 1;
}

function main() {
  const arg = process.argv.slice(2);
  if (arg.includes('--selftest')) return selftest();

  if (arg.includes('--migrate')) {
    if (existsSync(FONTE)) { console.error(`✗ ${FONTE} já existe — migrate é one-shot.`); return 1; }
    const atual = sec5Atual(readFileSync(PROIBICOES, 'utf8'));
    writeFileSync(FONTE, atual, 'utf8');
    const n = partir(atual.split('\n')).lapides.length;
    console.log(`✓ fonte criada: memory/licoes-rejeitadas.md — ${n} lápides, ${atual.length} chars (íntegras).`);
    return 0;
  }

  if (!existsSync(FONTE)) { console.error(`✗ fonte ausente: ${FONTE} — rode --migrate primeiro.`); return 1; }
  const fonteTxt = readFileSync(FONTE, 'utf8');
  const derivado = derivar(fonteTxt);
  const proibTxt = readFileSync(PROIBICOES, 'utf8');

  if (arg.includes('--audit')) {
    const odd = auditar(fonteTxt);
    console.log(`lápides com nº de bullets "- **O limite" != 1: ${odd.length}`);
    for (const x of odd) console.log(`  n=${x.n}  ${x.head.slice(0, 80)}`);
    const np = conferirNaoPerda(fonteTxt, derivado);
    console.log(`\nnão-perda: ${np.totalFonte} limites na fonte · faltando no derivado: ${np.faltando.length}`);
    for (const h of np.faltando) console.log(`  ✗ ${h}`);
    return 0;
  }

  if (arg.includes('--write')) {
    writeFileSync(PROIBICOES, aplicar(proibTxt, derivado), 'utf8');
    console.log(`✓ §5 derivado regravado — ${derivado.length} chars.`);
    return 0;
  }

  // --check (default): DUAS pernas independentes.
  // (1) não-perda — nenhum limite da fonte some no derivado (o que importa);
  // (2) sincronia — o §5 no disco é exatamente o que o gerador produz (anti-drift).
  const np = conferirNaoPerda(fonteTxt, derivado);
  if (!np.ok) {
    console.error(`  ✗ PERDA DE LIMITE — ${np.faltando.length} de ${np.totalFonte} não chegaram ao derivado:`);
    for (const h of np.faltando) console.error(`      ${h}`);
    console.error('    O derivado existe pra carregar os limites; se um some, a separação comeu a regra.');
    return 1;
  }

  const atual = sec5Atual(proibTxt).trimEnd();
  if (atual === derivado.trimEnd()) {
    console.log(`  OK — §5 derivado em dia com a fonte (${derivado.length} chars · ${np.totalFonte} limites, 0 perdidos).`);
    return 0;
  }
  console.error('  ✗ §5 DRIFOU da fonte (memory/licoes-rejeitadas.md).');
  console.error('    A fonte é licoes-rejeitadas.md; o §5 é gerado. Se você editou o §5 à mão,');
  console.error('    a edição vai ser PERDIDA — mova-a pra fonte e regenere:');
  console.error('      node scripts/governance/sec5-derive.mjs --write');
  return 1;
}

if (process.argv[1] && resolve(process.argv[1]) === resolve(fileURLToPath(import.meta.url))) {
  process.exit(main());
}
