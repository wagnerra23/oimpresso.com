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
//   node scripts/design/protocolo.config.mjs            # imprime o painel (IDs, paths, fases)
//   node scripts/design/protocolo.config.mjs --json     # idem, JSON
//   node scripts/design/protocolo.config.mjs --selftest # trava se ID/path/script sumir (CI)
//
// IMPORT (scripts + o próprio agente):
//   import { COWORK_PROJECT_ID, STAGING_DIR, FASES } from './protocolo.config.mjs'
//
// Refs: ADR 0325 (pull direto) · ADR 0324 (identidade normalizada) · INDEX-DESIGN-MEMORIAS §0.2 ·
//       memory/reference/prototipo-ui/PROTOCOL.md (política; este arquivo é dono da execução).

import { existsSync, readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { join, dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { homedir } from 'node:os';

// binding local + re-export (re-export puro não cria binding usável no selftest deste módulo)
import { normalize, contentHash } from '../governance/cowork-mirror-freshness.mjs';
import { resolveAncora } from './ancora.mjs';
import { lerLock, donoDaTela, DONOS, LOCK_PATH } from './design-lock.mjs';
export { normalize, contentHash, resolveAncora };

const HERE = dirname(fileURLToPath(import.meta.url));
const REPO_ROOT = resolve(HERE, '../..');

// Estes arquivos são consumidores/ponteiros. Não podem voltar a carregar cópias dos comandos ou
// IDs deste painel: foi assim que o fluxo ZIP, o preview incompleto e a aplicação atual ficaram
// simultaneamente "canônicos". PROTOCOL.md é dono da política; este módulo é o único dono da
// execução.
const PONTEIROS_EXECUCAO = [
  'memory/reference/prototipo-ui/PROTOCOL.md',
  'memory/reference/prototipo-ui/PROTOCOL-F3-COWORK-CODE.md',
  'memory/reference/prototipo-ui/RUNBOOK-aplicar-prototipo-orquestracao.md',
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
// logo o `DesignSync` daqui NUNCA vai listá-las e o espelho `prototipo-ui/cowork/Wagner/` NUNCA
// vai contê-las. Elas estão CORRETAS assim.
// CONSEQUÊNCIA PRA QUEM MEDE: pra estas telas, "não achei no espelho / no DesignSync" é
// ORIGEM EXTERNA, não drift nem fonte faltando. Máquina que as tratar como ausência gera
// FP permanente — e a resposta certa a "cadê a fonte de design da Venda?" é "outra conta",
// não "gerar do DS canon". Consulte esta constante em vez de reinferir pelo nome do arquivo.
export const FORA_DESTA_CONTA = [
  { tela: 'Sells (Venda)',   arquivos: ['resources/css/venda-v3.css', 'resources/js/Pages/Sells/CreateV3.tsx'], quem: '[L]/[M]', conta: 'felipe', declaradoPor: '[W]', em: '2026-08-13' },
  { tela: 'Produto',         arquivos: ['resources/js/Pages/Produto/'],                                          quem: '[L]/[M]', conta: 'felipe', declaradoPor: '[W]', em: '2026-08-13' },
];

// ── CONTAS DE DESIGN ([W] 2026-09-11) ──────────────────────────────────────────
// Por que a conta é dimensão de primeira classe, e não detalhe: o DesignSync autentica como UM
// usuário. Tudo que ele lista, lê ou mede é da conta logada. Logo "não achei no DesignSync" só
// significa "não existe" DENTRO da conta corrente — pra qualquer outra conta a ferramenta é cega
// POR CONSTRUÇÃO, não por ausência. Sem esta tabela, a próxima sessão relê a cegueira como drift.
//
// Isto não inventa fato novo: a FORA_DESTA_CONTA abaixo já registrava, desde 2026-08-13, que
// Venda e Produto vêm de outra conta ([L]/[M]). O que faltava era o eixo explícito, pra cada
// tela saber dizer de QUAL conta ela veio. Quem responde isso por tela: `--procedencia`.
export const CONTAS = {
  w: {
    id: 'w',
    dono: '[W] Wagner',
    papel: 'conta do dono — origem das telas do ERP e do DS',
    alcancavel: true,          // o DesignSync desta sessão enxerga
    espelhada: true,           // desce pro repo por bundle/--export-from
    projetos: ['cowork', 'designSystem', 'mobile'],
  },
  felipe: {
    id: 'felipe',
    dono: '[F] Felipe (titular da conta)',
    usadaPor: ['[F] Felipe', '[M] Maiara', '[L] Luiz'],
    papel: 'conta da equipe — telas desenhadas fora da conta do dono ([W] 2026-09-11)',
    alcancavel: false,         // ⚠ invisível deste lado: outra conta, outro login
    espelhada: true,           // espelho em prototipo-ui/cowork/Felipe/ (ADR 0405) — ativado 2026-09-21
    projetos: ['telasFelipe'], // ID informado pelo [F] em 2026-09-21 (URL do projeto no Cowork)
    // APOSENTADA em 2026-09-28 por [W] ("sim, faz os dois", sobre aposentar a conta do Felipe),
    // cumprindo a D4 (c) de 2026-09-25 (proposta 2026-09-24-sincronia-entre-contas-cowork-por-dono
    // §9): "todos devem ficar junto" = tudo no projeto Cowork do [W]. O [F] já tinha passado a
    // trabalhar só no Code (§8) e a última leitura do `telasFelipe` foi o #7944 (2026-09-25).
    // Efeito: o receber-handoff RECUSA importar para esta conta (um zip dela sobrescreveria o
    // histórico), e `prototipo-ui/cowork/Felipe/` fica como HISTÓRICO — trabalho novo da equipe
    // entra por `cowork/Wagner/` e sobe ao Cowork com o opt-in [W]. A pasta não é apagada: as telas
    // que só existem nela (§9.1) seguem lá até cada uma ser migrada, tela a tela, com dono no lock.
    aposentada: { em: '2026-09-28', por: '[W]', destino: 'projeto Cowork do [W] (cowork/Wagner/)' },
    // DESIGN SYSTEM DESTA CONTA — o MESMO DS do [W], não outro ([F] 2026-09-21, textual: "O Design
    // system que informei é o mesmo que do Wagner. Puxo as atualizações direto do main do git, então
    // eles estão sincronizados. O ID é diferente porque importei o DS na minha conta").
    //   · `49a36f76-…` é a CÓPIA na conta do [F]; `DESIGN_SYSTEM_PROJECT_ID` (`019dd02f-…`) é o
    //     projeto do [W]. Mesmo conteúdo, dois endereços: não é divergência, e não há o que decidir.
    //   · a fonte dos dois é o git (`prototipo-ui/design-system/`): o DS desta conta PUXA do `main`,
    //     não é espelhado PARA o repo. Por isso não entra em PROJETOS (lá todo projeto tem pasta
    //     espelho, e o `--procedencia` a lê; o `--de-quem` saiu em 2026-10-23, ADR 0438).
    //   · conferido pelo Code no pacote de 2026-09-18: o bundle do `019dd02f` é o do `49a36f76`
    //     byte a byte (9.354 linhas) + 11 linhas de alias do nome global antigo.
    dsCopia: { id: '49a36f76-2672-43f6-b955-c6cbb52f7f86', nome: 'WAGNER Office Impresso — Design System', mesmoDsQue: 'designSystem', fonte: 'git main (prototipo-ui/design-system/)' },
    // Titular x usuários é distinção OPERACIONAL, não burocracia: quem exporta o handoff é
    // quem tem o login, e só o titular consegue. [W] 2026-09-11: "conta do Felipe (usada pelo
    // Felipe, Maiara e o Luiz)".
    //
    // ATIVADO em 2026-09-21: o [F] informou o ID pela URL do projeto
    // ("PROTÓTIPO OFICIAL - PRODUTO UNIFICADO V2"), e a rota ZIP passou a escrever no espelho do
    // dono liberado. O roteiro abaixo fica como registro de como se chegou aqui.
    //
    // PRA ATIVAR (o que faltava, exatamente):
    //   1. o projectId do projeto de telas dessa conta — NINGUÉM deste lado consegue descobrir:
    //      o DesignSync autentica como [W], então a conta do [F] é invisível POR CONSTRUÇÃO.
    //      list_projects vazio sobre ela não é evidência de nada.
    //   2. com o ID: acrescentar aqui + em PROJETOS (com 'espelho'), e o --procedencia passa a
    //      carimbar as telas dela sozinho, sem mais nenhuma mudança de código.
    //   3. o conteúdo desce pela rota que já existe — receber-handoff.mjs --zip, exportado do
    //      login do [F]. Nada de rota nova.
    //
    // Não confunda "sem ID aqui" com "não existe": a lista é vazia porque nunca foi informada,
    // não porque foi medida.
  },
};

export const PROJETOS = {
  cowork:       { id: COWORK_PROJECT_ID,        nome: 'Oimpresso ERP Comunicação Visual', papel: 'telas',  listado: false, conta: 'w', espelho: 'prototipo-ui/cowork/Wagner/' },
  designSystem: { id: DESIGN_SYSTEM_PROJECT_ID, nome: 'Office Impresso — Design System',   papel: 'ds',     listado: true,  conta: 'w', espelho: 'prototipo-ui/design-system/' },
  // Projeto de telas da conta do Felipe (usada por [F]/[M]/[L]). ID informado pelo [F] em
  // 2026-09-21. `listado: false` pelo mesmo motivo do `cowork`: projeto de telas não aparece em
  // `list_projects` (que só lista design system), e a conta nem é visível deste lado.
  telasFelipe:  { id: '2e7d3640-825c-4c09-ac52-17c8469f3b91', nome: 'PROTÓTIPO OFICIAL - PRODUTO UNIFICADO V2', papel: 'telas', listado: false, conta: 'felipe', espelho: 'prototipo-ui/cowork/Felipe/' },
  // Protótipo do APP DAS LOJAS (repo wagnerra23/oimpresso-app — Capacitor, telas próprias),
  // apontado por [W] em 2026-10-01 como "o correto". NÃO é o shell do ERP: não tem
  // `oimpresso.com.html`, não vira bundle nem entra no ledger de frescor. Entra pela rota
  // `receber-handoff --zip … --projeto mobile` (cópia fiel: mostra o que muda, nunca apaga,
  // invalida CPF/CNPJ com DV válido do mock). O espelho é a cópia de referência que o app e o
  // DECISOES.md já citam (PRs #8467/#8485); o `github.md` do projeto aponta para o oimpresso-app.
  mobile:       { id: 'b29cacda-da7c-4c02-b91e-4064b35ba951', nome: 'Mobile app structure review', papel: 'telas', listado: false, conta: 'w', espelho: 'mobile/ref/design-v4/project/', importacao: 'copia-fiel', entrada: 'Oimpresso Mobile.dc.html', app: 'wagnerra23/oimpresso-app' },
};

// ── PATHS FIXOS (as âncoras do protocolo dependem destes — RUNBOOK Fase −1) ─────
// MIRROR_DIR: SSOT do design no repo, build-only (R1 do cowork-ssot-guard rejeita .md aqui).
//
// ⚠️ STAGING_DIR é LEGADO do caminho ZIP ([W] 2026-08-13: "não existe mais zip"). Continua
// exportado por compatibilidade com consumidores legados. Em 2026-09-10 o default de
// `render-proto-baseline.mjs` foi corrigido para MIRROR_DIR; o Downloads antigo não deve
// alimentar capturas novas implicitamente. NÃO é destino de nada novo:
//   · a catraca `ancora-guard` já lista `_cowork-handoff-staging` e `Downloads/` como LUGAR
//     PROIBIDO pra âncora ([W] 2026-07-01: "não pode trocar de lugar nunca");
//   · o destino do design versionado é MIRROR_DIR, e o shell mora lá desde 2026-08-13.
// Ler design de dentro do STAGING_DIR é reabrir a doença: o pacote de lá estava congelado em
// 01/jul e conhecia 103 deps quando o vivo já tinha 120.
//
// 📌 EMENDA 2026-09-10 — o ZIP VOLTOU como insumo, e isto acima segue valendo INTEIRO.
// [W] entregou 3 handoffs .zip neste dia e pediu a recepção automatizada ("exportar uma única
// vez"). Decisão do dono, não proposta — e ela reverte só o "não existe mais zip" de 13/08,
// nada mais. O que NÃO muda, e é o motivo do parágrafo acima existir:
//   · `receber-handoff.mjs` extrai pra um tmpdir EFÊMERO, nunca pra cá — sem árvore persistente
//     não há árvore velha alimentando captura nova, que era a doença de fato;
//   · `Downloads/` e `_cowork-handoff-staging` seguem LUGAR PROIBIDO pra âncora no
//     `ancora-guard::PROIBIDOS`. O ZIP é INSUMO DE IMPORTAÇÃO, nunca fonte de design;
//   · o destino do design versionado continua sendo MIRROR_DIR.
// Ou seja: o que foi banido era o STAGING PERSISTENTE, não o formato .zip.
export const STAGING_DIR = join(homedir(), 'Downloads', '_cowork-handoff-staging');
export const MIRROR_DIR = join(REPO_ROOT, 'prototipo-ui', 'cowork', 'Wagner');
export const FELIPE_MIRROR_DIR = join(REPO_ROOT, 'prototipo-ui', 'cowork', 'Felipe');

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
// O antigo `scripts/design-sync/mirror-snapshot/` foi removido: criava uma segunda cópia
// física do DS e podia divergir do diretório canônico abaixo.
// Template não é token nem bundle; enfiar ali confundiria os dois papéis.
//   ⚠️ ERRATA 2026-09-10 (medida, não lida): esta linha dizia que o README do
// O shell de Wagner referencia este diretório diretamente; preview não materializa `_ds/`.
//   FALSO hoje: `git ls-files prototipo-ui/design-system/` = **11**
//   (`_ds_bundle.js` · `colors_and_type.css` · `cockpit_domains.css` · 7 `.woff2` ·
//   README), e o próprio README se declara "o único destino versionado dos artefatos
//   compilados". O escopo de 1 arquivo foi verdade na origem do sentinela
//   `ds-mirror-drift`; o `--preview-ds` de 2026-08-24 repôs 10 deps e o snapshot
//   cresceu junto. Fica o fato datado, não a frase em presente (LC-10).
//
// ⚠️ REGRA DE ESCRITA, e ela não é estilo — é a lápide de 2026-08-11: o conteúdo
// SAI DO DADO, POR SCRIPT (`get_file` → JSON → `writeFile`). NUNCA transcrito pelo
// contexto do agente. Foi transcrição que produziu o STALE daquele dia.
export const DS_MIRROR_DIR = join(REPO_ROOT, 'prototipo-ui', 'design-system');

// ── ONDE O DESIGN SYSTEM MORA — e por que é UM só ponto de entrada ─────────────
//
// [W] 2026-09-10: *"tem que fundamentar melhor a localização e a importação única
// do design system"* · *"Importação em único lugar do design system"*.
//
// São TRÊS diretórios e UMA direção. Ler a direção é o que impede o erro: quem trata
// derivado como fonte importa duas vezes, e as duas divergem em silêncio.
//
//   (1) IMPORTA-SE AQUI, e só aqui ── prototipo-ui/design-system/     [251 versionados]
//       Espelho do projeto Cowork `ds` (ID em COWORK_DS_PROJECT_ID, NÃO o de telas).
//       É a FONTE: 148 components · 31 templates · 18 ui_kits · 13 assets · 12 preview
//       + Canvas.dc.html · HANDOFF.md · NOTAS_INTERNAS.md · SKILL.md · Norte/.
//       ÚNICO destino de importação do DS. Qualquer `--export-from`/applier que traga
//       DS aponta pra cá.
//                                    ↓ deriva (subconjunto de RUNTIME)
//   (2) DERIVADO ───────────────── prototipo-ui/design-system/  [11 versionados]
//       Só o que o shell PRECISA pra renderizar: bundle + 2 CSS + 7 fontes.
//       Escrito por `--ds-runtime`, lido por `--preview-ds`. O README dele é o dono
//       da regra e diz o mesmo: "único destino versionado dos artefatos compilados".
//                                    ↓ materializa (cache descartável)
//   (3) CACHE ──────────────────── prototipo-ui/cowork/Wagner/_ds/          [ZERO versionados]
//       Gitignored. Medido: `git ls-files "prototipo-ui/cowork/Wagner/_ds*"` = 0.
//       Até o #7224 (2026-09-11) era reposto sob demanda pelo `--preview-ds` e pelo hook
//       SessionStart `ds-preview-materialize.mjs`; naquele PR o shell passou a referenciar
//       `../../design-system/` direto, o `--preview-ds` foi aposentado e o hook deixou de
//       escrever. O README do snapshot é categórico —
//       "Nunca copie ou versione `_ds/` dentro do espelho Cowork".
//
// POR QUE ISTO PRECISA ESTAR ESCRITO (o risco é medido, não hipotético): 3 arquivos
// existem nos DOIS destinos versionados — `_ds_bundle.js`, `colors_and_type.css` e
// `cockpit_domains.css`. Medidos byte-a-byte em 2026-09-10: IDÊNTICOS (sha256
// a3ac15c11030 · fbe2cf878e9a · 77046b89f2ba). Idênticos POR ORA — nada garantia isso
// até o selftest abaixo. Atualizar (1) sem regerar (2) faz o preview renderizar com
// tokens velhos, e a tela "diverge do design" por causa do cache, não do código:
// conclusão errada com aparência de achado.
//
// Artefatos COMPILADOS que o preview consome. É um papel diferente de DS_MIRROR_DIR
// (fonte/templates): `--ds-runtime` grava aqui e `--preview-ds` lê daqui, sem diretório órfão.
// Alias preservado para consumidores antigos. Runtime e fonte têm o MESMO dono físico;
// criar outro snapshot versionado é duplicação e volta a permitir drift silencioso.
export const DS_RUNTIME_SNAPSHOT_DIR = DS_MIRROR_DIR;

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
//   · (até 2026-10-23) .github/workflows/governance-script-tests.yml job `espelho-verificado`,
//       name: espelho — mexeu depois de verificar → --unverified --check. Demovido pela
//       ADR 0438 D7; o par saiu de REQUIRED_DO_DOMINIO, como manda o item (b) abaixo.
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
  'node scripts/design/ds-guard.mjs <arquivos-tocados>            # em RÉPLICA de protótipo: --report (ADR 0388) — o achado vai pra lista, não veta',
  // ADR 0388 — réplica primeiro: a conformidade do DS vira LISTA pós-aplicação, gerada no MESMO PR
  'node scripts/governance/replica-inconsistencias.mjs --modulo <Mod> [--prototipo <jsx do espelho>]   # NUNCA bloqueia; exit 2 só se NÃO mediu',
  //   ^ cobre também o eixo ESLINT-DS (13 regras `ds/*`), delegando a `ds-report.mjs --by-file`.
  //     Precisa de node_modules (o dono invoca o ESLint); sem ele o item sai NÃO MEDIDO e o
  //     exit vira 2 — use `--sem-eslint` pra medir só o resto. O `ds/*` NÃO aparecia na lista
  //     antes: o baseline agrega tudo sob o ruleId `no-restricted-syntax` (o prefixo `ds/` só
  //     existe na mensagem), então só se via quando o required `ESLint · ratchet vs baseline`
  //     avermelhava — e some de novo quando é absorvido com `BASELINE-ABSORB:` (PR #6553).
  'node scripts/governance/cowork-ssot-guard.mjs',
  // REQUIRED do domínio que faltavam (2026-08-21) — ver mapeamento provado acima
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
  // FASE -1 — a fonte do prototipo e o GIT (ADR 0438, [W] 2026-10-23; ADR 0433 D1).
  // Ate 2026-10-23 esta fase listava as rotas de BAIXAR do Cowork (ZIP via receber-handoff,
  // bundle via gerar-payload-partes + aplicar-payload, avulso via --export-from) e o passo
  // "de quem e?" do ZIP. A 0438 encerrou o Cowork como rota de entrada: os scripts da rota
  // ZIP e da rota bundle sairam na onda 2a. O que resta da fase e o pedido do Artefato:
  // a tela que o [W] vai revisar e publicada como Artefato A PARTIR do arquivo do repo,
  // e o comentario dele volta como commit (0438 D2/D3).
  { fase: '-1', nome: 'Fonte do prototipo (git)', comandos: [
      '# A fonte e o arquivo sob prototipo-ui/ no git — nao ha download (ADR 0438 D1).',
      '# Revisao visual: publique o arquivo do repo como Artefato; comentario vira commit (0438 D2/D3).',
      '# Ancora da tela: node scripts/design/ancora.mjs <Mod/Tela>',
    ] },
  { fase: '0/0.5', nome: 'Detectar + manifesto', comandos: [
      'node scripts/design/detectar-telas.mjs --staging <dir> --json --strict',
    ], selftest: 'node scripts/design/detectar-telas.mjs --selftest' },
  { fase: '1', nome: 'Mapear / comparar', comandos: [
      'node scripts/design/ancora.mjs <Mod/Tela>',
      '# ⚠ A ORDEM DOS DOIS E INVERTIDA ENTRE SI — trocar produz relatorio plausivel com os lados espelhados.',
      '#   style-fingerprint = PROTO primeiro · design-diff = PROD primeiro. Nao e simetria, e pegadinha.',
      '#   design-diff MORDE na troca (deriva o lado de `location.href`: espelho e file:/localhost, prod e https).',
      '#   style-fingerprint nao consegue derivar (a sonda dele grava so `location.pathname`) — a defesa la e a',
      '#   trava de ancora do `--tela`, e quem passa `--sem-ancora` fica sem defesa contra inversao.',
      'node scripts/design/style-fingerprint.mjs --compare proto.json prod.json --tela <Mod/Tela>',
      'node scripts/design/design-diff.mjs --compare prod.json design.json --check',
      '# [LOTE · sob demanda, NÃO é gate] as duas sondas acima em N telas de uma vez — seleciona anchored do',
      '#   application-report, resolve âncora pelo ancora.mjs, renderiza espelho + vivo (playwright), compara com o',
      '#   dono e emite governance/design/targets/medidas/RESUMO.md derivado. --dry mostra o plano sem browser (2026-09-06).',
      'node scripts/design/design-diff-lote.mjs --dry   # plano; sem --dry exige playwright + app vivo (ver docblock)',
      'node scripts/design/gerar-map.mjs <gap.md>   # esqueleto do <tela>.map.json (ponte design↔código persistente)',
      'node scripts/design-sync/status.mjs --mark-compared <fonte> --target <Pages/...> --map <tela.map.json>',
    ], selftest: 'node scripts/design/style-fingerprint.mjs --selftest' },
  { fase: '3/4', nome: 'Registrar + aplicar região', comandos: [
      'node scripts/design/consumir-map.mjs <Mod/Tela>   # portão de frescor (aborta se o protótipo re-exportou) + plano de leitura: a sessão abre SÓ os ranges do map',
      'node scripts/design/gerar-contrato.mjs <gap.md>',
      'node scripts/contrato-de-tela.mjs --contract <c.json> --contract-alvo <Pages/...>',
      'node scripts/design/recortar-regiao.mjs --contract <c.json> --bboxes <b.json> --png <shot.png> --out <dir>',
      'node scripts/design-sync/status.mjs --mark-applied <fonte> --target <Pages/...> --evidence <arquivo-versionado>',
      'node scripts/design-sync/status.mjs --run-test <fonte> --target <Pages/...> --runner <local|ct100|ci> --command-json <array-json>',
    ], selftest: 'node scripts/design/gerar-contrato.mjs --selftest' },
  { fase: '4-preflight', nome: 'Gates antes do PR', comandos: PREFLIGHT_GATES },
  { fase: '5', nome: 'Fechar o loop', comandos: [
      'node scripts/design-sync/status.mjs --refresh --check-mapping   # evidência stale volta a pendente pelos hashes',
      'node scripts/design-sync/status.mjs --record-smoke <fonte> --target <Pages/...> --route </rota> --deploy-sha <sha> --screenshot <arquivo> --tenant 1',
      'node scripts/design-sync/status.mjs --check-lifecycle --source <fonte> --minimum <estado>   # catraca só do escopo novo; legado não ganha anistia',
      'node scripts/governance/anchor-lint.mjs --check memory/requisitos/<Mod>/SPEC.md',
      'node scripts/governance/design-code-map-check.mjs --check --strict   # % telas mapeadas + invalida map.json com sha stale',
    ], selftest: 'node scripts/design/integrity-check.mjs' },
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
      // Os DOIS destinos: o atual (pós-#7224) e o LEGADO pré-#7224, que worktree stale
      // ainda materializa rodando o hook antigo (medido 2026-09-15: 10 arquivos).
      'ls-files', '-z', '--',
      'prototipo-ui/cowork/Wagner/_ds/**',
      'prototipo-ui/cowork/_ds/**',
    ], { cwd: REPO_ROOT, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] })
      .split('\0').filter(Boolean);
    for (const rel of tracked) {
      problemas.push(`${rel} está rastreado; cowork/_ds é cópia paralela proibida do design-system`);
    }
  } catch {
    problemas.push('não foi possível provar via git que os _ds/ de prototipo-ui/cowork (atual e legado) não estão rastreados');
  }

  return problemas;
}
// ── CONSISTÊNCIA DOS IDs (2026-08-13) ───────────────────────────────────────────
// Este arquivo se declara "a fonte" dos 2 IDs, e o selftest provava que eles são UUID
// e distintos — mas NÃO que o resto do repo concorda com eles. Dois consumidores
// dependem disso na prática:
//   · o SHELL do espelho (`prototipo-ui/cowork/Wagner/oimpresso.com.html`) linka
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
  if (resolve(DS_RUNTIME_SNAPSHOT_DIR) !== resolve(DS_MIRROR_DIR)) {
    fails.push('runtime do DS criou um segundo dono físico; deve apontar para DS_MIRROR_DIR');
  }
  if (existsSync(join(REPO_ROOT, 'scripts', 'design-sync', 'mirror-snapshot'))) {
    fails.push('snapshot paralelo do DS ainda existe em scripts/design-sync/mirror-snapshot');
  }
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
  // PROCEDÊNCIA — trava as 2 pernas que já falharam ao vivo nesta sessão:
  //  (a) declaração de [W] que aponta pro .tsx tem que casar o .charter.md do MESMO stem;
  //  (b) e NÃO pode capturar a tela vizinha cujo nome é prefixo (Create vs CreateV3) — sem o
  //      controle negativo, um guard que casa tudo passaria por "funcionando".
  {
    const fixture = [{ tela: 'X', arquivos: ['resources/js/Pages/Sells/CreateV3.tsx'], quem: '[L]/[M]', conta: 'felipe', declaradoPor: '[W]', em: '2026-08-13' }];
    const bom  = procedenciaDaTela({ charter: 'resources/js/Pages/Sells/CreateV3.charter.md', caminho: '' }, fixture);
    // ⚠ O controle negativo tem que ir no sentido DECLARAÇÃO-CURTA → CHARTER-LONGO. A 1ª versão
    // deste assert usava fixture 'CreateV3.tsx' vs charter 'Create.charter.md' e NÃO mordia:
    // provado por mutação (removi a trava do ponto e o selftest seguiu verde). 'Create.charter.md'
    // nunca começa com 'CreateV3' — o par estava invertido. É neste sentido que a trava trabalha.
    const curto = [{ tela: 'Y', arquivos: ['resources/js/Pages/Sells/Create.tsx'], quem: '[L]/[M]', conta: 'felipe', declaradoPor: '[W]', em: '2026-08-13' }];
    const viz  = procedenciaDaTela({ charter: 'resources/js/Pages/Sells/CreateV3.charter.md', caminho: 'prototipo-ui/cowork/Wagner/vendas-page.jsx' }, curto);
    const esp  = procedenciaDaTela({ charter: 'resources/js/Pages/Kb/Index.charter.md', caminho: 'prototipo-ui/cowork/Wagner/kb-page.jsx' }, fixture);
    const na   = procedenciaDaTela({ charter: 'resources/js/Pages/Z/Index.charter.md', caminho: '', isNa: true, declaracaoNa: 'herda PT-01' }, fixture);
    if (bom.classe !== 'outra-conta')  fails.push('procedencia: stem .tsx -> .charter.md NAO casou (CreateV3)');
    if (viz.classe === 'outra-conta')  fails.push('procedencia: CONTROLE NEGATIVO falhou — declaracao Create.tsx capturou a tela CreateV3');
    if (esp.classe !== 'espelhada' || esp.conta !== 'w') fails.push('procedencia: âncora no espelho cowork/ nao virou conta w');
    if (na.classe !== 'sem-prototipo') fails.push('procedencia: isNa nao virou sem-prototipo');
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

/** PROCEDÊNCIA — de qual CONTA e de qual PROJETO vem a fonte de design de uma tela.
 *
 *  DERIVADA, nunca escrita à mão (ADR 0256: derivado sobrevive, escrito apodrece). A entrada é o
 *  `ancora.mjs --list --json` (dono de "qual é a âncora desta tela"); esta função só carimba a
 *  ORIGEM em cima, usando PROJETOS/CONTAS. Não decide âncora e não contradiz o dono.
 *
 *  Pura: recebe as entradas e devolve o carimbo, pra o --selftest exercitar sem tocar disco.
 */
export function procedenciaDaTela(entrada, foraDestaConta = FORA_DESTA_CONTA) {
  const caminho = entrada.caminho || entrada.source || '';
  const charter = entrada.charter || '';

  // 1º: a declaração de [W] vence qualquer inferência por path — é fato de dono, não heurística.
  // O casamento tem DUAS formas porque a declaração aponta pro artefato (Pages/X/Y.tsx ou uma
  // pasta), enquanto a entrada aqui traz o CHARTER (Pages/X/Y.charter.md). Prefixo cru só resolve
  // a pasta; pro arquivo é preciso comparar o STEM — e exigir que o próximo caractere seja '.',
  // senão 'Create' capturaria 'CreateV3', que é outra tela. Medido: sem o stem, a Venda (CreateV3)
  // não casava com a própria declaração de [W] e saía carimbada como se fosse da conta do dono.
  const casa = (a) => {
    if (a.endsWith('/')) return charter.startsWith(a) || caminho.startsWith(a);
    if (charter.startsWith(a) || caminho.startsWith(a)) return true;
    const stem = a.replace(/.[A-Za-z0-9]+$/, '');
    if (stem === a) return false;
    return (charter.startsWith(stem) && charter.charAt(stem.length) === '.')
        || (caminho.startsWith(stem) && caminho.charAt(stem.length) === '.');
  };
  for (const f of foraDestaConta) {
    if (f.arquivos.some(casa)) {
      return { conta: f.conta, projeto: null, espelho: null, classe: 'outra-conta',
               nota: 'origem externa declarada por ' + f.declaradoPor + ' em ' + f.em + ' (' + f.quem + ') — ausência aqui NÃO é drift' };
    }
  }

  // 2º: âncora dentro de um espelho conhecido → a conta é a dona daquele projeto.
  for (const [chave, p] of Object.entries(PROJETOS)) {
    if (caminho.startsWith(p.espelho)) {
      return { conta: p.conta, projeto: chave, espelho: p.espelho, classe: 'espelhada',
               nota: 'projeto ' + p.id + ' (' + p.nome + ')' };
    }
  }

  // 3º: sem protótipo POR DECISÃO — herda Padrão de Tela. Não é buraco.
  if (entrada.isNa) {
    return { conta: null, projeto: null, espelho: null, classe: 'sem-prototipo',
             nota: entrada.declaracaoNa || 'herda o Padrão de Tela (decisão declarada no charter)' };
  }

  // 4º: tem âncora, mas fora de qualquer espelho → arquivo local sem dono remoto.
  if (caminho) {
    return { conta: null, projeto: null, espelho: null, classe: 'local-sem-dono',
             nota: 'âncora fora dos espelhos: ' + caminho + ' — não vem de projeto Cowork nenhum' };
  }

  return { conta: null, projeto: null, espelho: null, classe: 'indefinida',
           nota: 'charter sem fonte declarada' };
}

function procedencia() {
  let entradas;
  try {
    const raw = execFileSync(process.execPath, [join(HERE, 'ancora.mjs'), '--list', '--json'],
      { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
    entradas = JSON.parse(raw);
  } catch (e) {
    // Falha de leitura NÃO vira "0 telas": isso afirmaria saúde sobre o que não foi medido.
    console.error('PROCEDENCIA: não consegui ler ancora.mjs --list --json — ' + (e && e.message));
    process.exit(2);
  }

  // DONO por usuário (§9.3 da proposta 2026-09-24): a conta diz de ONDE veio o arquivo; o dono
  // diz QUEM responde pela tela. Vem do design-lock (o dono do tema) — nunca inferido da pasta.
  const lockInfo = lerLock();
  if (lockInfo.existe && !lockInfo.lock) {
    console.error('PROCEDENCIA: ' + LOCK_PATH + ' ilegível — ' + lockInfo.erro);
    process.exit(2);
  }
  const porClasse = new Map();
  const linhas = [];
  for (const e of entradas) {
    const p = procedenciaDaTela(e);
    porClasse.set(p.classe, (porClasse.get(p.classe) || 0) + 1);
    const tela = String(e.charter || '').replace(/\\/g, '/').replace(/^.*?Pages\//, '').replace(/\.charter\.md$/, '');
    linhas.push({ page: e.page, charter: e.charter, ancora: e.caminho || null, ...p, dono: donoDaTela(lockInfo.lock, tela) });
  }

  if (process.argv.includes('--json')) { console.log(JSON.stringify({ contas: CONTAS, projetos: PROJETOS, telas: linhas }, null, 2)); return; }

  console.log('PROCEDÊNCIA POR TELA — de qual conta/projeto vem a fonte de design');
  console.log('(derivado de ancora.mjs --list --json + PROJETOS/CONTAS · não editar à mão)');
  console.log('');
  console.log('CONTAS:');
  for (const c of Object.values(CONTAS)) {
    console.log('  ' + c.id.padEnd(7) + c.dono.padEnd(34)
      + (c.alcancavel ? 'alcançável daqui' : '⚠ INVISÍVEL daqui (outra conta)')
      + (c.espelhada ? ' · espelhada' : ' · sem espelho')
      + (c.aposentada ? ' · APOSENTADA em ' + c.aposentada.em + ' (pasta = histórico; trabalho novo -> ' + c.aposentada.destino + ')' : ''));
    for (const k of c.projetos) console.log('          └─ ' + PROJETOS[k].espelho.padEnd(30) + PROJETOS[k].id + '  "' + PROJETOS[k].nome + '"');
    if (c.dsCopia) console.log('          └─ ' + ('DS = ' + c.dsCopia.mesmoDsQue + ' (cópia) ').padEnd(30) + c.dsCopia.id + '  "' + c.dsCopia.nome + '" · fonte: ' + c.dsCopia.fonte);
  }
  console.log(''); console.log('RESUMO (' + entradas.length + ' telas com charter):');
  for (const [k, v] of [...porClasse.entries()].sort((a, b) => b[1] - a[1])) {
    console.log('  ' + String(v).padStart(4) + '  ' + k);
  }
  console.log(''); console.log('DONO POR USUÁRIO (' + LOCK_PATH + ' · pegar/devolver tela = PR, merge do [W] autoriza):');
  console.log('  ' + DONOS.map((d) => d + '=' + linhas.filter((l) => l.dono === d).length).join(' · ')
    + ' · sem dono=' + linhas.filter((l) => !l.dono).length);
  console.log(''); console.log('TELAS QUE NÃO VÊM DE ESPELHO (as que merecem olho):');
  const suspeitas = linhas.filter((l) => l.classe === 'outra-conta' || l.classe === 'local-sem-dono' || l.classe === 'indefinida');
  if (!suspeitas.length) console.log('  (nenhuma)');
  for (const l of suspeitas) { console.log('  [' + l.classe + '] ' + l.page); console.log('        ' + l.nota); }
}

function painel() {
  console.log('  De qual CONTA vem cada tela?  node scripts/design/protocolo.config.mjs --procedencia [--json]');
  console.log('');
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
  else if (argv.includes('--procedencia')) procedencia();
  else if (argv.includes('--json')) {
    console.log(JSON.stringify({ projetos: PROJETOS, stagingDir: STAGING_DIR, mirrorDir: MIRROR_DIR, fases: FASES, preflightGates: PREFLIGHT_GATES }, null, 2));
  } else painel();
}
