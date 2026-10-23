#!/usr/bin/env node
// @ts-check
/**
 * cowork-mirror-freshness.mjs — integridade LOCAL do espelho de protótipo (v3, 2026-10-23).
 *
 * O NOME FICOU, O PAPEL MUDOU. Até 2026-10-23 este script comparava o espelho
 * `prototipo-ui/cowork/<dono>/` com o Cowork VIVO (`DesignSync.get_file`), gravava a rodada num
 * ledger e cobrava SLA. A ADR 0438 tirou o Cowork do fluxo: a fonte do protótipo é o arquivo do
 * git, e o Artefato é só para ver e comentar. Os modos de frescor saíram na onda 2c-2
 * (--manifest, --compare, --ledger, --sla, --live-only, --sla-live-only, --lista-download,
 * --export-from, --snapshot-from, --check-novos) e o --unverified na 2c-1. Sem modo, sai 2.
 *
 * O que FICA é 100% local, sem auth nem rede:
 *   --check-refs     a poda deste PR quebrou o grafo INTERNO do espelho? (deriva do DIFF)
 *   --check-orfaos   este PR ADICIONA ao espelho arquivo que o shell não declara? (DELTA)
 *   --absent-local   o shell CARREGA algo que o espelho não tem? (deriva do SHELL)
 *   --css-refs       CSS do design-system aponta para arquivo que não existe?
 *   --preview-ds     repõe as dependências do DS no cache de preview (portão fail-closed)
 *   --compare-bundle espelho × bundle promovido — sai na onda 2c-3, junto com o bundle.
 * --check-refs e --absent-local se completam: apagar o espelho inteiro passa vazio no primeiro
 * (o diff não tem quem carregue) e é pego pelo segundo.
 *
 * IDENTIDADE DE ARQUIVO (v2, adversário 2026-07-06, segue valendo):
 *   HASH = sha256( normalize(conteúdo) ), keyed por PATH RELATIVO COMPLETO dentro do espelho.
 *   normalize = strip BOM · CRLF/CR→LF · trailing-newline única · UTF-8.
 *
 * Uso:
 *   node scripts/governance/cowork-mirror-freshness.mjs --check-refs        # exit 1 = a poda quebrou o grafo
 *   node scripts/governance/cowork-mirror-freshness.mjs --check-refs --range <a>..<b>
 *   node scripts/governance/cowork-mirror-freshness.mjs --check-refs --deleted-from <lista.txt>   # fixture/manual
 *   node scripts/governance/cowork-mirror-freshness.mjs --check-orfaos      # exit 1 = adicionou arquivo que o shell não declara
 *   node scripts/governance/cowork-mirror-freshness.mjs --check-orfaos --range <a>..<b>
 *   node scripts/governance/cowork-mirror-freshness.mjs --check-orfaos --added-from <lista.txt>   # fixture/manual
 *   node scripts/governance/cowork-mirror-freshness.mjs --absent-local
 *   node scripts/governance/cowork-mirror-freshness.mjs --css-refs [--enforce]
 *   node scripts/governance/cowork-mirror-freshness.mjs --preview-ds
 *   node scripts/governance/cowork-mirror-freshness.mjs --compare-bundle [--owner Felipe] [--check]
 */

