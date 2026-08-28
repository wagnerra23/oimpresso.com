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
//       prototipo-ui/PROTOCOL.md (política; este arquivo é dono da execução).

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

// Estes arquivos são consumidores/ponteiros. Não podem voltar a carregar cópias dos comandos ou
// IDs deste painel: foi assim que o fluxo ZIP, o preview incompleto e a aplicação atual ficaram
// simultaneamente "canônicos". PROTOCOL.md é dono da política; este módulo é o único dono da
// execução.
const PONTEIROS_EXECUCAO = [
  'prototipo-ui/PROTOCOL.md',
  'prototipo-ui/PROTOCOL-F3-COWORK-CODE.md',
  'prototipo-ui/RUNBOOK-aplicar-prototipo-orquestracao.md',
  '.claude/skills/aplicar-prototipo/SKILL.md',
  '.claude/hooks/design-agente-ativa.mjs',
];

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

// ── DESTINO DA FONTE PUXADA DO **DESIGN SYSTEM** (projeto ≠ Cowork) ────────────
//
// BURACO QUE ISTO FECHA (medido 2026-08-18): o protocolo tinha destino pro Cowork
// (`MIRROR_DIR`, 200 arquivos versionados, 117 provados idênticos ao vivo) e NENHUM
// pro Design System. Consequência real: em 2026-08-17 o agente puxou
// `templates/pt-05-dashboard/Pt05Dashboard.dc.html` via `DesignSync.get_file` pra
// responder ao [W], leu o conteúdo NO CONTEXTO e não gravou byte nenhum —
// `git ls-files | grep -c pt-05-dashboard` = **0**. A fonte evaporou com a sessão, e
// a próxima teria que re-baixar pra responder a mesma pergunta.
//
// O `mirror-snapshot/` NÃO servia: o README dele declara escopo de UM arquivo — o
// `colors_and_type.css`, que existe pro sentinela `ds-mirror-drift` comparar TOKENS
// sem login no CI. Template não é token; enfiar ali confundiria os dois papéis.
//
// ⚠️ REGRA DE ESCRITA, e ela não é estilo — é a lápide de 2026-08-11: o conteúdo
// SAI DO DADO, POR SCRIPT (`get_file` → JSON → `writeFile`). NUNCA transcrito pelo
// contexto do agente. Foi transcrição que produziu o STALE daquele dia.
export const DS_MIRROR_DIR = join(REPO_ROOT, 'prototipo-ui', 'design-system');
// Artefatos COMPILADOS que o preview consome. É um papel diferente de DS_MIRROR_DIR
// (fonte/templates): `--ds-runtime` grava aqui e `--preview-ds` lê daqui, sem diretório órfão.
export const DS_RUNTIME_SNAPSHOT_DIR = join(REPO_ROOT, 'scripts', 'design-sync', 'mirror-snapshot');

