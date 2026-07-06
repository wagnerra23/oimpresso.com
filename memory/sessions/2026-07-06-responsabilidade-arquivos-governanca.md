---
date: "2026-07-06"
topic: "Mapa canônico de responsabilidade dos arquivos de governança — por tela, por módulo, por frota"
authors: [C]
related_adrs:
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
outcomes:
  - "Ficha de responsabilidade única de cada tipo de arquivo (7 campos fixos)"
  - "Diagrama de fluxo charter→casos→teste→scorecard→vital-signs→batch→BRIEFING"
  - "Tabela arquivo × gate de 1 olhada + lista honesta de arquivos sem defensor mecânico"
---

# Mapa canônico de responsabilidade — arquivos do sistema de governança

> **Pra que serve:** manual definitivo do Wagner: *quais arquivos cada tela/módulo TEM que ter, o que cada um responde, quem escreve, quem lê, e qual máquina pega quando ele mente*.
> **Fontes:** ADR 0264 (trio + G-1..G-7) · ADR 0256 (catraca+sentinela+gate+cadência) · ADR 0273 (âncoras SPEC) · `scripts/casos-coverage-guard.mjs` · `scripts/qa/vital-signs.mjs`/`mv-metabolismo.mjs` · `scripts/governance/gates-registry.json` (85 workflows) · `scripts/memory-schemas/*.schema.json` · roadmap SDD+MV · exemplos reais (Financeiro/Impostos). Tudo lido de `origin/main@2dbdceed`.
> **Lei-mãe (ADR 0256):** *o que é derivado + enforçado sobrevive; o que é escrito + lembrado apodrece.* Cada ficha abaixo diz em qual lado o arquivo está.

Estado da frota no 1º snapshot MV1 (2026-07-06): **234 telas · 219 com scorecard · 141 com charter · 25 com casos.md · 15 stale**.

---

## Como ler cada ficha

| Campo | Significado |
|---|---|
| **Arquivo** | nome/path pattern |
| **Responsabilidade única** | a pergunta que SÓ ele responde |
| **Quem escreve** | humano/agente/máquina + quando |
| **Quem lê** | consumidor real (Wagner, sessão CC, gate CI, metabolismo, Jana/MCP) |
| **Gate que o defende** | CI/hook que pega falta/mentira/drift (ou "NENHUM — descoberto") |
| **Frescor** | como envelhece e quem detecta |
| **Counterfactual** | se mentir/sumir, o que quebra e quem pega |

---

# Nível A — POR TELA (trio + satélites)

Toda `.tsx` roteada em `resources/js/Pages/**` (fora `_components/`) tem obrigação de nascer com o **trio**: `.tsx` + `.charter.md` + `.casos.md` (ADR 0264 G-1). Scorecard, E2E e visual-comparison orbitam o trio.

## A1 · `<Tela>.tsx`

| Campo | Conteúdo |
|---|---|
| **Arquivo** | `resources/js/Pages/<Mod>/<Tela>.tsx` |
| **Responsabilidade única** | O comportamento REAL da tela — é o único arquivo que o usuário de fato executa. |
| **Quem escreve** | Agente CC (sessão de build, sob mwart-process/aplicar-prototipo) ou dev humano; sempre via PR. |
| **Quem lê** | Usuário final (build Vite), gates DS inteiros, vital-signs (universo de telas), guard G-6 (data do commit). |
| **Gate que o defende** | Bateria DS: `ds-gate` · `foundation-ratchet` · `pageheader-gate` · `eslint/stylelint-gate` · `a11y-gate`/`a11y-axe-gate` · `visual-regression` · `contrato-de-tela` · `no-mock-gate` · `layout-primitives-guard` · `casos-gate` G-1 (tela nova sem trio bloqueia). Hook runtime `block-mwart-violation.ps1` (Edit sem RUNBOOK de tela). |
| **Frescor** | Não envelhece — ele É a verdade. São os IRMÃOS que envelhecem contra ele: G-6 compara commit do `.tsx` vs `last_run` do casos; vital-signs compara vs `graded_at` do scorecard. |
| **Counterfactual** | Se some, a rota quebra (smoke real R1 pega em prod; CI de build pega antes). Se muda sem atualizar irmãos, quem pega é G-6 (casos stale) + sentinela de frescor do MV1 — não o próprio arquivo. |

## A2 · `<Tela>.charter.md` — a LEI da tela

| Campo | Conteúdo |
|---|---|
| **Arquivo** | `resources/js/Pages/<Mod>/<Tela>.charter.md` (ao lado do `.tsx`) |
| **Responsabilidade única** | O CONTRATO homologado da tela: Mission (1 frase), Goals (faz), **Non-Goals (NÃO faz — anti-alucinação)**, UX targets, hooks, `related_prototype` (proveniência do design), `related_us` (join com SPEC), `smoke` (última prova real). |
| **Quem escreve** | Agente CC ao criar/migrar a tela (skill `charter-write`); Wagner homologa (`status: live` + `last_validated`); bump a cada mudança de contrato. |
| **Quem lê** | Sessão CC ANTES de editar o `.tsx` (skill `charter-first` + `charter-fetch` MCP), screen-qa (deriva UCs dele), `ancora.mjs` (âncora de design vem do `related_prototype`, nunca "no olho"), charter-us-lint (join US), vital-signs (presença). |
| **Gate que o defende** | `casos-gate` G-1 (ausência bloqueia tela nova) · `memory-schema-gate` (frontmatter: `page`/`component`/`status` obrigatórios, owner enum) · `charter-refs-gate` (refs quebradas ≤ teto, catraca) · `charter-us-gate` (advisory — `related_us` presente) · hook `ancora-guard` (print não-declarado pelo charter não vira "design"). |
| **Frescor** | `last_validated` + campo `smoke` datado. Envelhece quando o `.tsx` muda sem bump — **atenção:** gate "charter tocado no diff" foi avaliado e DESCARTADO (proibições 2026-07-01: presença ≠ correção); o enforcement de comportamento é o casos.md+teste, o bump de charter é higiene. |
| **Counterfactual** | Se mentir (declara feature que a tela não tem), o UC derivado dele falha no teste (G-2/G-7) — o charter mente, o caso desmente. Se sumir, `casos-gate` fica vermelho pra tela nova e a sessão CC perde a lei → risco de re-alucinar Non-Goals (foi exatamente o buraco da "locação de caçamba"). |

## A3 · `<Tela>.casos.md` — o contrato de NÃO-REGRESSÃO

