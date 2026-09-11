#!/usr/bin/env node
// @ts-check
/**
 * receber-handoff.mjs — recebe o ZIP de handoff do Cowork e o transforma em import validado.
 *
 * POR QUE EXISTE ([W] 2026-09-10): "o objetivo é eu exportar uma única vez, sem depender de uma
 * receita manual em cada importação". Nos ciclos de 08/09 e 10/09 a mesma sequência foi executada
 * À MÃO três vezes (#7043, #7197, #7199) — extrair, ver que o `sync/` do pacote está velho, reger
 * com o gerador canônico, reconciliar o `_ds/`, validar, aplicar. Receita manual repetida é a
 * definição de "escrito+lembrado apodrece" (ADR 0256): aqui ela vira comando.
 *
 * ⚠️ ORQUESTRADOR, NÃO MOTOR. Ele não reimplementa nada: o contrato é do `bundle-contract.mjs`,
 * o pacote é do `gerar-payload-partes.mjs`, a escrita é do `aplicar-payload.mjs`, o mapa de
 * destino do DS é do `cowork-mirror-freshness.mjs`. Se um deles mudar, isto acompanha de graça.
 *
 * ⚠️ O ZIP NÃO VIRA STAGING. A extração vai pra um diretório EFÊMERO (mkdtemp), nunca pro
 * `STAGING_DIR`. O motivo está no próprio painel: staging persistente foi banido porque uma
 * árvore velha alimentava captura nova em silêncio. Aqui não há árvore velha pra alimentar nada —
 * cada execução extrai o ZIP daquele momento e joga fora. `Downloads/` segue LUGAR PROIBIDO pra
 * âncora (`ancora-guard`), e isto não mexe nisso: o ZIP é INSUMO DE IMPORTAÇÃO, não fonte de
 * design nem destino de nada.
 *
 * O QUE ELE DECIDE, E O QUE ELE SE RECUSA A DECIDIR:
 *   · "o `sync/` do pacote descreve a árvore que veio com ele?" — DECIDÍVEL (sha256 declarado x
 *     real, arquivo a arquivo). É assim que se detecta pacote velho, não pelo `generatedAt`.
 *   · "quem avançou, o zip ou o espelho?" — só é decidível com o MANIFESTO ATIVO como terceiro
 *     ponto. Com dois pontos (zip x espelho) hash diferente não revela direção, e o
 *     `cowork-mirror-freshness` já diz isso no docblock dele. Então classifico em 4 e deixo
 *     `AMBOS-DIVERGEM` explicitamente indecidido, em vez de chutar.
 *   · `_ds/**` (role `preview-cache`): o dono é o projeto Design System (#7096), não o export de
 *     telas. Aqui o espelho do repo vence POR REGRA declarada, nunca por inferência de frescor.
 *   · "o que existe no vivo e NUNCA desceu?" (live-only, passo [3c]) — a árvore extraída responde
 *     sozinha, sem `DesignSync`. Era rotina separada que só a sessão logada rodava, e vencia por
 *     isso; virou subproduto do ciclo. O medidor segue sendo o `cowork-mirror-freshness`.
 *
 * FAIL-CLOSED: qualquer passo que não fecha aborta antes de escrever. Sem `--apply` nada é
 * promovido — o default é só medir e validar.
 *
 * Uso:
 *   node scripts/design-sync/receber-handoff.mjs --zip <arquivo.zip>              # mede + valida
 *   node scripts/design-sync/receber-handoff.mjs --zip <arquivo.zip> --apply      # + promove
 *   node scripts/design-sync/receber-handoff.mjs --zip <arquivo.zip> --out <dir>  # guarda a árvore
 *   node scripts/design-sync/receber-handoff.mjs --selftest
 *
 * Exit: 0 = ok · 1 = insumo/validação reprovou · 2 = erro de uso.
 */
import { readFileSync, existsSync, mkdtempSync, mkdirSync, writeFileSync, readdirSync } from 'node:fs';
import { join, dirname, relative, resolve } from 'node:path';
import { tmpdir } from 'node:os';
import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { extrairZip } from './zip-reader.mjs';
import { roleForPath, validateManifest } from './bundle-contract.mjs';
import { dsRuntimeRelPath } from '../governance/cowork-mirror-freshness.mjs';

const AQUI = dirname(fileURLToPath(import.meta.url));
const REPO = resolve(AQUI, '..', '..');
const ATIVO = join(REPO, 'scripts/design-sync/state/active-bundle.json');
const SNAPSHOT_DS = join(REPO, 'prototipo-ui/design-system');
const ENTRY = 'oimpresso.com.html';