// ── PRÉ-FLIGHT da Fase 4 — os gates que a tela nova zera ANTES do PR ────────────
// ("funciona no staging ≠ passa no portão": incidente perfil 2026-06-24 tripou 6 gates no PR).
//
// ── RECONCILIAÇÃO 2026-08-21: a lista cobria 2 dos 5 REQUIRED do próprio domínio ──
// Medido nesta data (união `classic_protection.contexts` ∪ `rulesets[].contexts` do
// governance/required-checks-baseline.json — ler só a clássica SUBCONTA, §5 2026-08-08):
// 46 required no repo, 5 no domínio design/espelho. Desses, o painel cobria `DS gate`
// e `Casos-coverage`; os outros 3 estavam FORA — e a fase se chama "Gates antes do PR".
//
// Custo medido, não hipotético: uma sessão rodou os 6 gates desta lista, todos verdes,
// e mesmo assim levou VERMELHO num required (`espelho — mexeu depois de verificar`,
// PR #6117). Rodar a lista inteira do painel não era suficiente pra abrir PR — que é
// exatamente o serviço que esta constante promete prestar.
//
// O caso do espelho é a §5 2026-07-28 em estado puro ("validar um gate rodando UM dos
// modos que o CI roda"): o `cowork-mirror-freshness.mjs` JÁ era citado no painel em 4
// modos (`--export-from`, `--ds-runtime`, `--snapshot-from`, `--preview-ds`) e o modo
// que MORDE no CI não estava em nenhum. Um script com N modos é N gates.
//
// MAPEAMENTO PROVADO job→context→comando (cada um conferido no .yml, não inferido do
// nome — o meu 1º palpite pro charter era `ancora-guard.mjs` e estava ERRADO):
//   · .github/workflows/governance-script-tests.yml  job `espelho-verificado`
//       name: espelho — mexeu depois de verificar          → --unverified --check
//   · .github/workflows/anchor-content-required.yml  job `anchor-content`
//       name: Ancora de design nao-shell (F2/F6 required)  → anchor-content-check --check
//   · .github/workflows/anchor-drift.yml             job `charter-live-signal`
//       name: charter status:live precisa de sinal de prod → charter-live-signal --check
// O selftest `conferirCoberturaRequired()` trava esses 3 pares (comando presente aqui
// + context ainda required no baseline). O que ele NÃO cobre está dito lá, sem inflar.
export const PREFLIGHT_GATES = [
  'node scripts/layout-primitives-guard.mjs',
  'node scripts/casos-coverage-guard.mjs',
  'npm run lint:baseline:check',
  'node_modules/.bin/tsc --noEmit',
  'node prototipo-ui/ds-guard.mjs <arquivos-tocados>',
  'node scripts/governance/cowork-ssot-guard.mjs',
  // REQUIRED do domínio que faltavam (2026-08-21) — ver mapeamento provado acima
  'node scripts/governance/cowork-mirror-freshness.mjs --unverified --check   # espelho editado sem prova de fidelidade',
  'node scripts/governance/anchor-content-check.mjs --check                   # âncora de design MISSING/SHELL',
  'node scripts/governance/charter-live-signal.mjs --check <charters-tocados> # status:live sem sinal de prod',
  // as 2 LEIs que o required `DS gate` agrega — nenhuma estava aqui (medido 2026-08-21)
  'node scripts/conformance-gate.mjs --all                                   # cor crua NOVA vs baseline (LEI)',
  'node scripts/foundation-guard.mjs                                         # token-def só na fundação (LEI)',
  'php artisan ui:lint   # 2a perna do DS gate — exige PHP; ausência de env NÃO é reprovação do gate',
];

// Pares (context required ↔ comando local) que o selftest trava. Vive aqui, ao lado da
// lista, porque é a lista que ele defende. NÃO é inventário de required do repo — é o
// recorte do domínio design/espelho, o que este painel governa.
export const REQUIRED_DO_DOMINIO = [
  { context: 'espelho — mexeu depois de verificar',
    cmd: 'node scripts/governance/cowork-mirror-freshness.mjs --unverified --check',
    workflow: '.github/workflows/governance-script-tests.yml', job: 'espelho-verificado' },
  { context: 'Ancora de design nao-shell (F2/F6 required)',
    cmd: 'node scripts/governance/anchor-content-check.mjs --check',
    workflow: '.github/workflows/anchor-content-required.yml', job: 'anchor-content' },
  { context: 'charter status:live precisa de sinal de prod',
    cmd: 'node scripts/governance/charter-live-signal.mjs --check',
    workflow: '.github/workflows/anchor-drift.yml', job: 'charter-live-signal' },
  // ⚠️ `DS gate` é job AGREGADOR (`needs: [conformance, ui-lint]`) — ele NÃO roda
  // `ds-guard.mjs`. Confundir os dois foi o meu 2º palpite errado nesta reconciliação:
  // `ds-guard.mjs` é o guard de design-memory (PROCESSO_MEMORIA_CC §8), outro papel.
  // O par abaixo aponta pra LEI que de fato avermelha: cor-crua. A outra perna
  // (`ui-lint` → `php artisan ui:lint`) exige PHP e está na lista com essa ressalva.
  { context: 'DS gate',
    cmd: 'node scripts/conformance-gate.mjs --all',
    workflow: '.github/workflows/ds-gate.yml', job: 'ds-gate ← needs conformance' },
  { context: 'Casos-coverage · ratchet (trio + rastreabilidade)',
    cmd: 'node scripts/casos-coverage-guard.mjs',
    workflow: '.github/workflows/casos-gate.yml', job: 'casos-gate' },
];