| Campo | Conteúdo |
|---|---|
| **Arquivo** | `resources/js/Pages/<Mod>/<Tela>.casos.md` (ao lado do `.tsx`) |
| **Responsabilidade única** | Os casos de uso verificáveis (UC-XXX-NN, Dado/Quando/Então + "Pronto quando"), cada um com `Status:` (✅/🧪/⬜/❌) e âncora no teste que o defende. É o elo tela↔teste. |
| **Quem escreve** | Agente screen-qa (derivado do CHARTER/SPEC/ADR — **nunca do código**, anti-tautologia §Ideias descartadas); header `owner` + `last_run` obrigatórios (G-5). |
| **Quem lê** | `casos-coverage-guard.mjs` (G-2/G-5/G-6/G-7), Playwright (UC vem daqui, MV5), sessão CC antes de mexer na tela, vital-signs (`casos_pct` por módulo). |
| **Gate que o defende** | `casos-gate` — 4 dentes próprios: **G-2** UC sem teste que cite o ID = órfão · **G-5** sem `owner`+`last_run`+`Status:` por UC = inválido · **G-6** `.tsx` com commit mais novo que `last_run` = STALE · **G-7** `Status: ✅` tem que bater com o veredito REAL do JUnit (`casos-test-results.json`) — ✅+teste-vermelho = mentira detectada. `guards-meta-gate` testa o próprio guard. |
| **Frescor** | `last_run` amarrado a mudança de CÓDIGO via git (G-6), não wall-clock — o melhor frescor do sistema. |
| **Counterfactual** | É o arquivo MAIS defendido do sistema: se mentir no Status, G-7 pega; se apodrecer, G-6 pega; se o UC ficar sem teste, G-2 pega; se sumir, G-1 pega. Único furo: UC mal-escrito (aceite frouxo) passa — defesa é o gate pré-adoção "asserção cita âncora de contrato" (revisão humana). |

## A4 · Scorecard de tela — `memory/governance/scorecards/screens/<mod>-<tela>.yaml`

| Campo | Conteúdo |
|---|---|
| **Arquivo** | `memory/governance/scorecards/screens/*.yaml` (219 hoje; template `scorecards/_template.yaml`) |
| **Responsabilidade única** | A NOTA de qualidade UX da tela: 16 dimensões + `nota` + `nivel` + `baseline_anterior` (catraca) + `graded_at` + `gaps` rankeados com fix proposto. Fonte-única da qualidade de telas (FONTE-UNICA-QUALIDADE-TELAS.md). |
| **Quem escreve** | Agente screen-qa (LLM-as-judge 16-dim) — header diz explicitamente "NÃO editar à mão". `source:` registra a proveniência (ex: `mv-batch-2026-07-06`). |
| **Quem lê** | `vital-signs.mjs` (agrega por módulo, regra "pior tela puxa"), `screen-grades-ratchet.mjs` (catraca), `mv-metabolismo` (indireto, via snapshot), Wagner (board derivado). |
| **Gate que o defende** | `screen-grades-ratchet` (nota não cai abaixo de `baseline_anterior` sem aprovação) + `screen-coverage-gate` (mapa de cobertura). |
| **Frescor** | `graded_at` vs prazo por criticidade: **>30d dinheiro/fiscal, >60d resto = STALE** (vital-signs); stale multiplica a prioridade na fila ×1.5. Tela sem scorecard = nota 0 na fila ("não medido ≠ bom"). |
| **Counterfactual** | Se mentir pra cima (nota inflada), a catraca perversamente TRAVA a mentira — defesa é o re-grade do metabolismo + `source:` auditável. Se apodrecer, vital-signs flagueia stale e o metabolismo re-prioriza sozinho. Se sumir, a tela volta pro topo da fila com prioridade máxima (comportamento correto: Impostos entrou no batch 2026-07-06 com prioridade 600 exatamente assim). |

## A5 · `<tela>-visual-comparison.md` — gate visual F1.5/F3 (MWART)

| Campo | Conteúdo |
|---|---|
| **Arquivo** | `memory/requisitos/<Mod>/<tela>-visual-comparison.md` (ex: Financeiro/dre-visual-comparison.md) |
| **Responsabilidade única** | A comparação 15-dimensões protótipo Cowork × tela implementada + registro do "Wagner aprovou o SCREENSHOT" (ADR 0107/0114) — a prova de que o design entregue é o design aprovado. |
| **Quem escreve** | Agente CC via skill `mwart-comparative` ANTES de editar a Page; Wagner aprova o screenshot (~10min síncrono). |
| **Quem lê** | Wagner (aprovação), sessão de build (draft→aprovado), auditorias de design. |
| **Gate que o defende** | **Só hook runtime** (`block-mwart-violation.ps1` exige o RUNBOOK da tela; a skill exige o visual-comparison antes do Edit). O CI `mwart-gate.yml` foi **DELETADO** (ADR 0271 — era soft/teatro). |
| **Frescor** | Congela no aprovado; envelhece a cada redesign. Ninguém detecta mecanicamente. |
| **Counterfactual** | Se sumir/mentir, nada no CI pega — a defesa real virou o par `casos-gate`+`screen-coverage` + smoke R1 pós-merge. É artefato de PROCESSO (aprovação), não de regressão. **Semi-descoberto por decisão consciente da 0271.** |

## A6 · Spec E2E — `e2e/<slug>.spec.ts` (Playwright)

| Campo | Conteúdo |
|---|---|
| **Arquivo** | `e2e/*.spec.ts` (hoje: oficina-uc06-gate-etapa, oficina-os-funcional-fluxo, sells-index, sells-venda-balcao) |
| **Responsabilidade única** | Prova de COMPORTAMENTO em navegador real dos UCs críticos (ADR 0264 G-3) — o único nível que pega "código existe mas não funciona" (L-24 presença ≠ correção). |
| **Quem escreve** | Agente screen-qa, derivado dos UCs do `casos.md` (nunca do código); locators `getByRole`/`data-testid`, NUNCA classe CSS. |
| **Quem lê** | `e2e-gate` no CI; G-2 aceita o spec como "teste que cita o UC". |
| **Gate que o defende** | `e2e-gate` (bloqueante nos 3 UCs críticos determinísticos; demovido de required pela poda ADR 0314 D-1 junto com a11y — verificar branch protection viva antes de afirmar terminal). Regressão data-driven tipo Radix-empty-value só ELE pega antes do prod. |
| **Frescor** | Quebra sozinho quando a tela muda (é teste) — auto-fresco. Flaky = quarentena, nunca required LLM-eval. |
| **Counterfactual** | Se sumir, o UC volta a ser órfão → G-2 pega. Se o locator apodrecer, o spec falha vermelho (ruído, não mentira). O furo histórico dele é ausência de escala: 4 specs pra 234 telas (MV5 pendente, depende de MV3). |

## A7 · `<Tela>.review.md` — round de smoke pós-merge

