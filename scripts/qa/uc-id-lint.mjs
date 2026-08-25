#!/usr/bin/env node
// uc-id-lint.mjs — o id de UC casa o regex canônico? (ADR 0264 · fonte única scripts/lib/uc-regex.mjs)
//
// =====================================================================================
// POR QUE EXISTE (medido 2026-08-23, ANTES de escrever uma linha de gate)
// =====================================================================================
// Um id de UC fora do formato canônico NÃO falha na escrita. Ele fica INVISÍVEL: o
// `ucBlocksInCasos()` faz split por bloco `## ` e descarta todo bloco cujo heading não
// case `ucHeadRe()`. Consequência em cadeia, nesta ordem:
//
//   1. `ucsDeclaredInCasos()` devolve [] pra aquele arquivo;
//   2. o `casos-coverage-guard` (G-2, REQUIRED) não tem UC pra cobrar → passa por VACUIDADE;
//   3. o `prototipo-readiness` conta `temCasosComUC = contaUCs(...) > 0` → false;
//   4. a tela aparece na fila como `falta: casos.md-com-UC` — com o casos.md escrito e cheio.
//
// O autor conclui "faltou critério" e reescreve o que já existia. O trabalho não some do
// disco; some da MÁQUINA. É esse o defeito que este lint mata — e é por isso que ele reporta
// o LIMITE e o CORTE sugerido, em vez de só dizer "inválido".
//
// CORPUS MEDIDO ANTES DE INSTALAR (§Sempre-fazer #4 de memory/proibicoes.md — máquina nova
// exige FP medido ANTES). 173 `.casos.md`, 994 blocos que se apresentam como UC:
//   952 casam o canônico  ·  42 NÃO casam  ·  FALSO-POSITIVO: 0
// Os 42 são reais, e 37 deles estão em 5 telas cujo casos.md inteiro é invisível:
//   Jana/Chat (11) · Jana/Index (14) · Ponto/Espelho/Show (5) · Ponto/Importacoes/Show (4)
//   · Ponto/Intercorrencias/Show (3)      [+5 em prototipo-ui/.../Programa.casos.md]
// Duas famílias de erro, nenhuma exótica: prefixo com 7 caracteres (ESPSHOW, IMPSHOW,
// INTSHOW, PROGDOC) e prefixo em DOIS segmentos (COPI-CHAT, COPI-PAINEL).
//
// =====================================================================================
// O QUE ELE NÃO É
// =====================================================================================
// Não é um 2º oráculo do formato: o regex vem inteiro de `scripts/lib/uc-regex.mjs`, que já
// é a fonte única (nasceu justamente de 4 regex que drifaram). Aqui só se ACHA quem não casa
// e se DIZ por quê. Se o formato mudar, muda lá — este arquivo não tem cópia da regra.
// Não confere se o UC tem teste (isso é G-2 do casos-gate) nem se o Status é honesto (G-7).
//
// =====================================================================================
// NASCE ADVISORY E FORWARD-ONLY (proibicoes.md §Sempre-fazer #6 · ADR 0271/0275)
// =====================================================================================
// `--check` cru morde os 42 e red-locka o main. `--check --baseline <path>` grandfathera o
// legado e morde só id NOVO (no-new-lie), que é o padrão do `anchor-lint`/`doneness-lint`.
// Renomear os 42 em lote está PROIBIDO por §5 2026-07-12: `.casos.md` está sob glob de gate
// diff-aware (casos-gate G-6 mede data-git), então o backfill acordaria dívida de frescor em
// 8 arquivos. A dívida fica VISÍVEL no report; pagá-la é decisão [W], por tela.
//
// USO (na raiz do repo):
//   node scripts/qa/uc-id-lint.mjs                          # report full-tree (exit 0)
//   node scripts/qa/uc-id-lint.mjs --check                  # exit 1 se houver QUALQUER inválido
//   node scripts/qa/uc-id-lint.mjs --check --baseline governance/uc-id-baseline.json
//   node scripts/qa/uc-id-lint.mjs --write-baseline governance/uc-id-baseline.json
//   node scripts/qa/uc-id-lint.mjs --json                   # determinístico (sem data/sha)
//   node scripts/qa/uc-id-lint.mjs <arquivo.casos.md ...>   # só os arquivos passados
//
// BITE-TEST: node scripts/qa/uc-id-lint.test.mjs (irmão). Ele exercita o CLI DE FORA, com
// controle negativo — sem isso o exit 0 desta catraca não valeria nada (Lei C).

import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { sep } from 'node:path';
import { pathToFileURL } from 'node:url';
// FONTE ÚNICA do formato. Importar (nunca copiar) é o que impede este lint de virar a 5ª
// regex que drifa — a doença que a própria lib foi criada pra matar.
import { ucHeadRe } from '../lib/uc-regex.mjs';

const argv = process.argv.slice(2);
const has = (f) => argv.includes(f);
const valOf = (f) => { const i = argv.indexOf(f); return i >= 0 ? argv[i + 1] : null; };

