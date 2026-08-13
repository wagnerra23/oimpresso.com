#!/usr/bin/env node
// protocolo.config.mjs — FONTE ÚNICA EXECUTÁVEL do protocolo de aplicação de protótipo (skill
// `aplicar-prototipo`). O "painel" do RUNBOOK: IDs, paths fixos e mapa fase→comando num só lugar.
//
// PROBLEMA que resolve (Wagner 2026-07-09: "o que mais precisa ficar procurando? tudo tem que
// estar documentado para o processo"): os IDs dos 2 projetos Cowork, o path do staging fixo e o
// mapa fase→comando viviam só em PROSA (INDEX §0.2, ADR 0325, RUNBOOK de 190 linhas, runbooks).
// O agente RECONSTRUÍA isso lendo 5 docs — cada leitura = um "procura" = risco de pular/errar.
// A confusão entre os 2 IDs de nome parecido já mordeu 3× (INDEX §0.2, 2026-07-06). Aqui vira
// CONSTANTE nomeada + mapa executável + selftest que trava drift: "o protocolo sabe" deixa de
// depender de eu ter lido o INDEX.
//
// NÃO reimplementa nada — re-exporta os motores canônicos (normalize/contentHash do
// cowork-mirror-freshness · resolveAncora do ancora). Só CONSOLIDA o que estava espalhado.
//
// USO:
//   node prototipo-ui/protocolo.config.mjs            # imprime o painel (IDs, paths, fases)
//   node prototipo-ui/protocolo.config.mjs --json     # idem, JSON
//   node prototipo-ui/protocolo.config.mjs --selftest # trava se ID/path/script sumir (CI)
//
// IMPORT (scripts + o próprio agente):
//   import { COWORK_PROJECT_ID, STAGING_DIR, FASES } from './protocolo.config.mjs'
//
// Refs: ADR 0325 (pull direto) · ADR 0324 (identidade normalizada) · INDEX-DESIGN-MEMORIAS §0.2 ·
//       prototipo-ui/RUNBOOK-aplicar-prototipo-orquestracao.md (as 7 fases −1..5).