// ── MAPA FASE → comando(s) reais (o "painel" executável do RUNBOOK) ─────────────
export const FASES = [
  // ⚠️ FASE −1 RECONCILIADA ([W] 2026-08-13: "não existe mais zip, é direto o protocolo").
  // Este painel se declara "fonte única" e listava TRÊS caminhos como se fossem alternativas
  // vivas. Não são: o ZIP é o caminho MORTO. Estado medido no dia da reconciliação:
  //   · `importar-bundle.mjs` — invocado em CI SÓ como `--selftest`; nenhum import real.
  //     (Segue vivo como MÓDULO: `render-proto-baseline.mjs` importa `acharBundleRoot` dele.)
  //   · `check-handoff.ps1 -Zip` — mais fraco ainda: nem `--selftest`, zero invocador. (Os
  //     hits de "check-handoff" em `.github/` são o `bin/check-handoff-scope.php`, outro
  //     script, sobre `memory/handoffs/` — não confundir.)
  //   · a skill `aplicar-prototipo` e o `RUNBOOK-aplicar-prototipo-orquestracao.md` mandavam
  //     usar `importar-bundle.mjs "<zip>"`. **Dívida PAGA em 2026-08-13**: os dois passaram a
  //     citar o pull direto; o ZIP ficou como história/lição, marcado sem invocador.
  // O canônico é o pull direto (ADR 0325) escrito por `--export-from` (ADR 0374, ratificada
  // 2026-08-13). Os comandos de ZIP saem da lista de FASES — deletar os SCRIPTS é poda de
  // capacidade, decisão [W], e não se faz de lado dentro de uma reconciliação de redação.
  // ── QUAL ROTA USAR (2026-08-20) — a lista abaixo tinha DUAS e nenhuma hierarquia ──
  // Medido nesta data: uma sessao rodou este painel, leu a fase -1, pegou o `--export-from`
  // (que serve pra arquivo AVULSO) e passou horas concluindo que "nao ha rota fiel" pro
  // espelho — a conclusao que o `sync/README.md` ja nomeia, no primeiro paragrafo, como
  // "errada como teto absoluto". As duas rotas apareciam lado a lado, como se fossem
  // alternativas equivalentes. Nao sao:
  //
  //   SINCRONIZAR O ESPELHO (muitos arquivos)  -> aplicar-payload.mjs   [ROTA PRINCIPAL]
  //   ARQUIVO AVULSO (1-3, ja medido)          -> --export-from         [caso pontual]
  //
  // FRONTEIRA MEDIDA do get_file (2026-08-20, testada arquivo a arquivo): conteudo acima de
  // ~48 KB volta PERSISTIDO em disco (jana-merge.jsx 59 KB, financeiro-page.jsx 128 KB);
  // abaixo do piso volta INLINE no contexto (jana-merge.css 18 KB, jana-pro.css 7 KB).
  // So o persistido pode alimentar `--export-from` sem transcrever.
  // ⚠️ O PISO NAO E ~36 KB — este bloco dizia isso e apodreceu. Refutado por medicao:
  // `part01` de 40.896 B chegou INLINE (CODE_NOTES.prompt-cowork-payload-gerador-2026-08-22:27,
  // que ja registrava "o piso desta harness NAO e ~36 KB"), e em 2026-08-27 jana-metas.jsx
  // (~20 KB) e .css (~3 KB) vieram inline enquanto bundle.manifest.json (60,6 KB) persistiu.
  // O piso e uma FAIXA que varia com a resposta, nao um corte fixo: o repo ja carregou 4
  // numeros diferentes (~36 · ~47 · 48-49,1 · 60 KiB). NAO restatear numero aqui — se
  // precisar do valor de hoje, MEÇA (baixe um arquivo conhecido e veja se persistiu).
  // COROLARIO que abre a rota: partes de payload <=256 KiB ficam ACIMA da fronteira, entao
  // persistem — da pra baixar as ~14 partes por get_file e aplicar, SEM URL curta.
  //
  // Dono da rota completa: `prototipo-ui/design-docs/sync/README.md` (esta no git, leitura direta).
  // ⚠️ PONTEIRO CORRIGIDO (medido 2026-08-27): esta linha mandava ler `sync/README.md` NO PROJETO
  // Cowork via `DesignSync.get_file` — e la ele NAO EXISTE (HTTP 404 not_found). O `list_files`
  // do projeto mostra `sync/` com o `bundle.manifest.json` e as `payload.partNN.json`, nada mais.
  // Quem seguisse o ponteiro concluiria "a rota nao tem dono" a partir de um 404 que era do
  // ponteiro, nao da rota. O README real desceu pelo transporte e vive no git desde entao.
  { fase: '-1', nome: 'Importar/baixar o design', comandos: [
      '# [ROTA PRINCIPAL] bundle v2 — snapshot inicial; depois delta por manifesto anterior',
      'node scripts/design-sync/gerar-payload-partes.mjs --root <design-vivo> --out <sync> [--previous <bundle.manifest.json>]',
      'node scripts/design-sync/aplicar-payload.mjs <payload.part*.json> --dry --require-complete-shell  # valida lote + estado-alvo em staging',
      'node scripts/design-sync/aplicar-payload.mjs <payload.part*.json> --require-complete-shell        # promove atomicamente ou restaura tudo',
      'node scripts/design-sync/status.mjs --check-mapping                                             # lista mudanças + tela/alvo/módulo/ação',
      '  ^ snapshot baixa tudo uma vez; delta baixa só added/modified. deleted/unchanged não carregam bytes.',
      '  ^ `_ds` e cache derivado do preview. Manifesto, relatório e evidências ficam em scripts/design-sync/state/.',
      '  ^ partes <=256 KiB voltam em ARQUIVO pelo get_file; parte ausente/base/hash divergente bloqueiam antes do swap.',
      'DesignSync.get_file(projectId=COWORK_PROJECT_ID, path=<âncora>)                  # pull direto, agente logado (ADR 0325)',
      '# [caso pontual] arquivo AVULSO — NAO e a rota de sincronizar o espelho (use o applier acima)',
      'node scripts/governance/cowork-mirror-freshness.mjs --export-from <dir-jsons>     # escreve o raw.content no espelho (ADR 0374 — transcrever à mão é PROIBIDO)',
      'node scripts/governance/cowork-mirror-freshness.mjs --export-from <dir> --ds-runtime  # bundle/CSS/fontes → snapshot ÚNICO consumido pelo preview',
      '# [VALE PRAS DUAS ROTAS] medir e portão — nao sao "caso pontual" (2026-08-21: estavam',
      '#   sob o cabecalho de avulso, e quem le um cabecalho leva os 4 comandos junto)',
      'node scripts/governance/cowork-mirror-freshness.mjs --snapshot-from <dir> --emit-snapshot <s>  # MEDIR sem consertar (antes de aplicar/exportar)',
      'node scripts/governance/cowork-mirror-freshness.mjs --preview-ds                  # PORTÃO fail-closed: exit != 0 PROÍBE editar produto',
      '# [ORDEM DE OPERACOES] NAO adivinhe o que falta — a maquina enumera. Rode NESTA ordem:',
      '#  1. --sla                      o espelho esta sendo medido? (2026-08-24 respondeu: mediu 1 de 137)',
      '#  2. --manifest -> get_file de cada -> --compare snap.json --check      acha o que esta STALE',
      '#  3. refresque o STALE COMECANDO PELO SHELL (oimpresso.com.html): dele saem as DEPS DE RENDER',
      '#  4. --manifest de novo -> a secao ABSENT-LOCAL lista TODO arquivo que o shell carrega e o espelho nao tem',
      '#  5. baixe essa lista — mas ela e SO O 1o NIVEL: deps DIRETAS do shell.',
      '#  6. --preview-ds               2o NIVEL: grafo CSS recursivo (@import) + fonte por url().',
      '#     ABSENT-LOCAL le so parseShellDeps (link/script do shell). Quem anda no CSS e o',
      '#     previewDsPlan(), e ele so roda no --preview-ds. CSS que importa CSS, ou que puxa',
      '#     .woff2, NAO aparece no passo 4. Medido 2026-08-24: o --preview-ds repos 10 deps',
      '#     (colors_and_type.css, cockpit_domains.css, _ds_bundle.js e 7 fontes) invisiveis ao 4.',
      '#  7. o que os passos 4+6 nao cobrem, POR CONSTRUCAO: o que o shell e o CSS nao declaram.',
      '#     A ponte (cowork-inbox) e um desses — por isso ela tem bloco proprio logo abaixo.',
      '#  POR QUE a ordem importa (medido 2026-08-24): o ABSENT-LOCAL disse "ausentes: 0" e estava',
      '#  CERTO sobre o shell que leu — o do espelho, velho, que nao citava arquivos-*. O shell VIVO',
      '#  cita arquivos-page.jsx, arquivos-data.jsx e modulos-faltantes.css. Shell velho = detector cego,',
      '#  e o cego responde 0 com confianca. Refresque o shell ANTES de confiar no ABSENT-LOCAL.',
      '# [PONTE / INTAKE] o PEDIDO vive em cowork-inbox/<modulo>/ — nao e o design, e o QUE fazer com ele.',
      '#   Medido 2026-08-24: este painel nao citava cowork-inbox nem design-docs em lugar nenhum, entao a',
      '#   intake so existia em prosa (PROTOCOL 87-88) e a sessao trouxe o .jsx e deixou o pedido pra tras.',
      'DesignSync.list_files(projectId=COWORK_PROJECT_ID)                              # ache cowork-inbox/<mod>/ e modulos-faltantes/<mod>.*',
      'DesignSync.get_file(projectId=COWORK_PROJECT_ID, path=cowork-inbox/<mod>/<PEDIDO|PROMPT>.md)',
      'node scripts/governance/cowork-mirror-freshness.mjs --export-from <dir-jsons>   # .md ROTEIA pra prototipo-ui/design-docs/ (R1 do ssot-guard proibe .md em cowork/)',
      '  ^ TRAGA o pedido/handoff (PEDIDO-*, PROMPT-*): sem ele a proveniencia do charter fica so no corpo do PR.',
      '  ^ NAO traga rascunho de charter/casos/contract: PROTOCOL 10.4 = nao trazer rascunho pro canon.',
      '    O canon nasce em resources/js/Pages/<Mod>/ via criar-tela.mjs, reconciliado contra SPEC/ADR.',
    ], selftest: 'node prototipo-ui/handoff-changed.mjs --selftest' },
  { fase: '0/0.5', nome: 'Detectar + manifesto', comandos: [
      'node prototipo-ui/detectar-telas.mjs --staging <dir> --json --strict',
    ], selftest: 'node prototipo-ui/detectar-telas.mjs --selftest' },
  { fase: '1', nome: 'Mapear / comparar', comandos: [
      'node prototipo-ui/ancora.mjs <Mod/Tela>',
      'node prototipo-ui/style-fingerprint.mjs --compare proto.json prod.json --tela <Mod/Tela>',
      'node prototipo-ui/design-diff.mjs --compare prod.json design.json --check',
      'node prototipo-ui/gerar-map.mjs <gap.md>   # esqueleto do <tela>.map.json (ponte design↔código persistente)',
      'node scripts/design-sync/status.mjs --mark-compared <fonte> --target <Pages/...> --map <tela.map.json>',
    ], selftest: 'node prototipo-ui/style-fingerprint.mjs --selftest' },
  { fase: '3/4', nome: 'Registrar + aplicar região', comandos: [
      'node prototipo-ui/consumir-map.mjs <Mod/Tela>   # portão de frescor (aborta se o protótipo re-exportou) + plano de leitura: a sessão abre SÓ os ranges do map',
      'node prototipo-ui/gerar-contrato.mjs <gap.md>',
      'node scripts/contrato-de-tela.mjs --contract <c.json> --contract-alvo <Pages/...>',
      'node prototipo-ui/recortar-regiao.mjs --contract <c.json> --bboxes <b.json> --png <shot.png> --out <dir>',
      'node scripts/design-sync/status.mjs --mark-applied <fonte> --target <Pages/...> --evidence <arquivo-versionado>',
      'node scripts/design-sync/status.mjs --run-test <fonte> --target <Pages/...> --runner <local|ct100|ci> --command-json <array-json>',
    ], selftest: 'node prototipo-ui/gerar-contrato.mjs --selftest' },
  { fase: '4-preflight', nome: 'Gates antes do PR', comandos: PREFLIGHT_GATES },
  { fase: '5', nome: 'Fechar o loop', comandos: [
      'node scripts/design-sync/status.mjs --refresh --check-mapping   # evidência stale volta a pendente pelos hashes',
      'node scripts/design-sync/status.mjs --record-smoke <fonte> --target <Pages/...> --route </rota> --deploy-sha <sha> --screenshot <arquivo> --tenant 1',
      'node scripts/design-sync/status.mjs --check-lifecycle --source <fonte> --minimum <estado>   # catraca só do escopo novo; legado não ganha anistia',
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

function conferirFonteUnicaExecutavel() {
  const problemas = [];
  const marcadoresPrivados = [
    ['comando do applier', /scripts[\\/]design-sync[\\/]aplicar-payload\.mjs/],
    ['comando do preview DS', /scripts[\\/]governance[\\/]cowork-mirror-freshness\.mjs\s+--preview-ds/],
    ['COWORK_PROJECT_ID literal', new RegExp(COWORK_PROJECT_ID.replaceAll('-', '\\-'))],
    ['DESIGN_SYSTEM_PROJECT_ID literal', new RegExp(DESIGN_SYSTEM_PROJECT_ID.replaceAll('-', '\\-'))],
  ];

  for (const rel of PONTEIROS_EXECUCAO) {
    const abs = join(REPO_ROOT, rel);
    if (!existsSync(abs)) {
      problemas.push(`ponteiro obrigatório ausente: ${rel}`);
      continue;
    }
    const txt = readFileSync(abs, 'utf8');
    for (const [nome, regex] of marcadoresPrivados) {
      if (regex.test(txt)) problemas.push(`${rel} duplicou ${nome}; a fonte é protocolo.config.mjs`);
    }
  }

  try {
    const safeRoot = REPO_ROOT.replaceAll('\\', '/');
    const tracked = execFileSync('git', [
      '-c', `safe.directory=${safeRoot}`,
      'ls-files', '-z', '--', 'prototipo-ui/cowork/_ds/**',
    ], { cwd: REPO_ROOT, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] })
      .split('\0').filter(Boolean);
    for (const rel of tracked) {
      problemas.push(`${rel} está rastreado; cowork/_ds é cache derivado do mirror-snapshot`);
    }
  } catch {
    problemas.push('não foi possível provar via git que prototipo-ui/cowork/_ds não está rastreado');
  }

  return problemas;
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
// O hook `design-agente-ativa` não carrega mais cópia do ID: manda executar este painel.
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
    const safeRoot = REPO_ROOT.replaceAll('\\', '/');
    const out = execFileSync('git', ['-c', `safe.directory=${safeRoot}`, 'ls-files', '-z', '--', ...paths], {
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

// ── COBERTURA DOS REQUIRED DO DOMÍNIO (2026-08-21) ──────────────────────────────
// POR QUE EXISTE: a Fase 4 se chama "Gates antes do PR" e cobria 2 dos 5 required do
// domínio design/espelho. Uma sessão rodou os 6 gates da lista, todos verdes, e levou
// vermelho num required que não estava aqui (`espelho — mexeu depois de verificar`,
// PR #6117). Lista de gate que não cobre o required do próprio domínio é pior que
// ausente: ela PARECE cobertura. Sem esta função, a reconciliação de hoje seria
// "escrito+lembrado" e apodreceria no próximo gate promovido (ADR 0256).
//
// O QUE ELE PROVA, exatamente:
//   (a) cada `cmd` dos pares aparece em PREFLIGHT_GATES — a lista não perde um gate;
//   (b) cada `context` ainda está required no baseline — o par não vira fóssil quando
//       um gate é demovido (aí o conserto é TIRAR o par, não deixá-lo mentindo).
// Lê a UNIÃO `classic_protection.contexts` ∪ `rulesets[].contexts`: ler só a clássica
// SUBCONTA e faz concluir "não é required" sobre gate que é (§5 2026-08-08).
//
// O QUE ELE NÃO PROVA — e dizer isto é parte do contrato:
//   · que o comando no .yml continua sendo ESTE. Se o workflow trocar a flag, o par
//     fica desatualizado em silêncio. Fechar isso exigiria parsear YAML de 3 workflows,
//     e parser frágil que reprova o legítimo é a família já morta 4× no §5. O elo
//     humano é o `workflow`/`job` anotado em cada par: dá pra reconferir em 1 grep.
//   · que rodar os 12 comandos garante CI verde. Eles são o piso do domínio, não o teto
//     do repo — os outros 41 required existem e não são governados por este painel.
function conferirCoberturaRequired() {
  const problemas = [];
  const listado = PREFLIGHT_GATES;
  for (const par of REQUIRED_DO_DOMINIO) {
    if (!listado.some((g) => g.includes(par.cmd))) {
      problemas.push(`required '${par.context}' sem comando em PREFLIGHT_GATES (esperado: ${par.cmd} · ${par.workflow})`);
    }
  }
  const baseline = join(REPO_ROOT, 'governance', 'required-checks-baseline.json');
  if (!existsSync(baseline)) return { problemas, conferidos: 0, semBaseline: true };
  let contexts;
  try {
    const j = JSON.parse(readFileSync(baseline, 'utf8'));
    const acc = [];
    const walk = (o) => {
      if (!o || typeof o !== 'object') return;
      if (Array.isArray(o)) { o.forEach(walk); return; }
      for (const [k, v] of Object.entries(o)) {
        if (k === 'contexts' && Array.isArray(v)) v.forEach((x) => typeof x === 'string' && acc.push(x));
        else walk(v);
      }
    };
    walk(j);
    contexts = new Set(acc);
  } catch (e) {
    // ILEGÍVEL != AUSENTE: parsear pra "vazio" inventaria estado (§5 2026-07-29).
    problemas.push(`baseline de required ilegível (${e && e.message}) — não dá pra provar cobertura`);
    return { problemas, conferidos: 0, semBaseline: true };
  }
  for (const par of REQUIRED_DO_DOMINIO) {
    if (!contexts.has(par.context)) {
      problemas.push(`par aponta pra context que NÃO é mais required: '${par.context}' — remova o par ou reconfira o baseline`);
    }
  }
  return { problemas, conferidos: REQUIRED_DO_DOMINIO.length, semBaseline: false };
}

function selftest() {
  const fails = [];
  if (!UUID.test(COWORK_PROJECT_ID)) fails.push('COWORK_PROJECT_ID não é UUID');
  if (!UUID.test(DESIGN_SYSTEM_PROJECT_ID)) fails.push('DESIGN_SYSTEM_PROJECT_ID não é UUID');
  if (COWORK_PROJECT_ID === DESIGN_SYSTEM_PROJECT_ID) fails.push('os 2 IDs colidiram (anti-confusão dos projetos)');
  if (!existsSync(MIRROR_DIR)) fails.push(`MIRROR_DIR ausente no repo: ${MIRROR_DIR}`);
  if (!existsSync(DS_RUNTIME_SNAPSHOT_DIR)) fails.push(`DS_RUNTIME_SNAPSHOT_DIR ausente no repo: ${DS_RUNTIME_SNAPSHOT_DIR}`);
  const ids = conferirIdsNoRepo();
  fails.push(...ids.problemas);
  fails.push(...conferirFonteUnicaExecutavel());
  const cobReq = conferirCoberturaRequired();
  fails.push(...cobReq.problemas);
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
  console.log(`✓ protocolo.config selftest OK — 2 IDs válidos+distintos · ${cob} · ${PONTEIROS_EXECUCAO.length} ponteiros sem cópia operacional · cowork/_ds não rastreado · MIRROR_DIR presente · ${scripts.length} scripts do mapa existem · motores (normalize/contentHash/resolveAncora) vivos · `
    + `${cobReq.conferidos} required do domínio cobertos por comando local${cobReq.semBaseline ? ' ⚠ baseline não lido: cobertura NÃO provada contra o vivo' : ''}.`);
  process.exit(0);
}

function painel() {
  console.log('PROTOCOLO DE APLICAÇÃO DE PROTÓTIPO — fonte única (protocolo.config.mjs)\n');
  console.log('PROJETOS Cowork (ADR 0325 · só por ID — NÃO confundir):');
  console.log(`  telas   ${COWORK_PROJECT_ID}  "${PROJETOS.cowork.nome}"  [não-listado, por ID]`);
  console.log(`  ds      ${DESIGN_SYSTEM_PROJECT_ID}  "${PROJETOS.designSystem.nome}"  [listado]`);
  console.log(`\nPATHS FIXOS:\n  STAGING_DIR              ${STAGING_DIR}\n  MIRROR_DIR               ${MIRROR_DIR}\n  DS_MIRROR_DIR            ${DS_MIRROR_DIR}\n  DS_RUNTIME_SNAPSHOT_DIR  ${DS_RUNTIME_SNAPSHOT_DIR}`);
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