const CHECK = has('--check');
const JSON_OUT = has('--json');
const BASELINE_PATH = valOf('--baseline');
const WRITE_BASELINE = valOf('--write-baseline');

// Limites do canônico, para MENSAGEM apenas. A decisão de casar/não-casar é 100% do
// `ucHeadRe()` importado; estes números nunca decidem nada — se divergirem da lib, o
// veredito continua o da lib e só o texto do conselho fica velho.
const PREFIXO_MAX = 6;
const DIGITOS_MAX = 3;

const posix = (p) => p.split(sep).join('/');

/** Um bloco "se apresenta como UC" quando o heading começa com `UC-` ou `UC `. */
const PARECE_UC = /^UC[-\s]/i;

/** O token do id, cru: tudo que o autor escreveu antes do primeiro separador de prosa. */
export function tokenDe(heading) {
  const m = /^(UC[A-Za-z0-9_-]*)/i.exec(heading.trim());
  return m ? m[1] : heading.trim().split(/\s+/)[0];
}

/**
 * POR QUE o id não casa — em português, com o corte sugerido.
 *
 * Não tenta ser exaustivo nem "provar" a razão: o veredito de validade já saiu do regex.
 * Isto é o conselho ao autor. Quando nenhuma regra conhecida explica, diz isso em vez de
 * inventar uma causa (anti-padrão inventado é pior que ausente — §5 2026-07-16).
 */
export function diagnostica(token) {
  const segs = token.split('-');
  if (segs.length < 2) {
    return { razao: 'sem parte numérica (o formato é UC-<PREFIXO>-<NN>)', sugestao: null };
  }
  const cauda = segs[segs.length - 1];
  const prefixoSegs = segs.slice(1, -1);
  const prefixoJunto = prefixoSegs.join('').toUpperCase();

  const sugestao = (() => {
    const num = /^(\d{1,3})([a-zA-Z]?)$/.exec(cauda);
    if (!num) return null;
    let p = prefixoJunto.replace(/[^A-Z0-9]/g, '');
    if (!p) return `UC-${num[1]}${num[2]}`;
    if (!/^[A-Z]/.test(p)) return null; // prefixo tem que começar por letra — corte não resolve
    p = p.slice(0, PREFIXO_MAX);
    return `UC-${p}-${num[1]}${num[2]}`;
  })();

  const razoes = [];
  if (prefixoSegs.length > 1) {
    razoes.push(`prefixo em ${prefixoSegs.length} segmentos ("${prefixoSegs.join('-')}") — o canônico aceita 1`);
  }
  if (prefixoJunto.length > PREFIXO_MAX) {
    razoes.push(`prefixo com ${prefixoJunto.length} caracteres (máx ${PREFIXO_MAX})`);
  }
  if (prefixoJunto && !/^[A-Z]/.test(prefixoJunto)) {
    razoes.push('prefixo começa por dígito (o 1º caractere tem que ser letra, senão colide com o número do UC)');
  }
  if (!/^\d{1,3}[a-zA-Z]?$/.test(cauda)) {
    razoes.push(`cauda "${cauda}" não é até ${DIGITOS_MAX} dígitos (+ letra opcional)`);
  }
  if (!razoes.length) razoes.push('não casa o regex canônico (razão não classificada — confira scripts/lib/uc-regex.mjs)');
  return { razao: razoes.join(' · '), sugestao };
}

