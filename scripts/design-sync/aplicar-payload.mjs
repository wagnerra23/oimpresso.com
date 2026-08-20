#!/usr/bin/env node
// @ts-check
/**
 * aplicar-payload.mjs — escreve o espelho Cowork a partir de um PAYLOAD servido, não de `get_file`.
 *
 * POR QUE EXISTE: o caminho `DesignSync.get_file` entrega o conteúdo no CONTEXTO do agente, e
 * escrever de lá é transcrição — a classe que causou o STALE de 2026-08-11. Isso criava um teto
 * artificial ("arquivo pequeno não tem rota fiel"), que era limitação do transporte, não do
 * problema. Aqui o conteúdo entra como DADO: fetch → JSON.parse → writeFile. Nenhum byte passa
 * por prosa de agente, em nenhuma das duas pontas.
 *
 * Os payloads são gerados do lado do design: Cowork (shell + arquivos da aplicação) e DS
 * (bundle/CSS/assets). Em `--require-complete-shell`, este lado NÃO confia na lista do gerador:
 * recalcula e fecha transitivamente `src/link` + `@import/url` + imports JS. Manifesto DERIVADO,
 * não lista curada; query `?v=` é normalizada.
 *
 * FIDELIDADE — o que este script VERIFICA de fato (ver o bloco no laço, com a medição):
 *   (a) BYTES declarado == bytes reais, por arquivo. Divergiu = NÃO escreve e sai != 0.
 *   (b) o payload traz `fnv64`, mas a convenção NÃO é reproduzível daqui — 5 variantes testadas,
 *       0/118 bateram. Ele é impresso como referência, nunca usado como veredito: bloquear por
 *       hash que eu não sei calcular seria transformar ignorância minha em falha do transporte.
 *   (c) a prova forte é EXTERNA a este script: os 21 arquivos que desceram pela rota
 *       independente `get_file` → `--export-from` são byte-idênticos aos do payload (21/21).
 *
 * Uso:
 *   node scripts/design-sync/aplicar-payload.mjs <payload.json> --dry   # relatório, não escreve
 *   node scripts/design-sync/aplicar-payload.mjs <payload.json>         # aplica lote parcial
 *   node scripts/design-sync/aplicar-payload.mjs <cowork.json> <ds.json> --require-complete-shell
 *     # exige oimpresso.com.html + fechamento transitivo HTML/CSS/JS; `_ds/` vai ao snapshot
 *
 * ⚠️ NUNCA aponte o destino pra fora de `prototipo-ui/cowork/` e nunca ponha `.md` lá dentro:
 * R1 do `cowork-ssot-guard` reprova (cowork/ é build-only; knowledge mora em canon).
 */
import { readFileSync, writeFileSync, existsSync, mkdirSync } from 'node:fs';
import { join, dirname, normalize, sep } from 'node:path';
import { payloadDependencyGraph, normalizePayloadPath } from './payload-dependency-graph.mjs';
import { dsRuntimeRelPath } from '../governance/cowork-mirror-freshness.mjs';

const ROOT = process.cwd();
const DESTINO = 'prototipo-ui/cowork';
const args = process.argv.slice(2);
const arquivos = args.filter((a) => !a.startsWith('--'));
const dry = args.includes('--dry');
const requireCompleteShell = args.includes('--require-complete-shell');

if (!arquivos.length || arquivos.some((a) => !existsSync(a))) {
  console.error('✗ uso: node scripts/design-sync/aplicar-payload.mjs <payload.json> [<ds.json> ...] [--dry] [--require-complete-shell]');
  process.exit(2);
}

/** FNV-1a 64-bit sobre os bytes UTF-8 — BigInt porque 64 bits não cabem em Number. */
function fnv1a64(value) {
  const bytes = Buffer.isBuffer(value) ? value : Buffer.from(value, 'utf8');
  let h = 0xcbf29ce484222325n;
  const prime = 0x100000001b3n, mask = 0xffffffffffffffffn;
  for (const b of bytes) { h = ((h ^ BigInt(b)) * prime) & mask; }
  return h.toString(16).padStart(16, '0');
}

/**
 * Lê um payload com as DUAS guardas que faltavam — medidas em 2026-08-19.
 *
 * O painel do protocolo anuncia este caminho como "sem teto get_file". A frase valia pro
 * conteúdo POR ARQUIVO, mas não pro payload em si: `sync/payload.json` tem ~3,5 MB e o
 * único transporte que o agente tem pra buscá-lo (`DesignSync.get_file`) corta em 256 KiB
 * e devolve `"truncated": true`. Sem guarda, o JSON cortado morria num
 * `SyntaxError: Unterminated string`, que não diz NADA sobre a causa nem sobre o remédio —
 * e o remédio já existe aqui: o applier aceita vários lotes e faz merge (`payloads.flatMap`).
 */
