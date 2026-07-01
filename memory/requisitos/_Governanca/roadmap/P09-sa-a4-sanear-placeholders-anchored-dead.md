---
roadmap_item: P09
slug: sa-a4-sanear-placeholders-anchored-dead
onda: 4
status: executed
executed_at: "2026-07-01"
executed_prs: [3473, 3475]
depende_de: []
destrava: [P10]
related_adrs: [273, 271, 270, 256]
esforco_estimado: "0.8d codável + IA-pair (margem 2x) · 0d relógio real (sem janela humana, exceto ~2 decisões de identidade pontuais do Wagner)"
---

# P09 · SA-A4: sanear 22 placeholders + 15 anchored_dead

> **✅ EXECUTADO 2026-07-01** (PRs [#3473](https://github.com/wagnerra23/oimpresso.com/pull/3473) + [#3475](https://github.com/wagnerra23/oimpresso.com/pull/3475), mergeados). **DoD atingido no main: `anchored_dead=0` E `placeholder=0`.**
> Estado vivo divergiu do plano (que media dead=15/placeholder=22): sessões prévias já tinham saneado Jana (4) + NfeBrasil (1). Restou:
> - **12 placeholder = 100% PontoWr2** → backfill de âncora (#3473) + **trio completo** (`@covers-us` nos 4 testes substanciais + 2 testes novos de show `Espelho`/`Importacao` + `Testado em`). O `entry/covers gate` (required) me pegou carimbando "implementada" sem prova de teste — resolvido com cobertura REAL, não fachada.
> - **9 dead = 100% MemCofre** → opção **(d) deletar** o `SPEC.md` arquivado (duplicata do `Modules/SRS` em deprecation), decisão Wagner. Delete cirúrgico (preservou os 8 ADRs de módulo).
>
> **Ressalvas honestas (fora do escopo P09, não fechadas aqui):**
> - `anchor-lint --check` (gate F2 total) **ainda sai 1** por `dead_tests` pré-existentes (Accounting/Crm/Essentials) — outra classe, pré-req do **P10**.
> - Os testes do Ponto rodam na **nightly CT100** (DB real), NÃO nas lanes sqlite do PR-CI. Wagner bancou (2026-07-01) que essa cobertura conta pro trio. **Follow-up durável:** `req_sem_lane` precisa reconhecer a lane CT100-nightly antes de armar, senão PontoWr2 vira falso-vermelho.

## Problema (o que está quebrado, em 2-3 frases)
O `anchor-lint.mjs` detecta hoje, em estado LIVE, **22 placeholders** (campo `**Implementado em:**` legado sem path verificável) e **15 anchored_dead** (anchor preenchido apontando pra path que NÃO existe no disco — "mentira detectável", ADR 0273 §2). Enquanto esses 15 dead persistirem, a catraca F2 (`--check` → exit 1 se `dead>0`) não pode ser ligada — P10 (promoção do gate a required) está bloqueada. O backfill consolidado (PR #2611) foi fechado por conflito e o saneamento dos dead ficou sem owner desde a deferral do Wagner em 2026-06-20.

## Causa-raiz (evidência VERIFICADA — file:line reais que confirmei)
Rodei `node scripts/governance/anchor-lint.mjs --json` na raiz real (`D:\oimpresso.com`). Estado LIVE confirmado: `placeholder=22`, `anchored_dead=15`, `sem_campo=728`, `us_total=823`, `anchor_coverage=7%`. A classificação `anchored_dead` vem de `scripts/governance/anchor-lint.mjs:82-84` (`existsSync` falso OU zero segmento-path).

Os 15 anchored_dead, por módulo (verifiquei cada path com `existsSync` — TODOS confirmados ausentes):

**Jana (4 dead) — código REAL existe, anchor aponta pro path ERRADO (corrigir path, não remover):**
- `memory/requisitos/Jana/SPEC.md:946` (US-COPI-108) → anchor diz `Modules/Jana/Ai/Services/LangfuseClient.php` (ausente); real = `Modules/Jana/Services/Telemetry/LangfuseClient.php` + também aponta `infra/ct100/langfuse/docker-compose.yml` que NÃO existe (infra nunca subiu — provável `_pendente_` ou `_parcial_`).
- `memory/requisitos/Jana/SPEC.md:1038` (US-COPI-111) → `Modules/Jana/Http/Resources/RoadmapTaskResource.php` (ausente, `find` não acha em lugar nenhum) + `resources/js/Pages/Admin/Roadmap/Index.tsx` (ausente; o Roadmap real vive em `resources/js/Pages/ProjectMgmt/Roadmap/Index.tsx`).
- `memory/requisitos/Jana/SPEC.md:1073` (US-COPI-112) → `Modules/Jana/Services/Handoff/HandoffDrafterService.php` (ausente; real provável = `Modules/Jana/Mcp/Tools/HandoffDraftTool.php`) + `Modules/Jana/Providers/OimpressoMcpServer.php` (ausente; real = `Modules/Jana/Mcp/OimpressoMcpServer.php`).
- `memory/requisitos/Jana/SPEC.md:1107` (US-COPI-113) → aponta `memory/schemas/...` (todo o diretório está ERRADO; real = `scripts/memory-schemas/{adr,spec,...}.schema.json` + `scripts/memory-schemas/README.md`) + `.github/workflows/memory-schema-lint.yml` (real = `memory-schema-gate.yml`) + `app/Console/Commands/Jana/ValidateMemorySchemas.php` (real = `Modules/Jana/Console/Commands/JanaValidateMemoryCommand.php`).

**MemCofre (10 dead) — SPEC DUPLICADO de módulo RENOMEADO+DEPRECADO (caso especial, ver §Estado atual):**
- `memory/requisitos/MemCofre/SPEC.md:33,50,67,84,114,130,145,160,175,189` (US-DOCVAULT-001..011, exceto 005) → todos apontam pra `Modules/MemCofre/...` ou paths relativos (`Services/MemoryReader.php`, `Console/Commands/...`). O módulo `Modules/MemCofre/` foi renomeado pra `Modules/SRS/` no commit `8f7a51380e` ("MemCofre→SRS"). Os paths existem hoje em `Modules/SRS/...` (verifiquei: `Modules/SRS/Http/Controllers/DashboardController.php`, `Modules/SRS/Services/MemoryReader.php`, etc. EXISTEM).

**NfeBrasil (1 dead):**
- `memory/requisitos/NfeBrasil/SPEC.md:40` (US-NFE-001) → anchor tem 3 segmentos via markdown-link; 2 existem (`CertificadoController.php`, `CertificadoService.php`), mas `resources/js/Pages/NfeBrasil/Configuracao/Certificado.tsx` NÃO existe (não há diretório `Configuracao/` em `Pages/NfeBrasil/`; nenhum `.tsx` de certificado no tree). 1 path morto basta pra marcar a US inteira como dead (`anchor-lint.mjs:84`).

Os 22 placeholders se distribuem (de `--json`): **NfeBrasil 8**, **PontoWr2 12**, **Jana 1**, **Vestuario 1** (forma `(a criar)` em `memory/requisitos/Vestuario/SPEC.md:173`).

## Estado atual no repo (o que achei ao verificar agora)
- `anchor-lint.mjs` existe e está em `scripts/governance/anchor-lint.mjs` (no repo principal; o worktree atual tem `scripts/governance/` vazio — fonte da verdade é `D:\oimpresso.com`). Modo `--check` (exit 1) já está codificado (`anchor-lint.mjs:192`) mas é **reservado pra F2** (ADR 0273 §4 — hoje roda ADVISORY, exit 0 sempre).
- O diretório `memory/requisitos/_Governanca/roadmap/` NÃO existia até esta sessão (criado agora pra abrigar este plano). Único arquivo prévio em `_Governanca/` era `BLUEPRINT-SDD-ONDA1.md`.
- **DIVERGÊNCIAS que reporto (a evidência do prompt está correta no espírito, mas incompleta):**
  1. **Os 15 dead NÃO são todos "telas não construídas".** Pelo menos 4 (Jana) e 10 (MemCofre→SRS) têm **código real existente** — o anchor só aponta pro path errado por rename/refactor. Saneá-los é majoritariamente **corrigir o path** (existsSync→carimba sha7), NÃO remover a âncora. Só `infra/ct100/langfuse/...` e `RoadmapTaskResource.php` e o `Certificado.tsx` parecem genuinamente não-construídos → viram `_pendente_`/`_parcial_`.
  2. **MemCofre/SPEC.md tem `status: arquivado`** (frontmatter L8) e é um DUPLICADO do módulo que virou `Modules/SRS/` (que por sua vez tem `DEPRECATION-PLAN.md` — está sendo descontinuado). `anchor-lint.mjs` NÃO filtra specs arquivados (não há checagem de `status:` no script — confirmei: linhas 50-54 só extraem frontmatter pra detectar `anchor_format: v1`). Isso significa que 10 dos 15 dead vêm de uma spec morta. Decisão de identidade do Wagner necessária aqui (ver §Passos passo 4).
  3. **PontoWr2 foi renomeado pra `Ponto`** (módulo `Modules/Ponto/` existe; SPEC ainda em `memory/requisitos/PontoWr2/`). Regra de DAG do plano-mãe (`memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md:57`): **codemod/rename do módulo SEMPRE antes do anchor-backfill do mesmo módulo**. Os 12 placeholders de PontoWr2 dependem do rename estar concluído — verificar antes de tocar.
- `git rev-parse --short=7 origin/main` = `a76474a` no momento da verificação (o sha7 de provenance muda a cada commit — usar o sha vigente no momento do backfill, não este).

## Objetivo / DoD (critério de pronto OBJETIVO e checável)
1. `node scripts/governance/anchor-lint.mjs --json | jq .summary.by_state.anchored_dead` retorna **0**.
2. `... .summary.by_state.placeholder` retorna **0**.
3. Cada US saneada cai em um destes estados verificáveis pelo lint: `anchored_ok` (path real + sha7 provenance), `parcial` (`_parcial_` + ≥1 path real + o-que-falta), ou `pendente` (`_pendente_` + justificativa) — NUNCA com path inventado.
4. `node scripts/governance/anchor-lint.mjs --check` (modo F2) retorna **exit 0** (prova mecânica de que dead=0).
5. Nenhum path novo aponta pra arquivo inexistente (regra ADR 0273: "nunca inventar path" — backfill só promove com `existsSync` true).
6. Caso MemCofre resolvido explicitamente (re-point pra SRS OU `_pendente_` OU exclusão do spec duplicado) com decisão do Wagner registrada no PR.

## Passos (ordenados, concretos)
1. **Snapshot inicial.** Rodar `node scripts/governance/anchor-lint.mjs --json > /tmp/anchor-before.json`; confirmar `dead=15`, `placeholder=22`. Este é o baseline da prova.
2. **Jana (4 dead) — corrigir paths reais.** Pra cada US (108/111/112/113): localizar o arquivo real (já mapeei na §Causa-raiz), reescrever o campo `**Implementado em:**` na gramática ADR 0273 §1: ``` `path/real` · verificado@<sha7-vigente> (YYYY-MM-DD) ```. Segmentos genuinamente não-construídos (`infra/ct100/langfuse/...`, `RoadmapTaskResource.php`) → mover pra `_parcial_ · <paths-reais> · verificado@... — falta <X>` OU `_pendente_`. Rodar `anchor-lint.mjs memory/requisitos/Jana/SPEC.md` (diff-aware) pra confirmar Jana dead→0.
3. **NfeBrasil (1 dead + 8 placeholder).** US-NFE-001: manter os 2 paths que existem (Controller+Service), mover `Certificado.tsx` pra `_parcial_` (tela ainda não migrada) OU achar a tela real se existir. Backfill dos 8 placeholders por `existsSync`: path embutido válido→promove com sha7; senão `_pendente_`.
4. **MemCofre (10 dead) — DECISÃO DE IDENTIDADE (Wagner).** Apresentar ao Wagner 3 opções: (a) re-point os 10 anchors pra `Modules/SRS/...` (paths existem hoje, mas SRS está em deprecation — anchor morreria de novo quando SRS for deletado); (b) marcar todos `_pendente_` com justificativa "módulo arquivado, sucessor SRS em deprecação"; (c) **preferida** — adicionar filtro de `status: arquivado` no `anchor-lint.mjs` (1 linha: pular specs com frontmatter `status: arquivado`) + deletar/manter o SPEC duplicado. Opção (c) resolve a causa-raiz (spec morta poluindo o lint) e é a mais honesta. Requer micro-ADR ou nota no PR. **Esta é a única dependência de input humano real.**
5. **PontoWr2 (12 placeholder).** Confirmar que o rename PontoWr2→Ponto está concluído no código (`Modules/Ponto/` é o canônico). Backfill por `existsSync` apontando pros paths em `Modules/Ponto/...`. Se o rename do SPEC-dir ainda não rolou, NÃO tocar — registrar dependência e seguir (regra DAG plano-mãe L57).
6. **Vestuario (1 placeholder).** `SPEC.md:173` forma `(a criar — hoje hooks vivem em DataController genérico)` → marcar `_pendente_` com a justificativa que já está escrita inline.
7. **Jana placeholder (1) + demais.** Backfill mecânico padrão.
8. **Prova final.** Rodar `anchor-lint.mjs --json` + `--check`; confirmar `dead=0`, `placeholder=0`, exit 0. Anexar before/after no PR.
9. **Atualizar scorecard.** Rodar `node scripts/governance/sdd-scorecard.mjs` (delega `anchor_coverage` ao anchor-lint, `gate-selftest.mjs:61`) pra confirmar que a melhora de coverage propagou.

## Arquivos a tocar (lista real)
- `memory/requisitos/Jana/SPEC.md` (linhas ~946, 1038, 1073, 1107 + 1 placeholder)
- `memory/requisitos/NfeBrasil/SPEC.md` (linha ~40 + 8 placeholders)
- `memory/requisitos/MemCofre/SPEC.md` (10 anchors) — OU deleção, conforme decisão passo 4
- `memory/requisitos/PontoWr2/SPEC.md` (12 placeholders)
- `memory/requisitos/Vestuario/SPEC.md` (linha ~173)
- (condicional, opção 4c) `scripts/governance/anchor-lint.mjs` — adicionar skip de `status: arquivado` (~3 linhas em `lintSpec`/seleção de specs)
- (novo, ADR de identidade SRS/MemCofre se opção 4a/4b) `memory/decisions/proposals/...` — só se Wagner pedir formalização

Particionar em ≥3 PRs ≤300 linhas (commit-discipline): PR-A Jana+NfeBrasil+Vestuario (re-point claro), PR-B MemCofre (decisão), PR-C PontoWr2 (depende do rename).

## Gate / counterfactual (COMO eu provo que o gate MORDE)
O gate que valida este item é o `anchor-lint.mjs --check` (modo F2, `anchor-lint.mjs:192`). Prova de que MORDE:
- **Antes (estado LIVE atual):** `node scripts/governance/anchor-lint.mjs --check; echo $?` → **exit 1** (porque `dead=15>0`). Já confirmei que o código faz isso (linha 192: `if (CHECK && (byState.anchored_dead > 0 ...)) process.exit(1)`).
- **Depois (DoD):** mesmo comando → **exit 0**.
- **Counterfactual (prova de mordida):** após sanear, re-introduzir UM path falso num anchor já corrigido (ex: trocar `Modules/Jana/Mcp/OimpressoMcpServer.php` de volta pra `Modules/Jana/Providers/OimpressoMcpServer.php`) e rodar `--check` → DEVE voltar a dar **exit 1** com `💀` apontando a US. Se der exit 0 com path falso, o gate está cego e o saneamento é teatro.
- **Reforço (gate-selftest):** `gate-selftest.mjs` já copia `anchor-lint.mjs` pra sandbox (`gate-selftest.mjs:62`); confirmar que a fixture "ruim" (anchor dead) é pega e a "boa" passa — se não houver fixture de anchor lá, P10 (a promoção) deve adicionar uma. Este item (P09) só garante o estado limpo; P10 liga o `--check` como required.

Nota honesta: P09 NÃO liga gate required (isso é P10). P09 entrega o **estado verde** que torna a promoção segura. A "mordida" provada aqui é a do `--check` manual + o counterfactual acima.

## Dependências (e por que)
- `depende_de: []` — não bloqueado por outro item; o backfill é mecânico e os paths reais já existem no disco.
- **Dependência implícita (DAG plano-mãe L57):** rename de módulo SEMPRE antes do anchor-backfill do mesmo módulo. PontoWr2→Ponto deve estar concluído antes do passo 5; MemCofre→SRS já está. Reportado, não bloqueia os outros módulos.
- `destrava: [P10]` — P10 (promover `anchor-lint --check` a gate required na catraca F2, ADR 0273 §4) é IMPOSSÍVEL enquanto `dead>0`, porque o gate ligado daria exit 1 no main no dia 1 e quebraria todo PR. P09 é o pré-requisito de estado-verde de P10.

## Esforço (recalibrado ADR 0106)
- **Codável com IA-pair (10x + margem 2x):** edição de 5 SPECs (~28 anchors/placeholders) + opcional 3 linhas no `anchor-lint.mjs`. Trabalho determinístico (paths já mapeados, gramática conhecida, prova mecânica). Estimo **~0.8 dia** codável. Maior custo é a verificação anti-alucinação (cada path novo passa por `existsSync` antes de carimbar — disciplina, não tempo).
- **Relógio do mundo real / humano-limitado:** ~0 dias de janela. A ÚNICA dependência humana é **1 decisão de identidade do Wagner** sobre MemCofre (passo 4, opção a/b/c) — minutos, não dias, mas é síncrona e bloqueia PR-B. Sem catraca de N dias aqui (a catraca 14d é de P10, não de P09).
- Total realista: **1 sessão de trabalho** se a decisão MemCofre vier rápido; senão PR-A e PR-C avançam e PR-B espera o Wagner.

## Kill-criteria / risco (quando parar ou reabrir)
- **PARAR e reabrir** se ao tentar re-point um anchor o `existsSync` falhar em TODOS os candidatos (ex: `RoadmapTaskResource.php` não existe em lugar nenhum) → a US é genuinamente `_pendente_`/`_parcial_`, NÃO inventar path. Risco-mãe do item é o operador "consertar" o lint inventando um path plausível — isso recria a mentira que o lint existe pra pegar.
- **REABRIR** se o backfill subir o coverage mas o `--json` mostrar `dead=0` com US que viraram `pendente` em massa sem justificativa real (placeholder disfarçado de pendente). A regra ADR 0273: `_pendente_` é estado legítimo só quando a tela genuinamente não foi construída.
- **Risco MemCofre:** se opção 4a (re-point pra SRS) for escolhida, o anchor morre de novo quando SRS for deletado (DEPRECATION-PLAN ativo). Preferir 4c (filtro de arquivado) pra não criar dívida que volta.
- **Risco de escopo:** NÃO tentar resolver os 728 `sem_campo` aqui (são SA-A5/A6, batches IA — ADR 0273 §"escopo"). P09 é só dead+placeholder. Misturar viola 1 PR = 1 intent e estoura ≤300 linhas.