/** Blocos inválidos de UM arquivo. Puro (recebe conteúdo), pra o selftest não tocar disco. */
export function invalidosEm(rel, content) {
  const out = [];
  for (const block of String(content).split(/^##\s+/m).slice(1)) {
    const heading = block.split('\n')[0].trim();
    if (!PARECE_UC.test(heading)) continue;        // bloco que não se diz UC não é problema deste lint
    if (ucHeadRe().test(block)) continue;          // casa o canônico -> ok
    const token = tokenDe(heading);
    const { razao, sugestao } = diagnostica(token);
    out.push({ arquivo: rel, id: token, heading: heading.slice(0, 110), razao, sugestao });
  }
  return out;
}

/** Quantos blocos SE APRESENTAM como UC num conteúdo (denominador honesto do report). */
export function blocosUcEm(content) {
  let n = 0;
  for (const b of String(content).split(/^##\s+/m).slice(1)) {
    if (PARECE_UC.test(b.split('\n')[0].trim())) n++;
  }
  return n;
}

function arquivosDoRepo() {
  const posicionais = argv.filter((a, i) => (
    !a.startsWith('--') && argv[i - 1] !== '--baseline' && argv[i - 1] !== '--write-baseline'
  ));
  if (posicionais.length) return posicionais.map(posix).filter((p) => p.endsWith('.casos.md') && existsSync(p));
  // git ls-files (não walk do fs): o universo é o que está VERSIONADO, e quem responde isso
  // é o git — não uma travessia de diretório que pode pegar arquivo ignorado ou de worktree.
  // `design-docs/cowork-inbox/` é CAIXA DE ENTRADA do Cowork (pedido que o Code ainda NÃO adotou),
  // não artefato ativo do repo. Corrigir id de UC é trabalho de quem ADOTA o pedido — cobrar isso
  // do inbox reprova o mensageiro. Medido 2026-08-24: o inbox era 93 arquivos 100% `.md` e passava;
  // ao descer 87 novos do vivo, o `Programa.casos.md` veio com prefixo de 7 chars (`UC-PROGDOC-*`),
  // legítimo COMO PEDIDO e inválido como UC do repo.
  const foraDoInbox = (p) => !String(p).split(String.fromCharCode(92)).join('/').includes('design-docs/cowork-inbox/');
  const raw = execFileSync('git', ['ls-files', '--', '*.casos.md'], { encoding: 'utf8' });
  return raw.split('\n').map((s) => s.trim()).filter(Boolean).filter(foraDoInbox);
}

function carregaBaseline(p) {
  if (!p) return null;
  if (!existsSync(p)) { console.error(`uc-id-lint: --baseline ${p} não existe.`); process.exit(2); }
  try { return new Set(JSON.parse(readFileSync(p, 'utf8')).grandfathered || []); }
  catch (e) { console.error(`uc-id-lint: --baseline ${p} não parseia (${e.message}).`); process.exit(2); }
}

const chave = (v) => `${v.arquivo}::${v.id}`;

function main() {
  const arquivos = arquivosDoRepo();
  let blocos = 0;
  const violacoes = [];
  for (const f of arquivos.sort()) {
    const c = readFileSync(f, 'utf8');
    blocos += blocosUcEm(c);
    violacoes.push(...invalidosEm(posix(f), c));
  }

  if (WRITE_BASELINE) {
    const payload = {
      _meta: {
        schema: 'uc-id-baseline/v1',
        purpose: 'Grandfathera id de UC fora do formato canônico que JÁ existia. `--check --baseline` morde só id NOVO (no-new-lie).',
        contrato: 'grandfathered[] = "<arquivo>::<id>". CRESCER esta lista = grandfatherar id novo, o oposto do propósito. DIMINUIR (id consertado) é livre.',
        regenerar: `node scripts/qa/uc-id-lint.mjs --write-baseline ${WRITE_BASELINE}`,
        nao_e: 'NÃO é lista de exceção permanente — é dívida datada. Pagar (renomear o id + os testes que o citam) é decisão [W], por tela.',
      },
      grandfathered: violacoes.map(chave).sort(),
    };
    writeFileSync(WRITE_BASELINE, `${JSON.stringify(payload, null, 2)}\n`);
    console.log(`uc-id-lint: baseline escrito em ${WRITE_BASELINE} (${violacoes.length} id grandfatherado).`);
    return 0;
  }

  const base = carregaBaseline(BASELINE_PATH);
  const ativas = base ? violacoes.filter((v) => !base.has(chave(v))) : violacoes;

  if (JSON_OUT) {
    console.log(JSON.stringify({
      _meta: { schema: 'uc-id-lint/v1', baseline: BASELINE_PATH || null },
      resumo: { arquivos: arquivos.length, blocos_uc: blocos, invalidos: violacoes.length, ativos: ativas.length },
      violacoes: ativas,
    }, null, 2));
    return CHECK && ativas.length ? 1 : 0;
  }

  console.log('\n  uc-id-lint — formato de id de UC (fonte: scripts/lib/uc-regex.mjs)\n');
  console.log(`  arquivos .casos.md: ${arquivos.length} · blocos que se apresentam como UC: ${blocos}`);
  console.log(`  fora do formato canônico: ${violacoes.length}${base ? ` (ativos após baseline: ${ativas.length})` : ''}\n`);

  if (!ativas.length) {
    console.log('  ✅ nenhum id ativo fora do formato.\n');
    return 0;
  }
  let ultimo = null;
  for (const v of ativas) {
    if (v.arquivo !== ultimo) { console.log(`  ${v.arquivo}`); ultimo = v.arquivo; }
    console.log(`     ⛔ ${v.id}`);
    console.log(`        por quê: ${v.razao}`);
    if (v.sugestao) console.log(`        vira:   ${v.sugestao}`);
  }
  console.log('\n  O id fora do formato não quebra nada na hora — ele fica INVISÍVEL pro');
  console.log('  ucBlocksInCasos(), então o UC some do casos-gate (G-2 passa por vacuidade)');
  console.log('  e a tela reaparece como "falta: casos.md-com-UC" no prototipo-readiness.');
  console.log('  Renomear o id exige renomear junto os testes que o citam.\n');
  return CHECK ? 1 : 0;
}

// Só executa quando INVOCADO como entrypoint. Sem esta guarda o `main()` roda no import
// e o irmão `.test.mjs` (que importa os helpers) executaria a varredura full-tree em vez
// do teste — foi exatamente o que aconteceu na 1a rodada deste arquivo.
if (import.meta.url === pathToFileURL(process.argv[1]).href) process.exit(main());