function lerPayload(arquivo) {
  const bruto = readFileSync(arquivo, 'utf8');
  let obj;
  try {
    obj = JSON.parse(bruto);
  } catch (e) {
    console.error(`✗ payload ilegível: ${arquivo}`);
    console.error(`  ${e && e.message}`);
    console.error(`  JSON cortado no meio costuma ser TETO DE TRANSPORTE, não payload ruim.`);
    console.error(`  Este arquivo tem ${bruto.length.toLocaleString('pt-BR')} chars; o DesignSync.get_file`);
    console.error(`  corta em 256 KiB e sinaliza com "truncated": true no envelope.`);
    console.error(`  Remédio: sirva o payload em PARTES de até 256 KiB — este applier já junta lotes:`);
    console.error(`    node scripts/design-sync/aplicar-payload.mjs p1.json p2.json ... --require-complete-shell`);
    process.exit(2);
  }
  // 3o caso: ENVELOPE do `DesignSync.get_file`, nao o payload. Medido 2026-08-20 com o
  // artefato REAL (259,5 KB): o envelope e JSON VALIDO (so o `content` de dentro esta
  // cortado) e nao tem `fileCount` — entao escapa das duas guardas acima e cai la embaixo
  // no generico "payload sem `files`", que foi exatamente a mensagem que mandou uma sessao
  // concluir errado. E o caso que o agente MAIS encontra: quando o harness persiste a
  // resposta do get_file em disco, e este o formato do arquivo.
  //
  // Detecta, NAO desembrulha: o docblock deste arquivo diz que ele aplica payload SERVIDO,
  // "nao de get_file". Desembrulhar aqui abriria a rota que o desenho recusa. Quem consome
  // envelope de proposito e o `cowork-mirror-freshness --export-from`.
  if (obj && typeof obj === 'object' && typeof obj.content === 'string' && !Array.isArray(obj.files)) {
    console.error(`✗ isto e um ENVELOPE do DesignSync.get_file, nao um payload: ${arquivo}`);
    console.error(`  path="${obj.path || '?'}" truncated=${obj.truncated === true}`);
    if (obj.truncated === true) {
      console.error(`  O download veio INCOMPLETO — o get_file corta em 256 KiB e o payload tem ~3,5 MB.`);
    }
    console.error(`  Este applier recebe o payload SERVIDO, nao a resposta do get_file.`);
    console.error(`  Remedio: sirva o payload em PARTES de ate 256 KiB — este applier ja junta lotes:`);
    console.error(`    node scripts/design-sync/aplicar-payload.mjs p1.json p2.json ... --require-complete-shell`);
    process.exit(2);
  }

  const declarado = Number(obj && obj.fileCount);
  const real = Array.isArray(obj && obj.files) ? obj.files.length : 0;
  if (Number.isFinite(declarado) && declarado !== real) {
    console.error(`✗ payload incompleto: ${arquivo}`);
    console.error(`  declara fileCount=${declarado} mas traz ${real} arquivo(s) — faltam ${declarado - real}.`);
    console.error(`  Aplicar assim escreveria um espelho pela metade SEM avisar, que é pior que não aplicar.`);
    process.exit(2);
  }
  return obj;
}

const payloads = arquivos.map((arquivo) => ({ arquivo, ...lerPayload(arquivo) }));
const files = payloads.flatMap((p) => p.files || []);
if (!Array.isArray(files) || !files.length) { console.error('✗ payload sem `files`'); process.exit(2); }

const totalDeclarado = payloads.reduce((n, p) => n + (Number(p.totalBytes) || 0), 0);
console.log(`\n  APLICAR PAYLOAD — ${payloads.length} lote(s) · ${files.length} arquivo(s) · ${totalDeclarado.toLocaleString('pt-BR')} bytes`);
for (const p of payloads) console.log(`  origem: ${typeof p.source==='string'?p.source:JSON.stringify(p.source)} · gerado: ${p.generatedAt || '?'} · ${p.arquivo}`);
console.log(`  modo: ${requireCompleteShell ? 'SHELL COMPLETO (fechamento transitivo obrigatório)' : 'lote parcial'}${dry ? ' · DRY — nada será escrito' : ''}`);
console.log(`  destinos: ${DESTINO}/ + scripts/design-sync/mirror-snapshot/ para _ds/**\n`);

