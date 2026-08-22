#!/usr/bin/env node
// @ts-check
/**
 * gerar-payload-partes.mjs — emite o payload do espelho Cowork em PARTES de até 256 KiB.
 *
 * POR QUE EXISTE: o `aplicar-payload.mjs` já sabia juntar lotes (`payloads.flatMap`), mas nada
 * no repo sabia PRODUZI-LOS — os payloads nasciam à mão do lado do design. Sem gerador, cada
 * regeneração recomeça do zero e as três armadilhas do envelope (abaixo) são redescobertas na
 * unha. Este script é o par produtor do applier e mora ao lado dele de propósito.
 *
 * ONDE RODA: na máquina que TEM os arquivos em disco (o lado do design/Cowork). NÃO roda do
 * lado do agente consumidor: lá o conteúdo chegaria por `DesignSync.get_file`, que entrega no
 * CONTEXTO do agente — e escrever de lá é transcrição, a classe que causou o STALE de
 * 2026-08-11. Aqui nenhum byte passa por prosa: readFileSync -> JSON.stringify -> writeFileSync.
 *
 * POR QUE EM PARTES: o consumidor busca o payload com `DesignSync.get_file`, que corta em
 * 256 KiB e devolve `"truncated": true`. Payload único de ~3,5 MB volta cortado e é inútil.
 *
 * AS TRÊS ARMADILHAS DO ENVELOPE (lidas do `aplicar-payload.mjs`, não inventadas):
 *   1. `fileCount` é conferido POR PARTE contra `files.length` DAQUELA parte — não contra o
 *      total do lote. Declarar o total em cada parte reprova todas menos a última.
 *   2. `missing` precisa ser ARRAY em TODA parte. Em `--require-complete-shell`, parte sem o
 *      campo vira "payload sem `missing: []`" e derruba o lote inteiro.
 *   3. `bytes` é obrigatório por arquivo nesse mesmo modo: arquivo sem `bytes` não é
 *      "conferido", é NÃO MEDIDO, e o applier recusa em vez de escrever sem prova.
 *
 * FIDELIDADE: `bytes` é a prova que o applier consegue verificar (pega truncagem, o modo de
 * falha dominante quando o transporte fatia). O `fnv64` vai junto pela convenção declarada em
 * `hash`, e o applier REPORTA divergência sem transformar em veredito — ver o docblock dele
 * para a contradição em aberto entre os dois lados, com as duas medições datadas.
 *
 * Uso:
 *   node scripts/design-sync/gerar-payload-partes.mjs --root <dir-do-vivo> --out sync
 *   ... [--entry oimpresso.com.html] [--cap 262144] [--exclude <glob>]
 *
 * Do outro lado:
 *   node scripts/design-sync/aplicar-payload.mjs sync/payload.part*.json --require-complete-shell
 */
