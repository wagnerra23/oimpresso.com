#!/usr/bin/env node
// @ts-check
/**
 * aplicar-payload.mjs — consome bundle Design v2 (ou payload legado) sem transcrição.
 *
 * POR QUE EXISTE: o caminho `DesignSync.get_file` entrega o conteúdo no CONTEXTO do agente, e
 * escrever de lá é transcrição — a classe que causou o STALE de 2026-08-11. Isso criava um teto
 * artificial ("arquivo pequeno não tem rota fiel"), que era limitação do transporte, não do
 * problema. Aqui o conteúdo entra como DADO: fetch → JSON.parse → writeFile. Nenhum byte passa
 * por prosa de agente, em nenhuma das duas pontas.
 *
 * No schema v2, o manifesto descreve o estado-alvo completo e as partes carregam somente chunks
 * added/modified. O consumidor exige sequência 1..N, base ativa, SHA-256 e grafo completo;
 * aplica em staging e promove os quatro destinos atomicamente, com rollback. O modo legado
 * completo também é promovido por transação; lote legado parcial fica como compatibilidade.
 *
 * FIDELIDADE — o que este script VERIFICA de fato (ver o bloco no laço, com a medição):
 *   (a) BYTES declarado == bytes reais, por arquivo. Divergiu = NÃO escreve e sai != 0.
 *       Arquivo SEM `bytes` não é "conferido", é NÃO MEDIDO: conta no rodapé, e em
 *       `--require-complete-shell` recusa o lote.
 *   (b) DIGEST — o envelope declara a convenção (`hash`) e o arquivo traz `fnv64`. Comparação
 *       feita e REPORTADA no rodapé; nunca veredito. Medições, com data:
 *         · 2026-08-17, [CL], 5 variantes → 0/118 · 2026-08-22, [CC], 11 variantes → 0/118
 *         · a `fnv1a64()` daqui bate 5/5 vetores publicados de FNV-1a 64 (controle positivo)
 *         · o envelope do payload de 2026-08-17 declara exatamente o algoritmo implementado aqui
 *       Ou seja: os dois lados dizem rodar a mesma função e o valor não bate. Isso é
 *       CONTRADIÇÃO EM ABERTO, não ignorância de um lado só. Vira veredito quando o gerador
 *       emitir selftest de vetor conhecido no próprio payload.
 *   (c) `missing` declarado pelo gerador é lido nos DOIS modos — relato em lote parcial,
 *       bloqueio em `--require-complete-shell`.
 *   (d) a prova cruzada dos 21 arquivos (2026-08-17, rota independente `get_file` →
 *       `--export-from`) é INDÍCIO, não "prova forte": 21 sem falha limita a taxa a ~13% e a
 *       amostra é enviesada para arquivos grandes (os pequenos voltam inline), com poder zero
 *       sobre a rota que originou o STALE de 2026-08-11.
 *
 * Uso:
 *   node scripts/design-sync/aplicar-payload.mjs payload.part*.json --dry --require-complete-shell
 *   node scripts/design-sync/aplicar-payload.mjs payload.part*.json --require-complete-shell
 *   node scripts/design-sync/aplicar-payload.mjs <legado.json>         # compatibilidade parcial
 *
 * ⚠️ NUNCA ponha `.md` dentro de `prototipo-ui/cowork/`: R1 do `cowork-ssot-guard` reprova
 * (cowork/ é build-only; knowledge mora em canon). Isso NÃO mudou. O que mudou (2026-08-21,
 * decisão [W]) é o desfecho: `.md` deixou de ser DESCARTADO e passa a pousar em
 * `prototipo-ui/design-docs/`, preservando a árvore do vivo. O descarte custava caro — 204
 * `.md` vivos no Cowork, 0 no repo, e ondas construídas a partir de cópia colada no chat.
 */
import { readFileSync, writeFileSync, existsSync, mkdirSync } from 'node:fs';
import { join, dirname, normalize, sep } from 'node:path';
import { payloadDependencyGraph, normalizePayloadPath } from './payload-dependency-graph.mjs';
import { dsRuntimeRelPath } from '../governance/cowork-mirror-freshness.mjs';
import { anchorRelPath } from '../governance/anchor-content-check.mjs'; // fonte unica do parse da ancora
import { BUNDLE_SCHEMA } from './bundle-contract.mjs';
import { applyBundleTransaction, applyLegacySnapshotTransaction } from './bundle-transaction.mjs';

