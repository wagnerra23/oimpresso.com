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
 *   node scripts/design-sync/receber-handoff.mjs --zip <arquivo.zip> --conta w    # declara a origem
 *                                                                                 # (exigido quando o
 *                                                                                 # PASSO 0 da indeterminado)
 *   node scripts/design-sync/receber-handoff.mjs --selftest
 *
 * Exit: 0 = ok · 1 = insumo/validação reprovou (inclui PASSO 0 não liberado) · 2 = erro de uso.
 */
import { readFileSync, existsSync, mkdtempSync, mkdirSync, writeFileSync, readdirSync, rmSync } from 'node:fs';
import { join, dirname, relative, resolve } from 'node:path';
import { tmpdir } from 'node:os';
import { createHash } from 'node:crypto';
import { execFileSync, spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { extrairZip } from './zip-reader.mjs';
import { roleForPath, validateManifest } from './bundle-contract.mjs';
import { dsRuntimeRelPath } from '../governance/cowork-mirror-freshness.mjs';
// PASSO 0 do painel. O dono da pergunta "de quem e este handoff" e o protocolo.config:
// importo a funcao dele em vez de reimplementar (LC-19 — maquina paralela ao dono).
import { deQuemEhOHandoff, CONTAS, PROJETOS } from '../design/protocolo.config.mjs';

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

/** Path do repo onde um path LÓGICO do bundle pousaria. Mesma regra de `espelho()` no passo [3]
 *  e de `targetForLogical` na transação — extraída daqui pra poder ser testada sem fs. */
export function pathNoEspelho(rel, papel = roleForPath(rel)) {
  if (papel === 'preview-cache') return `prototipo-ui/design-system/${dsRuntimeRelPath(rel)}`;
  const base = papel === 'design-doc'
    ? 'prototipo-ui/cowork/Wagner/handoffs'
    : 'prototipo-ui/cowork/Wagner';
  return `${base}/${rel}`;
}

/** Paths do export que o `.gitignore` do REPO exclui — logo NÃO PODEM existir no espelho.
 *
 *  POR QUE EXISTE (medido 2026-09-17): o `.gitignore` raiz ganhou `prototipo-ui/cowork/**\/*.png`
 *  em 15/09 (#7314, "só fontes no espelho"). O gerador continuou declarando os 4
 *  `inbox-photo-c*.png` no manifesto; a promoção os escrevia no disco (`writeFileSync` não
 *  consulta `.gitignore`) e o git nunca os versionava. Em qualquer checkout limpo eles somem —
 *  e aí `bundle-transaction` exige o estado-alvo completo e RECUSA o lote inteiro
 *  ("estado-alvo ausente no staging: inbox-photo-c1.png"), enquanto `--compare-bundle` os
 *  reporta STALE pra sempre. Duas máquinas vermelhas por um estado que o repo proíbe.
 *
 *  O filtro é `git check-ignore` — a REGRA JÁ ESCRITA do repo — exatamente como o `ABSENT-LOCAL`
 *  do `cowork-mirror-freshness` já faz ("⬜ ignorados por design"), e não uma denylist de nome
 *  inventada aqui (família banida: allowlist-de-pasta · guard `@scope`).
 *
 *  `preview-cache` (`_ds/**`) fica FORA deste filtro de propósito: ele é gitignored também, mas
 *  já tem dono e tratamento próprios (passo [4], projeto DS #7096) e o gerador já o exclui.
 *
 *  FP medido no bundle ativo (709 arquivos): 11 gitignored = 7 `_ds/**` (excluídos por role) +
 *  os 4 PNGs. Zero colateral.
 *
 *  ⚠️ Isto NÃO traz os arquivos de volta — declara honestamente que eles não descem. Se o desejo
 *  for versioná-los, o caminho é mudar o `.gitignore` (decisão [W], reabre o #7314).
 */
export function ignoradosPeloRepo(rels, checkIgnore = checkIgnoreGit) {
  const candidatos = rels.filter((rel) => {
    // `roleForPath` LANÇA pro que está fora do contrato build-only (`.gitignore`, `.thumbnail`,
    // screenshots…). Esses o gerador já descarta, então não chegam ao manifesto e não é comigo.
    let papel;
    try { papel = roleForPath(rel); } catch { return false; }
    return papel !== 'preview-cache';
  });
  if (!candidatos.length) return new Set();
  const porPath = new Map(candidatos.map((rel) => [pathNoEspelho(rel), rel]));
  const ignorados = checkIgnore([...porPath.keys()]);
  return new Set(ignorados.map((p) => porPath.get(p)).filter(Boolean));
}

/** Injetor default: `git check-ignore --stdin`. Sai 1 quando NADA casa — isso é resposta, não
 *  falha (§5 2026-07-31: vazio de comando que pode falhar ≠ ausência). Qualquer outro rc é erro
 *  de execução e propaga, pra não virar "0 ignorados" silencioso. */
function checkIgnoreGit(paths) {
  try {
    const out = execFileSync('git', ['check-ignore', '--stdin'], {
      cwd: REPO, input: paths.join('\n'), encoding: 'utf8',
    });
    return out.split('\n').map((s) => s.trim()).filter(Boolean);
  } catch (e) {
    if (e.status === 1) return [];            // nenhum path ignorado
    throw new Error(`git check-ignore falhou (rc=${e.status}): ${e.stderr || e.message}`);
  }
}

/** PASSO 0 — DE QUEM É, decidido pra ESTE destino de escrita.
 *
 *  POR QUE ISTO EXISTE (medido 2026-09-14): o painel manda rodar `--de-quem` ANTES de qualquer
 *  importação e diz "fail-closed: só `vinculada` libera". Mas a rota ZIP **não chamava o passo**
 *  — `grep -niE "de-quem|procedencia"` neste arquivo devolvia ZERO. Então o passo obrigatório
 *  era obrigatório só pra quem importava à mão.
 *
 *  E o buraco não era cosmético: o `espelho()` abaixo escreve em `prototipo-ui/cowork/Wagner`
 *  HARDCODED. Um handoff exportado de outra conta entrava por aqui e pousava no espelho do
 *  Wagner — que é exatamente o "espelho ganha arquivo órfão" que o `--de-quem` foi escrito pra
 *  impedir. A conta do [F] tem `espelhada: false` e projeto nenhum em PROJETOS: não há espelho
 *  pra receber, logo não há importação possível por esta rota.
 *
 *  O 3º VEREDITO É INALCANÇÁVEL DE OUTRO JEITO, e é por isso que `--conta` existe: o
 *  `deQuemEhOHandoff` procura o UUID do projeto num PATH, e o zip do Cowork nomeia a raiz pelo
 *  SLUG (`oimpresso-erp-conunica-o-visual`), nunca pelo id. Medido no handoff 19: o id do projeto
 *  de telas aparece **0 vezes** em 816 arquivos; só o id do DS, e só dentro do cache `_ds/` — que
 *  é justamente o caso que o `deQuemEhOHandoff` exclui de propósito, e está certo em excluir
 *  (cache do DS diz o que o material CONSOME, não de quem ele é). Consequência: TODO zip do
 *  projeto de telas cai em `indeterminado`, por construção. Sem uma saída declarada, o passo
 *  barraria 100% da rota que [W] pediu em 2026-09-10 ("exportar uma única vez").
 *
 *  A saída NÃO é inferência. Não deduzo do slug (nome não é id — e decidir por nome é a família
 *  de guard que o §5 das proibições já enterrou várias vezes) nem da sobreposição com o espelho
 *  (o próprio `deQuemEhOHandoff` declara a camada 2 "fraca por construção": export delta dá
 *  sobreposição ~0 sendo legítimo). A saída é o humano DECLARAR a conta, que é literalmente o que
 *  o painel prescreve pra este veredito: "resolva perguntando a quem exportou". A sobreposição
 *  entra no relatório como CORROBORAÇÃO pro olho humano, nunca como veredito.
 *
 *  Puro de propósito: o `--selftest` exercita ESTA função, a mesma que o pipeline chama — não uma
 *  cópia paralela (§5 2026-08-14: selftest que roda cópia fica verde enquanto o pipeline regride).
 *
 *  @returns {{ok: boolean, conta: string|null, motivo: string, exigeDeclaracao: boolean}}
 */
export function decidirDono(dono, contaDeclarada, contas = CONTAS, projetos = PROJETOS) {
  const contasComEspelho = Object.values(projetos).filter((p) => p.espelho).map((p) => p.conta);
  const aceita = [...new Set(contasComEspelho)];

  if (dono.veredito === 'vinculada') {
    return { ok: true, conta: dono.conta, motivo: dono.porque, exigeDeclaracao: false };
  }

  if (dono.veredito === 'nao-vinculada') {
    // Sem escape: o painel manda registrar a conta em CONTAS + PROJETOS primeiro. `--conta` aqui
    // carimbaria material de origem desconhecida com uma conta conhecida — o oposto do passo.
    return {
      ok: false, conta: null, exigeDeclaracao: false,
      motivo: `${dono.porque} — registre a conta em CONTAS + PROJETOS (com a chave espelho) antes de importar; --conta NAO vale aqui`,
    };
  }

  // indeterminado — o único veredito que uma declaração resolve.
  if (!contaDeclarada) {
    return {
      ok: false, conta: null, exigeDeclaracao: true,
      motivo: `${dono.porque} — declare a conta de origem com --conta <${aceita.join('|')}>`,
    };
  }
  if (!contas[contaDeclarada]) {
    return { ok: false, conta: null, exigeDeclaracao: true, motivo: `conta "${contaDeclarada}" nao esta em CONTAS` };
  }
  if (!aceita.includes(contaDeclarada)) {
    const c = contas[contaDeclarada];
    return {
      ok: false, conta: null, exigeDeclaracao: true,
      motivo: `conta "${contaDeclarada}" (${c.dono}) nao tem espelho no repo — esta rota escreve em `
        + `prototipo-ui/cowork/Wagner, entao importar aqui criaria arquivo orfao no espelho de outro dono`,
    };
  }
  return {
    ok: true, conta: contaDeclarada, exigeDeclaracao: true,
    motivo: `declarado --conta ${contaDeclarada} (${contas[contaDeclarada].dono}); o material so embutia o cache do DS`,
  };
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
 * O git responde uma coisa só: aquele conteúdo JÁ ESTEVE versionado neste caminho. Sem isto,
 * importar um handoff antigo reverte o espelho em silêncio, e o dry-run aprova (é um delta
 * legítimo — só que pro lado errado). Era o furo da receita manual.
 *
 * ⚠️ O QUE ISTO NÃO DECIDE (ADR 0404/0406 D3): o mesmo sinal sai de DUAS causas opostas —
 * (a) o ZIP é um export velho (replay), e aí não se aplica; (b) o espelho foi ENRIQUECIDO ou
 * editado deste lado depois do import, e aí o ZIP é o estado real da conta e PREVALECE. Foi o
 * caso do #7256, onde a fusão da ADR 0398 deixou o espelho maior que a origem. O desempate não
 * é o git — é a IDENTIDADE do pacote (bundleId/sequência/estado-base) contra o estado
 * ativo. Pacote posterior ao ativo ⇒ leia "alguém escreveu no espelho", não "o ZIP é velho".
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

  // 0. DE QUEM E — PASSO 0 do painel, fail-closed ANTES de medir ou escrever qualquer byte.
  //    Fica DEPOIS do [1] no numero porque a arvore precisa existir pra ser classificada, mas e o
  //    primeiro PORTAO: nada abaixo roda se ele nao liberar.
  const relDaRaiz = listarRelativos(raiz);
  const dono = deQuemEhOHandoff(relDaRaiz);
  const decisao = decidirDono(dono, arg('--conta'));
  console.log(`\n  [0] DE QUEM      ${dono.veredito}${decisao.conta ? ` - conta ${decisao.conta}` : ''}`);
  console.log(`                   ${decisao.motivo}`);
  // Corroboracao pro olho humano — quantos paths do zip JA existem em cada espelho registrado.
  // NAO e veredito, e o motivo esta no proprio deQuemEhOHandoff: a camada 2 e "fraca por
  // construcao" (um export DELTA traz so arquivo novo e da sobreposicao ~0 sendo legitimo).
  //
  // ⚠️ NAO uso o `dono.placar` aqui, e a razao e medida: ele casa PREFIXO DE PATH
  // (`x.startsWith('cowork/Wagner/')`), e os paths de um zip sao relativos a raiz do projeto
  // (`app.jsx`), nunca prefixados pelo espelho. Na 1a versao desta linha eu passei o placar
  // direto e ela NUNCA imprimia: `casam` era 0 em todos os projetos, sempre. Linha que nao pode
  // disparar e pior que linha ausente, porque parece cobertura.
  for (const [chave, p] of Object.entries(PROJETOS)) {
    if (!p.espelho) continue;
    const base = join(REPO, ...p.espelho.replace(/[/]$/, '').split('/'));
    const batem = relDaRaiz.filter((rel) => existsSync(join(base, ...rel.split('/')))).length;
    if (batem > 0) {
      const pct = ((batem / relDaRaiz.length) * 100).toFixed(1);
      console.log(`                   corrobora: ${batem}/${relDaRaiz.length} (${pct}%) path(s) do zip ja existem no espelho de ${chave}`);
    }
  }
  if (!decisao.ok) morre(`PASSO 0 nao liberou: ${decisao.motivo}`);

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
    console.log(`                   => DUAS leituras possiveis, e o git nao separa as duas (ADR 0406 D3):`);
    console.log(`                      (a) este ZIP e um export VELHO  -> aplicar reverte; nao aplique.`);
    console.log(`                      (b) o espelho foi EDITADO aqui  -> o ZIP e o estado real da conta e PREVALECE.`);
    console.log(`                      Desempate = identidade do pacote x estado ativo em scripts/design-sync/state/,`);
    console.log(`                      nunca o historico do repo. Pacote posterior ao ativo => caso (b).`);
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
  //
  // ⚠️ O DENOMINADOR É A ÁRVORE, não o bundle ativo (corrigido 2026-09-17). Iterando
  // `ativo.files`, todo `_ds/**` que está no ZIP e AINDA NÃO no bundle escapava da regra — e o
  // gerador o declarava logo abaixo com o conteúdo velho do ZIP. Medido no pacote 23: as fontes
  // `ibm-plex-sans-{500,600,700}` do `_ds/` eram live-only (nunca entraram no bundle), vinham as
  // três com 45.712 B (cópias byte-idênticas do 400 subset) e pousariam — via `dsRuntimeRelPath`
  // — SOBRE as 4 fontes distintas que o #7465 importou hoje, derrubando o lote no
  // `cowork-ssot-guard` R4 (500=600=700). Quem manda no `_ds/` é o projeto DS (#7096), e isso
  // vale pra todo path do papel, esteja ele no bundle ativo ou não.
  let reconciliados = 0;
  const previewCacheDaArvore = listarRelativos(raiz).filter((rel) => {
    // Dois donos podem recusar o path, e os dois vazam exceção: `roleForPath` (fora do contrato
    // build-only) e `dsRuntimeRelPath` (`_ds/**` que não é bundle/CSS/asset — ex. um `README.md`).
    // Sem destino de runtime não há autoritativo pra comparar, e o gerador também não o declara.
    try { return roleForPath(rel) === 'preview-cache' && Boolean(dsRuntimeRelPath(rel)); }
    catch { return false; }
  });
  for (const rel of previewCacheDaArvore) {
    const autoritativo = espelho(rel);
    const naZip = naArvore(rel);
    if (autoritativo === null || naZip === null || sha(autoritativo) === sha(naZip)) continue;
    writeFileSync(join(raiz, ...rel.split('/')), autoritativo);
    reconciliados++;
    console.log(`\n  [4] DS           ${rel}`);
    console.log(`                   zip ${naZip.length} B -> espelho ${autoritativo.length} B (dono = projeto Design System, #7096)`);
  }
  if (!reconciliados) console.log(`\n  [4] DS           nada a reconciliar - o _ds/ do zip ja bate com o espelho`);

  // 4b. IGNORADOS PELO REPO — some da ÁRVORE antes de gerar, pra o manifesto não declarar
  //     estado-alvo que o `.gitignore` proíbe (ver `ignoradosPeloRepo`). A árvore é o tmpdir
  //     efêmero da extração, não o espelho: nada do repo é tocado aqui.
  const ignorados = ignoradosPeloRepo(listarRelativos(raiz));
  if (ignorados.size) {
    console.log(`\n  [4b] IGNORADOS   ${ignorados.size} arquivo(s) fora por .gitignore do repo - nao podem existir no espelho`);
    for (const rel of [...ignorados].sort()) {
      rmSync(join(raiz, ...rel.split('/')), { force: true });
      console.log(`                   ${rel}  -> ${pathNoEspelho(rel)}`);
    }
    console.log(`                   nao e perda: o espelho ja nao os tinha. Versiona-los reabre o #7224/#7314 ([W]).`);
  } else {
    console.log(`\n  [4b] IGNORADOS   nada - todo path do export pode existir no espelho`);
  }

  // 5. REGERAR pelo gerador CANÔNICO
  const outSync = join(destino, '_sync-regerado');
  // `--owner` vem da conta que o PASSO 0 liberou — quem chama sabe de quem e o lote; o path da
  // arvore extraida (tmpdir) nao diz. Sem isto o gerador sai `owner: "project"` (medido).
  const donoDoLote = decisao.conta === 'felipe' ? 'Felipe' : 'Wagner';
  const g = roda('scripts/design-sync/gerar-payload-partes.mjs',
    ['--root', raiz, '--out', outSync, '--previous', ATIVO, '--full-tree', '--owner', donoDoLote]);
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
    // A recusa e fail-closed de proposito (o caso (a) do [3b] reverte o espelho em silencio),
    // mas o VEREDITO nao esta decidido aqui: o git nao separa "ZIP velho" de "espelho editado
    // deste lado". No caso (b) o ZIP e o estado real da conta e PREVALECE (ADR 0404/0406 D3) —
    // ai a flag NAO e "aceitar uma regressao", e sim aplicar o protocolo. O nome dela ficou do
    // tempo em que so o caso (a) era previsto.
    morre(`recuso promover sem decisao: ${regressoes.length} arquivo(s) ficariam com um conteudo que JA ESTEVE`
      + ` versionado (ver [3b]).\n`
      + `    Confira a identidade do pacote contra scripts/design-sync/state/ ANTES de decidir:\n`
      + `      · pacote ANTERIOR ao estado ativo  -> caso (a), ZIP velho: nao aplique.\n`
      + `      · pacote POSTERIOR ao estado ativo -> caso (b), espelho editado aqui: o pacote prevalece\n`
      + `        (ADR 0404) — repita com --permitir-regressao, que neste caso e o caminho CERTO.`);
  }
  const a = roda('scripts/design-sync/aplicar-payload.mjs', [...partes, '--require-complete-shell']);
  if (!a.ok) { console.error(a.out); morre('o aplicador falhou na promocao (transacao atomica: nada mudou)'); }
  console.log(`\n  [7] APLICAR      PROMOVIDO ATOMICAMENTE`);
  console.log(`${a.out.split('\n').filter((l) => /id:|transporte:/.test(l)).join('\n')}`);

  // 8. REGISTRAR A RODADA — sem isto o `--sla` segue lendo a ÚLTIMA rodada de `--compare`, que
  //    pode ser de semanas atrás, e reporta um denominador CONGELADO. Medido 2026-09-17: o
  //    espelho estava provado 701/701 pelo bundle e o `--sla` dizia "271 sync · 2 unchecked ·
  //    mediu 271/273" — números de 11/09, quando o manifesto tinha 273 paths. O `--compare-bundle
  //    --ledger` existia e ninguém o invocava; máquina que existe e ninguém chama é bug, não
  //    neutralidade (CLAUDE.md §LIGUE A MÁQUINA, item 2). A entrada é datada com o `generatedAt`
  //    do bundle, nunca com a hora da leitura — quem garante isso é o próprio `--ledger`, que
  //    RECUSA bundle sem `generatedAt` em vez de inventar frescor.
  const reg = roda('scripts/governance/cowork-mirror-freshness.mjs', ['--compare-bundle', '--ledger']);
  const linha = (reg.out.match(/✓ sync:.*/) || [''])[0].trim();
  console.log(`\n  [8] REGISTRAR    ${reg.ok ? linha || 'rodada registrada' : 'FALHOU - o --sla vai seguir lendo a rodada anterior'}`);
  if (!reg.ok) console.log(`                   ${reg.out.split('\n').filter(Boolean).slice(-1)[0] || ''}`);
  else console.log(`                   ledger de frescor atualizado - commite scripts/governance/.cowork-freshness-ledger.json\n`);
}