const sha = (buf) => createHash('sha256').update(buf).digest('hex');
const arg = (nome, padrao = null) => {
  const i = process.argv.indexOf(nome);
  return i >= 0 && process.argv[i + 1] && !process.argv[i + 1].startsWith('--') ? process.argv[i + 1] : padrao;
};
const tem = (nome) => process.argv.includes(nome);

function morre(msg, code = 1) {
  console.error(`\n  x ${msg}\n    Nada foi promovido.`);
  process.exit(code);
}

/** Acha, na árvore extraída, o diretório que contém o shell (o `--root` do gerador). */
export function acharRaiz(base, existe = existsSync, listar = readdirSync) {
  const fila = [base];
  while (fila.length) {
    const dir = fila.shift();
    if (existe(join(dir, ENTRY))) return dir;
    let itens = [];
    try { itens = listar(dir, { withFileTypes: true }); } catch { continue; }
    for (const d of itens) if (d.isDirectory()) fila.push(join(dir, d.name));
  }
  return null;
}

/**
 * Todos os arquivos da árvore, como paths relativos POSIX. É o insumo do `--live-only`, que
 * aceita `{paths:[…]}` de QUALQUER origem — não é acoplado ao `DesignSync`.
 */
export function listarRelativos(raiz, listar = readdirSync) {
  const saida = [];
  const fila = [''];
  while (fila.length) {
    const sub = fila.shift();
    let itens = [];
    try { itens = listar(join(raiz, sub), { withFileTypes: true }); } catch { continue; }
    for (const d of itens) {
      const rel = sub ? `${sub}/${d.name}` : d.name;
      if (d.isDirectory()) fila.push(rel);
      else saida.push(rel);
    }
  }
  return saida.sort();
}

/**
 * O `sync/` que veio no pacote descreve a árvore que veio junto?
 * Núcleo puro e testável: recebe o manifesto e um leitor de bytes.
 * @returns {{veredito: string, divergentes: string[], motivo: string|null}}
 */
export function auditarPacote(manifesto, lerArquivo) {
  // O contrato é do `bundle-contract.mjs`, e o `validateManifest` JÁ recalcula o `bundleId` e
  // reprova quando não bate. Não repito a conta aqui de propósito: a 1a versão deste arquivo
  // repetiu, copiando a fórmula da PARÁFRASE da errata de 08/09 (`{mode, baseBundleId, files,
  // missing}`) em vez de ler o código (`{schema, source, entry, files, missing}`) — e o selftest
  // mordeu. Segundo oráculo do mesmo fato é como se erra duas vezes com um passo a mais.
  let motivo = null;
  try { validateManifest(manifesto); } catch (e) { motivo = e.message; }
  const divergentes = [];
  for (const f of manifesto.files || []) {
    const bytes = lerArquivo(f.path);
    if (bytes === null || sha(bytes) !== f.sha256) divergentes.push(f.path);
  }
  const veredito = motivo ? 'FORA-DO-CONTRATO' : divergentes.length ? 'DESATUALIZADO' : 'CONFORME';
  return { veredito, divergentes, motivo };
}

/**
 * Classificação de 3 pontos (zip x espelho x manifesto ativo). Pura.
 *
 * ⚠️ OS NOMES NÃO DIZEM DIREÇÃO, DE PROPÓSITO. A 1a versão chamava o 1o caso de `ZIP-NOVO`, e
 * na 1a execução real ele caiu justamente no `_ds_bundle.js`, onde o zip é mais VELHO — o rótulo
 * afirmava novidade que a medição não estabelece. Com três hashes dá pra dizer QUEM está fora do
 * bundle ativo; NÃO dá pra dizer quem avançou (é o mesmo limite que o `cowork-mirror-freshness`
 * declara no docblock dele). Direção só existe onde há REGRA: `_ds/**` é do projeto Design
 * System, e isso é decidido no passo [4], não aqui.
 */
export function classificar({ zipHash, repoHash, manifestoHash }) {
  if (zipHash === null) return 'AUSENTE-NO-ZIP';
  if (repoHash === null) return 'AUSENTE-NO-ESPELHO';
  if (zipHash === repoHash) return 'IGUAL';
  if (manifestoHash && repoHash === manifestoHash) return 'ZIP-FORA-DO-BUNDLE';
  if (manifestoHash && zipHash === manifestoHash) return 'ESPELHO-FORA-DO-BUNDLE';
  return 'AMBOS-DIVERGEM';
}