const tally = { NOVO: 0, ATUALIZADO: 0, inalterado: 0 };
const corrompidos = [], forade = [], preparados = [];

for (const f of files) {
  let rel;
  try { rel = normalizePayloadPath(f.path); }
  catch (e) { forade.push(`${String(f.path || '')} (${e.message})`); continue; }
  // trava de escopo: nada fora do espelho, nada subindo diretório
  // ⚠️ normalize() devolve o separador da PLATAFORMA (`\` no Windows, `/` no POSIX). Comparar
  // com a constante escrita com `/` reprovava 118/118 no Windows e 0/118 no CI — o mesmo teste
  // com dois vereditos conforme o SO (§5 2026-08-07). Normaliza os DOIS lados antes de comparar.
  let destinoBase = DESTINO, destinoPath = rel;
  if (rel.startsWith('_ds/')) {
    try { destinoPath = dsRuntimeRelPath(rel); }
    catch (e) { forade.push(`${rel} (${e.message})`); continue; }
    destinoBase = 'scripts/design-sync/mirror-snapshot';
  } else if (rel.toLowerCase().endsWith('.md')) {
    forade.push(rel + ' (.md — R1 do ssot-guard)'); continue;
  }
  const alvoRel = normalize(join(destinoBase, destinoPath));
  const baseRel = normalize(destinoBase);
  if (!alvoRel.startsWith(baseRel + sep) && alvoRel !== baseRel) { forade.push(rel + ' (fora do destino)'); continue; }

  // INTEGRIDADE — o que eu consigo VERIFICAR, não o que soa mais forte.
  //
  // O payload traz `fnv64` por arquivo, mas a convenção dele não é reproduzível daqui: testei
  // 5 variantes (FNV-1a/FNV-1 × bytes-utf8/latin1/charCodeAt/codePoint) e 0 de 118 bateram.
  // Bloquear por um hash que eu não sei calcular seria transformar minha ignorância em veredito
  // — e o controle positivo já provou que o errado era eu, não o transporte.
  //
  // O que eu verifico de verdade, e é mais forte:
  //   (a) BYTES declarado == bytes reais — pega truncagem/corrupção de transporte. 118/118 ✓
  //   (b) PROVA CRUZADA — os 21 arquivos que desceram hoje pela rota INDEPENDENTE do
  //       `get_file` → `--export-from` são byte-idênticos aos do payload. 21/21 ✓
  // Duas rotas que não compartilham nada concordando é evidência melhor que um hash opaco.
  if (typeof f.content !== 'string') { corrompidos.push({ rel, declarado: 'content ausente', calculado: 'esperava string' }); continue; }
  const binary = f.isBase64 === true || f.encoding === 'base64';
  const compact = binary ? f.content.replace(/\s+/g, '') : '';
  if (binary && (!compact || !/^[A-Za-z0-9+/]*={0,2}$/.test(compact) || compact.length % 4 !== 0)) {
    corrompidos.push({ rel, declarado: 'base64', calculado: 'base64 inválido' }); continue;
  }
  const conteudo = binary ? Buffer.from(compact, 'base64') : Buffer.from(f.content, 'utf8');
  const calc = fnv1a64(conteudo);
  const bytesReais = conteudo.length;
  if (f.bytes != null && f.bytes !== bytesReais) { corrompidos.push({ rel, declarado: f.bytes + ' bytes', calculado: bytesReais + ' bytes' }); continue; }

  preparados.push({ rel, destinoBase, destinoPath, alvoRel, conteudo, binary, text: binary ? null : f.content, calc });
}