const ROOT = process.cwd();
const DESTINO = 'prototipo-ui/cowork';
// Knowledge do Cowork (.md) NÃO pousa no espelho: R1 do cowork-ssot-guard reprova .md em
// cowork/, que é build-only. Antes eles eram DESCARTADOS — e o efeito foi 204 .md vivos
// invisíveis ao repo (medido 2026-08-20), incluindo os F1 que ondas inteiras precisavam.
// Destino canônico decidido por [W] em 2026-08-21: fora de cowork/, preservando a árvore
// do vivo. Não pode ser `prototipo-ui/cowork-*` (R2 barra) nem `prototipo-ui/handoffs/`
// (o handoff-sign-submit.yml observa esse path e submeteria cada doc à Forja).
const DESTINO_DOCS = 'prototipo-ui/design-docs';
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

// ── ÂNCORA DE DESIGN: normalização na ENTRADA, porque o Cowork escreve com pasta de módulo ──
//
// O QUE FOI MEDIDO (2026-08-24, corpus real deste repo, 292 charters):
//   O Cowork emite `related_prototype: prototipo-ui/cowork/ponto/ponto-telas.jsx`, mas o
//   arquivo pousa em `prototipo-ui/cowork/ponto-telas.jsx` — a pasta `ponto/` não existe no
//   espelho. Resultado: 15 charters do staging com âncora apontando pra lugar nenhum
//   (13 Ponto · 1 Relatorios · 1 Modules), todos com o arquivo PRESENTE na forma plana.
//
// POR QUE A NORMALIZAÇÃO É AQUI: este é o chokepoint onde todo `.md` do Cowork entra no repo.
// Corrigir o arquivo já pousado seria editar ESPELHO — some no próximo `--export-from`
// (§5 2026-08-13). O conserto tem que ser no mecanismo, e é forward-only: charter que chega
// daqui pra frente nasce com a âncora resolvível.
//
// ⚠️ O ESPELHO **NÃO** É PLANO — e essa era a premissa errada que quase virou código. Medido:
// 244 arquivos na raiz de `cowork/` MAIS 74 em 5 subdirs REAIS (`ds-v6/`, `venda-v3/`,
// `produto-preco-especial/`, `prototipos/`, `prototipo-ui-patch/`). Três charters VIVOS
// dependem desses subdirs (Financeiro/Cobranca, Produto/SellingPrices, Sells/CreateV3).
// Colapsar tudo pra basename quebraria os três. Por isso a guarda é ORDENADA e a primeira
// perna é a que protege: **path original existe ⇒ NÃO TOCA, ponto final** — determinístico,
// não depende de o basename coincidir (que é o que os salvaria por sorte, não por desenho).
//
// FP medido no corpus: 0. As 3 legítimas passam na 1ª guarda; 15 das 16 quebradas colapsam
// pra um arquivo que existe; a 16ª (`public/cowork-preview/Chat.html`) não tem forma plana e
// por isso é DEIXADA COMO ESTÁ e reportada — nunca inventada.

// "Como extrair o path de um `related_prototype`" JÁ TEM DONO: `anchorRelPath` do
// `anchor-content-check.mjs`, que o `cowork-mirror-freshness.mjs` importa declarando-o "fonte
// única". Reescrevi o regex aqui na 1ª versão — duplicata que ficaria drifando em silêncio
// (§5 2026-08-03: autorar máquina paralela a um tema que já tem dono). Importa, não reimplementa.

/**
 * Normaliza UMA âncora. Puro: recebe o valor e um predicado `existe(relPath)`.
 * Devolve `{ valor, mudou, motivo }` — `motivo` alimenta o relato (silêncio seria pior que
 * o defeito: quem não colapsa precisa aparecer).
 *
 * Ordem das guardas — a ordem É o desenho, não estilo:
 *   1. não resolve a path (prosa / n/a)      → intocado
 *   2. path SEM `/` (já plano)               → intocado
 *   3. path original EXISTE no espelho       → intocado  ← protege subdir legítimo
 *   4. basename existe no espelho            → COLAPSA
 *   5. nenhum dos dois existe                → intocado + reportado (nunca inventa)
 */