/**
 * O conteúdo que o ZIP traz pra este arquivo JÁ ESTEVE no espelho e foi substituído?
 *
 * Esta é a única pergunta de direção que É decidível — e ela vem do git, não de inferência:
 * se o blob do zip bate com uma versão ANTERIOR do arquivo versionado, o ZIP está ATRÁS.
 * Sem isto, importar um handoff antigo reverte o espelho em silêncio, e o dry-run aprova
 * (é um delta legítimo — só que pro lado errado). Era o furo da receita manual.
 *
 * `execFileSync` sem shell: o `<ref>:<path>` não passa por MSYS, então não sofre o mangling
 * de path do Git Bash (§5 2026-08-23).
 */
export function jaEsteveNoEspelho(pathRepo, hashZip, { repo = REPO, limite = 40 } = {}) {
  let commits = [];
  try {
    commits = execFileSync('git', ['log', '-n', String(limite), '--format=%H', '--', pathRepo], { cwd: repo, encoding: 'utf8' })
      .split('\n').map((s) => s.trim()).filter(Boolean);
  } catch { return null; }
  for (const c of commits.slice(1)) { // slice(1): o HEAD já foi comparado pelo classificador
    try {
      const blob = execFileSync('git', ['show', `${c}:${pathRepo}`], { cwd: repo, maxBuffer: 64 * 1024 * 1024 });
      if (sha(blob) === hashZip) return c.slice(0, 10);
    } catch { /* o arquivo não existia nesse commit */ }
  }
  return null;
}

function roda(script, args) {
  try {
    const out = execFileSync(process.execPath, [join(REPO, script), ...args], {
      cwd: REPO, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'], maxBuffer: 64 * 1024 * 1024,
    });
    return { ok: true, out };
  } catch (e) {
    return { ok: false, out: `${e.stdout || ''}${e.stderr || ''}` };
  }
}