// PORTÃO DO SHELL COMPLETO — roda ANTES de qualquer write. O payload servido é a rota que
// derruba o teto de 256 KiB do get_file; o grafo impede que "missing: []" seja aceito por fé.
if (requireCompleteShell) {
  const semDeclaracao = payloads.filter((p) => !Array.isArray(p.missing)).map((p) => p.arquivo);
  const declarados = payloads.flatMap((p) => Array.isArray(p.missing) ? p.missing : []);
  const grafo = payloadDependencyGraph(preparados.map((f) => ({
    path: f.rel, content: f.text, binary: f.binary,
  })));
  console.log(`  grafo: ${grafo.reachable.length} alcançável(is) · ${grafo.edges.length} aresta(s) · ${grafo.external.length} externa(s) ignorada(s)`);
  if (semDeclaracao.length) forade.push(`payload sem \`missing: []\`: ${semDeclaracao.join(', ')}`);
  if (declarados.length) forade.push(`payload declarou ${declarados.length} ausente(s): ${declarados.join(', ')}`);
  if (!grafo.entryPresent) forade.push(`entry ausente: ${grafo.entry}`);
  if (grafo.missing.length) forade.push(`grafo local incompleto: ${grafo.missing.join(', ')}`);
  if (grafo.unsafe.length) forade.push(`referência insegura: ${grafo.unsafe.map((x) => `${x.from} → ${x.ref}`).join(', ')}`);
  if (grafo.duplicates.length) forade.push(`paths duplicados: ${grafo.duplicates.join(', ')}`);
  if (!forade.length && !corrompidos.length) console.log('  ✓ GRAFO COMPLETO — HTML/CSS/JS fecham sem dependência local ausente.');
  console.log('');
}

// Atomicidade: qualquer corrupção/escopo/grafo incompleto cancela o LOTE INTEIRO.
if (forade.length || corrompidos.length) {
  if (forade.length) { console.log(`  ⛔ ${forade.length} RECUSADO(s) por escopo/cobertura:`); forade.forEach((x) => console.log(`     · ${x}`)); }
  if (corrompidos.length) {
    console.log(`\n  🔴 ${corrompidos.length} arquivo(s) com CONTEÚDO/BYTES DIVERGENTE(s):`);
    corrompidos.forEach((c) => console.log(`     · ${c.rel}\n       declarado ${c.declarado} · calculado ${c.calculado}`));
  }
  console.log('\n  Nada foi escrito deste lote.');
  process.exit(1);
}

for (const f of preparados) {
  const { rel, alvoRel, conteudo, binary, calc } = f;

  const abs = join(ROOT, alvoRel);
  const antes = existsSync(abs) ? readFileSync(abs) : null;
  const nota = antes === null ? 'NOVO' : antes.equals(conteudo) ? 'inalterado' : 'ATUALIZADO';
  tally[nota]++;
  // DELTA DE LINHAS — porque "sync" pode ser REGRESSÃO. O espelho às vezes está À FRENTE do
  // vivo: trabalho que entrou direto nele (ex: o `qa-conformance.js` ganhou os gates G14/G15
  // pelo PR #4597 em 2026-07-20, e o vivo segue em v2.4/G13). Aplicar cego apaga isso.
  // O número que denuncia é a ASSIMETRIA: os 6 syncs legítimos deste lote tinham remoção da
  // ordem da adição (+24/−8, +84/−43, +19/−19…); o regressivo era +2/−171.
  // Isto é RELATO, não bloqueio — "quem está à frente" é semântico e depende do arquivo.
  if (nota !== 'inalterado') {
    const la = binary ? 0 : (antes ? antes.toString('utf8') : '').split(/\n/).length;
    const ln = binary ? 0 : conteudo.toString('utf8').split(/\n/).length;
    const perda = binary ? 0 : la - ln;
    // Critério = PERDA LÍQUIDA de linhas, medido nos 7 deste lote (não chutado):
    //   os 6 syncs legítimos → líquido 0 · +16 · +41 · +70 · 0 · +3   (nenhum perdeu)
    //   o regressivo         → 787→618 = −169
    // Um teto de proporção (`ln < la*0.75`) NÃO discrimina: 618/787 = 78% e deixaria passar.
    const flag = perda > 20 ? '  ⚠️ PERDE ' + perda + ' LINHAS — confira se o espelho não está À FRENTE do vivo' : '';
    const metrica = binary ? `${conteudo.length.toLocaleString('pt-BR')} bytes binários` : `${conteudo.length.toLocaleString('pt-BR')} bytes · linhas ${la}→${ln}`;
    console.log(`  ${nota.padEnd(11)} ${rel}  (${metrica} · ${calc.slice(0, 12)})${flag}`);
  }
  if (!dry) { mkdirSync(dirname(abs), { recursive: true }); writeFileSync(abs, conteudo); }
}

console.log(`\n  ${tally.ATUALIZADO} atualizado(s) · ${tally.NOVO} novo(s) · ${tally.inalterado} inalterado(s)`);
// Órfãos são RELATO, não poda: o apply não apaga, e o que sobra no espelho fora deste lote
// pode ser legítimo (bundles, origem externa). Podar é decisão [W].
console.log(`\n  ℹ️  apply não apaga — arquivos do espelho fora deste lote seguem lá (relato, não poda).`);
console.log('');
process.exit(0);