export function normalizarAncora(valor, existe) {
  const rel = anchorRelPath(valor);
  if (!rel) return { valor, mudou: false, motivo: 'prosa' };
  if (!rel.includes('/')) return { valor, mudou: false, motivo: 'ja-plano' };
  if (existe(rel)) return { valor, mudou: false, motivo: 'subdir-real' };
  const base = rel.slice(rel.lastIndexOf('/') + 1);
  if (!existe(base)) return { valor, mudou: false, motivo: 'sem-alvo' };
  // Substitui SÓ o trecho de diretório, preservando prefixo (`prototipo-ui/cowork/`), a seção
  // no parêntese e toda a prosa em volta — reescrever a linha inteira comeria informação
  // vizinha (§5 2026-08-02). Âncora ao redor do path pra não casar dentro de forma maior.
  const alvo = rel.slice(0, rel.lastIndexOf('/') + 1);
  return { valor: valor.replace(alvo + base, base), mudou: true, motivo: 'colapsado' };
}

/** Aplica a normalização ao FRONTMATTER de um charter. Toca exclusivamente a linha
 *  `related_prototype:` — nenhum outro byte do documento é reescrito. */
export function normalizarCharter(texto, existe) {
  const m = texto.match(/^related_prototype:[ \t]*(.+)$/m);
  if (!m) return { texto, mudou: false, motivo: 'sem-campo' };
  const r = normalizarAncora(m[1].trim(), existe);
  if (!r.mudou) return { texto, mudou: false, motivo: r.motivo, de: m[1].trim() };
  const linha = m[0].replace(m[1], r.valor);
  return { texto: texto.replace(m[0], linha), mudou: true, motivo: r.motivo, de: m[1].trim(), para: r.valor };
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

  if (obj?.schema === BUNDLE_SCHEMA) return obj;
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
const v2 = payloads.filter((payload) => payload.schema === BUNDLE_SCHEMA);
if (v2.length) {
  if (v2.length !== payloads.length) {
    console.error('✗ lote mistura bundle v2 com payload legado; aplique contratos separados.');
    process.exit(2);
  }
  try {
    const result = await applyBundleTransaction({ root: ROOT, parts: v2, dry });
    const summary = result.report.summary;
    console.log(`\n  ✓ BUNDLE v2 ${dry ? 'VALIDADO (dry-run)' : 'PROMOVIDO ATOMICAMENTE'}`);
    console.log(`  id: ${result.manifest.bundleId} · modo ${result.manifest.mode} · ${result.manifest.totals.files} arquivo(s)`);
    console.log(`  transporte: ${summary.transportChanges} mudança(s) · telas: ${summary.screens} · pendentes: ${summary.pending || 0} · bloqueadas: ${summary.blocked || 0}`);
    console.log(`  estado: scripts/design-sync/state/active-bundle.json`);
    console.log(`  lista operacional: scripts/design-sync/state/application-report.json\n`);
    process.exit(0);
  } catch (error) {
    console.error(`\n✗ BUNDLE v2 RECUSADO: ${error.message}`);
    console.error('  Nada foi promovido; o estado anterior permanece ativo.\n');
    process.exit(1);
  }
}
// O envelope pode declarar a convenção do digest (`hash`). O `flatMap` achata os lotes e
// perderia essa procedência, então guardo por REFERÊNCIA do objeto — sem copiar conteúdo.
const hashDeclarado = new WeakMap();
const files = payloads.flatMap((p) => {
  const lista = p.files || [];
  const decl = typeof p.hash === 'string' && p.hash.trim() ? p.hash.trim() : null;
  if (decl) for (const f of lista) hashDeclarado.set(f, decl);
  return lista;
});
if (!Array.isArray(files) || !files.length) { console.error('✗ payload sem `files`'); process.exit(2); }

const totalDeclarado = payloads.reduce((n, p) => n + (Number(p.totalBytes) || 0), 0);
console.log(`\n  APLICAR PAYLOAD — ${payloads.length} lote(s) · ${files.length} arquivo(s) · ${totalDeclarado.toLocaleString('pt-BR')} bytes`);
for (const p of payloads) console.log(`  origem: ${typeof p.source==='string'?p.source:JSON.stringify(p.source)} · gerado: ${p.generatedAt || '?'} · ${p.arquivo}`);
console.log(`  modo: ${requireCompleteShell ? 'SHELL COMPLETO (fechamento transitivo obrigatório)' : 'lote parcial'}${dry ? ' · DRY — nada será escrito' : ''}`);
console.log(`  destinos: ${DESTINO}/ + scripts/design-sync/mirror-snapshot/ para _ds/** + ${DESTINO_DOCS}/ para .md\n`);

const tally = { NOVO: 0, ATUALIZADO: 0, inalterado: 0 };
const corrompidos = [], forade = [], preparados = [];
// Contadores do que este script NÃO verifica — o silêncio era indistinguível de "conferi".
const digest = { comparados: 0, divergentes: 0, convencao: null };
let semProvaDeBytes = 0;

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
    destinoBase = DESTINO_DOCS;
  }
  const alvoRel = normalize(join(destinoBase, destinoPath));
  const baseRel = normalize(destinoBase);
  if (!alvoRel.startsWith(baseRel + sep) && alvoRel !== baseRel) { forade.push(rel + ' (fora do destino)'); continue; }

  // INTEGRIDADE — o que eu consigo VERIFICAR, e o que eu deixo de verificar DITO EM VOZ ALTA.
  //
  // (a) BYTES declarado == bytes reais. Pega truncagem — o modo de falha dominante quando o
  //     transporte fatia (o teto de 256 KiB do get_file). 118/118 ✓ em 2026-08-17.
  //     NÃO pega o que preserva tamanho: troca de byte, reordenação, U+FFFD substituindo
  //     sequência de exatamente 3 bytes. Para essas classes a chance de escapar é 1, não é baixa.
  //
  // (b) DIGEST — comparado e reportado, nunca veredito. Bloquear por um valor que nenhum dos
  //     dois lados consegue reconciliar transformaria uma contradição em aberto numa reprovação
  //     de transporte. Ver o docblock do topo para as duas medições datadas e o controle positivo.
  //
  // (c) A prova cruzada dos 21 arquivos é indício com amostra enviesada — ver docblock (d).
  //     A frase anterior aqui ("duas rotas que não compartilham nada" / "melhor que um hash
  //     opaco") era forte demais em três frentes: as rotas COMPARTILHAM a origem (mesmo projeto,
  //     mesmo backend — só o trecho final difere), 21/118 não sustenta "prova", e um digest de
  //     64 bits deixa passar corrupção acidental com probabilidade ~5e-20, dezenove ordens de
  //     grandeza abaixo do que a amostragem limita.
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
  // (C2) `bytes` ausente não é "conferido" — é NÃO MEDIDO. Antes passava calado, com o mesmo
  // texto de log de quem foi verificado. Conta pra sair no rodapé; em --require-complete-shell
  // recusa, porque modo que promete fechamento não pode escrever conteúdo sem prova nenhuma.
  if (f.bytes == null) semProvaDeBytes++;
  // (C1) O envelope declara a convenção do digest e o arquivo traz o valor. Comparar é de graça
  // — o `calc` já foi computado acima. NÃO vira veredito: bloquear por um digest que não se sabe
  // reproduzir é transformar ignorância em reprovação. Vira CONTRADIÇÃO REPORTADA no rodapé.
  const decl = hashDeclarado.get(f);
  if (decl && typeof f.fnv64 === 'string' && f.fnv64) {
    digest.convencao = decl;
    digest.comparados++;
    if (f.fnv64.toLowerCase() !== calc.toLowerCase()) digest.divergentes++;
  }

  preparados.push({ rel, destinoBase, destinoPath, alvoRel, conteudo, binary, text: binary ? null : f.content, calc });
}