import { existsSync, readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { join, dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { homedir } from 'node:os';

// binding local + re-export (re-export puro não cria binding usável no selftest deste módulo)
import { normalize, contentHash } from '../scripts/governance/cowork-mirror-freshness.mjs';
import { resolveAncora } from './ancora.mjs';
export { normalize, contentHash, resolveAncora };

const HERE = dirname(fileURLToPath(import.meta.url));
const REPO_ROOT = resolve(HERE, '..');

// ── PROJETOS Cowork (ADR 0325 · INDEX §0.2) ─────────────────────────────────────
// Os DOIS têm nome parecido — NÃO confundir (mordeu 3× em 2026-07-06). E ATENÇÃO: só o DS é
// listado/writable em `DesignSync.list_projects`; o Cowork (as TELAS) alcança-se por ID EXPLÍCITO
// — nunca "descobrir" por lista. Por isso o ID tem que viver aqui, não na memória do agente.
export const COWORK_PROJECT_ID = '019dcfd3-6ef2-7ee6-8512-b1b0e5544e58';        // "Oimpresso ERP Comunicação Visual" — FONTE DAS TELAS (*-page.jsx, 1337 arq)
export const DESIGN_SYSTEM_PROJECT_ID = '019dd02f-d2d0-7ba6-a57f-24b3ddd073ac'; // "Office Impresso — Design System" — biblioteca do DS (tokens/componentes)

// ── TELAS QUE VÊM DE OUTRA CONTA DE DESIGN ([W] 2026-08-13) ─────────────────────
// Fato de dono, não heurística: **Venda e Produto originam-se de OUTRA CONTA** do design
// (telas do Luiz e da Maiara). Não é "outro projeto dentro da minha conta" — é outra conta,
// logo o `DesignSync` daqui NUNCA vai listá-las e o espelho `prototipo-ui/cowork/` NUNCA
// vai contê-las. Elas estão CORRETAS assim.
// CONSEQUÊNCIA PRA QUEM MEDE: pra estas telas, "não achei no espelho / no DesignSync" é
// ORIGEM EXTERNA, não drift nem fonte faltando. Máquina que as tratar como ausência gera
// FP permanente — e a resposta certa a "cadê a fonte de design da Venda?" é "outra conta",
// não "gerar do DS canon". Consulte esta constante em vez de reinferir pelo nome do arquivo.
export const FORA_DESTA_CONTA = [
  { tela: 'Sells (Venda)',   arquivos: ['resources/css/venda-v3.css', 'resources/js/Pages/Sells/CreateV3.tsx'], quem: '[L]/[M]', declaradoPor: '[W]', em: '2026-08-13' },
  { tela: 'Produto',         arquivos: ['resources/js/Pages/Produto/'],                                          quem: '[L]/[M]', declaradoPor: '[W]', em: '2026-08-13' },
];

export const PROJETOS = {
  cowork:       { id: COWORK_PROJECT_ID,        nome: 'Oimpresso ERP Comunicação Visual', papel: 'telas',  listado: false },
  designSystem: { id: DESIGN_SYSTEM_PROJECT_ID, nome: 'Office Impresso — Design System',   papel: 'ds',     listado: true  },
};

// ── PATHS FIXOS (as âncoras do protocolo dependem destes — RUNBOOK Fase −1) ─────
// MIRROR_DIR: SSOT do design no repo, build-only (R1 do cowork-ssot-guard rejeita .md aqui).
//
// ⚠️ STAGING_DIR é LEGADO do caminho ZIP ([W] 2026-08-13: "não existe mais zip"). Continua
// exportado porque `render-proto-baseline.mjs` ainda o usa como default (com `args.staging ||`
// na frente, então quem passa o path explícito não depende dele) — remover a constante hoje
// quebraria esse consumidor sem ganho. Mas NÃO é destino de nada novo:
//   · a catraca `ancora-guard` já lista `_cowork-handoff-staging` e `Downloads/` como LUGAR
//     PROIBIDO pra âncora ([W] 2026-07-01: "não pode trocar de lugar nunca");
//   · o destino do design versionado é MIRROR_DIR, e o shell mora lá desde 2026-08-13.
// Ler design de dentro do STAGING_DIR é reabrir a doença: o pacote de lá estava congelado em
// 01/jul e conhecia 103 deps quando o vivo já tinha 120.
export const STAGING_DIR = join(homedir(), 'Downloads', '_cowork-handoff-staging');
export const MIRROR_DIR  = join(REPO_ROOT, 'prototipo-ui', 'cowork');

// ── PRÉ-FLIGHT da Fase 4 — os gates que a tela nova zera ANTES do PR ────────────
// ("funciona no staging ≠ passa no portão": incidente perfil 2026-06-24 tripou 6 gates no PR).
export const PREFLIGHT_GATES = [
  'node scripts/layout-primitives-guard.mjs',
  'node scripts/casos-coverage-guard.mjs',
  'npm run lint:baseline:check',
  'node_modules/.bin/tsc --noEmit',
  'node prototipo-ui/ds-guard.mjs <arquivos-tocados>',
  'node scripts/governance/cowork-ssot-guard.mjs',
];

// ── MAPA FASE → comando(s) reais (o "painel" executável do RUNBOOK) ─────────────
export const FASES = [
  // ⚠️ FASE −1 RECONCILIADA ([W] 2026-08-13: "não existe mais zip, é direto o protocolo").
  // Este painel se declara "fonte única" e listava TRÊS caminhos como se fossem alternativas
  // vivas. Não são: o ZIP é o caminho MORTO. Estado medido no dia da reconciliação:
  //   · `importar-bundle.mjs` — invocado em CI SÓ como `--selftest`; nenhum import real.
  //   · `check-handoff.ps1 -Zip` — idem, sem invocador de produção.
  //   · a skill `aplicar-prototipo` AINDA manda usar `importar-bundle.mjs "<zip>"` (dívida
  //     conhecida, fica anotada aqui em vez de sumir).
  // O canônico é o pull direto (ADR 0325) escrito por `--export-from` (ADR 0374, ratificada
  // 2026-08-13). Os comandos de ZIP saem da lista de FASES — deletar os SCRIPTS é poda de
  // capacidade, decisão [W], e não se faz de lado dentro de uma reconciliação de redação.
  { fase: '-1', nome: 'Importar/baixar o design', comandos: [
      'DesignSync.get_file(projectId=COWORK_PROJECT_ID, path=<âncora>)                  # pull direto, agente logado (ADR 0325)',
      'node scripts/governance/cowork-mirror-freshness.mjs --export-from <dir-jsons>     # escreve o raw.content no espelho (ADR 0374 — transcrever à mão é PROIBIDO)',
      'node scripts/governance/cowork-mirror-freshness.mjs --snapshot-from <dir> --emit-snapshot <s>  # MEDIR sem consertar (antes do export)',
    ], selftest: 'node prototipo-ui/handoff-changed.mjs --selftest' },
  { fase: '0/0.5', nome: 'Detectar + manifesto', comandos: [
      'node prototipo-ui/detectar-telas.mjs --staging <dir> --json --strict',
    ], selftest: 'node prototipo-ui/detectar-telas.mjs --selftest' },
  { fase: '1', nome: 'Mapear / comparar', comandos: [
      'node prototipo-ui/ancora.mjs <Mod/Tela>',
      'node prototipo-ui/style-fingerprint.mjs --compare proto.json prod.json --tela <Mod/Tela>',
      'node prototipo-ui/design-diff.mjs --compare prod.json design.json --check',
      'node prototipo-ui/gerar-map.mjs <gap.md>   # esqueleto do <tela>.map.json (ponte design↔código persistente)',
    ], selftest: 'node prototipo-ui/style-fingerprint.mjs --selftest' },
  { fase: '3/4', nome: 'Registrar + aplicar região', comandos: [
      'node prototipo-ui/consumir-map.mjs <Mod/Tela>   # portão de frescor (aborta se o protótipo re-exportou) + plano de leitura: a sessão abre SÓ os ranges do map',
      'node prototipo-ui/gerar-contrato.mjs <gap.md>',
      'node scripts/contrato-de-tela.mjs --contract <c.json> --contract-alvo <Pages/...>',
      'node prototipo-ui/recortar-regiao.mjs --contract <c.json> --bboxes <b.json> --png <shot.png> --out <dir>',
    ], selftest: 'node prototipo-ui/gerar-contrato.mjs --selftest' },
  { fase: '4-preflight', nome: 'Gates antes do PR', comandos: PREFLIGHT_GATES },
  { fase: '5', nome: 'Fechar o loop', comandos: [
      'node scripts/governance/anchor-lint.mjs --check memory/requisitos/<Mod>/SPEC.md',
      'node scripts/governance/design-code-map-check.mjs --check --strict   # % telas mapeadas + invalida map.json com sha stale',
    ], selftest: 'node prototipo-ui/integrity-check.mjs' },
];

// ── selftest hermético (trava drift — vai pro design-memory-gate no CI) ─────────
const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/;
function scriptsReferenciados() {
  const cmds = [...PREFLIGHT_GATES, ...FASES.flatMap((f) => [...(f.comandos || []), f.selftest].filter(Boolean))];
  const out = new Set();
  for (const c of cmds) {
    const m = c.match(/(?:node|pwsh)\s+([^\s"']+\.(?:mjs|ps1))/);
    if (m) out.add(m[1]);
  }
  return [...out];
}
// ── CONSISTÊNCIA DOS IDs (2026-08-13) ───────────────────────────────────────────
// Este arquivo se declara "a fonte" dos 2 IDs, e o selftest provava que eles são UUID
// e distintos — mas NÃO que o resto do repo concorda com eles. Dois consumidores
// dependem disso na prática:
//   · o SHELL do espelho (`prototipo-ui/cowork/oimpresso.com.html`) linka
//     `_ds/<DESIGN_SYSTEM_PROJECT_ID>/…`, e o `--preview-ds` do cowork-mirror-freshness
//     DERIVA o id dali pra repor o DS. Se o shell trouxer outro id (troca de design
//     system upstream), o preview repõe no diretório errado e a tela abre sem tokens
//     — exatamente o "falta css" de 2026-08-13, só que silencioso.
//   · o `ds-push.mjs` carrega o id hardcoded pra ESCREVER no projeto; divergir daqui
//     é empurrar tokens pro DS errado.
//   · o hook `design-agente-ativa` manda o agente consultar o Cowork vivo por ID.
// Varre só CÓDIGO EXECUTÁVEL: doc/handoff/ADR citam id por CONTEXTO HISTÓRICO
// (o id que valia naquela data) e não devem ser corrigidos — §5 "registro datado".
//
// FP MEDIDO ANTES DE FECHAR O VETOR (2026-08-13, e o número reprovou a MINHA lista):
// a lista nasceu com 5 alvos, incluindo `venda-v3.css` e `Sells/CreateV3.tsx` sob a
// justificativa "código de produção carrega o id hardcoded". FALSO em duas camadas:
//   forma  — medido: citam o PREFIXO abreviado (`019dd02f`, 8 chars) em COMENTÁRIO de
//            proveniência, não o UUID; `git grep` do id completo não os lista;
//   fundo  — [W] 2026-08-13: essas duas telas são do **Luiz e da Maiara** e têm **fonte
//            de design diferente** desta — e estão CORRETAS. Não são consumidoras deste
//            DS, então cobrá-las pelo id daqui é falso-positivo POR CONSTRUÇÃO, não por
//            detalhe de regex. Nenhuma variante de casamento conserta isso.
// Com elas, o vetor "alvo parou de citar o id" acusava 2/5. Sem elas, 0/3. Os 3 que
// ficam consomem o id de verdade (o shell é de onde o `--preview-ds` DERIVA o caminho).
// Corolário pra quem for ampliar esta lista: alvo entra por CONSUMIR o id, e o teste é
// "de qual projeto de design esta tela vive?" — não "o id aparece no arquivo?".
// ⚠️ O `continue` silencioso mordeu AQUI (adversário 2026-08-13): "arquivo sumiu" e
// "arquivo perdeu o id" eram indistinguíveis de "conferido e OK" — 3 dos 4 vetores de
// drift escapavam. A regra agora é a do §5 2026-07-29: o instrumento **não colapsa
// "não consegui medir" num estado do objeto medido**. Alvo RASTREADO NO GIT que sumiu
// ou perdeu o id é FALHA (o repo é a fonte, e ele diz que o arquivo deveria estar lá);
// alvo não-rastreado (checkout parcial, artefato local) é PULADO e sai na contagem.
/** git ls-files nos alvos → Set de rastreados, ou null se não deu pra medir (sem git). */
function rastreadosNoGit(paths) {
  try {
    const out = execFileSync('git', ['ls-files', '-z', '--', ...paths], {
      cwd: REPO_ROOT, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'],
    });
    return new Set(out.split('\0').filter(Boolean).map((p) => resolve(REPO_ROOT, p)));
  } catch { return null; }
}

/** Arquivos de código que carregam algum dos 2 IDs, e qual esperamos. */
function conferirIdsNoRepo() {
  const alvos = [
    { path: join(MIRROR_DIR, 'oimpresso.com.html'), espera: DESIGN_SYSTEM_PROJECT_ID, papel: 'shell do espelho (de onde --preview-ds deriva)' },
    { path: join(REPO_ROOT, 'scripts', 'design-sync', 'ds-push.mjs'), espera: DESIGN_SYSTEM_PROJECT_ID, papel: 'push do DS' },
    { path: join(REPO_ROOT, '.claude', 'hooks', 'design-agente-ativa.mjs'), espera: COWORK_PROJECT_ID, papel: 'hook que manda o agente consultar o vivo' },
  ];
  const problemas = [];
  const rastreados = rastreadosNoGit(alvos.map((a) => a.path));
  let medidos = 0;
  const pulados = [];
  for (const a of alvos) {
    const rel = a.path.replace(REPO_ROOT, '.');
    // rastreados === null ⇒ sem git: não dá pra separar "sumiu" de "nunca existiu".
    // Não invento falha NEM verde — o alvo sai como não-medido e a contagem denuncia.
    const eRastreado = rastreados === null ? null : rastreados.has(resolve(a.path));
    if (!existsSync(a.path)) {
      if (eRastreado === true) problemas.push(`${a.papel}: alvo RASTREADO no git sumiu do checkout (${rel})`);
      else pulados.push(rel);
      continue;
    }
    const txt = readFileSync(a.path, 'utf8');
    const achados = [...new Set([...txt.matchAll(/\b(019d[0-9a-f]{4}-[0-9a-f-]{20,})\b/g)].map((m) => m[1]))];
    if (!achados.length) {
      // Alvo rastreado que PAROU de citar o id é drift: o consumidor virou órfão do
      // projeto Cowork sem ninguém notar. Só é "nada a conferir" se não for rastreado.
      if (eRastreado === true) problemas.push(`${a.papel}: alvo RASTREADO no git não cita mais nenhum ID de projeto (${rel})`);
      else pulados.push(rel);
      continue;
    }
    medidos++;
    if (!achados.includes(a.espera)) {
      problemas.push(`${a.papel}: esperava ${a.espera.slice(0, 8)}…, achei ${achados.map((x) => x.slice(0, 8) + '…').join(', ')} (${rel})`);
    }
  }
  return { problemas, medidos, total: alvos.length, pulados, semGit: rastreados === null };
}

function selftest() {
  const fails = [];
  if (!UUID.test(COWORK_PROJECT_ID)) fails.push('COWORK_PROJECT_ID não é UUID');
  if (!UUID.test(DESIGN_SYSTEM_PROJECT_ID)) fails.push('DESIGN_SYSTEM_PROJECT_ID não é UUID');
  if (COWORK_PROJECT_ID === DESIGN_SYSTEM_PROJECT_ID) fails.push('os 2 IDs colidiram (anti-confusão dos projetos)');
  if (!existsSync(MIRROR_DIR)) fails.push(`MIRROR_DIR ausente no repo: ${MIRROR_DIR}`);
  const ids = conferirIdsNoRepo();
  fails.push(...ids.problemas);
  for (const fn of [['normalize', normalize], ['contentHash', contentHash], ['resolveAncora', resolveAncora]]) {
    if (typeof fn[1] !== 'function') fails.push(`motor re-exportado quebrou: ${fn[0]} não é função`);
  }
  if (typeof contentHash === 'function' && contentHash('abc') !== contentHash('abc')) {
    fails.push('contentHash não-determinístico');
  }
  const scripts = scriptsReferenciados();
  for (const s of scripts) {
    if (!existsSync(join(REPO_ROOT, s))) fails.push(`script referenciado no mapa FASES não existe: ${s}`);
  }
  if (fails.length) { console.error('SELFTEST FALHOU:\n - ' + fails.join('\n - ')); process.exit(1); }
  // A contagem é o antídoto do "verde mudo": se `medidos` < `total`, o OK acima vale
  // só pelos medidos — quem lê sabe quantos ficaram de fora e por quê (§5 2026-07-29).
  const cob = `conferi ${ids.medidos} de ${ids.total} alvos de ID`
    + (ids.pulados.length ? ` (${ids.pulados.length} pulado(s), não-rastreado(s): ${ids.pulados.join(', ')})` : '')
    + (ids.semGit ? ' ⚠ sem git: não deu pra separar "sumiu" de "nunca existiu"' : '');
  console.log(`✓ protocolo.config selftest OK — 2 IDs válidos+distintos · ${cob} · MIRROR_DIR presente · ${scripts.length} scripts do mapa existem · motores (normalize/contentHash/resolveAncora) vivos.`);
  process.exit(0);
}

function painel() {
  console.log('PROTOCOLO DE APLICAÇÃO DE PROTÓTIPO — fonte única (protocolo.config.mjs)\n');
  console.log('PROJETOS Cowork (ADR 0325 · só por ID — NÃO confundir):');
  console.log(`  telas   ${COWORK_PROJECT_ID}  "${PROJETOS.cowork.nome}"  [não-listado, por ID]`);
  console.log(`  ds      ${DESIGN_SYSTEM_PROJECT_ID}  "${PROJETOS.designSystem.nome}"  [listado]`);
  console.log(`\nPATHS FIXOS:\n  STAGING_DIR  ${STAGING_DIR}\n  MIRROR_DIR   ${MIRROR_DIR}`);
  console.log('\nFASES (fase → comando real):');
  for (const f of FASES) {
    console.log(`  [${f.fase}] ${f.nome}`);
    for (const c of f.comandos) console.log(`      ${c}`);
    if (f.selftest) console.log(`      selftest: ${f.selftest}`);
  }
}

const invokedDirectly = process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (invokedDirectly) {
  const argv = process.argv.slice(2);
  if (argv.includes('--selftest')) selftest();
  else if (argv.includes('--json')) {
    console.log(JSON.stringify({ projetos: PROJETOS, stagingDir: STAGING_DIR, mirrorDir: MIRROR_DIR, fases: FASES, preflightGates: PREFLIGHT_GATES }, null, 2));
  } else painel();
}