import { readFileSync, writeFileSync, readdirSync, statSync, existsSync, mkdirSync, mkdtempSync, renameSync, rmSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { join, dirname, resolve, relative } from 'node:path';
import { anchorRelPath } from './anchor-content-check.mjs'; // fonte única: como extrair o path do related_prototype
import { pathsForOwner } from '../design-sync/bundle-transaction.mjs'; // fonte única: onde cada CONTA pousa

const ROOT = process.cwd();

/** Normalização canônica de conteúdo (a MESMA que o git aplica no index via eol=lf):
 *  strip BOM · CRLF/CR→LF · trailing-newline única. Identidade nunca depende de "sorte de
 *  checkout". String vazia permanece vazia. */
export function normalize(s) {
  if (s === '') return '';
  return s.replace(/^\uFEFF/, '').replace(/\r\n?/g, '\n').replace(/\n*$/, '\n');
}

/** Hash canônico de identidade: sha256(normalize(utf8)). Aceita Buffer ou string. */
export function contentHash(bufOrStr) {
  const s = Buffer.isBuffer(bufOrStr) ? bufOrStr.toString('utf8') : bufOrStr;
  return createHash('sha256').update(normalize(s), 'utf8').digest('hex');
}

/** Classificação 3-vias (pura, testável) a partir dos dois hashes canônicos. */
export function classifyMirror({ repoHash, liveHash }) {
  if (liveHash == null || liveHash === '') return 'LIVE-ABSENT';
  return repoHash === liveHash ? 'SYNC' : 'STALE';
}

/** --check só morde em STALE — o único sinal hash-provado de divergência. */
export function shouldFail(verdicts) {
  return verdicts.some((v) => v === 'STALE');
}

/* ═══════════════════════════════════════════════════════════════════════════════════════
 * DEVOLUTIVA DA RECUSA — o que o lado design NÃO tem como saber sozinho
 *
 * POR QUE EXISTE (medido 2026-09-07, no import do ciclo de 07/09): o `exportPlan` acima
 * recusa canon de tela e IMPRIME a lista. Imprimir resolve para quem está olhando o
 * terminal naquele segundo; não resolve para o DESIGN, que é quem produziu o arquivo e
 * quem vai produzi-lo de novo no ciclo seguinte. Naquele import foram 54 recusados, e a
 * sessão (eu) fechou o ciclo sem devolver nada — exatamente o buraco D3/D4 que [W]
 * mandou consertar no mesmo dia.
 *
 * O QUE SÓ ESTE LADO SABE, e é o conteúdo da devolutiva: como o rascunho recusado se
 * compara com o canon VIVO. Medição daquele import, e é o motivo de isto não ser
 * burocracia — 20 charters/casos do Ponto vieram para sobrescrever os do repo, TODOS
 * divergentes e de 3 a 7× MENORES (ex.: `Espelho/Show.casos.md` 2.539 B contra 17.528 B),
 * com `last_run: "—"` contra um canon que registra medição datada. Se a porta tivesse
 * aceitado, teria trocado contrato maduro por esqueleto em silêncio.
 *
 * NÃO É GATE, e não vira: reprovar o design por mandar rascunho puniria o ato de propor.
 * A saída é INFORMAR — e informar com o número do outro lado, que é o que muda a
 * decisão de quem desenha.
 *
 * DERIVADO, não escrito à mão (ADR 0256): o arquivo é REGENERADO a cada export, nunca
 * append. Um registro append de "o que foi recusado" viraria pilha de retratos velhos
 * competindo entre si; o que serve é o retrato de AGORA, com a data da medição.
 *
 * ── HISTÓRICO DE TENTATIVAS (append-only — [W] 2026-09-07: "manter o histórico de
 *    evolução dessas tentativas de todas as máquinas é muito importante") ──────────────
 *
 * 1ª versão (2026-09-07, mesma sessão, REVERTIDA antes do commit): resolvia o alvo pelo
 *   `component:` do frontmatter e comparava BYTES contra ele. Como `component:` aponta o
 *   `.tsx`, a saída comparou um charter de 4.097 B com um componente de 69.748 B e emitiu
 *   veredito "canon mais rico" a partir de grandezas incomensuráveis. Pego no PRIMEIRO
 *   controle positivo, não em revisão — o número saiu plausível, que é como esta classe
 *   atravessa. Conserto: o irmão do `.charter.md` é o `.charter.md` ao lado do componente,
 *   nunca o componente. Pinado pelo assert "IRMÃO, não componente" no teste irmão.
 *
 * 2ª versão (a atual): ao consertar o alvo, apareceu um estado que a 1ª não sabia
 *   representar — tela existe e canon NÃO. Virou `LACUNA-TELA-SEM-CANON`, e é o único
 *   veredito que manda o rascunho ADIANTE em vez de recusá-lo: ali ele não colide com
 *   nada, preenche buraco. Não disparou no corpus de 07/09 (cobertura de charter é
 *   218/218), então é ramo provado por FIXTURE, não por dado real — declarado aqui para
 *   ninguém tratá-lo como medido em produção.
 *
 * 3ª versão (2026-09-08, defeito descoberto no PR #6990): o `exportPlan`/devolutiva não mudou — mudou QUANDO o
 *   arquivo é APAGADO. A 2ª versão removia a devolutiva em toda rodada sem recusa, e um
 *   pouso de UM `.md` avulso apagou o retrato de 54 recusados de 07/09. O erro não estava
 *   no `rmSync`: estava em tratar o universo da RODADA como universo GLOBAL. Agora a
 *   rodada que não pode provar que o retrato acabou o PRESERVA e diz de quando ele é; o
 *   descarte é `--limpar-devolutiva`, declaração do operador. O teste de cobertura que
 *   pareceria a saída certa foi medido e é insatisfazível (ver o bloco no call site).
 *
 * O QUE NÃO SE TENTOU, e por quê: transformar a recusa em GATE. Reprovar o design por
 *   mandar rascunho puniria o ato de propor, e o §5 tem 4 lápides de guard sintático que
 *   reprovava trabalho legítimo. A recusa já existia e funcionava; o que faltava era o
 *   retorno.
 * ═══════════════════════════════════════════════════════════════════════════════════════ */

/** Caminho canônico de um artefato de RUNTIME do Design System dentro do snapshot.
 *  O get_file pode devolver `_ds/<slug>/...`; o shell já escolhe o slug no destino,
 *  portanto persistir esse prefixo de novo criaria `_ds/<slug>/_ds/<slug>/...`.
 *  Template/componente-fonte não entra neste destino: usa `--ds`, não `--ds-runtime`. */
const DS_RUNTIME_RE = /^(?:_ds_bundle\.js|colors_and_type\.css|cockpit_domains\.css|styles\.css|assets\/)/;

export function dsRuntimeRelPath(path) {
  const p = String(path || '').replace(/\\/g, '/').replace(/^\.\//, '');
  const inseguro = (rel) => !rel || rel.startsWith('/') || rel.split('/').includes('..');
  // DUAS FORMAS de bind, e as duas são legítimas (medido 2026-09-17, PR-A9):
  //   Wagner  `_ds/<slug>/colors_and_type.css`   — o bind carimba o slug do projeto DS
  //   Felipe  `_ds/colors_and_type.css`          — bind sem slug; `Felipe/venda-v3/index.html`
  //                                                 carrega assim nas linhas 38, 39 e 64
  // Antes só a 1ª resolvia: o regex exige DUAS barras, então o caminho sem slug não casava,
  // caía na whitelist com o prefixo `_ds/` intacto e dava `throw`. `slug: null` é forma
  // legítima do outro dono, não erro.
  //
  // ORDEM: tenta COM slug primeiro (preserva o comportamento de quem já funcionava) e cai pra
  // SEM slug — mas cada tentativa só vale se o resultado BATER a whitelist. Isso não é detalhe:
  //   `_ds/assets/fonts/a.woff2`        com-slug daria `fonts/a.woff2` (não bate) ⇒ cai pro
  //                                     sem-slug e resolve `assets/fonts/a.woff2`. Correto.
  //   `_ds/assets/colors_and_type.css`  AMBÍGUO de verdade: as duas leituras batem
  //                                     (`colors_and_type.css` × `assets/colors_and_type.css`).
  //                                     Com-slug vence, por regra declarada — nunca por acaso.
  // Os dois casos estão no teste do dono; sem eles a ordem viraria detalhe de implementação.
  const comSlug = p.replace(/^_ds\/[^/]+\//, '');
  if (comSlug !== p && !inseguro(comSlug) && DS_RUNTIME_RE.test(comSlug)) return comSlug;

  const semSlug = p.replace(/^_ds\//, '');
  if (semSlug !== p && !inseguro(semSlug) && DS_RUNTIME_RE.test(semSlug)) return semSlug;

  const candidato = comSlug !== p ? comSlug : semSlug;
  if (inseguro(candidato)) throw new Error(`ds-runtime: caminho inseguro "${path}"`);
  throw new Error(`ds-runtime: "${path}" não é bundle/CSS/asset de runtime; use --ds para fonte/template`);
}

// ── ROTEAMENTO DO BUNDLE — fonte ÚNICA da regra "onde cada path do bundle pousa" ──────
// Vivia SÓ dentro do `aplicar-payload.mjs`. Passou pra cá porque o `--compare-bundle`
// (abaixo) precisa da MESMA regra pra saber ONDE conferir cada arquivo, e duas cópias da
// mesma regra é o vetor do §5 2026-08-02 (corrigir uma implementação e a outra seguir
// errada). O applier importa daqui — a direção do import já existia (`dsRuntimeRelPath`).
// Retorna sempre com '/' — quem precisa de separador de plataforma usa join() no consumidor.
// ── 2026-09-13 · o `.md` deixou de ter destino PRÓPRIO (decisão [W]) ────────────────────
// Havia aqui uma perna que mandava todo `.md` pra `…/Wagner/handoffs/<p>` enquanto o irmão
// `.jsx` da MESMA pasta ia pra `…/Wagner/<p>`. Efeito: uma pasta do Cowork chegava partida em
// duas, por extensão — `cowork-inbox/ancora/playbook/00-INDICE.md` num lugar e o payload
// `.mjs` ao lado dele noutro. Isso é a desorganização que [W] nomeou ("deve ser igual ao
// cowork … facilita muito mais na importação ou leitura lá").
// A perna também era CÓDIGO MORTO: o `aplicar-payload` recusava `.md` antes de chegar aqui, e
// a R3 do `cowork-ssot-guard` reprovava `.md` aninhado em `handoffs/` — ou seja, ela roteava
// pra um destino que o guard proibia. Tirar a perna unifica a árvore e mata a contradição.
export function destinoDoBundle(rel, owner = 'Wagner') {
  const p = String(rel || '').split(String.fromCharCode(92)).join('/').replace(/^\.\//, '');
  if (p.startsWith('_ds/')) return { destinoBase: 'prototipo-ui/design-system', destinoPath: dsRuntimeRelPath(p) };
  return { destinoBase: pathsForOwner(owner).cowork, destinoPath: p };
}

/** sha256 do buffer CRU — a mesma conta que o `gerar-payload-partes` faz do lado do vivo.
 *  NÃO é o contentHash: aquele normaliza (BOM/CRLF/newline final) e serve à comparação com
 *  o `get_file`. Misturar os dois daria STALE em 100% dos arquivos com CRLF, e o número
 *  sairia plausível — o pior tipo de erro. Bruto compara com bruto, e só. */
export function rawHash(buf) {
  return createHash('sha256').update(buf).digest('hex');
}

/** Rows do `--compare-bundle` (PURA — recebe o leitor de arquivo, não toca fs direto).
 *
 *  ── POR QUE ISTO NÃO É TAUTOLOGIA (a pergunta que mata instrumentos, §5 2026-07-17) ──
 *  O `liveHash` NÃO sai do espelho: sai do `bundle.manifest.json`, que o
 *  `gerar-payload-partes` calculou do LADO DO VIVO, arquivo por arquivo, antes do transporte.
 *  O `repoHash` sai do disco AGORA. São duas origens independentes, e é por isso que o
 *  veredito significa algo: ele pega o espelho editado À MÃO depois da aplicação — que é
 *  exatamente o remendo de 2026-08-13 que passou 4 dias sem ninguém ver.
 *
 *  ── O QUE ELE **NÃO** PROVA, e o instrumento diz isso em voz alta ────────────────────
 *  Prova igualdade com o vivo **na data de emissão do bundle**, nunca "agora". Bundle de
 *  ontem + tela mexida no Cowork hoje = SYNC honesto aqui e desatualizado no mundo. Por isso
 *  a entrada de ledger é datada com o `generatedAt` do BUNDLE, não com `Date.now()`:
 *  carimbar a hora da leitura faria o SLA acreditar num frescor que ninguém mediu.
 */
export function rowsDoBundle(bundle, manifest, lerArquivo, owner = 'Wagner') {
  const rows = [];
  const cobertos = new Set();
  // O espelho tem DOIS donos (Wagner/Felipe) e `pathsForOwner` é o único que sabe onde cada
  // conta pousa. Até 2026-09-17 esta base era o literal `.../Wagner`: lote do Felipe pousava
  // em `.../Felipe/`, TODO arquivo era lido do path errado e o veredito saía STALE pra tudo
  // (medido: sync 0 · stale 2 num lote de 2). Falso-vermelho é pior que alarme ausente.
  const coworkBase = pathsForOwner(owner).cowork;
  // PR-A9 — com `transforms`, o que POUSA não é o que VIAJOU: a ref do DS é convertida na
  // aterrissagem. O `sha256` do manifesto descreve o PAYLOAD (pré-transform); o que está no disco
  // é o pós. Comparar contra o `sha256` marcaria o host STALE em 100% dos ciclos — e aí este
  // alarme, que é o ÚNICO que pega remendo à mão no espelho, morreria de ruído.
  // Por isso o gerador declara `shaDepois` por arquivo tocado: é contra ele que se compara.
  const shaPosTransform = new Map((bundle?.transforms || []).map((t) => [t.path, t.shaDepois]));
  for (const f of (bundle?.files || [])) {
    const { destinoBase, destinoPath } = destinoDoBundle(f.path, owner);
    // O universo do freshness é o espelho DO DONO (`prototipo-ui/cowork/<owner>/`). `_ds/**` pousa
    // em outro destino e NÃO entra no denominador — contá-los inflaria a cobertura com
    // arquivos que o --compare nunca mediu (§5 2026-07-27: denominador é o executável).
    if (destinoBase !== coworkBase) continue;
    cobertos.add(destinoPath);
    const buf = lerArquivo(destinoBase + '/' + destinoPath);
    rows.push({
      cowork: destinoPath,
      repoHash: buf == null ? null : rawHash(buf),
      // FAIL-CLOSED: arquivo do bundle que sumiu do espelho não é SYNC nem silêncio.
      veredito: buf == null ? 'STALE' : classifyMirror({ repoHash: rawHash(buf), liveHash: shaPosTransform.get(f.path) || f.sha256 }),
    });
  }
  // O que o espelho tem e o bundle NÃO cobriu segue UNCHECKED — nunca vira SYNC por omissão
  // (LC-13: "0 failed" numa suíte que não rodou). É o mesmo contrato do --compare.
  for (const m of manifest) {
    if (!cobertos.has(m.cowork)) rows.push({ cowork: m.cowork, repoHash: m.repoHash, veredito: 'UNCHECKED' });
  }
  return rows;
}

// ── LEDGER + SLA (a metade que o CI headless PODE checar com honestidade) ─────
// O CI não lê o Cowork vivo (auth interativa). Então o CI NÃO mede frescor — mede se a
// ROTINA de dispatch rodou dentro do SLA e qual foi o último resultado. Ledger datado,
// append-only, commitado: prova > promessa (session 2026-07-06-arte-design-code-sync-frescor).
export const LEDGER_REL = 'scripts/governance/.cowork-freshness-ledger.json';

/** Bundle promovido (`state/active-bundle.json`) — leitor ÚNICO. Ausente/ilegível → null,
 *  nunca objeto vazio: "não consegui ler" não pode virar "não tem nada" (§5 2026-07-29). */
export function lerBundlePromovido(root = ROOT, rel = 'scripts/design-sync/state/active-bundle.json') {
  const p = join(root, rel);
  if (!existsSync(p)) return null;
  try { return JSON.parse(readFileSync(p, 'utf8')); } catch { return null; }
}

/** Entrada de ledger (pura, testável) a partir das rows do --compare. */
export function ledgerEntry(rows, dateIso, meta = {}) {
  const n = (v) => rows.filter((r) => r.veredito === v).length;
  const e = {
    date: dateIso,
    files: rows.length,
    sync: n('SYNC'),
    stale: n('STALE'),
    liveAbsent: n('LIVE-ABSENT'),
    unchecked: n('UNCHECKED'),
    staleList: rows.filter((r) => r.veredito === 'STALE').map((r) => r.cowork),
    // QUAIS arquivos esta rodada de fato PROVOU idênticos ao vivo. Sem esta lista o ledger
    // sabe "21 sync" mas não sabe 21 de QUEM — e aí não há como responder a pergunta que o
    // remendo à mão de 2026-08-13 escapou por 4 dias: "este arquivo mudou DEPOIS da última
    // vez que alguém provou que ele bate com o vivo?". É o insumo do `--unverified`.
    // path + HASH. Só o path não basta: merge/squash/rebase reescrevem a data de commit de
    // todo arquivo tocado sem mudar um byte, e um detector que compara só DATAS acusa isso como
    // "mexido à mão". Medido 2026-08-17: o squash do #5854 (12:10:47) gerou 6 falsos-positivos
    // sobre arquivos verificados 10min antes (12:00:45). Com o hash, "commitou depois" só vira
    // achado quando o CONTEÚDO também mudou.
    verified: rows.filter((r) => r.veredito === 'SYNC').map((r) => r.cowork),
    verifiedHash: Object.fromEntries(rows.filter((r) => r.veredito === 'SYNC').map((r) => [r.cowork, r.repoHash])),
  };
  // `stale` conta o que DIVERGE AGORA. Numa rodada cujo snapshot veio de `--export-from`,
  // isso é sempre 0 por construção — o export consertou antes de medir. `stalePreExport`
  // é o número que responde "o espelho drifta com que frequência?", que é o que a ADR 0324
  // precisa do ledger. Sem ele a série histórica só sabe dizer 0 e vira carimbo.
  if (meta.origin) e.origin = meta.origin;
  // ADR 0389: escrita inline DECLARADA (--origem agente). Viaja pro ledger pra que a rodada
  // não se passe por export de saída persistida — a mentira, se houver, fica datada aqui.
  if (meta.origemDeclarada) e.origemDeclarada = meta.origemDeclarada;
  // Qual projeto do Claude Design foi lido de volta (`pendentes-cowork --conferir --projeto`).
  // Desde 2026-09-29 o git é a fonte e há 2 projetos sincronizados a partir dele ([W]: "a
  // maiara deveria poder fazer isso"): a prova pode vir do `w` OU do `copia`, e o ledger
  // guarda de qual — sem campo = rodada anterior a isto, sempre do `w`.
  if (meta.projetoCowork) e.projetoCowork = meta.projetoCowork;
  if (typeof meta.stalePreExport === 'number') e.stalePreExport = meta.stalePreExport;
  return e;
}

/** Extrai as DEPS DE RENDER dos src/href de um shell HTML (pura, testável — LC-07).
 *  Regras: strip query/hash (`app.jsx?v=eb2` → `app.jsx`, o cache-bust do exportador) ·
 *  exclui remotos (http/https — CDN React/Babel não é espelho) · só extensões build
 *  (jsx/css/js) · dedup preservando 1ª ocorrência · paths relativos normalizados. */
export function parseShellDeps(html) {
  const out = [];
  const seen = new Set();
  for (const m of String(html).matchAll(/(?:src|href)="([^"]+)"/g)) {
    let p = m[1].split(/[?#]/)[0].trim();
    if (!p || /^(https?:)?\/\//i.test(p) || p.startsWith('data:')) continue;
    // REF MONTADA EM RUNTIME não é path (medido 2026-09-17, pacote 25). O host passou a fazer
    // `document.write('<link href="'+window.__OI_DS_BASE__+'colors_and_type.css"/>')`, e o regex
    // acima captura o MIOLO da expressão JS — `'+window.__OI_DS_BASE__+'colors_and_type.css` —
    // como se fosse arquivo. O `ABSENT-LOCAL` então acusava 3 deps FALTANDO que não existem como
    // path nenhum: gate vermelho permanente por FP.
    // Aspas, crase, `+` e `${` não ocorrem em path do espelho. FP medido no shell real: 274 deps
    // → 3 descartadas (as 3 expressões) e 271 mantidas, das quais 271 EXISTEM no espelho — zero
    // falso-negativo. Quem decide a base em runtime é o browser, e nenhuma sonda estática sabe.
    if (/['"`+]/.test(p) || p.includes('${')) continue;
    if (!/\.(jsx|css|js)$/i.test(p)) continue;
    p = p.replace(/^\.\//, '').split('\\').join('/');
    if (!seen.has(p)) { seen.add(p); out.push(p); }
  }
  return out;
}

// ── ABSENT-LOCAL: a 3ª doença — o espelho INCOERENTE (2026-08-13) ────────────────
// STALE mede "o hash DIVERGE" (direção não medida — ver 2026-08-17); LIVE-ABSENT mede "sumiu do vivo"; liveOnly mede
// "existe no vivo e nunca desceu". Faltava a que quebra o RENDER: dep que o SHELL
// carrega e que não existe no espelho — o app monta e a tela vem vazia/quebrada, sem
// nenhum veredito vermelho. Foi assim que o `app.jsx` de 07-07 (montando o JanaCockpit
// antigo) conviveu por dias com um `jana-merge.jsx` já versionado: chegou o arquivo,
// não chegou a fiação.
//
// Por que NÃO era detectável: `buildManifest.add()` faz `existsSync → return`, então dep
// ausente é DESCARTADA em silêncio. O comentário de lá dizia "ausência upstream é outro
// sinal" — mas esse outro sinal não existia em lugar nenhum.
//
// FP MEDIDO ANTES de escrever (§5 — 5 lápides de guard sintático mataram o contrário):
//   corpus real 2026-08-13, shell vivo: 16 deps ausentes brutas → 3 são `_ds/**`, que o
//   .gitignore do espelho exclui POR DESIGN (bundle do design-system, não é fonte).
//   Excluindo o que o git ignora: 13 sinal real, 0 falso-positivo.
// Por isso o filtro é `git check-ignore` — a REGRA JÁ ESCRITA do repo, não uma denylist
// de nome inventada aqui (que é a família banida: allowlist-de-pasta · guard `@scope`).
/** Deps que o shell CARREGA e que não existem no espelho (puro exceto o check-ignore).
 *  Devolve { faltando, ignorados } — `ignorados` é reportado, nunca escondido. */
export function absentLocal(shellHtml, root = ROOT) {
  if (!shellHtml) return { faltando: [], ignorados: [] };
  const rel = (d) => `prototipo-ui/cowork/Wagner/${d}`;
  const ausentes = parseShellDeps(shellHtml).filter((d) => !existsSync(join(root, rel(d))));
  const ignorados = ausentes.filter((d) => {
    try {
      // Em worktrees controladas por outro usuário (Codex sandbox no Windows), git recusava
      // antes de consultar o .gitignore. O cache `_ds` existente mascarava isso e deixava o
      // teste verde; ao limpar o cache, o falso-verde apareceu. A exceção é escopada ao root
      // que já recebemos, não altera config global e mantém o .gitignore como dono da regra.
      const safeRoot = resolve(root).replaceAll('\\', '/');
      execFileSync('git', ['-c', `safe.directory=${safeRoot}`, 'check-ignore', '-q', rel(d)], {
        cwd: root,
        stdio: 'ignore',
      });
      return true;
    }
    catch { return false; } // exit≠0 = NÃO ignorado (git check-ignore -q usa o código, não a saída)
  });
  return { faltando: ausentes.filter((d) => !ignorados.includes(d)), ignorados };
}

/** Enumera os arquivos-âncora do espelho, keyed por PATH RELATIVO COMPLETO (nunca basename)
 *  + as DEPS DE RENDER do shell (LC-07). `kind`: 'ancora' (tem tela de charter) | 'dep'.
 *  Mesmo conjunto de âncoras que o anchor-content-check enxerga (reusa anchorRelPath). */
export function buildManifest(root = ROOT, { all = false, shellHtml = null, universo = 'frescor', owner = 'Wagner', extras = [] } = {}) {
  const PAGES = join(root, 'resources', 'js', 'Pages');
  // A raiz do espelho é a do DONO: manifesto do Felipe lido da árvore do Wagner devolveria
  // UNCHECKED pra arquivos de OUTRA conta — denominador de outro universo (§5 2026-07-27).
  const COWORK = join(root, ...pathsForOwner(owner).cowork.split('/'));
  const seen = new Map(); // relPath → { cowork, repoPath, repoHash, telas }

  const add = (relPath, telas) => {
    const abs = join(COWORK, relPath);
    if (!existsSync(abs)) return; // âncora sumida é território do anchor-content (MISSING)
    // `_ds/` é BUILD DE PREVIEW (o --preview-ds cria, o .gitignore exclui). Deixá-lo entrar
    // fazia o denominador do --sla variar por MÁQUINA: 124 aqui, 122 num clone limpo — e o
    // "mediu 2/124" publicado não era propriedade do repo (§5 2026-07-27, denominador que
    // nenhuma decisão estabeleceu). Achado do adversário 2026-08-13, reproduzido: escondi os
    // 2 arquivos e o manifesto caiu 124→122. O universo é o que o GIT versiona.
    if (relPath.startsWith('_ds/')) return;
    if (!seen.has(relPath)) {
      seen.set(relPath, {
        cowork: relPath,
        repoPath: `prototipo-ui/cowork/Wagner/${relPath}`,
        repoHash: contentHash(readFileSync(abs)),
        telas: [],
      });
    }
    for (const t of telas) if (!seen.get(relPath).telas.includes(t)) seen.get(relPath).telas.push(t);
  };

  if (all) {
    walkRel(COWORK, COWORK, [], universo).forEach((rel) => add(rel, []));
  }
  for (const charter of walkCharters(PAGES)) {
    const t = readFileSync(charter, 'utf8');
    const m = t.match(/^related_prototype:\s*(.+)$/m);
    if (!m) continue;
    let rel = anchorRelPath(m[1].trim());
    if (!rel) continue; // prosa não-resolvível — mesmo escopo que o anchor-content pula
    if (rel.startsWith('Felipe/')) continue; // outro dono/conta, fora do projeto Cowork Wagner
    if (rel.startsWith('Wagner/')) rel = rel.slice('Wagner/'.length);
    const tela = charter.slice(PAGES.length + 1).split('\\').join('/').replace(/\.charter\.md$/, '');
    add(rel.split('\\').join('/'), [tela]);
  }
  // DEPS DE RENDER (LC-07): derivadas do shell, nunca curadas na mão. Dep que não existe
  // no espelho fica FORA daqui — mas NÃO some mais em silêncio: `absentLocal()` a reporta
  // (era o buraco que deixou a fiação nova conviver com componente ausente, 2026-08-13).
  if (shellHtml) for (const dep of parseShellDeps(shellHtml)) add(dep, []);
  // O SHELL entra no próprio manifesto quando versionado: ele é a fiação, e fiação que não
  // é medida é a doença nº1. Assim, [W] mexer no shell do Cowork vira STALE na próxima rodada.
  add('oimpresso.com.html', []);
  // EXTRAS (2026-09-30): paths que o CHAMADOR leu de volta e quer provar, fora do universo de
  // frescor. Existe por causa do `pendentes-cowork --conferir` — ver `--incluir-lidos` no CLI.
  // Passam pelo mesmo `add`: arquivo ausente do espelho e `_ds/` continuam fora.
  for (const rel of extras) add(rel, []);
  return [...seen.values()]
    .map((e) => ({ ...e, kind: e.telas.length ? 'ancora' : 'dep' }))
    .sort((a, b) => a.cowork.localeCompare(b.cowork));
}

/** Shell default. Ordem: (1) o VERSIONADO no espelho; (2) o staging fixo de handoff.
 *  Null se não houver — o CLI avisa em vez de omitir em silêncio.
 *
 *  Por que o repo vem PRIMEIRO (2026-08-13): o universo de deps de render era derivado de
 *  um arquivo em `~/Downloads` — fora do git, na máquina de UMA pessoa, e congelado na data
 *  do último ZIP extraído. Medido: o staging estava em 01/jul e conhecia 103 deps; o shell
 *  vivo já tinha 120. As 17 deps novas (incl. `jana-merge.*` e as 6 telas de cliente)
 *  eram invisíveis POR CONSTRUÇÃO — o manifesto não podia acusar o que não sabia existir.
 *  Versionar o shell põe a fiação sob a mesma catraca dos arquivos que ela carrega: o shell
 *  entra no manifesto, e mudança dele no vivo vira STALE como qualquer outro arquivo. */
/** CONTEÚDO do shell default (null se não houver). Existe porque `buildManifest`/`absentLocal`
 *  querem o HTML e `defaultShellPath` devolve o CAMINHO — passar um pelo outro não dá erro:
 *  o regex de `parseShellDeps` simplesmente não casa nada e o manifesto sai sem deps, calado.
 *  Mordeu em 2026-08-13 (bite-test do nasce-sem-medição acusou um arquivo que ESTAVA no shell)
 *  e estava latente no `--manifest`, onde o `all:true` mascarava o efeito. */
export function lerShellHtml(root = ROOT, owner = 'Wagner') {
  const p = defaultShellPath(root, owner);
  return p && existsSync(p) ? readFileSync(p, 'utf8') : null;
}

export function defaultShellPath(root = ROOT, owner = 'Wagner') {
  const noRepo = join(root, ...pathsForOwner(owner).cowork.split('/'), 'oimpresso.com.html');
  if (existsSync(noRepo)) return noRepo;
  // Sem fallback pro `~/Downloads/_cowork-handoff-staging` ([W] 2026-08-13: "não existe mais
  // zip, é direto o protocolo"). O staging era o caminho do BUNDLE, e ele morreu duas vezes:
  // a catraca `ancora-guard` já lista `_cowork-handoff-staging` e `Downloads/` como LUGAR
  // PROIBIDO ([W] 2026-07-01 "não pode trocar de lugar nunca. deve ser isso que fica errando"),
  // e a rota canônica hoje é `get_file` → `--export-from` (ADR 0374, ratificada 2026-08-13).
  // Ler o shell de lá reintroduzia exatamente a doença que motivou versioná-lo: o staging
  // estava congelado em 01/jul e conhecia 103 deps quando o vivo já tinha 120.
  // Sem shell no espelho, o certo é dizer que não há — não achar um velho fora do git.
  return null;
}

function walkCharters(dir, acc = []) {
  if (!existsSync(dir)) return acc;
  for (const e of readdirSync(dir)) {
    const f = join(dir, e);
    if (statSync(f).isDirectory()) walkCharters(f, acc);
    else if (f.endsWith('.charter.md')) acc.push(f);
  }
  return acc;
}

/** Anda o espelho devolvendo PATHS RELATIVOS (v1 devolvia basenames — arquivos homônimos em
 *  subdirs sumiam do --all; v2 preserva a identidade).
 *
 *  ⚠️ O ESPELHO TEM DOIS UNIVERSOS, e confundi-los foi um defeito medido (2026-09-16):
 *
 *    `universo: 'frescor'` (default) — O QUE PODE SER COMPARADO com o vivo. Filtra por
 *      extensão de build porque o `--compare` casa hash arquivo-a-arquivo contra o que o
 *      `get_file`/bundle traz; `.php`/`.json` de pedido não têm contraparte a comparar.
 *      (`css|js` entraram em 2026-07-07, LC-07: o `--all` era cego pra folha de estilo, o
 *      vetor exato do drift de design.)
 *
 *    `universo: 'conteudo'` — O QUE O ESPELHO CONTÉM, sem filtro. É o universo do `liveOnly`,
 *      cuja pergunta é de CONTINÊNCIA (*"isto já desceu?"*), não de frescor.
 *
 *  Por que a distinção é defeito e não preciosismo, contado no espelho de 675 arquivos: com o
 *  filtro de frescor o manifesto tem **290** entradas e **0** de `cowork-inbox/` — uma
 *  subárvore de **378** arquivos (87% `.md`). Para ela o `liveOnly` não distinguia PRESENTE de
 *  AUSENTE: classificava **327** paths como *"existe no vivo e NUNCA desceu"* quando estavam
 *  versionados ali, a um `existsSync` de distância. Com `'conteudo'` isso vai a **0**.
 *
 *  ⚠️ Alinhar o filtro de frescor ao `roleForPath` do `bundle-contract` foi TENTADO primeiro e
 *  MEDIDO: leva o manifesto a 633 (336 de `cowork-inbox/`) e derruba os falso-ausentes de 327
 *  para **38** — mas os 38 seguem no disco, logo continuam falsos, só em menor número. O
 *  predicado do contrato responde *"isto é material de bundle?"*, que é a pergunta do frescor;
 *  continência é `existsSync`. Trocar de filtro não conserta conflação de pergunta.
 */
function walkRel(base, dir = base, acc = [], universo = 'frescor') {
  if (!existsSync(dir)) return acc;
  for (const e of readdirSync(dir)) {
    const f = join(dir, e);
    if (statSync(f).isDirectory()) walkRel(base, f, acc, universo);
    else if (universo === 'conteudo' || /\.(jsx|html|css|js)$/i.test(e)) {
      acc.push(f.slice(base.length + 1).split('\\').join('/'));
    }
  }
  return acc;
}

// ── PREVIEW-DS: o espelho é versionado, mas NÃO RENDERIZA sozinho (2026-08-13) ────
// O shell do Cowork faz <link> de `_ds/<id>/colors_and_type.css` + `cockpit_domains.css`
// e <script> do `_ds_bundle.js`. O `.gitignore` do espelho exclui `_ds/` — correto, porque
// o Design System tem dono próprio em git (ADR 0239) e duplicá-lo criaria 2º armazém.
// CONSEQUÊNCIA MEDIDA: quem clona o repo e abre o protótipo vê `--pos`/`--neg`/`--warn`
// VAZIOS — a tela renderiza sem as cores de status. Isso contradiz o motivo da ADR 0374
// ("vai ter computadores que não vão ter acesso ao design dessa máquina e vão trabalhar
// só com o git"): o time recebe o protótipo, mas não o vê como ele é.
//
// O conteúdo JÁ ESTÁ versionado em `prototipo-ui/design-system/` (dono:
// ds-mirror-build.mjs + gate ds-mirror-drift) — só não no path que o shell procura.
// Este modo REPÕE, derivando o id do DS do PRÓPRIO SHELL (nunca hardcode: o shell é
// versionado, então o id acompanha quando [W] trocar de design system).
// O `_ds/` continua gitignored: isto é build de preview, não versionamento.
//
// Não é detector — é ação determinística (copiar), então não tem FP a medir.
// Resolve uma referência relativa (url()/@import/href) contra uma base, devolvendo o path
// normalizado dentro do espelho ou `null` quando a referência é insegura: traversal que
// escapa da raiz, caminho absoluto, drive-letter do Windows, NUL ou percent-encoding quebrado.
//
// EXTRAÍDA de `previewDsPlan` em 2026-09-17 sem alterar uma linha do corpo — era um closure
// ali dentro, e o `--css-refs` precisa da MESMA regra. Copiá-la seria a LC-19 (duas cópias
// da mesma regra divergindo no primeiro conserto, §5 2026-08-02). Os asserts de traversal que
// já existem no `.test.mjs` (`id inseguro`, `referência insegura no shell`) seguem exercitando
// este corpo pelo caminho do `previewDsPlan` — mudou o endereço, não o comportamento.
export function resolveRefSegura(valor, base = '') {
  let decodificado;
  try { decodificado = decodeURIComponent(String(valor)); } catch { return null; }
  const limpo = decodificado.replaceAll('\\', '/').split(/[?#]/)[0].replace(/^\.\//, '');
  if (!limpo || limpo.includes('\0') || /^(?:[a-z]:|\/)/i.test(limpo)) return null;
  const partes = `${base ? `${base}/` : ''}${limpo}`.split('/');
  const normalizadas = [];
  for (const parte of partes) {
    if (!parte || parte === '.') continue;
    if (parte === '..') {
      if (!normalizadas.length) return null;
      normalizadas.pop();
    } else normalizadas.push(parte);
  }
  return normalizadas.join('/') || null;
}

export function previewDsPlan(shellHtml, root = ROOT) {
  if (!shellHtml) return { erro: 'sem shell — não dá pra derivar o id do design system', arquivos: [] };
  // o id sai dos próprios <link>/<script> do shell — NUNCA da prosa. Medido em 2026-09-25: desde
  // o handoff 36 (#7889) o shell explica em COMENTÁRIO HTML por que as refs a `_ds/` não são
  // literais, e o regex antigo lia essa prosa como id — "achei 3" com UM design system real,
  // derrubando o render de todos os 9 baselines. Tirar comentário não afrouxa a checagem de 1 DS:
  // comentário não carrega nada no browser, então não é ref.
  const shell = String(shellHtml).replace(/<!--[\s\S]*?-->/g, '');
  const ids = [...new Set([...shell.matchAll(/_ds\/([^/"'\s]+)\//g)].map((m) => m[1]))];
  if (ids.length !== 1) return { erro: `esperava 1 design system no shell, achei ${ids.length}`, arquivos: [] };
  const id = ids[0];
  if (!/^[a-z0-9][a-z0-9._-]*$/i.test(id) || id === '.' || id === '..') {
    return { erro: `id inseguro do design system no shell: "${id}"`, arquivos: [] };
  }
  const seguro = resolveRefSegura;
  // Duas formas de ref: a LITERAL (`href="_ds/<id>/x.css"`) e a RUNTIME do handoff 36
  // (`window.__OI_DS_BASE__+'x.css'`, base resolvida no browser). Sem a 2ª, o plano sai vazio e o
  // render não injeta o DS que o shell de fato carrega.
  const refsShell = [...new Set([
    ...[...shell.matchAll(/_ds\/[^/"'\s]+\/([^"'?]+)/g)].map((m) => m[1]),
    ...[...shell.matchAll(/__OI_DS_BASE__\s*\+\s*['"]([^'"?]+)/g)].map((m) => m[1]),
  ])];
  const querUsar = refsShell.map((ref) => seguro(ref));
  const inseguraShell = refsShell.find((_, index) => !querUsar[index]);
  if (inseguraShell) return { erro: `referência insegura no shell do preview: "${inseguraShell}"`, arquivos: [] };
  const origem = join(root, 'prototipo-ui', 'design-system');
  // ── 2ª CAMADA: o que os CSS repostos pedem POR DENTRO (2026-08-14) ───────────
  // Derivar o plano só do SHELL deixa de fora tudo que está a uma indireção: o
  // shell não menciona fonte alguma — quem as pede é o `colors_and_type.css`, com
  // `url('assets/fonts/…woff2')` (7 refs únicas). MEDIDO no preview antes deste
  // conserto: 7 `@font-face` com status `error` e a tipografia caindo pro fallback
  // do sistema — falha 404 SILENCIOSA, que é pior que ausência declarada, porque
  // o `--preview-ds` dizia "2 reposto(s)" e dava a impressão de plano completo.
  // Só refs RELATIVAS entram (data:/http(s):/protocol-relative/âncora ficam fora —
  // não são arquivo do espelho). Isto não inventa origem: o que não existir no
  // mirror-snapshot sai como "SEM FONTE", igual ao `_ds_bundle.js`.
  const deCss = new Set();
  const filaCss = querUsar.filter((f) => /\.css$/i.test(f));
  const cssLidos = new Set();
  while (filaCss.length) {
    const f = filaCss.shift();
    if (cssLidos.has(f)) continue;
    cssLidos.add(f);
    const src = join(origem, f);
    if (!existsSync(src)) continue;
    const css = String(readFileSync(src, 'utf8'));
    const refs = [
      ...[...css.matchAll(/url\(\s*['"]?([^)'"]+?)['"]?\s*\)/g)].map((m) => ({ ref: m[1], isImport: false })),
      ...[...css.matchAll(/@import\s+(?:url\(\s*)?['"]([^'"]+)['"]\s*\)?/g)].map((m) => ({ ref: m[1], isImport: true })),
    ];
    for (const item of refs) {
      const ref = String(item.ref).trim();
      if (!ref || /^(data:|https?:|\/\/|#)/i.test(ref)) continue;
      const normalizado = seguro(ref, dirname(f).replaceAll('\\', '/'));
      if (!normalizado) {
        return { erro: `referência CSS insegura em "${f}": "${ref}"`, arquivos: [] };
      }
      deCss.add(normalizado);
      if (item.isImport && /\.css$/i.test(normalizado)) filaCss.push(normalizado);
    }
  }
  const todos = [...new Set([...querUsar, ...deCss])];
  return {
    id,
    destino: `prototipo-ui/cowork/Wagner/_ds/${id}`,
    destinoAbs: join(root, 'prototipo-ui', 'cowork', 'Wagner', '_ds', id),
    arquivos: todos.map((f) => ({
      nome: f,
      de: join(origem, f),
      para: join(root, 'prototipo-ui', 'cowork', 'Wagner', '_ds', id, f),
      temNoRepo: existsSync(join(origem, f)),
    })),
  };
}

// ── REFS-CHECK — todo `url()`/`@import` do CSS do espelho aponta pra arquivo que existe? ──
//
// POR QUE EXISTE (incidente de 2026-09-17, medido): o `colors_and_type.css` do espelho declarava
// `@font-face` para os pesos 500/600/700 do IBM Plex Sans apontando pra `assets/fonts/
// ibm-plex-sans-{500,600,700}.woff2` — arquivos que o #7224 tinha removido do espelho. Resultado:
// três 404 silenciosos, a tipografia caindo pro fallback do sistema, e NENHUM gate vendo. O
// `ds-mirror-drift` não podia ver: ele compara VALOR DE TOKEN (`--color-*`) entre git e espelho,
// nos 4 escopos, e `src: url(...)` não é token — ele deu `0 vs baseline 0` o tempo todo, e estava
// certo sobre o que mede. Quem via era o `--preview-ds`, cujo `previewDsPlan` já fazia grafo
// recursivo de `@import` + fonte por `url()` (o comentário da 2ª camada, de 2026-08-14, nomeia
// exatamente estes "7 `@font-face` com status error"). Só que o `--preview-ds` foi aposentado
// quando o shell passou a ler `prototipo-ui/design-system/` direto, e a verificação foi junto:
// a função continua viva e testada, mas nenhum caminho de CLI a exercita mais sobre o espelho.
//
// Este eixo é essa verificação SEM a dependência que morreu — não parte do shell nem do `_ds/`,
// parte dos próprios `.css` do espelho. Reusa `resolveRefSegura` (a mesma regra de traversal do
// `previewDsPlan`) em vez de reimplementá-la.
//
// FP MEDIDO ANTES DE ARMAR (regra "LIGUE A MÁQUINA" #4 · §5 tem 8 lápides de guard sintático que
// reprovava o legítimo): no espelho de 2026-09-17, 8 arquivos `.css`, 7 refs relativas, **0
// mortas** — e as 7 são exatamente as `url('assets/fonts/…woff2')` que o incidente atingiu.
// Zero falso-positivo por construção: o predicado é `existsSync` sobre path resolvido, não
// heurística de nome. `data:`, `http(s):`, protocol-relative e âncora ficam fora — não são
// arquivo do espelho.
//
// NASCE ADVISORY (ADR 0275): sem `--enforce` sempre sai 0 e só reporta. Promover é flip de [W].
export function cssRefsMortas(root = ROOT) {
  const base = join(root, 'prototipo-ui', 'design-system');
  if (!existsSync(base)) return { medido: false, motivo: `espelho ausente: ${base}`, mortas: [], refs: 0, arquivos: 0 };
  const cssRel = [];
  const anda = (dir) => {
    for (const entrada of readdirSync(dir, { withFileTypes: true })) {
      const abs = join(dir, entrada.name);
      if (entrada.isDirectory()) anda(abs);
      else if (/\.css$/i.test(entrada.name)) cssRel.push(relative(base, abs).replaceAll('\\', '/'));
    }
  };
  anda(base);
  const mortas = [];
  let refs = 0;
  for (const arquivo of cssRel.sort()) {
    const css = String(readFileSync(join(base, arquivo), 'utf8'));
    const achadas = [
      ...[...css.matchAll(/url\(\s*['"]?([^)'"]+?)['"]?\s*\)/g)].map((m) => m[1]),
      ...[...css.matchAll(/@import\s+(?:url\(\s*)?['"]([^'"]+)['"]\s*\)?/g)].map((m) => m[1]),
    ];
    for (const bruta of achadas) {
      const ref = String(bruta).trim();
      // Fora do espelho por natureza — não são arquivo daqui, não há o que conferir.
      if (!ref || /^(data:|https?:|\/\/|#)/i.test(ref)) continue;
      refs++;
      const alvo = resolveRefSegura(ref, dirname(arquivo).replaceAll('\\', '/').replace(/^\.$/, ''));
      // `null` = referência insegura (traversal/absoluto). Ela também não resolve pra arquivo
      // do espelho, então conta como morta — com o motivo separado, pra não confundir diagnóstico.
      if (!alvo) { mortas.push({ arquivo, ref, motivo: 'insegura' }); continue; }
      if (!existsSync(join(base, alvo))) mortas.push({ arquivo, ref, motivo: 'ausente', alvo });
    }
  }
  return { medido: true, mortas, refs, arquivos: cssRel.length };
}

// Materializa como TROCA DE DIRETÓRIO, não como sequência de writes no cache vivo.
// Além de impedir estado parcial, a troca remove órfãos de uma versão anterior.
export function materializePreviewDs(plano) {
  if (plano.erro) throw new Error(plano.erro);
  const faltando = plano.arquivos.filter((a) => !a.temNoRepo).map((a) => a.nome);
  if (faltando.length) throw new Error(`preview incompleto; sem fonte: ${faltando.join(', ')}`);

  const bundle = plano.arquivos.find((a) => a.nome === '_ds_bundle.js');
  if (bundle) {
    try { execFileSync(process.execPath, ['--check', bundle.de], { stdio: 'ignore' }); }
    catch { throw new Error('_ds_bundle.js inválido'); }
  }

  const destino = plano.destinoAbs;
  const parent = dirname(destino);
  mkdirSync(parent, { recursive: true });
  const stage = mkdtempSync(join(parent, `.${plano.id}.staging-`));
  const backup = join(parent, `.${plano.id}.backup-${process.pid}-${Date.now()}`);
  let moveuAnterior = false;
  try {
    for (const a of plano.arquivos) {
      const para = join(stage, a.nome);
      mkdirSync(dirname(para), { recursive: true });
      writeFileSync(para, readFileSync(a.de));
    }
    if (existsSync(destino)) {
      renameSync(destino, backup);
      moveuAnterior = true;
    }
    renameSync(stage, destino);
    if (moveuAnterior) rmSync(backup, { recursive: true, force: true });
    return { escritos: plano.arquivos.length, destino };
  } catch (error) {
    // Se a publicação falhar depois de mover o cache bom, restaura-o.
    if (!existsSync(destino) && moveuAnterior && existsSync(backup)) renameSync(backup, destino);
    throw error;
  } finally {
    rmSync(stage, { recursive: true, force: true });
    // Só sobra backup se a publicação terminou e a limpeza intermediária falhou.
    if (existsSync(destino)) rmSync(backup, { recursive: true, force: true });
  }
}

/** Imprime o ABSENT-LOCAL. Não afeta o exit code: `shouldFail()` morde SÓ em STALE, que é o
 *  sinal hash-provado — isso é comportamento DESTE script, provado no self-test, não promessa.
 *  Se esta lane bloqueia merge ou não, quem sabe é `governance/required-checks-baseline.json`
 *  (dono único, vigiado por `protection-drift.mjs`): script não afirma o próprio enforcement
 *  em presente (LC-10). Desenho de nascimento em ADR 0275; promoção por mordida em ADR 0336.
 *
 *  `stream`: no --manifest o STDOUT é o JSON do manifesto — relatório humano ali dentro
 *  quebra `--manifest | jq` / `require()` (peguei isso na 1ª vez que consumi por pipe).
 *  Regra: modo que emite dado → relatório vai pro stderr; modo que emite relatório → stdout. */
function reportAbsentLocal(shellHtml, stream = process.stdout) {
  if (!shellHtml) return;
  const { faltando, ignorados } = absentLocal(shellHtml);
  if (!faltando.length && !ignorados.length) return;
  const w = (s) => stream.write(s + '\n');
  w(`  ── ABSENT-LOCAL — o shell CARREGA e o espelho NÃO TEM (render quebra sem veredito)\n`);
  for (const d of faltando) w(`  ⛔ FALTA        ${d}`);
  if (ignorados.length) {
    w(`\n  (${ignorados.length} fora por .gitignore do espelho — esperado, não é achado: ${ignorados.map((d) => d.split('/')[0]).filter((v, i, a) => a.indexOf(v) === i).join(', ')})`);
  }
  w(
    `\n  ⛔ ausentes: ${faltando.length} · ⬜ ignorados por design: ${ignorados.length}\n` +
    (faltando.length
      ? `  O que falta se escreve no git, como qualquer protótipo (ADR 0438 D1): crie o arquivo no espelho\n` +
        `  ou tire a referência do shell.\n  (não muda o exit code: o --check morde só em STALE)\n`
      : `  ✓ toda dep do shell existe no espelho.\n`),
  );
}

// ── INTEGRIDADE REFERENCIAL NA PODA (--check-refs) ────────────────────────────
// O QUE DEFENDE: podar o espelho é operação legítima e recorrente (4 commits na história
// removeram 296 arquivos). O risco não é podar — é podar um arquivo que OUTRO arquivo do
// espelho ainda carrega, e o protótipo parar de renderizar sem ninguém notar. O ABSENT-LOCAL
// (acima) já cobre as deps do SHELL; este cobre o resto do grafo: qualquer arquivo do espelho
// apontando pra qualquer outro.
//
// POR QUE ESTE PREDICADO E NÃO "toda referência resolve": medido 2026-08-13 no espelho real,
// 30 das 171 referências não resolvem POR DESENHO — alias de bundler (`@/Layouts/…`), pacote
// npm (`@inertiajs/react`), rota de API (`/api/…`) e o `_ds/` que é gitignored. Gatear nisso
// seria 30 falso-positivos no dia 1, a doença das 4 lápides de guard sintático do §5. O
// predicado que importa é diff-aware: "sobrou alguém apontando pro que ESTE diff apagou?".
//
// FP MEDIDO ANTES DE INSTALAR (§5 "ligar ≠ criar"): rodado contra os 4 commits da história que
// deletaram do espelho (152 · 42 · 6 · 96 arquivos) → 0 disparo. Não reprova poda legítima.
// MORDIDA PROVADA: removendo `app.jsx` e `styles.css` (deps reais do shell) o predicado acusa
// as 2, nominalmente. Fixture boa/ruim em tests/governance-fixtures/cowork-refs/, wirada no
// gate-selftest — o par existe porque "0 disparos em 4 commits" também é o perfil de um
// detector cego, e sem controle positivo não dá pra distinguir os dois.
//
// LIMITE HONESTO (§5 2026-08-10, catraca que itera o lado mutável): se o arquivo QUE REFERENCIA
// também é apagado no mesmo diff, o par some junto e isto passa em silêncio — corretamente,
// mas significa que apagar o espelho inteiro passa vazio. Quem cobre esse flanco é o
// ABSENT-LOCAL, que deriva o universo do shell, não do diff.
const REF_PAT = {
  html: [/<script[^>]+src=["']([^"']+)["']/gi, /<link[^>]+href=["']([^"']+)["']/gi,
         /<img[^>]+src=["']([^"']+)["']/gi, /<iframe[^>]+src=["']([^"']+)["']/gi],
  js: [/\bimport\s+[^"'()]*?from\s*["']([^"']+)["']/g, /\bimport\s*\(\s*["']([^"']+)["']/g,
       /\brequire\s*\(\s*["']([^"']+)["']/g],
  css: [/@import\s+(?:url\()?["']?([^"')\n]+)["']?\)?/g, /url\(\s*["']?([^"')\n]+)["']?\s*\)/g],
};
const refKind = (f) => (/\.html?$/i.test(f) ? 'html' : /\.css$/i.test(f) ? 'css'
  : /\.(m?jsx?|tsx?)$/i.test(f) ? 'js' : null);
// Externo = protocolo/âncora. Alias = `@…` (alias de bundler ou escopo npm) ou token sem
// barra e sem extensão (pacote). Nenhum dos dois é caminho de arquivo — ignorar não é
// leniência, é não confundir universos.
const refExterno = (r) => /^(https?:|data:|blob:|mailto:|#|\/\/)/i.test(r) || !r.trim();
const refAlias = (r) => r.startsWith('@') || /^[a-z0-9_-]+$/i.test(r);

/** Referências do espelho que apontam pra path DELETADO (pura, testável).
 *  `vivos`: [{path, texto}] dos arquivos do espelho que SOBRARAM (path relativo à raiz do repo).
 *  `deletados`: Set de paths (relativos à raiz) removidos pelo diff.
 *  Devolve [{de, ref, alvo}] — vazio = a poda não quebrou nada. */
export function refsParaDeletado(vivos, deletados) {
  const out = [];
  for (const { path: f, texto } of vivos) {
    const kind = refKind(f);
    if (!kind || !texto) continue;
    const dir = posixDirname(f);
    for (const re of REF_PAT[kind]) {
      re.lastIndex = 0;
      let m;
      while ((m = re.exec(texto))) {
        const ref = m[1];
        if (refExterno(ref) || refAlias(ref)) continue;
        const limpo = ref.split('?')[0].split('#')[0];
        const alvo = limpo.startsWith('/')
          ? posixJoin(MIRROR_REL, limpo.slice(1))
          : posixJoin(dir, limpo);
        // extensão implícita: `import './x'` resolve `x.jsx`
        for (const e of ['', '.jsx', '.js', '.mjs', '.css', '.html']) {
          if (deletados.has(alvo + e)) { out.push({ de: f, ref, alvo: alvo + e }); break; }
        }
      }
    }
  }
  return out;
}

// posix path sem depender de node:path (o resto do arquivo também é self-contained)
function posixDirname(p) { const i = p.lastIndexOf('/'); return i === -1 ? '.' : p.slice(0, i); }
function posixJoin(base, rel) {
  const partes = `${base}/${rel}`.split('/');
  const pilha = [];
  for (const seg of partes) {
    if (!seg || seg === '.') continue;
    if (seg === '..') pilha.pop(); else pilha.push(seg);
  }
  return pilha.join('/');
}
export const MIRROR_REL = 'prototipo-ui/cowork/Wagner';

// ── ÓRFÃO NA ADIÇÃO (--check-orfaos) ─────────────────────────────────────────
// O QUE DEFENDE: a direção que faltava da paridade host↔espelho. O lado Cowork autorou um
// `scripts/cowork-paridade.mjs` (existe no projeto vivo, nunca desceu — e não desce: seria
// script paralelo a este dono, LC-19; avaliação completa na session 2026-09-01) cujo check C2
// é "arquivo em cowork/ que o host não declara". A metade C1 (declarado→presente) já é o
// --absent-local; esta é a metade C2 (presente→declarado), em forma DELTA.
//
// POR QUE DELTA E NUNCA ABSOLUTO (FP medido ANTES — §5 "ligar ≠ criar"): remedido 2026-09-01
// no espelho real: 29 órfãos de 280 rastreados com extensão de build, dos quais 26 têm
// proveniência declarada — 21 `venda-v3/` + 2 `produto-preco-especial/` (FORA_DESTA_CONTA,
// telas de [L]/[M] — protocolo.config.mjs, [W] 2026-08-13), 2 `ds-v6/`, 1 `prototipos/`.
// Predicado absoluto = ~90% FP no dia 1 (família §5 2026-08-24, delta vs absoluto). Os 3 da
// raiz (`Financeiro - Prova Viva (primitivos).html` + 2 `.js` citados só em PROSA de mockup)
// são sinal real porém HERDADO — grandfathered (ADR 0275, forward-only): entram no relatório
// do lado Cowork, nunca no vermelho de PR alheio.
//
// A EXCEÇÃO É POR PROVENIÊNCIA DECLARADA, não por adivinhação de nome: cada prefixo cita a
// decisão escrita que o sustenta, é REPORTADO como `ignorados` (nunca some calado — mesmo
// contrato do absentLocal), e SAI da lista quando a proveniência mudar (lista que só cresce
// vira allowlist — §5 2026-08-02).
export const ORFAO_POR_DESIGN = [
  { prefixo: 'venda-v3/', motivo: 'FORA_DESTA_CONTA: tela Venda vem de outra conta de design ([L]/[M]) — protocolo.config.mjs, [W] 2026-08-13' },
  { prefixo: 'produto-preco-especial/', motivo: 'FORA_DESTA_CONTA: tela Produto vem de outra conta de design ([L]/[M]) — protocolo.config.mjs, [W] 2026-08-13' },
  { prefixo: 'ds-v6/', motivo: 'ds-v6/ não é build do shell (o próprio cowork-paridade do Cowork o isenta — IGNORAR_ORFAO)' },
  { prefixo: 'prototipos/', motivo: 'prototipos/ não é build do shell (idem IGNORAR_ORFAO do Cowork)' },
  { prefixo: '_shared/', motivo: '_shared/ não é build do shell (idem IGNORAR_ORFAO do Cowork)' },
  { prefixo: 'pipeline/', motivo: 'pipeline/ não é build do shell (idem IGNORAR_ORFAO do Cowork)' },
];

/** Adições ao espelho que o shell NÃO declara (pura, testável — irmã de refsParaDeletado).
 *  `adicionados`: paths relativos à raiz do repo (do diff --diff-filter=AR ou fixture).
 *  `declarados`: Set de paths relativos à raiz (deps do shell + o próprio shell).
 *  Só extensões de build (jsx/tsx/css/js/html) entram no universo — `.md` em cowork/ é
 *  problema do R1 do cowork-ssot-guard, não daqui (não duplicar régua, §5 2026-07-09).
 *  Devolve { orfaos, ignorados } — `ignorados` é reportado, nunca escondido. */
export function orfaosNaAdicao(adicionados, declarados) {
  const orfaos = [], ignorados = [];
  for (const f of adicionados) {
    if (!f.startsWith(`${MIRROR_REL}/`)) continue;
    const r = f.slice(MIRROR_REL.length + 1);
    if (!/\.(jsx|tsx|css|js|html)$/i.test(r)) continue;
    if (declarados.has(f)) continue;
    const ex = ORFAO_POR_DESIGN.find((e) => r.startsWith(e.prefixo));
    if (ex) { ignorados.push({ path: f, motivo: ex.motivo }); continue; }
    orfaos.push(f);
  }
  return { orfaos, ignorados };
}

// ── CLI ───────────────────────────────────────────────────────────────────────
function main() {
  const argv = process.argv.slice(2);
  // --unverified saiu em 2026-10-23 (ADR 0438 D7). Sem esta trava, quem ainda o chamasse
  // cairia no caminho padrão e sairia 0: verde sem ter medido nada (§5 2026-07-29).
  if (argv.includes('--unverified')) {
    console.error('✗ --unverified foi removido em 2026-10-23 (ADR 0438 D7): o check "espelho — mexeu depois de verificar" saiu junto. Nada foi medido.');
    process.exit(2);
  }

  // Teste deste script vive no IRMÃO `cowork-mirror-freshness.test.mjs` (wirado em
  // design-memory-gate.yml:239) — que já cobria 9 funções puras. Em 2026-08-11 eu cheguei a
  // escrever um `--selftest` embutido aqui e o REMOVI: seria um 2º dono do mesmo teste, que é
  // o anti-padrão que este repo cataloga (§5 "duplica régua consolidada" · LC-19). As funções
  // novas (liveOnly · exportPlan) foram para o irmão.

  // --absent-local: "o shell CARREGA e o espelho NÃO TEM?" — o 4º flanco do cabeçalho, que até
  // aqui era MEDIDO e INCAPAZ DE REPROVAR: `reportAbsentLocal` só imprime, e o exit code do
  // --manifest vem do `shouldFail()`, que morde SÓ em STALE. Gate mudo com cara de cobertura.
  //
  // 100% LOCAL — lê o shell do próprio espelho, zero DesignSync — e por isso É gateável, ao
  // contrário do --compare/--live-only, que precisam da auth interativa (ADR 0315). A ressalva
  // do cabeçalho ("rotina de dispatch logado, não gate") vale pro flanco que fala com o VIVO;
  // este não fala. Responde literalmente "pegou todos os componentes, subpáginas, CSS e JS?"
  // ([W] 2026-08-14). NÃO substitui o --live-only: aquele vê o que NUNCA desceu; este vê o que
  // o shell precisa e não está — universos diferentes (LIVE-ONLY, §5 2026-08-11).
  if (argv.includes('--absent-local')) {
    const shellHtml = lerShellHtml();
    if (!shellHtml) {
      // FAIL-CLOSED: "não consegui medir" NUNCA vira verde (§5 2026-07-29 — instrumento não
      // afirma saúde do que não percorreu). Sem shell não há universo, logo não há veredito.
      console.error('✗ --absent-local: shell do espelho não encontrado — sem universo, sem veredito.');
      process.exit(2);
    }
    const { faltando, ignorados } = absentLocal(shellHtml);
    reportAbsentLocal(shellHtml, process.stdout);
    console.log(`\n  ABSENT-LOCAL — ⛔ ausentes: ${faltando.length} · ⬜ ignorados por design: ${ignorados.length}`);
    if (faltando.length) {
      console.error(`✗ espelho INCOMPLETO — ${faltando.length} dep(s) que o shell carrega não existe(m) no espelho.`);
      process.exit(1);
    }
    console.log('  ✓ toda dep do shell existe no espelho.');
    process.exit(0);
  }

  // --check-refs: a poda quebrou o grafo interno do espelho? Diff-aware, 100% LOCAL — por
  // isso ISTO é gateável e o --compare não é (aquele precisa da auth interativa do DesignSync).
  // Universo dos deletados: `git diff --diff-filter=D <range>` (default origin/main...HEAD) ou
  // --deleted-from <arquivo> (uma linha por path) pra fixture rodar sem git.
  if (argv.includes('--check-refs')) {
    const delIdx = argv.indexOf('--deleted-from');
    let deletados;
    if (delIdx !== -1) {
      const dp = argv[delIdx + 1];
      if (!dp || !existsSync(dp)) {
        console.error('✗ --deleted-from exige um arquivo com um path por linha.');
        process.exit(2);
      }
      deletados = new Set(readFileSync(dp, 'utf8').split('\n').map((s) => s.trim()).filter(Boolean));
    } else {
      const rIdx = argv.indexOf('--range');
      const range = rIdx !== -1 && argv[rIdx + 1] ? argv[rIdx + 1] : 'origin/main...HEAD';
      // O range é UM token (`a..b`/`a...b`). `--range HEAD~1 HEAD` pareceria funcionar e o git
      // compararia `HEAD~1` contra a WORKING TREE — universo diferente, veredito diferente, sem
      // aviso. Medir a coisa errada calado é pior que não medir (§5 2026-08-13).
      if (!range.includes('..')) {
        console.error(`✗ --check-refs: --range espera UM token de range ("a..b" ou "a...b"), recebi "${range}".`);
        console.error('  Passar duas revisões separadas faz o git diffar contra a working tree.');
        process.exit(2);
      }
      let saida;
      try {
        saida = execFileSync('git', ['diff', '--name-only', '--diff-filter=D', range, '--', `${MIRROR_REL}/`],
          { encoding: 'utf8', maxBuffer: 1 << 28 });
      } catch (e) {
        // Vazio de comando que FALHOU não é "nada deletado" (§5 2026-07-31 · 2026-08-11).
        console.error(`✗ --check-refs: git diff falhou no range "${range}" — sem universo, sem veredito.`);
        console.error(`  ${String(e.message || e).split('\n')[0]}`);
        process.exit(2);
      }
      deletados = new Set(saida.split('\n').map((s) => s.trim()).filter(Boolean));
    }

    const raizEspelho = join(ROOT, MIRROR_REL);
    const vivos = [];
    if (existsSync(raizEspelho)) {
      (function walk(rel) {
        for (const e of readdirSync(join(ROOT, rel), { withFileTypes: true })) {
          const p = `${rel}/${e.name}`;
          if (e.isDirectory()) walk(p);
          else if (refKind(p)) { try { vivos.push({ path: p, texto: readFileSync(join(ROOT, p), 'utf8') }); } catch { /* binário */ } }
        }
      })(MIRROR_REL);
    }

    const quebras = refsParaDeletado(vivos, deletados);
    console.log(`  espelho: ${vivos.length} arquivo(s) textual(is) · deletados no diff: ${deletados.size}`);
    if (!deletados.size) {
      console.log('  ✓ nenhuma deleção no espelho neste diff — nada a verificar.');
      process.exit(0);
    }
    if (!quebras.length) {
      console.log(`  ✓ integridade referencial preservada: nenhum arquivo do espelho aponta pros ${deletados.size} deletado(s).`);
      process.exit(0);
    }
    console.error(`\n  ⛔ ${quebras.length} referência(s) apontam pra arquivo que este diff APAGA:`);
    for (const q of quebras) console.error(`     ${q.de}\n        -> "${q.ref}"  = ${q.alvo}`);
    console.error('\n  A poda quebrou o render do protótipo. Restaure o arquivo OU remova a referência no mesmo PR.');
    process.exit(1);
  }

  // --check-orfaos: este PR ADICIONA ao espelho arquivo que o shell não declara? DELTA,
  // diff-aware, 100% LOCAL — mesmo desenho do --check-refs (aquele vigia a PODA, este a
  // ADIÇÃO). Universo: `git diff --diff-filter=AR` (A adiciona · R renomeia PARA dentro —
  // enumerar os status antes de escolher as letras, §5 2026-08-08) ou --added-from (fixture).
  // Racional + FP medido no docblock de ORFAO_POR_DESIGN/orfaosNaAdicao acima.
  if (argv.includes('--check-orfaos')) {
    const addIdx = argv.indexOf('--added-from');
    let adicionados;
    if (addIdx !== -1) {
      const ap = argv[addIdx + 1];
      if (!ap || !existsSync(ap)) {
        console.error('✗ --added-from exige um arquivo com um path por linha.');
        process.exit(2);
      }
      adicionados = readFileSync(ap, 'utf8').split('\n').map((s) => s.trim()).filter(Boolean);
    } else {
      const rIdx = argv.indexOf('--range');
      const range = rIdx !== -1 && argv[rIdx + 1] ? argv[rIdx + 1] : 'origin/main...HEAD';
      if (!range.includes('..')) {
        console.error(`✗ --check-orfaos: --range espera UM token de range ("a..b" ou "a...b"), recebi "${range}".`);
        console.error('  Passar duas revisões separadas faz o git diffar contra a working tree.');
        process.exit(2);
      }
      let saida;
      try {
        saida = execFileSync('git', ['diff', '--name-only', '--diff-filter=AR', range, '--', `${MIRROR_REL}/`],
          { encoding: 'utf8', maxBuffer: 1 << 28 });
      } catch (e) {
        // Vazio de comando que FALHOU não é "nada adicionado" (§5 2026-07-31 · 2026-08-11).
        console.error(`✗ --check-orfaos: git diff falhou no range "${range}" — sem universo, sem veredito.`);
        console.error(`  ${String(e.message || e).split('\n')[0]}`);
        process.exit(2);
      }
      adicionados = saida.split('\n').map((s) => s.trim()).filter(Boolean);
    }

    console.log(`  adicionados ao espelho neste diff: ${adicionados.length}`);
    if (!adicionados.length) {
      console.log('  ✓ nenhuma adição ao espelho neste diff — nada a verificar.');
      process.exit(0);
    }
    const shellHtml = lerShellHtml();
    if (!shellHtml) {
      // FAIL-CLOSED: sem shell não há "declarado", logo não há veredito (§5 2026-07-29).
      console.error('✗ --check-orfaos: shell do espelho não encontrado — sem universo, sem veredito.');
      process.exit(2);
    }
    const declarados = new Set([
      `${MIRROR_REL}/oimpresso.com.html`,
      ...parseShellDeps(shellHtml).map((d) => `${MIRROR_REL}/${d}`),
    ]);
    const { orfaos, ignorados } = orfaosNaAdicao(adicionados, declarados);
    for (const i of ignorados) console.log(`  ⬜ ignorado por proveniência: ${i.path}\n       ${i.motivo}`);
    if (!orfaos.length) {
      console.log(`  ✓ toda adição ao espelho está declarada pelo shell (ou tem proveniência declarada).`);
      process.exit(0);
    }
    console.error(`\n  ⛔ ${orfaos.length} arquivo(s) que este diff ADICIONA ao espelho e o shell NÃO declara:`);
    for (const f of orfaos) console.error(`     ${f}`);
    console.error('\n  Órfão nasce invisível: nenhuma rota o alcança e nenhum <link>/<script> o carrega.');
    console.error('  Declare-o no shell (oimpresso.com.html), remova-o do PR, ou registre a proveniência em ORFAO_POR_DESIGN.');
    process.exit(1);
  }

  // --compare-bundle [<manifest.json>] [--owner <Wagner|Felipe>] [--check] [--ledger]: veredito
  // a partir do BUNDLE v2
  // já promovido, em vez de N chamadas `DesignSync.get_file`.
  //
  // POR QUE EXISTE (2026-09-08, [W] "estende o freshness pro bundle"): a rota PRINCIPAL de
  // conteúdo virou o bundle (gerar-payload-partes -> aplicar-payload, removidos em 2026-10-23), que confere bytes
  // por arquivo antes de escrever. Mas o ledger só aceitava snapshot vindo de `get_file` por
  // path — então aplicar o bundle com fidelidade provada NÃO movia o `unchecked`, e fechar
  // 273 arquivos custava 273 chamadas. Medido nesse dia: bundle promovido, espelho idêntico
  // (0 mudança), e o --sla continuava dizendo "271 sem veredito". As duas metades do mesmo
  // sistema não se falavam.
  //
  // NÃO é atalho pra medir o vivo: o `liveHash` é o sha256 que o gerador calculou DO VIVO
  // antes do transporte. O que este modo NÃO faz é ir ao Cowork agora — e por isso a entrada
  // é datada com o `generatedAt` do bundle. Bundle velho => rodada velha => o --sla cobra.
  if (argv.includes('--compare-bundle')) {
    const bi = argv.indexOf('--compare-bundle');
    const alt = argv[bi + 1] && !argv[bi + 1].startsWith('--') ? argv[bi + 1] : null;
    // --owner <Wagner|Felipe>: QUAL espelho medir. Sem isto o modo lia o disco do Wagner
    // para qualquer lote, e o do Felipe nunca teve medição de frescor nenhuma.
    // `pathsForOwner` é o dono da regra e já recusa dono inválido — fail-closed de graça.
    const oi = argv.indexOf('--owner');
    const owner = oi === -1 ? 'Wagner' : argv[oi + 1];
    let ownerPaths;
    try { ownerPaths = pathsForOwner(owner); }
    catch (e) { console.error(`✗ --compare-bundle: ${e.message}`); process.exit(2); }
    // Cada conta tem seu PRÓPRIO estado (ADR 0405 §"Wagner conserva state/, Felipe recebe
    // state/Felipe/"): ler o active-bundle do Wagner para medir
    // o espelho do Felipe compararia um espelho contra o bundle de outra conta.
    const bundleRel = `${ownerPaths.state}/active-bundle.json`;
    const bundle = alt
      ? (existsSync(alt) ? JSON.parse(readFileSync(alt, 'utf8')) : null)
      : lerBundlePromovido(ROOT, bundleRel);
    // FAIL-CLOSED: sem bundle não há liveHash, logo não há veredito — nunca verde por ausência.
    if (!bundle || !Array.isArray(bundle.files) || bundle.files.length === 0) {
      console.error(`✗ --compare-bundle: bundle promovido ausente ou ilegível (${bundleRel}). O applier do bundle saiu em 2026-10-23 (ADR 0438); este modo sai na onda 2c-3.`);
      process.exit(2);
    }
    const manifestB = buildManifest(ROOT, { all: false, shellHtml: lerShellHtml(ROOT, owner), owner });
    const ler = (relPath) => { const p = join(ROOT, relPath); return existsSync(p) ? readFileSync(p) : null; };
    const rows = rowsDoBundle(bundle, manifestB, ler, owner);
    const conta = (v) => rows.filter((r) => r.veredito === v).length;
    const nSync = conta('SYNC'), nStale = conta('STALE'), nUnch = conta('UNCHECKED');
    const dataBundle = bundle.generatedAt || null;
    console.log(`\n  COMPARE-BUNDLE — espelho de ${owner} × bundle ${String(bundle.bundleId || '').slice(0, 16)} (emitido ${dataBundle || '?'})\n`);
    console.log(`  ✓ sync: ${nSync} · ⛔ stale: ${nStale} · ⬜ unchecked: ${nUnch}  (de ${rows.length})`);
    for (const r of rows.filter((x) => x.veredito === 'STALE')) console.log(`     ⛔ STALE  ${r.cowork}`);
    // O superlativo só fala do MEDIDO, e o denominador anda junto (LC-13 · §5 2026-08-13).
    console.log(nUnch === 0
      ? `\n  Cobertura TOTAL do espelho por este bundle.`
      : `\n  ⚠️ COBERTURA PARCIAL — ${nUnch} arquivo(s) do espelho não estão no bundle: seguem SEM VEREDITO.`);
    console.log(`  ⚠️ Isto prova igualdade com o vivo NA DATA DE EMISSÃO (${dataBundle || '?'}), não "agora".\n`);
    if (argv.includes('--ledger')) {
      if (!dataBundle) {
        console.error('✗ --ledger recusado: bundle sem `generatedAt`. Datar a rodada com a hora da LEITURA faria o SLA acreditar num frescor que ninguém mediu.');
        process.exit(2);
      }
      const lp = join(ROOT, LEDGER_REL);
      let entries = [];
      try { entries = existsSync(lp) ? JSON.parse(readFileSync(lp, 'utf8')) : []; } catch { entries = []; }
      if (!Array.isArray(entries)) entries = entries.runs || [];
      entries.push(ledgerEntry(rows, dataBundle, { origin: `bundle:${String(bundle.bundleId || '').slice(0, 16)}` }));
      writeFileSync(lp, JSON.stringify(entries, null, 2) + '\n');
      console.log(`  ledger: rodada registrada em ${LEDGER_REL} (origin=bundle, datada de ${dataBundle}). Commite o ledger.\n`);
    }
    if (argv.includes('--check') && shouldFail(rows.map((r) => r.veredito))) process.exit(1);
    return;
  }

  // --css-refs: todo `url()`/`@import` do CSS do espelho resolve pra arquivo que existe?
  // Herda o papel que morreu junto com o `--preview-ds` (ver docblock de `cssRefsMortas`).
  // Advisory por default; `--enforce` sai 1 no drift real e 2 quando NÃO CONSEGUIU MEDIR —
  // não-medição nunca sai com o código de defeito (§5 2026-07-29).
  if (argv.includes('--css-refs')) {
    const r = cssRefsMortas();
    const enforce = argv.includes('--enforce');
    if (!r.medido) {
      console.error(`✗ REFS-CHECK NÃO MEDIU — ${r.motivo}`);
      process.exit(enforce ? 2 : 0);
    }
    console.log(`\n  REFS-CHECK — referências de arquivo nos CSS do espelho\n`);
    console.log(`  ${r.arquivos} arquivo(s) .css · ${r.refs} referência(s) relativa(s) conferida(s)`);
    console.log(`  (data:, http(s):, protocol-relative e âncora ficam fora — não são arquivo do espelho)\n`);
    for (const m of r.mortas) {
      const detalhe = m.motivo === 'insegura' ? 'referência INSEGURA (traversal/absoluto)' : `não existe: ${m.alvo}`;
      console.log(`  ✗ ${m.arquivo}  ->  ${m.ref}   ${detalhe}`);
    }
    if (!r.mortas.length) {
      console.log('  ✓ nenhuma referência morta.\n');
      return;
    }
    console.log(`\n  ${r.mortas.length} referência(s) MORTA(S) — o browser dá 404 silencioso e cai no fallback.`);
    console.log('  Conserto: traga o arquivo da origem, ou reaponte o CSS. Não some o 404: ele não aparece em teste nenhum.\n');
    if (enforce) process.exit(1);
    console.log('  (advisory: exit 0 — promover a bloqueante é decisão [W])');
    return;
  }

  // --preview-ds: repõe o `_ds/` do espelho a partir do mirror-snapshot versionado.
  // Sem isso o protótipo abre COM os tokens de status vazios (medido 2026-08-13).
  if (argv.includes('--preview-ds')) {
    // APOSENTADO em 2026-09-11 (#7224) — fato datado, não afirmação em presente.
    //
    // A redação anterior dizia "o shell lê prototipo-ui/design-system/ diretamente". Isso era a
    // INTENÇÃO do #7224, não o estado: medido 2026-09-17, o shell do espelho ainda referencia
    // `_ds/<id>/…` nos 3 arquivos do DS. Artefato não declara comportamento em tempo presente —
    // ele reporta o NÚMERO e deixa a leitura com quem mede (LC-10). Então aqui eu conto as refs
    // em vez de afirmar o que o shell faz.
    const shellPath = (() => { const i = argv.indexOf('--shell'); return i !== -1 ? argv[i + 1] : defaultShellPath(); })();
    const htmlAtual = shellPath && existsSync(shellPath) ? readFileSync(shellPath, 'utf8') : null;
    console.log('\n  PREVIEW-DS aposentado em 2026-09-11 (#7224): nenhum cache _ds/ é criado por aqui.\n');
    if (htmlAtual === null) {
      console.log(`  ⬜ shell não encontrado (${shellPath || 'sem path'}) — não medi as referências ao DS.`);
    } else {
      const refsDs = parseShellDeps(htmlAtual).filter((p) => p.startsWith('_ds/'));
      const refsDiretas = parseShellDeps(htmlAtual).filter((p) => p.startsWith('design-system/'));
      // ESTÁTICO × TEXTUAL: `parseShellDeps` só enxerga `href=`/`src=` literais. Um shell que monta
      // a URL em runtime (`document.write('<link href="'+BASE+'x.css">')`) devolve 0 nas duas
      // contagens — e 0/0 lido como "não há ref ao DS" é o instrumento MUDO, não o shell limpo.
      // Medido 2026-09-17 no pacote 24, que passou a resolver a base por ambiente: estático 0/0,
      // textual 4/2. Então reporto os DOIS e digo qual é qual.
      const txtDs = (htmlAtual.match(/_ds\//g) || []).length;
      const txtDiretas = (htmlAtual.match(/design-system\//g) || []).length;
      console.log(`  medido agora em ${relative(ROOT, shellPath) || shellPath}:`);
      console.log(`    refs a _ds/           ${refsDs.length}  (estáticas)   · ${txtDs} ocorrência(s) no texto`);
      console.log(`    refs a design-system/ ${refsDiretas.length}  (estáticas)   · ${txtDiretas} ocorrência(s) no texto`);
      for (const r of refsDs) console.log(`      · ${r}`);
      if (!refsDs.length && !refsDiretas.length && (txtDs || txtDiretas)) {
        console.log(`\n  ⬜ Nenhuma ref ESTÁTICA, mas o texto cita o DS — o shell resolve a base em runtime.`);
        console.log(`     Esta sonda lê href/src literais; ela NÃO decide qual base o browser usa.`);
      }
      if (refsDs.length) {
        console.log(`\n  ⬜ Enquanto houver ref a _ds/, o preview LOCAL do espelho não carrega o DS:`);
        console.log(`     esses paths são ignorados por design (.gitignore) e não existem aqui.`);
        console.log(`     Quem reescreve o shell é o lado Cowork — não há conserto deste lado.`);
      }
    }
    console.log('');
    return;
    const shellIdx0 = argv.indexOf('--shell');
    const sp = shellIdx0 !== -1 ? argv[shellIdx0 + 1] : defaultShellPath();
    const html = sp && existsSync(sp) ? readFileSync(sp, 'utf8') : null;
    const plano = previewDsPlan(html);
    if (plano.erro) { console.error(`✗ ${plano.erro}`); process.exit(2); }
    console.log(`\n  PREVIEW-DS — repondo o _ds/ do espelho (id derivado do shell, não hardcode)\n`);
    console.log(`  design system: ${plano.id}`);
    let ok = 0, faltando = [], invalidos = [];
    // Fase 1: valida o lote INTEIRO antes da primeira escrita. Assim uma fonte ausente,
    // traversal ou bundle truncado não deixa um cache híbrido (parte nova + parte velha).
    for (const a of plano.arquivos) {
      if (!a.temNoRepo) { faltando.push(a.nome); console.log(`  ⚠ SEM FONTE   ${a.nome}  (não existe em prototipo-ui/design-system/)`); continue; }
      if (a.nome === '_ds_bundle.js') {
        try {
          execFileSync(process.execPath, ['--check', a.de], { stdio: 'ignore' });
        } catch {
          invalidos.push(a.nome);
          console.log(`  ⛔ INVÁLIDO    ${a.nome}  (JavaScript incompleto ou com erro de sintaxe)`);
        }
      }
    }
    // A frase precisa distinguir os dois tipos, mas AMBOS bloqueiam fidelidade. Sem o
    // `_ds_bundle.js`, Drawer/Skeleton/DropdownMenu ficam undefined e partes inteiras
    // somem (`if (!Drawer || !meta) return null`). Fonte ausente muda a tipografia.
    if (faltando.length) {
      const fontes = faltando.filter((f) => /\.(woff2?|ttf|otf|eot)$/i.test(f));
      const resto = faltando.filter((f) => !/\.(woff2?|ttf|otf|eot)$/i.test(f));
      if (resto.length) console.log(`  ⛔ o repo NÃO TEM ${resto.length} (${resto.join(', ')}) — componentes compilados do DS; drawers/eventos podem desaparecer no fallback.`);
      if (fontes.length) console.log(`  ⚠ o repo NÃO TEM ${fontes.length} FONTE(s) — elas NÃO têm fallback equivalente: o preview renderiza com a fonte do sistema, então a tipografia diverge do Cowork vivo. Não é cosmético.`);
    }
    if (faltando.length || invalidos.length) {
      console.log(`\n  0 reposto(s) · ${faltando.length} sem fonte no repo${faltando.length ? ` (${faltando.join(', ')})` : ''} · ${invalidos.length} inválido(s)`);
      console.log(`  destino preservado sem escrita parcial: ${plano.destino}/`);
      console.error('\n✗ PREVIEW INCOMPLETO — PARE antes de editar Pages/Modules. Recupere todos os artefatos e rode --preview-ds novamente.');
      process.exit(1);
    }
    // Fase 2: só um lote completamente validado pode materializar o cache. A função
    // publica por troca de diretório, removendo órfãos e sem expor estado intermediário.
    try {
      materializePreviewDs(plano);
    } catch (error) {
      console.error(`\n✗ PREVIEW NÃO MATERIALIZADO — cache anterior preservado: ${error.message}`);
      process.exit(2);
    }
    for (const a of plano.arquivos) { ok++; console.log(`  ✓ reposto     ${a.nome}`); }
    console.log(`\n  ${ok} reposto(s) · 0 sem fonte no repo · 0 inválido(s)`);
    console.log(`  destino: ${plano.destino}/ — segue gitignored (build de preview, não versionamento).`);
    console.log('\n✓ PREVIEW COMPLETO — todas as dependências do DS existem e o bundle passa no parser.');
    console.log('');
    return;
  }

  // Sem modo reconhecido: os modos de FRESCOR contra o Cowork (--manifest, --compare, --ledger,
  // --sla, --live-only, --sla-live-only, --lista-download, --export-from, --snapshot-from,
  // --check-novos) saíram em 2026-10-23 com a ADR 0438 (onda 2c-2): a fonte do protótipo é o git.
  // Cair aqui é uso errado, e sai 2 — nunca 0, que leria como "medi e está tudo bem" (§5 2026-07-29).
  console.error('uso: cowork-mirror-freshness.mjs --check-refs | --check-orfaos | --absent-local | --css-refs | --preview-ds | --compare-bundle');
  console.error('     (os modos de frescor contra o Cowork saíram em 2026-10-23, ADR 0438)');
  process.exit(2);
}

if (process.argv[1] && process.argv[1].replace(/\\/g, '/').endsWith('cowork-mirror-freshness.mjs')) main();