| Campo | Conteúdo |
|---|---|
| **Arquivo** | `<Tela>.review.md` (previsto pela skill `tela-smoke-pos-merge`: round N + console errors + perf) |
| **Responsabilidade única** | O laudo do smoke visual pós-merge em prod (screenshot 1440+1280 + erros de console), por round. |
| **Quem escreve** | Agente CC via skill `tela-smoke-pos-merge` após merge que toca a tela. |
| **Quem lê** | Wagner (notificação via mcp_alertas), próxima sessão. |
| **Gate que o defende** | **NENHUM — descoberto.** O hook `post-merge-ui-smoke-required.ps1` cobra o ATO do smoke (screenshot via browser MCP antes de declarar "pronto"), mas não a persistência do laudo. |
| **Frescor** | Cron daily 09:00 BRT previsto pra telas live ≥7d sem refresh (skill), sem enforcement. |
| **Counterfactual** | **Zero instâncias em `origin/main` hoje** — o artefato definido pela skill não está sendo materializado. Se o smoke acontece mas o laudo não persiste, a prova morre no chat. Candidato nº 1 a reconciliar (ou absorver no scorecard `smoke:`/campo `smoke` do charter, que JÁ cumpre esse papel na prática — 1 tema = 1 doc). |

---

# Nível B — POR MÓDULO

## B1 · As 8 peças de scaffold (RUNBOOK-criar-modulo)

| Campo | Conteúdo |
|---|---|
| **Arquivo** | `Modules/<Nome>/`: **1** `module.json` (providers) · **2** `composer.json` (psr-4) · **3** `Config/config.php` · **4** `Providers/<Nome>ServiceProvider.php` + `RouteServiceProvider.php` · **5** `Http/Controllers/DataController.php` (3 hooks: `superadmin_package`/`user_permissions`/`modifyAdminMenu`) · **6** `Http/Controllers/InstallController.php` (extends `BaseModuleInstallController`) · **7** `Routes/web.php` com as **3 rotas Install** · **8** `Resources/lang/pt-BR/` — mais a entrada em `modules_statuses.json` na raiz. |
| **Responsabilidade única** | Fazer o módulo EXISTIR pro runtime: aparecer em `/manage-modules` com Install funcional, entrar na sidebar, registrar permissões nas 3 camadas (pacote/business/Spatie). |
| **Quem escreve** | Agente CC na criação (skill `criar-modulo`, imitando Jana/Repair/Project — ADR 0011); raramente muda depois. |
| **Quem lê** | nWidart + UltimatePOS core (runtime), Install/ModulesController, sidebar AppShellV2. |
| **Gate que o defende** | Sem gate dedicado de scaffold — a defesa é o RUNBOOK (checklist) + `modules-pest`/lanes Pest por módulo + smoke R1. Sem as 3 rotas Install, o botão vira `#` silenciosamente (incidente ConsultaOs 2026-05-04). |
| **Frescor** | Quase estático; drift aparece como "botão sem ação"/módulo fora do menu. |
| **Counterfactual** | Faltou peça → módulo invisível ou ininstalável em prod; nenhum CI pega (é comportamento de runtime UltimatePOS). Quem pega é o smoke R1 e o cliente. **Gap conhecido e aceito** (frequência de criação de módulo é baixa; RUNBOOK validado 2×). |

## B2 · `memory/requisitos/<Mod>/SPEC.md` — o contrato funcional

| Campo | Conteúdo |
|---|---|
| **Arquivo** | `memory/requisitos/<Mod>/SPEC.md` (57 no repo) — US `US-XXX-NNN` + regras `R-XXX-NNN`, cada US com `**Implementado em:**` e `**Testado em:**` |
| **Responsabilidade única** | O QUE o módulo deve fazer, US por US, com âncora verificável spec↔código: gramática ADR 0273 (`` `path` · verificado@sha7 (data)`` ou sentinelas `_parcial_`/`_pendente_`/`_lacuna_`). |
| **Quem escreve** | Agente CC ao aprovar escopo (US nova) ou reconciliar código-sem-US (retro-US, ex US-FIN-062); backfill de âncora via batches IA com refutador G5 (P10). Wagner aprova escopo. |
| **Quem lê** | Sessão CC no PRÉ-FLIGHT (Regra Primária: ler SPEC antes de Edit em `Modules/<X>/`), `anchor-lint.mjs`, `charter-us-lint` (join), sdd-scorecard (`anchor_coverage` — 88.9% global hoje), module-grade. |
| **Gate que o defende** | `memory-schema-gate` (frontmatter: `module`/`version`/`last_updated` obrigatórios) · `anchor-drift.yml` (anchor-lint diff-aware no PR + cron full-tree; ADVISORY F1, catraca F2 planejada) · `charter-us-gate` (tela órfã de US exposta) · ledger de backfill (PR de âncora sem ledger → umbrella vermelho). |
| **Frescor** | `verificado@sha7 (data)` é a proveniência: código moveu → path some → anchor quebrado = "mentira detectável" (conta como não-coberto). Re-carimbo via `--fix` do lint. |
| **Counterfactual** | Se a âncora mentir (path inexistente), anchor-lint flagueia. Se a US mentir sobre COMPORTAMENTO (status done sem funcionar), o anchor não pega — quem pega é o casos.md+teste da tela (por isso o trio existe). Se o SPEC sumir, o PRÉ-FLIGHT fica cego e o preflight-modulo hook ainda deixa passar (só avisa) — drift de escopo. |

## B3 · `memory/requisitos/<Mod>/BRIEFING.md` — a verdade destilada