// ── NORMALIZAÇÃO DA ÂNCORA — depois da prova de transporte, antes de qualquer transação ─────
//
// A ORDEM importa e é deliberada: a conferência de `bytes`/digest acima mede FIDELIDADE DO
// TRANSPORTE (o payload chegou inteiro?). Normalizar antes dela invalidaria essa prova — o
// conteúdo deixaria de bater com o que o produtor declarou. Aqui, o transporte já foi provado
// e o que muda é a SEMÂNTICA do ponteiro, que é responsabilidade deste consumidor.
//
// Fica FORA do laço porque o predicado `existe()` precisa enxergar o LOTE INTEIRO: um charter
// e o `.jsx` que ele aponta podem chegar no mesmo payload, e nesse caso o alvo ainda não está
// em disco. Perguntar só ao disco marcaria como `sem-alvo` uma âncora que o próprio lote
// resolve.
const noLote = new Set(preparados.filter((p) => p.destinoBase === DESTINO).map((p) => p.destinoPath.split(sep).join('/')));
const existeNoEspelho = (relPath) => noLote.has(relPath) || existsSync(join(ROOT, DESTINO, relPath));
const ancoras = { colapsado: [], semAlvo: [], subdirReal: 0 };
for (const p of preparados) {
  if (p.binary || !p.rel.toLowerCase().endsWith('.charter.md')) continue;
  const r = normalizarCharter(p.text, existeNoEspelho);
  if (r.motivo === 'subdir-real') ancoras.subdirReal++;
  if (r.motivo === 'sem-alvo') ancoras.semAlvo.push(`${p.rel} → ${r.de}`);
  if (!r.mudou) continue;
  p.text = r.texto;
  p.conteudo = Buffer.from(r.texto, 'utf8');
  p.calc = fnv1a64(p.conteudo);
  ancoras.colapsado.push(`${p.rel}: ${r.de} → ${r.para}`);
}
if (ancoras.colapsado.length || ancoras.semAlvo.length || ancoras.subdirReal) {
  console.log(`  ÂNCORA DE DESIGN — ${ancoras.colapsado.length} normalizada(s) · ${ancoras.subdirReal} subdir real preservado(s) · ${ancoras.semAlvo.length} sem alvo`);
  for (const x of ancoras.colapsado) console.log(`     ✓ ${x}`);
  // `sem-alvo` NÃO é bloqueio: o alvo pode chegar num lote posterior, e inventar um path que
  // não existe seria pior que o ponteiro podre (o gate do anchor-content-check é quem cobra).
  for (const x of ancoras.semAlvo) console.log(`     ⚠ sem alvo no espelho, mantido como veio — ${x}`);
  console.log('');
}