import { readFileSync, writeFileSync, existsSync, mkdirSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { payloadDependencyGraph, normalizePayloadPath } from './payload-dependency-graph.mjs';

const args = process.argv.slice(2);
const opt = (nome, padrao = null) => {
  const i = args.indexOf(nome);
  return i >= 0 && args[i + 1] && !args[i + 1].startsWith('--') ? args[i + 1] : padrao;
};
const ROOT = opt('--root', process.cwd());
const OUT = opt('--out', 'sync');
const ENTRY = opt('--entry', 'oimpresso.com.html');
const CAP = Number(opt('--cap', '262144'));
// Piso de PERSISTÊNCIA do consumidor (ver o bloco do empacotamento). 60 KiB fica acima do maior
// inline medido (41 KB) e abaixo do menor persistido (87 KB). `--piso 0` desliga o aviso.
const PISO = Number(opt('--piso', '61440'));
const EXCLUDES = args.reduce((acc, a, i) => (a === '--exclude' && args[i + 1] ? [...acc, args[i + 1]] : acc), []);

if (!existsSync(join(ROOT, ENTRY))) {
  console.error(`\u2717 entry nao encontrado: ${join(ROOT, ENTRY)}`);
  console.error(`  rode este script na maquina que TEM os arquivos em disco (lado do design).`);
  process.exit(2);
}

/** FNV-1a 64-bit — MESMA funcao do applier, byte a byte. Divergir aqui e fabricar ruido. */
function fnv1a64(buf) {
  let h = 0xcbf29ce484222325n;
  const prime = 0x100000001b3n;
  const mask = 0xffffffffffffffffn;
  for (const b of buf) { h = ((h ^ BigInt(b)) * prime) & mask; }
  return h.toString(16).padStart(16, '0');
}

const BINARIO = /\.(woff2?|ttf|otf|eot|png|jpe?g|gif|webp|avif|ico|pdf|mp4|webm|zip)$/i;

/**
 * glob simples: `*` dentro de um segmento, `**` em qualquer profundidade.
 *
 * A ordem importa e foi errada na 1a versao: escapar a string INTEIRA antes de fatiar nao
 * escapa o `*` (ele nao esta na classe), entao o split por `**` nao casava nada e o RegExp
 * saia com `**` cru -> "Nothing to repeat". Fatia PRIMEIRO nos curingas, escapa DEPOIS so os
 * pedacos literais. Pego pelo teste com `--exclude '_ds/**\/_ds_bundle.js'`.
 */
function casaExclude(p) {
  const esc = (s) => s.replace(/[.+?^${}()|[\]\\]/g, '\\$&');
  return EXCLUDES.some((g) => {
    const re = new RegExp('^' + g.split('**').map((seg) => seg.split('*').map(esc).join('[^/]*')).join('.*') + '$');
    return re.test(p);
  });
}

// -- 1) FECHAMENTO TRANSITIVO a partir do entry ---------------------------------------------
// Usa o MESMO grafo do applier: manifesto DERIVADO, nunca lista curada. Se eu enumerasse a mao,
// o payload e a verificacao do outro lado usariam regua diferente — e a divergencia so apareceria
// no `--require-complete-shell`, depois de tudo pronto.
const lidos = new Map();
const ausentes = new Set();
const excluidos = new Set();

function carregar(rel) {
  if (lidos.has(rel) || ausentes.has(rel) || excluidos.has(rel)) return;
  if (casaExclude(rel)) { excluidos.add(rel); return; }
  const abs = join(ROOT, rel);
  if (!existsSync(abs) || !statSync(abs).isFile()) { ausentes.add(rel); return; }
  lidos.set(rel, readFileSync(abs));
}

carregar(ENTRY);
for (let passada = 0; passada < 40; passada++) {
  const grafo = payloadDependencyGraph([...lidos].map(([path, buf]) => ({
    path,
    binary: BINARIO.test(path),
    content: BINARIO.test(path) ? null : buf.toString('utf8'),
  })));
  const novos = grafo.missing.filter((r) => !ausentes.has(r) && !excluidos.has(r) && !lidos.has(r));
  if (!novos.length) break;
  novos.forEach(carregar);
}

// -- 2) MONTA OS REGISTROS ------------------------------------------------------------------
const registros = [...lidos].map(([rel, buf]) => {
  const binary = BINARIO.test(rel);
  const rec = {
    path: normalizePayloadPath(rel),
    bytes: buf.length,
    fnv64: fnv1a64(buf),
    content: binary ? buf.toString('base64') : buf.toString('utf8'),
  };
  if (binary) rec.isBase64 = true;
  // custo REAL desta entrada dentro do JSON da parte (ja com escape), + a virgula
  return { rec, custo: Buffer.byteLength(JSON.stringify(rec), 'utf8') + 1 };
});
registros.sort((a, b) => a.rec.path.localeCompare(b.rec.path));

// -- 3) ARMADILHA DE TAMANHO: arquivo e ATOMICO dentro de uma parte --------------------------
// Nao existe "parte de meio arquivo": o applier le `files[].content` inteiro. Logo, arquivo cujo
// JSON escapado passa do cap nao cabe em parte NENHUMA, e fatia-lo exigiria o applier saber
// remontar — coisa que ele HOJE nao faz. Falhar aqui, alto, e a unica saida honesta: emitir
// mesmo assim produziria uma parte que o consumidor nao consegue baixar, e o sintoma apareceria
// la na frente como `truncated: true`, longe da causa.
const RESERVA = 900;
const grandes = registros.filter((r) => r.custo + RESERVA > CAP);
if (grandes.length) {
  console.error(`\n\u2717 ${grandes.length} arquivo(s) NAO CABEM em uma parte de ${CAP} bytes:`);
  for (const g of grandes) console.error(`   . ${g.rec.path} — ${g.rec.bytes} bytes crus -> ${g.custo} bytes em JSON`);
  console.error(`\n  Arquivo e atomico dentro da parte, e o applier nao remonta arquivo fatiado.`);
  console.error(`  Saidas REAIS (as duas sao decisao, nao flag deste script):`);
  console.error(`   (a) ensinar o applier a remontar (campo de chunk) — mexe nos DOIS lados;`);
  console.error(`   (b) tirar o arquivo deste transporte e sincroniza-lo pela rota propria dele`);
  console.error(`       (ex.: '_ds/**' ja tem ds-mirror-build.mjs). Aqui: --exclude '_ds/**'.`);
  console.error(`  AVISO: excluir joga o arquivo em 'missing[]', e ai --require-complete-shell`);
  console.error(`     RECUSA o lote — corretamente: o grafo do shell nao fecha sem ele.\n`);
  process.exit(2);
}

// -- 4) EMPACOTA ----------------------------------------------------------------------------
// `missing` = o que o grafo PEDIU e este payload NAO carrega — por ausencia em disco OU por
// exclusao deliberada. Os dois casos entram: `carregar()` so e chamado para path REFERENCIADO,
// entao todo excluido e, por construcao, algo que o shell pede.
//
// Ate a 1a versao isto era so `ausentes`, e a mensagem da guarda de tamanho ja anunciava que
// excluir "joga o arquivo em missing[]" — anuncio que o codigo nao honrava (LC-15). O teste com
// `--exclude` pegou: saiu `missing: []` com o bundle fora. O consumidor acabaria recusando o
// lote de qualquer jeito (o grafo dele nao fecharia), mas pelo motivo generico "grafo local
// incompleto", sem dizer que a ausencia foi DELIBERADA deste lado.
const missing = [...new Set([...ausentes, ...excluidos])].sort();
// EMPACOTAMENTO EQUILIBRADO — o teto sozinho não basta, e o motivo é o consumidor.
//
// O guloso puro enche cada parte até o CAP e joga o resto na ÚLTIMA, que pode sair minúscula.
// Isso importa porque o outro lado busca as partes com `DesignSync.get_file`, e a resposta só
// é PERSISTIDA EM DISCO acima de um piso; abaixo dele ela chega inline no contexto do agente,
// e escrever de lá é transcrição — a classe do STALE de 2026-08-11. Ou seja: uma parte pequena
// demais é inaplicável, mesmo estando perfeitamente dentro do teto.
//
// Medido em 2026-08-22 nesta harness: 2,4 KB inline · 19 KB inline · 41 KB inline · 87 KB em
// disco. O piso real está entre 41 KB e 87 KB (o handoff de 08-17 mediu 52 KB, que cai no
// intervalo). Não sei o valor exato, então NÃO finjo precisão: equilibro as partes (o que
// dissolve o problema na prática) e AVISO se alguma ficar abaixo de `--piso`.
//
// Foi assim que o lote do Cowork de 2026-08-22 travou: 30 partes entre 157 e 250 KB, e a
// part01 com 40.896 B — a única abaixo do piso, e o lote inteiro parou nela.
function empacotar(limite) {
  const out = [];
  let atual = [], usado = RESERVA;
  for (const r of registros) {
    if (atual.length && usado + r.custo > limite) { out.push(atual); atual = []; usado = RESERVA; }
    atual.push(r);
    usado += r.custo;
  }
  if (atual.length) out.push(atual);
  return out;
}

const custoTotal = registros.reduce((n, r) => n + r.custo, 0);
const maiorCusto = registros.reduce((n, r) => Math.max(n, r.custo), 0);
// N = mínimo de partes que o teto permite; depois espalho o mesmo conteúdo por N partes de
// tamanho parecido. Se a distribuição uniforme gerar MAIS partes que o mínimo (acontece por
// granularidade dos arquivos), fico com o guloso — ele nunca perde no número.
let lotes = empacotar(CAP);
const N = lotes.length;

// Busca binária pelo MENOR limite que ainda cabe em N partes. A média (custoTotal/N) parece o
// alvo óbvio, mas não é: um único arquivo pequeno na cauda empurra uma parte a mais e o
// resultado é descartado. Medido com 6×50 KB + o shell de 250 B — a média dava 3 partes onde o
// teto dava 2, o equilíbrio era rejeitado, e a última parte saía com 50 KB (abaixo do piso).
// A busca acha o limite exato onde o empacotamento ainda fecha em N, e aí as partes saem
// parecidas por construção.
let lo = maiorCusto + RESERVA;
let hi = CAP;
while (lo < hi) {
  const meio = Math.floor((lo + hi) / 2);
  if (empacotar(meio).length <= N) hi = meio; else lo = meio + 1;
}
const equilibrado = empacotar(lo);
if (equilibrado.length <= N) lotes = equilibrado;

if (!existsSync(OUT)) mkdirSync(OUT, { recursive: true });
const generatedAt = new Date().toISOString();
const largura = Math.max(2, String(lotes.length).length);

/** [nome, bytes] de cada parte escrita — alimenta o aviso de piso lá embaixo. */
const tamanhosEscritos = [];

lotes.forEach((lote, i) => {
  const envelope = {
    schema: 'cowork-payload/1',
    source: `cowork:${ENTRY}`,
    generatedAt,
    hash: 'fnv1a64',
    part: i + 1,
    parts: lotes.length,
    // (1) POR PARTE — o applier compara com files.length DESTA parte.
    fileCount: lote.length,
    totalBytes: lote.reduce((n, r) => n + r.rec.bytes, 0),
    // (2) ARRAY EM TODA PARTE (o applier recusa parte sem o campo), mas o CONTEUDO so na
    // primeira: o applier faz `payloads.flatMap(p => p.missing)`, ou seja, a semantica e UNIAO
    // do lote. Repetir a lista inteira em cada parte multiplicava o mesmo path pelo numero de
    // partes — o teste com 23 partes e 1 ausente imprimiu "declarou 23 ausente(s)", que le como
    // 23 arquivos faltando. Uma vez no lote = a uniao certa, e a contagem volta a significar.
    missing: i === 0 ? missing : [],
    files: lote.map((r) => r.rec),
  };
  const nome = join(OUT, `payload.part${String(i + 1).padStart(largura, '0')}.json`);
  const texto = JSON.stringify(envelope);
  writeFileSync(nome, texto);
  const bytes = Buffer.byteLength(texto, 'utf8');
  tamanhosEscritos.push([nome, bytes]);
  console.log(`  ok ${nome} — ${String(lote.length).padStart(3)} arquivo(s) . ${(bytes / 1024).toFixed(1)} KiB${bytes > CAP ? '  ACIMA DO CAP' : ''}`);
});

const totalBytes = registros.reduce((n, r) => n + r.rec.bytes, 0);
console.log(`\n  PARTES: ${lotes.length} . ARQUIVOS: ${registros.length} . ${(totalBytes / 1048576).toFixed(2)} MB de conteudo`);

// AVISO DE PISO — relato, não veredito. O piso exato do consumidor não é conhecido (medido
// só o intervalo 41-87 KB), e reprovar por um número que eu não sei seria transformar
// ignorancia em reprovacao. Com o empacotamento equilibrado acima isto quase nunca dispara;
// quando disparar, e porque o lote e pequeno demais pra encher uma parte — ai a parte unica
// e o lote inteiro, e o aviso e o que importa.
if (PISO > 0) {
  const pequenas = tamanhosEscritos.filter(([, b]) => b < PISO);
  if (pequenas.length) {
    console.log(`\n  AVISO ${pequenas.length} parte(s) abaixo do piso de persistencia (${PISO} bytes):`);
    for (const [nome, b] of pequenas) console.log(`     . ${nome} — ${b} bytes`);
    console.log(`     Parte pequena pode chegar INLINE no contexto do consumidor em vez de virar`);
    console.log(`     arquivo em disco, e ai ela e inaplicavel (escrever de la e transcricao).`);
    console.log(`     Medido 2026-08-22: 41 KB veio inline · 87 KB veio em disco.`);
  }
}
if (excluidos.size) console.log(`  excluidos por --exclude: ${[...excluidos].join(', ')}`);
if (missing.length) {
  console.log(`  AVISO missing (${missing.length}): ${missing.join(', ')}`);
  console.log(`     --require-complete-shell vai RECUSAR o lote enquanto houver ausente. E o desenho.`);
} else {
  console.log(`  missing: [] — o grafo do shell fecha.`);
}
console.log(`\n  aplicar:\n    node scripts/design-sync/aplicar-payload.mjs ${OUT}/payload.part*.json --require-complete-shell\n`);