function principal() {
  const zip = arg('--zip');
  if (!zip) morre('faltou --zip <arquivo.zip>', 2);
  if (!existsSync(zip)) morre(`ZIP não existe: ${zip}`, 2);
  const aplicar = tem('--apply');

  // 1. EXTRAIR (efêmero por padrão)
  const destino = arg('--out') || mkdtempSync(join(tmpdir(), 'oi-handoff-'));
  mkdirSync(destino, { recursive: true });
  const entradas = extrairZip(readFileSync(zip), destino);
  console.log(`\n  [1] EXTRAIR      ${entradas.length} arquivo(s) - CRC-32 conferido em todos`);
  console.log(`                   ${destino}`);

  const raiz = acharRaiz(destino);
  if (!raiz) morre(`nao achei ${ENTRY} na arvore extraida - este ZIP nao e um handoff do Cowork`);
  console.log(`                   raiz do projeto: ${relative(destino, raiz) || '.'}`);

  const naArvore = (rel) => {
    const p = join(raiz, ...rel.split('/'));
    return existsSync(p) ? readFileSync(p) : null;
  };

  // 2. O sync/ que veio no pacote
  const manPath = join(raiz, 'sync', 'bundle.manifest.json');
  let auditoria = { veredito: 'AUSENTE', divergentes: [], motivo: 'o pacote nao traz sync/bundle.manifest.json' };
  let manifestoPacote = null;
  if (existsSync(manPath)) {
    manifestoPacote = JSON.parse(readFileSync(manPath, 'utf8'));
    auditoria = auditarPacote(manifestoPacote, naArvore);
  }
  console.log(`\n  [2] PACOTE sync/ ${auditoria.veredito}`);
  if (manifestoPacote) {
    console.log(`                   id ${String(manifestoPacote.bundleId).slice(0, 16)} - ${manifestoPacote.mode} - gerado ${manifestoPacote.generatedAt}`);
  }
  if (auditoria.motivo) console.log(`                   contrato: ${auditoria.motivo}`);
  if (auditoria.divergentes.length) {
    console.log(`                   NAO descreve a propria arvore: ${auditoria.divergentes.length} arquivo(s) com sha256 diferente`);
    console.log(`                   -> por isso o pacote e ignorado e o bundle e REGERADO abaixo.`);
  }

  // 3. DIREÇÃO (zip x espelho x manifesto ativo)
  if (!existsSync(ATIVO)) morre('nao ha bundle ativo em scripts/design-sync/state/');
  const ativo = JSON.parse(readFileSync(ATIVO, 'utf8'));
  const porPathAtivo = new Map(ativo.files.map((f) => [f.path, f.sha256]));
  const espelho = (rel) => {
    const papel = roleForPath(rel);
    const alvo = papel === 'preview-cache'
      ? join(SNAPSHOT_DS, ...dsRuntimeRelPath(rel).split('/'))
      : join(REPO, papel === 'design-doc' ? 'prototipo-ui/cowork/Wagner/handoffs' : 'prototipo-ui/cowork/Wagner', ...rel.split('/'));
    return existsSync(alvo) ? readFileSync(alvo) : null;
  };
  const contagem = {};
  const novos = [];
  for (const f of ativo.files) {
    const z = naArvore(f.path);
    const r = espelho(f.path);
    const cls = classificar({
      zipHash: z === null ? null : sha(z),
      repoHash: r === null ? null : sha(r),
      manifestoHash: porPathAtivo.get(f.path),
    });
    contagem[cls] = (contagem[cls] || 0) + 1;
    if (cls === 'ZIP-FORA-DO-BUNDLE') novos.push(f.path);
  }
  console.log(`\n  [3] TRES PONTOS  ${Object.entries(contagem).map(([k, v]) => `${k}=${v}`).join(' - ')}`);
  if (novos.length) console.log(`                   o zip difere e o espelho bate com o bundle ativo: ${novos.slice(0, 8).join(', ')}${novos.length > 8 ? ` (+${novos.length - 8})` : ''}`);
  if (contagem['AMBOS-DIVERGEM']) console.log(`                   ! ${contagem['AMBOS-DIVERGEM']} indecidivel(is) - nem o zip nem o espelho batem com o manifesto ativo`);

  // 3b. REGRESSAO: o conteudo do zip ja esteve versionado e foi substituido?
  const regressoes = [];
  for (const rel of novos) {
    const papel = roleForPath(rel);
    if (papel === 'preview-cache') continue; // dono e o projeto DS; resolvido por regra no passo [4]
    const pathRepo = `${papel === 'design-doc' ? 'prototipo-ui/cowork/Wagner/handoffs' : 'prototipo-ui/cowork/Wagner'}/${rel}`;
    const z = naArvore(rel);
    const commit = z && jaEsteveNoEspelho(pathRepo, sha(z));
    if (commit) regressoes.push({ rel, commit });
  }
  if (regressoes.length) {
    console.log(`\n  [3b] REGRESSAO   ${regressoes.length} arquivo(s) do zip JA ESTIVERAM no espelho e foram substituidos:`);
    for (const r of regressoes) console.log(`                   ${r.rel}  (versao do zip = a de ${r.commit})`);
    console.log(`                   => este ZIP esta ATRAS do espelho. Aplicar REVERTE esses arquivos.`);
  }

  // 3c. LIVE-ONLY: o que existe no export e NUNCA desceu pro espelho.
  //
  // POR QUE AQUI ([W] 2026-09-10: "ali esta o bundle inteiro"): esta medicao existia como
  // ROTINA SEPARADA que so a sessao logada conseguia rodar — o medidor pedia
  // `DesignSync.list_files`, cuja auth e interativa (ADR 0315), entao CI nao alcanca e sobrava
  // "o agente, quando lembra". Ela vencia. Mas o `--live-only` aceita `{paths:[...]}` de
  // qualquer origem, e o ZIP TEM o projeto inteiro: a lista sai da arvore que o passo [1] ja
  // extraiu, de graca. A medicao deixa de ser rotina com dono humano e vira subproduto do ciclo.
  //
  // O MEDIDOR CONTINUA SENDO O `cowork-mirror-freshness` — aqui so entrego a lista.
  //
  // ⚠️ O DENOMINADOR MUDA, e isso e esperado: o `list_files` conta DIRETORIOS, a arvore nao
  // (876 x 808 no ciclo de 10/09). O `--sla-live-only` ja recusa comparar escopos diferentes e
  // vai dizer "denominador mudou" na 1a rodada por esta rota. E o comportamento certo — inventar
  // entradas de diretorio pra casar o numero seria fabricar o denominador.
  const listaPath = join(destino, '_live-only.json');
  writeFileSync(listaPath, JSON.stringify({ paths: listarRelativos(raiz) }));
  // Ledger so no --apply: medicao de run exploratorio nao vira registro.
  const lo = roda('scripts/governance/cowork-mirror-freshness.mjs',
    ['--live-only', listaPath, ...(aplicar ? ['--ledger'] : [])]);
  const resumo = (lo.out.match(/\((\d+) de (\d+) paths\)/) || []);
  const telas = (lo.out.match(/prot[oó]tipo de tela \((\d+)\)/) || [])[1];
  console.log(`\n  [3c] LIVE-ONLY   ${resumo[1] ?? '?'} de ${resumo[2] ?? '?'} paths do export nunca desceram pro espelho`);
  if (telas !== undefined) {
    console.log(`                   destes, prototipo de TELA: ${telas}${telas === '0' ? ' (o resto e dotfile, interno do _ds e copia de repo)' : ' <- candidatos reais a versionar'}`);
  }
  if (!lo.ok) console.log(`                   ! o medidor saiu != 0 - nao medi, e isso NAO e "zero live-only"`);
  else if (aplicar) console.log(`                   registrado no ledger de frescor`);

  // 4. RECONCILIAR O DS (regra, não inferência)
  let reconciliados = 0;
  for (const f of ativo.files) {
    if (roleForPath(f.path) !== 'preview-cache') continue;
    const autoritativo = espelho(f.path);
    const naZip = naArvore(f.path);
    if (autoritativo === null || naZip === null || sha(autoritativo) === sha(naZip)) continue;
    writeFileSync(join(raiz, ...f.path.split('/')), autoritativo);
    reconciliados++;
    console.log(`\n  [4] DS           ${f.path}`);
    console.log(`                   zip ${naZip.length} B -> espelho ${autoritativo.length} B (dono = projeto Design System, #7096)`);
  }
  if (!reconciliados) console.log(`\n  [4] DS           nada a reconciliar - o _ds/ do zip ja bate com o espelho`);

  // 5. REGERAR pelo gerador CANÔNICO
  const outSync = join(destino, '_sync-regerado');
  const g = roda('scripts/design-sync/gerar-payload-partes.mjs', ['--root', raiz, '--out', outSync, '--previous', ATIVO]);
  if (!g.ok) { console.error(g.out); morre('o gerador canonico falhou'); }
  console.log(`\n  [5] REGERAR      ${((g.out.match(/BUNDLE v2: \w+/) || [''])[0] || '').trim()}`);
  console.log(`                   ${((g.out.match(/DELTA:.*/) || [''])[0] || '').trim()}`);
  if (/BLOQUEADO/.test(g.out)) { console.error(g.out); morre('o gerador emitiu manifesto BLOQUEADO (missing) - o grafo do shell nao fecha'); }

  const partes = readdirSync(outSync).filter((f) => f.startsWith('payload.part')).sort().map((f) => join(outSync, f));
  if (!partes.length) morre('o gerador nao emitiu partes');

  // 6. VALIDAR
  const d = roda('scripts/design-sync/aplicar-payload.mjs', [...partes, '--dry', '--require-complete-shell']);
  console.log(`\n  [6] VALIDAR      ${d.ok ? 'dry-run VALIDADO' : 'REPROVADO'}`);
  if (!d.ok) { console.error(d.out); morre('o aplicador recusou o lote no dry-run'); }

  // 7. APLICAR
  if (!aplicar) {
    console.log(`\n  [7] APLICAR      nao pedido - rode de novo com --apply pra promover.\n`);
    return;
  }
  if (regressoes.length && !tem('--permitir-regressao')) {
    morre(`recuso promover: ${regressoes.length} arquivo(s) voltariam a uma versao anterior (ver [3b]).\n`
      + `    Se for deliberado, repita com --permitir-regressao.`);
  }
  const a = roda('scripts/design-sync/aplicar-payload.mjs', [...partes, '--require-complete-shell']);
  if (!a.ok) { console.error(a.out); morre('o aplicador falhou na promocao (transacao atomica: nada mudou)'); }
  console.log(`\n  [7] APLICAR      PROMOVIDO ATOMICAMENTE`);
  console.log(`${a.out.split('\n').filter((l) => /id:|transporte:/.test(l)).join('\n')}\n`);
}

// Só executa quando chamado DIRETO: o `.test.mjs` importa `acharRaiz`/`auditarPacote`/
// `classificar` daqui, e sem esta guarda o import dispararia `principal()` no meio do teste.
const chamadoDireto = process.argv[1] && process.argv[1].replace(/\\/g, '/').endsWith('receber-handoff.mjs');
if (chamadoDireto) {
  if (tem('--selftest')) {
    const { selftest } = await import('./receber-handoff.test.mjs');
    await selftest();
  } else {
    principal();
  }
}