// PORTÃO DO SHELL COMPLETO — roda ANTES de qualquer write. O payload servido é a rota que
// derruba o teto de 256 KiB do get_file; o grafo impede que "missing: []" seja aceito por fé.
// (C3) `missing` é declaração DE QUEM SERVIU o payload — ele avisou que faltava coisa. Isso vale
// nos DOIS modos: em lote parcial é RELATO (o applier repassa em vez de engolir), em shell
// completo continua BLOQUEIO. Antes as duas leituras viviam dentro do `if` abaixo, então o
// lote parcial aplicava em silêncio um payload que se declarava incompleto.
const semDeclaracao = payloads.filter((p) => !Array.isArray(p.missing)).map((p) => p.arquivo);
const declarados = payloads.flatMap((p) => Array.isArray(p.missing) ? p.missing : []);
if (!requireCompleteShell && (declarados.length || semDeclaracao.length)) {
  if (declarados.length) console.log(`  ℹ️  o gerador declarou ${declarados.length} ausente(s): ${declarados.join(', ')}`);
  if (semDeclaracao.length) console.log(`  ℹ️  payload sem \`missing\`: ${semDeclaracao.join(', ')} — não dá pra saber se está completo`);
  console.log('');
}

if (requireCompleteShell) {
  const grafo = payloadDependencyGraph(preparados.map((f) => ({
    path: f.rel, content: f.text, binary: f.binary,
  })));
  console.log(`  grafo: ${grafo.reachable.length} alcançável(is) · ${grafo.edges.length} aresta(s) · ${grafo.external.length} externa(s) ignorada(s)`);
  if (semDeclaracao.length) forade.push(`payload sem \`missing: []\`: ${semDeclaracao.join(', ')}`);
  if (declarados.length) forade.push(`payload declarou ${declarados.length} ausente(s): ${declarados.join(', ')}`);
  if (semProvaDeBytes) forade.push(`${semProvaDeBytes} arquivo(s) sem \`bytes\` — sem prova de integridade, e este modo promete fechamento`);
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

// Payload legado completo também ganha promoção transacional. Se o produtor declarou part/parts,
// a sequência vira contrato: `part01` ausente não pode mais passar só porque o grafo restante fecha.
if (requireCompleteShell) {
  const grupos = new Map();
  for (const payload of payloads.filter((item) => item.part != null || item.parts != null)) {
    if (!Number.isInteger(payload.part) || !Number.isInteger(payload.parts) || payload.parts < 1) {
      console.error('✗ metadados part/parts inválidos no payload legado. Nada foi escrito.');
      process.exit(1);
    }
    const key = `${typeof payload.source === 'string' ? payload.source : JSON.stringify(payload.source)}|${payload.generatedAt || '?'}`;
    const group = grupos.get(key) || [];
    group.push(payload);
    grupos.set(key, group);
  }
  for (const [key, group] of grupos) {
    const totals = new Set(group.map((item) => item.parts));
    const total = group[0].parts;
    const indices = group.map((item) => item.part).sort((a, b) => a - b);
    const expected = Array.from({ length: total }, (_, index) => index + 1);
    if (totals.size !== 1 || indices.length !== total || indices.some((value, index) => value !== expected[index])) {
      console.error(`✗ lote legado incompleto (${key}): recebeu [${indices.join(',')}], esperava [${expected.join(',')}].`);
      console.error('  Nada foi escrito; baixe todas as partes, inclusive a part01.');
      process.exit(1);
    }
  }
  try {
    const result = await applyLegacySnapshotTransaction({
      root: ROOT,
      prepared: preparados,
      source: `legacy:${payloads.map((item) => item.source || item.arquivo).join('+')}`,
      dry,
    });
    console.log(`\n  ✓ PAYLOAD LEGADO ${dry ? 'VALIDADO em staging' : 'PROMOVIDO ATOMICAMENTE'} como snapshot ${result.manifest.bundleId}`);
    console.log('  Próxima exportação deve usar o bundle v2 para SHA-256, delta e estado-base verificável.\n');
    process.exit(0);
  } catch (error) {
    console.error(`\n✗ SNAPSHOT LEGADO RECUSADO: ${error.message}`);
    console.error('  Nada foi promovido; o estado anterior permanece ativo.\n');
    process.exit(1);
  }
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
// O que NÃO foi verificado sai junto do que foi — senão "não achei divergência" e "não procurei"
// ficam com o mesmo texto, que é a família LC-13 já catalogada no repo.
if (semProvaDeBytes) {
  console.log(`  ⚠ ${semProvaDeBytes} arquivo(s) escrito(s) SEM prova de bytes — o payload não declarou \`bytes\`.`);
}
if (digest.comparados) {
  const ok = digest.comparados - digest.divergentes;
  if (digest.divergentes) {
    console.log(`  ⚠ o gerador declara "${digest.convencao}" e o digest NÃO bate em ${digest.divergentes}/${digest.comparados} arquivo(s)`);
    console.log(`     bytes conferem em ${preparados.length - semProvaDeBytes}/${preparados.length}; o digest segue como REFERÊNCIA, não veredito.`);
    console.log(`     Pra virar veredito, o gerador precisa emitir selftest de vetor conhecido no payload.`);
  } else {
    console.log(`  ✓ digest bate em ${ok}/${digest.comparados} — convenção "${digest.convencao}" reproduzível daqui.`);
  }
}
// Órfãos são RELATO, não poda: o apply não apaga, e o que sobra no espelho fora deste lote
// pode ser legítimo (bundles, origem externa). Podar é decisão [W].
console.log(`\n  ℹ️  apply não apaga — arquivos do espelho fora deste lote seguem lá (relato, não poda).`);
console.log('');
process.exit(0);