| Campo | Conteúdo |
|---|---|
| **Arquivo** | `memory/requisitos/<Mod>/BRIEFING.md` (frontmatter `distilled_at`/`distilled_by`) |
| **Responsabilidade única** | 1 página executiva: capacidades REAIS + gaps + última mudança + proveniência (lista de sessions/handoffs/audits de onde foi destilado). É o que Wagner lê pra saber o estado sem pedir. |
| **Quem escreve** | Máquina: `jana:distill-module-truth` (destilador) + skill `brief-update` (Tier B pós-merge). NÃO é editado à mão. |
| **Quem lê** | Wagner, brief diário, onboarding do time MCP, capterra/comparativo. |
| **Gate que o defende** | `briefing-code-staleness.yml` — **reporter ADVISORY** (exit 0 sempre): mede porta-vs-CÓDIGO (commits em Modules/+Pages/ depois do `distilled_at`). Criado após o incidente Compras: BRIEFING **41 dias / 18 commits atrás** do código apesar da regra "atualiza por PR" (soft não disparou). |
| **Frescor** | `distilled_at` vs último commit do módulo — o sentinela reporta, ninguém bloqueia. |
| **Counterfactual** | Se mentir/apodrecer, Wagner decide com estado falso do módulo (foi o custo real do #3714). O reporter agora ACUSA, mas não impede — **defensor parcial**; a promoção a algo que morde esbarra na lei ADR 0314 (higiene ≠ required). |

## B4 · `memory/requisitos/<Mod>/RUNBOOK-*.md` — receitas reproduzíveis

| Campo | Conteúdo |
|---|---|
| **Arquivo** | `RUNBOOK-<tema|tela-kebab>.md` (ex: Financeiro/RUNBOOK-cobranca.md, RUNBOOK-unificado.md; Infra/RUNBOOK-criar-modulo.md) |
| **Responsabilidade única** | COMO operar/reproduzir um procedimento (passos executáveis + pegadinhas catalogadas + estado final esperado) — o anti-"cada sessão redescobre". |
| **Quem escreve** | Agente CC ao validar um procedimento (skill `cockpit-runbook` pra telas); `owner` (W/F/M/L/E) + `last_validated` obrigatórios. |
| **Quem lê** | Sessão CC no PRÉ-FLIGHT, hook `block-mwart-violation.ps1` (RUNBOOK de tela é PRÉ-CONDIÇÃO pra editar `Pages/<Mod>/<Tela>.tsx`), time MCP. |
| **Gate que o defende** | `memory-schema-gate` (owner enum estrito, `last_validated` date) + hook MWART (a EXISTÊNCIA do RUNBOOK de tela bloqueia Edit em runtime). Conteúdo: nenhum. |
| **Frescor** | `last_validated` manual; nenhum sentinela compara RUNBOOK vs código. |
| **Counterfactual** | Se o passo mentir (comando mudou), a sessão que seguir quebra e conserta — detecção por USO, não por máquina. Se sumir (tela), o hook MWART bloqueia edição da tela até recriar. Frescor de conteúdo é **descoberto**. |

## B5 · `memory/requisitos/<Mod>/CAPTERRA-FICHA.md` / `CAPTERRA-INVENTARIO.md`

| Campo | Conteúdo |
|---|---|
| **Arquivo** | `CAPTERRA-FICHA.md` (nota 0-100 vs mercado, 10 seções) + `CAPTERRA-INVENTARIO.md` (3 buckets ✅🟡❌ + batch de tasks) |
| **Responsabilidade única** | Onde o módulo está vs os melhores do mundo (features P0-P3 ponderadas) e qual o backlog derivado disso. |
| **Quem escreve** | Agente `capterra-senior` (FICHA, pesquisa profunda) → `/comparativo` (INVENTARIO + batch); Wagner aprova o batch. |
| **Quem lê** | Wagner (decisão de investimento), `audit-to-backlog` (tasks), BRIEFING (proveniência), charter (`parent_capterra`). |
| **Gate que o defende** | **NENHUM — descoberto.** Nem presença nem frescor. |
| **Frescor** | Congela na data da pesquisa; mercado anda. Ninguém detecta. |
| **Counterfactual** | Se apodrecer, decisões de priorização usam benchmark velho — sem alarme. Mitigação implícita: é insumo de decisão pontual, não runtime; re-rodar o agente é barato. Ainda assim, candidato a `reviewed_at` + sentinela de idade (ADR 0256 Onda 3). |

## B6 · `memory/dominio/<modulo>.md` — dicionário de domínio (G-4)

| Campo | Conteúdo |
|---|---|
| **Arquivo** | `memory/dominio/{financeiro,vendas,compras,estoque,fiscal-faturamento,oficina-auto}.md` |
| **Responsabilidade única** | A fonte ÚNICA do VOCABULÁRIO do domínio: conceitos PT-BR canônicos, sinônimos PROIBIDOS, e o bloco ```json de enums machine-checked (`fin_titulos.status: [aberto, parcial, quitado, cancelado]`…). |
| **Quem escreve** | Agente CC com veredito de domínio do Wagner (soberano — ex: erradicação de "locação", ADR 0265); mudar enum = decisão de domínio citando ADR. |
| **Quem lê** | `domain-dict-guard.mjs` (compara enum de migration ⇔ dicionário, ambos os sentidos), sessão CC (vocabulário), Jana (grounding). |
| **Gate que o defende** | `dominio-gate` (G-4) + `domain-dict-baseline.json` (ratchet) + `guards-meta-gate` (testa o guard). |
| **Counterfactual / Frescor** | Se uma migration criar enum fora do dicionário (ou o dicionário declarar enum que não existe), o CI FALHA — é a camada que a alucinação da locação atravessou e agora não atravessa mais ("agora é máquina, não memória"). Se o arquivo sumir, o guard quebra vermelho (fail-closed de fato). Furo residual: vocabulário em PROSA (labels de UI, textos) não é checado — só enums de migration. |

## B7 · `Modules/<X>/Tests/**` + registro no `phpunit.xml`

| Campo | Conteúdo |
|---|---|
| **Arquivo** | `Modules/<X>/Tests/{Feature,Unit}/*Test.php` + testsuite em `phpunit.xml` |
| **Responsabilidade única** | A PROVA executável: cada UC do casos.md e cada `**Testado em:**` do SPEC apontam pra cá; Tier 0 cross-tenant (biz=1 vs biz=99) mora aqui. |
| **Quem escreve** | Agente CC junto com a feature (F2 backend baseline); roda SEMPRE no CT 100/CI, nunca local (hook `block-test-fora-ct100.ps1`). |
| **Quem lê** | Lanes Pest do CI (`modules-pest`, `financeiro-pest`, `nfebrasil-pest` [required — risco fiscal ×150], `jana-pest`, `compras/estoque/arquivos-pest`…), G-2 (grep do UC-id), G-7 (JUnit → `casos-test-results.json`), floor da full-suite (SDD P04). |
| **Gate que o defende** | As próprias lanes + `multi-tenant-gate` + proibição "Tests/ sem phpunit.xml = falsa cobertura" (⚠️ essa proibição é TEXTO — verificar registro é manual) + `mutation-gate` (MSI, o juiz do juiz — MV4 expande pra Tier-0). |
| **Frescor** | Auto-fresco (quebra quando o código muda)… DESDE que rode: teste MySQL-only que vira skip no sqlite = "verde que mente" (caso arquivos-pest/nfebrasil — por isso lanes MySQL dedicadas + skip-as-pass required-ready). |
| **Counterfactual** | Teste tautológico (derivado do código, não do contrato) passa verde com comportamento errado — pior que não ter (§Ideias descartadas 2026-06-05); defesa = âncora de contrato citada + mutation. Teste fora do phpunit.xml = cobertura fantasma que nenhum gate pega hoje. |

## B8 · Scorecard de bucket/módulo — `memory/governance/scorecards/<mod>.yaml` + `buckets/*.yaml`

| Campo | Conteúdo |
|---|---|
| **Arquivo** | `scorecards/{admin,auditoria,governance,vestuario,comunicacaovisual}.yaml` (nota por módulo) + `buckets/{meta_governance,vertical_client_facing}.yaml` (dimensões scoped v4) |
| **Responsabilidade única** | A nota do MÓDULO (rubrica module-grade v3/v4: 9 dimensões core + dimensões do bucket) — visão irmã-maior do scorecard de tela. |
| **Quem escreve** | `php artisan module:grade` / `module:grade-v4 --bucket` (máquina); skill `governance-pr-summary` injeta a nota no corpo do PR. |
| **Quem lê** | Wagner (ranking/bucket), PRs (seção Module Grade), roadmap MV6 (escada M0→M3). |
| **Gate que o defende** | `module-grades-gate` — **DEMOVIDO a advisory** pela poda ADR 0314 D-1 (quality/não-Tier-0). Não re-promover sem reabrir a 0314. |
| **Frescor** | Re-grade sob demanda; sem sentinela de idade própria (o análogo de tela — vital-signs — não cobre bucket). |
| **Counterfactual** | Nota de módulo stale orienta priorização errada; hoje só o hábito do `governance-pr-summary` refresca. **Defensor fraco por decisão consciente (0314).** |

---

# Nível C — FROTA / GOVERNANÇA

## C1 · `memory/governance/vital-signs.json` — o prontuário da frota (MV1)

| Campo | Conteúdo |
|---|---|
| **Arquivo** | `memory/governance/vital-signs.json` (snapshot estável, sorted) |
| **Responsabilidade única** | O agregado POR MÓDULO da saúde de telas: `nota_min` ("pior tela puxa" — média esconde), média, `charter_pct`/`casos_pct`, stale, e a FILA de prioridade (peso_criticidade × (100−nota) × penalidade_frescor ×1.5). |
| **Quem escreve** | Máquina: `scripts/qa/vital-signs.mjs --json`, nightly no workflow `mv-metabolismo.yml` (06:30 BRT). Determinístico, zero LLM. |
| **Quem lê** | `mv-metabolismo.mjs` (único insumo do batch), Wagner (prontuário), MV6 futuro (render no BRIEFING/cockpit). |
| **Gate que o defende** | Advisory por lei (ADR 0314) — não é gate. Selftest `vital-signs.test.mjs` trava o CONTRATO do script (mudar regra verde-fresca exige atualizar header+selftest). |
| **Frescor** | `generated_at` diário via cron; se o cron morrer, o snapshot congela — quem pega é `cron-watchdog.mjs`/olho humano no PR nightly ausente. |
| **Counterfactual** | Se mentir, o metabolismo prioriza errado — mas ele é DERIVADO (scorecards+filesystem), então mentir exige fonte mentindo (defendida em A4) ou bug (defendido pelo selftest). Se sumir, `mv-metabolismo` falha ruidosamente (lê o snapshot como pré-condição). |

## C2 · `memory/governance/vital-signs-history.jsonl` — o trend append-only

| Campo | Conteúdo |
|---|---|
| **Arquivo** | `vital-signs-history.jsonl` (1 linha JSON por dia; NUNCA reescrever linhas antigas) |
| **Responsabilidade única** | A série temporal da frota — a única resposta a "estamos melhorando ou piorando?" (min/média/casos_pct por módulo, por dia). |
| **Quem escreve** | Máquina: `vital-signs.mjs --history` (appendFileSync), nightly. |
| **Quem lê** | Wagner/sessões de retrospectiva; MV6 (gráficos futuros). |
| **Gate que o defende** | **NENHUM mecânico** — o append-only é convenção do header do script; nada impede um PR reescrever linhas antigas (review humano é a única barreira). |
| **Frescor** | Auto (1 linha/dia); buraco de dias = cron morto. |
| **Counterfactual** | Se reescrito, o trend mente silenciosamente e a história da frota se perde. Candidato a check no `memory-health` (linhas antigas imutáveis via git diff — mesmo espírito do governance-gate pra ADR/handoff). |

## C3 · `memory/governance/mv-batches/YYYY-MM-DD.md` — a proposta do metabolismo (MV2)

| Campo | Conteúdo |
|---|---|
| **Arquivo** | `mv-batches/2026-07-06.md` (frontmatter: `date`/`status: proposto|aprovado|executado`/`modulos`/`budget`/`snapshot`) |
| **Responsabilidade única** | O batch de até 5 telas que o sistema PROPÕE trabalhar (fila MV1 + regras de parada: gate-humano-pendente não empilha · verde+fresca pula · batimento 1d/3d/7d por classe · budget). |
| **Quem escreve** | Máquina (`mv-metabolismo.mjs`, zero LLM) via auto-PR **SEM auto-merge**; a sessão de execução troca `status:` pra `executado`. |
| **Quem lê** | **Wagner é o gate**: merge do PR = aprova; fechar = rejeita. Depois: sessão de execução (audit-to-backlog + screen-qa por tela) e o próprio metabolismo (batimento/pendência). |
| **Gate que o defende** | O desenho do loop: batch `proposto|aprovado` pendente BLOQUEIA batch novo (não empilha fila fantasma). 19 checks de contrato no workflow. NUNCA cria task/merge (publication-policy). |
| **Frescor** | 1 por batimento; batch aprovado mas nunca executado fica pendurado — a regra de parada o mantém visível (trava novos), mas ninguém COBRA a execução (sem prazo). |
| **Counterfactual** | Se sumir, o metabolismo re-propõe no próximo ciclo (idempotente). Se o `status:` mentir (`executado` sem execução), o scorecard da tela continua velho → vital-signs re-flagueia e a tela volta à fila — o sistema se auto-corrige com atraso de 1 ciclo. |

## C4 · `scripts/governance/gates-registry.json` — o censo dos gates

| Campo | Conteúdo |
|---|---|
| **Arquivo** | `gates-registry.json` (85 workflows: nome + `classe` gate/meta/automacao/deploy + `terminal` required/cron/automacao/advisory + `anchor` de custo + `promote_by`) |
| **Responsabilidade única** | TODO workflow de `.github/workflows` registrado com classe e justificativa — o anti-"gate fantasma" e o teto de governança (ADR 0298: gate novo declara terminal + âncora de custo; advisory exige vencimento ≤14d cobrado pelo ZELADOR). |
| **Quem escreve** | Agente CC no MESMO PR que cria/remove workflow. |
| **Quem lê** | `memory-health.mjs` Check G (workflow fora do registry = FAIL em todo PR) + Check M (teto), ZELADOR (cobra promote_by vencido), Wagner (censo). |
| **Gate que o defende** | `memory-health.yml` (umbrella, todo PR) — entrada órfã = warn, workflow não-registrado = FAIL. |
| **Frescor** | Amarrado ao diff (registro no mesmo PR); `promote_by` vencido é cobrado por cadência (ZELADOR), não por gate. |
| **Counterfactual** | Se um gate nascer fora do registry, memory-health barra o PR. Se o `terminal` declarado mentir vs a branch protection REAL, quem pega é `protection-drift.yml` (meta) — o par registry+protection-drift fecha os dois lados. |

## C5 · `governance/sdd-scorecard.json` + `-baseline.json` — as 10 métricas SDD

| Campo | Conteúdo |
|---|---|
| **Arquivo** | `governance/sdd-scorecard.json` (agregado: anchor_coverage, ghost_count, front_door_coverage, full_suite floor, coverage_pct…) + baseline com `armed:` por métrica |
| **Responsabilidade única** | O placar HONESTO do processo SDD: mede o que é medível, declara `not_yet_measured` no resto ("mentir 0 seria pior"); baseline capturado na 1ª medição REAL, nunca copiado de plano (anti-stale). |
| **Quem escreve** | Máquina: `sdd-scorecard.mjs` (determinístico, diff vazio em re-run) + `sdd-scorecard-publish.yml` (materializa/nightly). Métrica só ARMA após 3 medições válidas (ADR 0275 §3). |
| **Quem lê** | `sdd-scorecard-ratchet.yml` (**GT-G3 — required, o dente SDD vivo em L3**), avaliador adversarial (`/sdd-avaliar`), brief diário (linha SDD), Wagner. |
| **Gate que o defende** | GT-G3: métrica armada que regride = exit 1 no required. `gate-selftest.yml` (46 counterfactuals — floor 299>298 morde, fonte-ausente morde). `armed ∧ ¬measured` = fail-red (P14). |
| **Frescor** | Nightly; floor stale por suíte que morre mid-run já foi o gargalo-raiz (OOM — fix 4G #3676). |
| **Counterfactual** | Se o placar mentir, o avaliador adversarial quinzenal caça ("a suite mente" é o alvo declarado dele). Se a fonte sumir, fail-red — não silêncio. É o artefato com a melhor cadeia de defesa do repo junto com casos.md. |

## C6 · Baselines de catraca — `scripts/*-baseline.json` + `memory/governance/*-baseline.json`

| Campo | Conteúdo |
|---|---|
| **Arquivo** | `scripts/casos-coverage-baseline.json` · `domain-dict-baseline.json` · `no-mock-baseline.json` · `layout-primitives-baseline.json` · `reuse-duplicates-baseline.json` · `perf-static-baseline.json` · `memory/governance/screen-grades-baseline*.json` · `screen-coverage-baseline.json` · `exposicao-tier0-baseline.json` · `scripts/governance/.memory-health-baseline.json` |
| **Responsabilidade única** | A fotografia do DÉBITO LEGADO aceito: o gate não obriga limpar tudo, só impede PIORAR (violação nova = exit 1; encolher = sempre OK). |
| **Quem escreve** | Máquina via `--write-baseline` (uso CONSCIENTE, com aprovação); ratchet F3 encolhe conforme telas são tocadas. |
| **Quem lê** | O guard gêmeo de cada um, a cada PR. |
| **Gate que o defende** | **`baseline-tamper-guard.yml`** — o anti-grandfather: afrouxar baseline + código no MESMO PR = pego (fecha o vetor #2848). `--check-baseline-shrink` (só-desce; crescimento consciente = label `casos-baseline-grow-approved`). |
| **Frescor** | Baseline que nunca desce é catraca inerte (foi o argumento da 0314 pra demover o foundation-ratchet: 127/15/75 estático). |
| **Counterfactual** | Se alguém inflar o baseline pra passar código sujo, tamper-guard pega. Se o baseline ficar estático pra sempre, ninguém MECANICAMENTE cobra o burn-down — é a fraqueza operacional apontada pela avaliação SDD (composto 79: "burn-down nunca começou"). |

## C7 · `memory/decisions/NNNN-*.md` — ADRs (a lei)

| Campo | Conteúdo |
|---|---|
| **Arquivo** | ADRs Nygard, frontmatter estrito (slug `^\d{4}-[a-z0-9-]+$`, status/authority/lifecycle enums); `proposals/` pra propostas |
| **Responsabilidade única** | POR QUE cada decisão custosa foi tomada — append-only: mudar = nova ADR com `supersedes:`, nunca editar aceita. |
| **Quem escreve** | Agente CC propõe, **Wagner aprova** (caminho canônico); numeração soberana [CL] (ADR 0238). |
| **Quem lê** | Toda sessão (decisions-search MCP), gates que citam âncora, humanos. |
| **Gate que o defende** | `governance-gate.yml` Job 1 (status M/R* em decisions/handoffs = merge bloqueado — ENFORCEMENT do append-only) · `adr-lint` (frontmatter) · `adr-index-gate` (índice GERADO, fonte única — os 4 índices manuais drifavam) · `memory-schema-gate` · check de colisão de número (memory-health; referenciar por SLUG, nunca número). |
| **Frescor** | ADRs não envelhecem — são história. O que envelhece é o VÍNCULO (linha stale citando ADR demovida, ex: 0307 §D) — reconciliação é por nova ADR, não edit. |
| **Counterfactual** | Se editada, governance-gate bloqueia. Se dois números colidirem, memory-health flagueia. Se uma ADR "morrer no tempo" (regra sem gate), é exatamente o cenário que a 0264 existe pra impedir — regra nova relevante DEVE nascer com dente. |

## C8 · `memory/handoffs/YYYY-MM-DD-HHMM-*.md` + `memory/sessions/YYYY-MM-DD-*.md`

| Campo | Conteúdo |
|---|---|
| **Arquivo** | Handoffs (estado pro próximo: `date`/`slug`/`tldr` obrigatórios + seção "Estado MCP no fechamento") · Session logs (o trabalho feito: `date`/`topic` obrigatórios) |
| **Responsabilidade única** | Handoff = ESTADO no fechamento (prova, não promessa); session = NARRATIVA do trabalho. São a matéria-prima do destilador (BRIEFING cita ambos como proveniência). |
| **Quem escreve** | Agente CC ao encerrar sessão (ADR 0130 append-only; skill `encerrar-sessao`); checklist MCP-first ANTES de escrever. |
| **Quem lê** | Próxima sessão (`/continuar`), `jana:distill-module-truth`, Wagner, MCP server (352+ docs sync). |
| **Gate que o defende** | `handoff-integrity` + `handoff-scope-guard` + `handoff-sign-submit` (handoffs) · `memory-schema-gate` (ambos) · `governance-gate` (append-only handoffs) · `dup-detector-gate` (duplicação) · regra Glob-antes-de-criar (proibições). |
| **Frescor** | Não envelhecem (são datados por natureza); o risco é VOLUME — poda via cadência (`consolidate-memory`, ADR 0270 decaimento). |
| **Counterfactual** | Handoff que mente sobre estado (promessa sem prova) → próxima sessão parte de premissa falsa; a seção "Estado MCP" obrigatória mitiga. **Conteúdo de session log não tem verificação** — foi por onde o falso-positivo "12 tier-A" (checkout stale) entrou na avaliação SDD; defesa nova: skeptics exigem prova `rev-parse == origin/main`. |

## C9 · `memory/proibicoes.md` (+ §Ideias descartadas) — o registro de regressões

| Campo | Conteúdo |
|---|---|
| **Arquivo** | `memory/proibicoes.md` — Tier 0 + "tentei, reprovou, não repete" (o que foi tentado · por que caiu · o limite) |
| **Responsabilidade única** | A LISTA NEGRA: o que é proibido sem ADR nova + as ideias mortas que uma sessão futura re-proporia (anti-regressão de DECISÃO, não de código). |
| **Quem escreve** | Agente CC ao catalogar incidente/veredito Wagner; append-only por convenção. |
| **Quem lê** | TODA sessão (é @import do CLAUDE.md — carrega sempre); agentes antes de propor abordagem. |
| **Gate que o defende** | Indireto e fragmentado: várias entradas TÊM dente próprio (hooks block-test-fora-ct100, block-automem, block-claim-without-evidence, dominio:check, governance-gate…), mas **o arquivo em si** não tem gate de conteúdo — sua força é estar no contexto de toda sessão. |
| **Frescor** | Entradas nunca saem sem ADR explícita; risco é crescer até diluir a atenção (hoje já é o maior @import). |
| **Counterfactual** | Se uma entrada sumir, a ideia morta volta (ex: re-promover foundation-ratchet — a entrada de 2026-07-01 existe EXATAMENTE porque o bookkeeping stale quase causou isso). Detecção: nenhuma mecânica — só a leitura obrigatória. |

## C10 · `scripts/memory-schemas/*.schema.json` — o contrato dos docs

| Campo | Conteúdo |
|---|---|
| **Arquivo** | `{adr,charter,handoff,runbook,session,spec}.schema.json` + README (regra "campo novo opcional até backfill") |
| **Responsabilidade única** | O que o frontmatter de CADA tipo de doc canon é OBRIGADO a ter (adr: 9 campos; charter: page/component/status; spec: module/version/last_updated; session: date/topic; runbook: title/owner/last_validated; handoff: date/slug/tldr). |
| **Quem escreve** | Agente CC via PR (mudança de schema = mudança de contrato do repo inteiro). |
| **Quem lê** | `memory-schema-gate` (valida todo doc tocado), skill `memory-schema-preflight` (valida ANTES do commit, mata o loop CI de ~10min). |
| **Gate que o defende** | `governance-script-tests` + `gate-selftest` (os scripts que consomem schema têm teste). |
| **Frescor** | Evolui com grace-period (ex: `anchor_format` opcional até backfill dos 57 SPECs). |
| **Counterfactual** | Se afrouxado indevidamente, docs sem dono/data entram e a base re-apodrece (é o dente da Onda 3 do 0256: `reviewed_at`/`lifecycle` obrigatórios). Mudança passa por PR visível — review humano é o defensor. |

---

# 1 · Diagrama — quem alimenta quem

```
  PROTÓTIPO Cowork (prototipo-ui/*)            ADRs (a lei) ─────────────┐
        │ related_prototype                        │ âncora de contrato  │
        ▼                                          ▼                     │
  ┌──────────────┐   deriva UCs   ┌────────────┐  cita   ┌────────────┐  │
  │ charter.md   │ ─────────────► │ casos.md   │ ◄────── │ SPEC.md    │  │
  │ (LEI da tela)│                │ (UC + aceite)         │ (US+âncora │  │
  └──────┬───────┘                └─────┬──────┘          │ ADR 0273)  │  │
         │ charter-us join (related_us) │ G-2: UC-id      └─────┬──────┘  │
         │                              ▼ citado em            │ anchor-lint
         │                     ┌─────────────────┐             ▼
         │                     │ Pest + Playwright│──JUnit──► casos-test-results.json
         │                     │ (a PROVA)        │             │ G-7: Status ✅ = veredito real
         │                     └─────────────────┘             │
         ▼                                                     ▼
  ┌────────────────────┐  screen-qa (LLM 16-dim)   ┌───────────────────────┐
  │ <Tela>.tsx (REAL)  │ ────────────────────────► │ scorecard screens/*.yaml│
  └────────┬───────────┘   G-6: commit .tsx        │ (nota+catraca+graded_at)│
           │               vs last_run casos       └──────────┬────────────┘
           │ universo de telas                                │ agrega (pior tela puxa)
           ▼                                                  ▼
  ┌──────────────────────────── vital-signs.mjs (MV1, nightly) ───────────┐
  │  vital-signs.json (snapshot) + vital-signs-history.jsonl (trend)      │
  └──────────────────────────────────┬────────────────────────────────────┘
                                     │ fila de prioridade
                                     ▼
                  mv-metabolismo.mjs (MV2) ──► mv-batches/YYYY-MM-DD.md
                                     │           (auto-PR SEM auto-merge)
                          GATE WAGNER│ merge = aprova · close = rejeita
                                     ▼
                  sessão de execução: screen-qa por tela (fecha trio,
                  re-grade scorecard, escreve teste) ──► loop re-alimenta
                                     │
             merges que mudam módulo │
                                     ▼
        jana:distill-module-truth ──► BRIEFING.md (verdade destilada p/ Wagner)
                                     ▲ sentinela: briefing-code-staleness (advisory)
```

Leitura executiva do loop: **contrato desce (charter→casos→teste), medição sobe (scorecard→vital-signs→batch), Wagner decide no meio (merge do batch), e a verdade consolidada volta pra ele (BRIEFING)**. Fora do loop de tela, SPEC↔código é vigiado por anchor-lint e vocabulário↔schema por dominio:check.

---

# 2 · Tabela arquivo × gate (1 olhada)

| Arquivo | Defensor mecânico principal | Modo |
|---|---|---|
| `<Tela>.tsx` | bateria DS + `casos-gate` G-1 + smoke R1 | required (maioria) |
| `<Tela>.charter.md` | `casos-gate` G-1 · `memory-schema-gate` · `charter-refs-gate` · `charter-us-gate` | required / catraca / advisory |
| `<Tela>.casos.md` | `casos-gate` G-1/G-2/G-5/G-6/G-7 + `guards-meta-gate` | required + ratchet |
| Scorecard de tela (`screens/*.yaml`) | `screen-grades-ratchet` + `screen-coverage-gate` + sentinela stale (vital-signs) | catraca + advisory |
| `*-visual-comparison.md` | só hook runtime MWART (CI `mwart-gate` deletado — ADR 0271) | hook |
| Spec E2E (`e2e/*.spec.ts`) | `e2e-gate` + G-2 (UC-id) | gate |
| `<Tela>.review.md` | **NENHUM** (0 instâncias no main) | — |
| 8 peças scaffold | RUNBOOK checklist + lanes Pest + smoke R1 | processo |
| `SPEC.md` | `memory-schema-gate` + `anchor-drift` (anchor-lint) + ledger de backfill | gate + advisory F1 |
| `BRIEFING.md` | `briefing-code-staleness` (reporter, exit 0 sempre) | advisory |
| `RUNBOOK-*.md` | `memory-schema-gate` (frontmatter) + hook MWART (existência p/ telas) | gate + hook |
| `CAPTERRA-*.md` | **NENHUM** | — |
| `memory/dominio/*.md` | `dominio-gate` (G-4) + baseline + `guards-meta-gate` | required-ready ratchet |
| `Modules/X/Tests` + phpunit | lanes Pest (nfebrasil **required**) + `multi-tenant-gate` + `mutation-gate` + G-2/G-7 | misto |
| Scorecard bucket/módulo | `module-grades-gate` (demovido advisory — ADR 0314) | advisory |
| `vital-signs.json` | selftest de contrato; advisory por lei 0314 | automação |
| `vital-signs-history.jsonl` | **NENHUM** (append-only por convenção) | — |
| `mv-batches/*.md` | desenho do loop (pendente trava novo batch) + gate humano Wagner | processo |
| `gates-registry.json` | `memory-health` Check G (FAIL) + Check M (teto ADR 0298) | required (umbrella) |
| `sdd-scorecard.json` + baseline | **GT-G3 ratchet (required)** + `gate-selftest` (46 counterfactuals) | required |
| Baselines de catraca | `baseline-tamper-guard` + `--check-baseline-shrink` | gate |
| ADRs | `governance-gate` (append-only) + `adr-lint` + `adr-index-gate` + colisão (memory-health) | required |
| Handoffs | `handoff-integrity`/`-scope-guard`/`-sign-submit` + `memory-schema-gate` | gate |
| Session logs | `memory-schema-gate` (só frontmatter) | gate fraco |
| `proibicoes.md` | leitura always-on (CLAUDE.md @import); dentes fragmentados por entrada | contexto |
| `memory-schemas/*.json` | `governance-script-tests` + `gate-selftest` + review humano | meta |

---

# 3 · Arquivos SEM defensor mecânico (candidatos a gap — honesto e exaustivo)

Ordenado por risco (custo do apodrecimento × ausência de detecção):

1. **`<Tela>.review.md`** — definido pela skill `tela-smoke-pos-merge`, **0 instâncias em origin/main**. O smoke acontece (hook cobra o ATO), mas o laudo não persiste. Decidir: materializar OU aposentar o pattern e oficializar o campo `smoke:` do charter como o registro canônico (1 tema = 1 doc).
2. **`CAPTERRA-FICHA/INVENTARIO`** — nem presença nem frescor vigiados; benchmark de mercado congelado orienta priorização sem alarme. Fix barato: `reviewed_at` + sentinela de idade no memory-health (ADR 0256 Onda 3, já prevista).
3. **`vital-signs-history.jsonl`** — append-only só por convenção; um PR pode reescrever a história da frota sem nenhum check. Fix barato: check "linhas antigas imutáveis" no memory-health (mesmo espírito do governance-gate pra ADR/handoff).
4. **Conteúdo dos RUNBOOKs** — a EXISTÊNCIA é cobrada (hook MWART, schema), o CONTEÚDO envelhece sem sentinela (`last_validated` é manual). Detecção hoje = sessão quebrar seguindo passo velho.
5. **`*-visual-comparison.md`** — semi-descoberto por decisão consciente (0271 deletou o mwart-gate teatro). OK enquanto casos-gate+screen-coverage seguram o comportamento; registrar que a aprovação visual em si não tem trilha mecânica.
6. **8 peças de scaffold** — falta de peça = módulo quebrado em runtime sem CI que pegue (botão Install `#`). Frequência baixa mitiga; um lint estrutural de `Modules/*/` seria barato se a criação de módulos acelerar com o time MCP.
7. **Teste fora do `phpunit.xml`** — a proibição existe como TEXTO; nenhum gate compara `Modules/*/Tests/` vs testsuites registradas. É "falsa cobertura" invisível — candidato a check determinístico (glob vs XML).
8. **Conteúdo de session logs** — schema valida frontmatter, nada valida claims (vetor do falso-positivo "12 tier-A" por checkout stale). Mitigação já aplicada nos skeptics (`rev-parse == origin/main`), mas session comum segue sem verificação.
9. **`proibicoes.md` como arquivo** — força vem de estar sempre no contexto; nenhuma máquina detecta remoção/edição de entrada (append-only não enforçado ali, diferente de decisions/handoffs). Fix barato: incluir no governance-gate Job 1.
10. **Scorecards de bucket/módulo** — demovidos a advisory pela 0314 (decisão consciente, não re-promover sem reabri-la); registrar que frescor de nota de MÓDULO não tem o análogo do vital-signs de tela (MV6 endereça).
11. **Batch MV aprovado-e-esquecido** — `status: aprovado` sem execução trava batches novos (bom: fica visível) mas ninguém cobra prazo de execução. Fix: idade do batch pendente como linha no Daily Brief.
12. **BRIEFING** — tem reporter (briefing-code-staleness) mas exit 0 sempre; 41 dias de drift foi o custo real que o criou. Classificado aqui como *defensor parcial*: acusa, não impede.

> **Nota de método:** os itens 5, 10 e parte do 12 são "descobertos" por DECISÃO (ADR 0271/0314: required = só Tier-0 dinheiro/PII/multi-tenant/fiscal; higiene fica advisory). Gap ≠ esquecimento nesses casos — a lista existe pra que a decisão continue consciente, não pra forçar promoção.

---

## Regra de bolso final (pro Wagner)

- **Tela nova** nasce com: `.tsx` + `.charter.md` + `.casos.md` (G-1 bloqueia sem) → ganha scorecard no 1º ciclo do metabolismo → UC crítico ganha Playwright.
- **Módulo novo** nasce com: 8 peças scaffold + `SPEC.md` (US com `Implementado em: _pendente_`) + entrada no dicionário de domínio quando tiver enum + `Tests/` registrado no phpunit + BRIEFING destilado.
- **Frota** se mantém sozinha: vital-signs mede toda noite → metabolismo propõe batch → **seu merge é o único gate humano do loop** → screen-qa executa → tudo re-alimenta.
- **Confie mais** no que tem catraca+sentinela (casos.md, scorecard, sdd-scorecard, dicionário); **desconfie por padrão** do que está na lista da seção 3.