// Só executa quando chamado DIRETO: o `.test.mjs` importa `acharRaiz`/`auditarPacote`/
// `classificar` daqui, e sem esta guarda o import dispararia `principal()` no meio do teste.
const chamadoDireto = process.argv[1] && process.argv[1].replace(/\\/g, '/').endsWith('receber-handoff.mjs');
if (chamadoDireto) {
  if (tem('--selftest')) {
    // SUBPROCESSO, não `await import()`. O ciclo é real: este módulo importava o `.test.mjs`, que
    // importa `acharRaiz`/`classificar`/… daqui — e com TOP-LEVEL await isso é DEADLOCK ESM: o
    // teste espera este módulo terminar de avaliar, este módulo espera o teste carregar. O Node
    // não trava, desiste: "Detected unsettled top-level await", exit 13, ZERO asserts rodados.
    // Medido 2026-09-17 no checkout principal, em outra branch e sem nenhuma mudança: mesmo 13 —
    // ou seja, o `--selftest` anunciado no docblock nunca rodou por esta porta (LC-15: mecanismo
    // anuncia saída que não implementa). Invocado direto (`node receber-handoff.test.mjs`) o
    // ciclo não existe, e por isso a suíte sempre esteve verde — o defeito era só desta entrada.
    const r = spawnSync(process.execPath, [join(AQUI, 'receber-handoff.test.mjs')], { stdio: 'inherit' });
    // Sem `status` houve falha de EXECUÇÃO (spawn não saiu). Sair 1 nesse caso é o certo: exit 0
    // por não-execução é o `0 failed` de suíte que não rodou (LC-13) — o que este fix veio matar.
    process.exit(typeof r.status === 'number' ? r.status : 1);
  } else {
    principal();
  }
}
