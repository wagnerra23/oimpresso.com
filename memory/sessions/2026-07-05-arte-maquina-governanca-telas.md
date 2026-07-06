---
date: "2026-07-05"
topic: "Estado da arte — máquina de Módulos Vivos (governança como metabolismo): pesquisa 2026 + inventário frota + desenho MV"
authors: [W, C]
---
# Estado da arte — A máquina de MÓDULOS VIVOS (governança como metabolismo)

> **Sessão:** 2026-07-05 · agente `estado-da-arte` · worktree relaxed-galileo-55cd8e
> **Problema:** Wagner quer uma "máquina séria muito bem planejada" que cuide AUTONOMAMENTE do ciclo de governança/qualidade — não só de telas, mas de **cada módulo como organismo vivo** (SPEC + memórias + tarefas MCP integrados), sem ele pedir tela a tela. Frame dele: *"o software deve amadurecer e se tornar vivo por módulo"*.
> **Escopo:** frota inteira (~40 pastas em `Pages/`, ~580 telas .tsx). Financeiro = amostra de diagnóstico detalhado.
> **Restrições:** PT-BR · não commitar · não criar task MCP · não editar código. Só pesquisa + este doc. Multi-tenant Tier 0; smoke sempre biz=1 (nunca biz=4 ROTA LIVRE).

---

## Sumário executivo (1 parágrafo)

Os melhores de 2026 (Meta ACH, GitHub Spec Kit, Playwright Test Agents, Infection/PIT, fitness functions de Neal Ford) convergiram numa receita: **spec é o contrato → mutação é o oráculo do "teste que morde" → agente gera/cura o teste → catraca trava o ganho → cron roda a frota**. O oimpresso **já tem quase todas as peças** — casos-gate/dominio-gate/screen-coverage (ADR 0264), Knowledge Survival catraca+sentinela (ADR 0256), 219 scorecards de tela versionados, screen-qa-specialist, anchor-lint spec↔código (ADR 0273), module-grade v4 com buckets (ADR 0160), audit-to-backlog. **O gap real não é peça, é METABOLISMO**: (1) **orquestração autônoma da frota** (hoje é manual, tela a tela); (2) **mutation-score como oráculo** de que o teste morde (hoje presença ≠ correção); (3) **E2E de comportamento em escala** (15 specs pra ~580 telas); (4) **sinais vitais agregados com frescor** que degradam visivelmente quando o módulo para de ser cuidado. A recomendação é uma **nova Onda no AUTOMATION-ROADMAP.md** + **ADR nova** que institui a *Escada de Maturidade de Módulo* (M0→M3) medida pela **espinha dorsal de scorecards** e cuidada por um **cron/scheduled agent (o "metabolismo")** com gate humano Wagner no ponto de escrita (task/commit).

---

# SEÇÃO 1 — Como os melhores fazem em 2026 (pesquisa limpa, com fontes)

Tabela enxuta dos players de referência (5 dimensões que emergiram):

| Referência | Mecanismo concreto (não buzzword) | Por que é referência |
|---|---|---|
| **Meta ACH / TestGen-LLM** (FSE 2025) | Mutação **guiada por preocupação**: gera poucos mutantes *relevantes* a um risco (ex.: privacidade), usa o mutante NÃO-pego como **prompt** pro LLM gerar o teste que o mata. Agente de "equivalent-mutant detection" precisão 0.79→0.95. Rodou em 10.795 classes; engenheiros aceitaram **73%** dos testes. | Prova em produção que **mutação é o oráculo do "teste que morde"** e que LLM+mutação juntos geram teste NÃO-tautológico. |
| **GitHub Spec Kit** (SDD, open-source late-2025) | Spec = **contrato/fonte-da-verdade**; fluxo Specify→Plan→Tasks→Implement. Agente lê requisito (estilo EARS), gera código E o teste que o verifica. "O spec diz *o quê*; o teste prova que funciona." | Formalizou **spec-driven development** como antídoto ao "vibe coding". Rastreabilidade viva spec↔test↔backlog. Roda em Claude Code/Copilot/Gemini. |
| **Playwright Test Agents** (Planner/Generator/Healer, 2026) | Tri-agente sobre **accessibility-tree** (não pixel): Planner explora e emite plano markdown, Generator compila em TS, **Healer** conserta locator quebrado em runtime (role-based) — 75%+ de sucesso em falhas de seletor. Playwright MCP é o protocolo default. | Padrão de **E2E self-healing gerado de spec/UC** — resolve o custo de manutenção que mata suítes E2E grandes. |
| **Infection (PHP) / PIT (Java)** — mutation testing 2026 | **MSI** (Mutation Score Indicator) = % de mutantes mortos. CI usa `--min-msi` / `--min-covered-msi` como **catraca** (ratchet acima do baseline). Usado como *architecture enforcement*, não só coverage. | Traz o oráculo de mutação pro **stack PHP/Pest** do oimpresso. Custo alto → aplicar **cirúrgico** (só código Tier-0/regra de negócio), não na suíte inteira. |
| **Fitness functions / Evolutionary Architecture** (Neal Ford, 2ª ed.) + **loop engineering** (2026) | Fitness function = teste automatizado, objetivo, repetível de UMA característica arquitetural, embutido no CI → **governança contínua** (feedback a cada commit, não review trimestral). "Loop engineering": programas que rodam agentes **no cron** com guardrails/skills (fix-CI, triagem, cobertura). Token-budget goal-scoped (Codex Goal Mode; Claude scheduled tasks). | Fundamenta o **metabolismo**: governança deixa de ser evento e vira batimento cardíaco automático. E o padrão de **frota de agentes no cron abrindo PRs** com orçamento de token. |

**Achados transversais (o que TODOS fazem):**
1. **Contrato-first, nunca código-first** — teste deriva da spec/UC (Spec Kit, ACH). Teste derivado do código é tautológico (exatamente a proibição §Ideias descartadas do oimpresso).
2. **Mutação, não coverage %, é a métrica de "teste que morde"** — coverage alto com asserção fraca = teatro; mutação expõe (ACH, Infection, PIT).
3. **Self-healing obrigatório em E2E** senão a suíte apodrece (Playwright Healer).
4. **Human-in-the-loop no ponto de escrita** — TraceLLM/Spec Kit geram *candidatos*, humano ratifica (bate com publication-policy: Wagner aprova batch 1×).
5. **Catraca + frescor** — ganho trava; conhecimento tem meia-vida, stale = flag (fitness functions contínuas; Knowledge Survival já é isso no oimpresso).

**Fontes:**
- [Mutation-Guided LLM-based Test Generation at Meta (ACH) — FSE 2025 / arXiv 2501.12862](https://arxiv.org/abs/2501.12862) · [Engineering at Meta — LLMs are the key to mutation testing](https://engineering.fb.com/2025/09/30/security/llms-are-the-key-to-mutation-testing-and-better-compliance/)
- [GitHub Spec Kit (repo)](https://github.com/github/spec-kit) · [Spec-driven development with AI — GitHub Blog](https://github.blog/ai-and-ml/generative-ai/spec-driven-development-with-ai-get-started-with-a-new-open-source-toolkit/) · [SDD Definitive 2026 Guide — BCMS](https://thebcms.com/blog/spec-driven-development)
- [Playwright Test Agents (docs)](https://playwright.dev/docs/test-agents) · [Playwright MCP (repo)](https://github.com/microsoft/playwright-mcp) · [Playwright AI Ecosystem 2026 — TestDino](https://testdino.com/blog/playwright-ai-ecosystem)
- [Infection PHP — guide](https://infection.github.io/guide/) · [Mutation Testing as Architecture Enforcement 2026 — DEV](https://dev.to/gabrielanhaia/mutation-testing-as-architecture-enforcement-infection-in-2026-318c) · [PIT mutation testing Java — JAVAPRO 2026](https://javapro.io/2026/01/21/test-your-tests-mutation-testing-in-java-with-pit/)
- [Fitness Functions — O'Reilly Building Evolutionary Architectures 2nd ed](https://www.oreilly.com/library/view/building-evolutionary-architectures/9781492097532/ch02.html) · [Architectural Fitness Functions — automating governance](https://developersvoice.com/blog/architecture/architectural-fitness-functions-automating-governance/) · [Governing data products using fitness functions — Fowler](https://martinfowler.com/articles/fitness-functions-data-products.html)
- [Loop Engineering — coding agents on cron 2026](https://explainx.ai/blog/loop-engineering-coding-agents-claude-code-guide-2026) · [Codex Goal Mode — token budgets + autonomous continuation](https://codex.danielvaughan.com/2026/04/16/codex-cli-goal-mode-persistent-objectives-token-budgets/) · [Scheduled Tasks for coding agents — AgentsRoom](https://agentsroom.dev/features/scheduled-tasks)
- [TraceLLM — LLM requirements traceability (arXiv 2602.01253)](https://arxiv.org/html/2602.01253v1) · [AI in Requirements Management 2026 — Jama](https://www.jamasoftware.com/blog/ai-requirements-management/)
- [Risk-based test prioritization — TestRail](https://www.testrail.com/blog/risk-based-testing/) · [Flaky test quarantine em monorepo — Airtable Eng](https://medium.com/airtable-eng/how-airtable-manages-flaky-tests-in-a-large-scale-monorepo-5fe09922e90c) · [Visual regression 2026 self-hosted — Lost Pixel](https://www.lost-pixel.com/) / [Argos](https://argos-ci.com/)

---

# SEÇÃO 2 — O que o oimpresso JÁ TEM × o que falta (inventário da frota + honestidade)

## 2.1 Inventário da FROTA INTEIRA (origin/main, 2026-07-05)

Contagem por módulo: telas `.tsx` × `.charter.md` × `.casos.md` × Pest (`Modules/<X>/Tests/*.php`) × E2E. **Não é auditoria tela-a-tela** — é dimensionamento da máquina.

| Módulo (pasta Pages) | .tsx | charter | casos.md | Pest (módulo) | Camada dinheiro/fiscal? |
|---|---:|---:|---:|---:|---|
| **Financeiro** | 60 | 16 | 4 | 77 | 🟥 dinheiro |
| **Sells** (core) | 43 | 8 | 2 | 0 (em `tests/`) | 🟥 dinheiro |
| Cliente | 38 | 7 | 0 | — | 🟨 |
| Admin | 33 | 8 | 0 | — (app) | ⬜ |
| **OficinaAuto** | 30 | 10 | 4 | 44 | 🟨 vertical |
| Ponto | 26 | 0 | 0 | — | 🟨 legal (Portaria 671) |
| Atendimento | 26 | 5 | 0 | — | ⬜ |
| **Fiscal** | 20 | 7 | 7 | — | 🟥 fiscal |
| ads | 19 | 0 | 0 | 19 | ⬜ |
| Jana | 17 | 8 | 0 | 133 | ⬜ IA |
| kb | 16 | 6 | 0 | — | ⬜ |
| team-mcp | 15 | 5 | 2 | — | ⬜ |
| **Whatsapp** | 14 | 1 | 0 | 120 | 🟨 |
| Repair | 13 | 12 | 0 | 22 | 🟨 shared |
| **RecurringBilling** | 13 | 6 | 0 | 38 | 🟥 dinheiro (boleto) |
| Essentials | 13 | 3 | 0 | — | ⬜ |
| ProjectMgmt | 10 | 3 | 0 | — | ⬜ |
| **NfeBrasil** | 9 | 4 | 0 | 47 | 🟥 fiscal |
| Produto | 8 | 8 | 0 | — | 🟨 estoque |
| governance | 7 | 7 | 1 | 47 | ⬜ meta |
| Site / Settings / Purchase / MemCofre / ConsultaOs / Compras / Nfse / **Vestuario** (1/0/0, prod!) / **ComunicacaoVisual** (1/1/0) | ≤7 cada | vários 0 | ~0 | vários | mistos |
| **TOTAL FROTA** | **~580** | **160** | **26** | **~1.070 (app+módulos)** | — |

Números-âncora (origin/main): **160 charters · 26 casos.md · 15 specs `.spec.ts` · 219 scorecards de tela** já versionados em `memory/governance/scorecards/screens/`. A baseline canônica (`screen-coverage-baseline.json`) confirma: **total 275 telas roteadas · charter 132 · e2e 4 · a11y 1 · scorecard 218**. (A diferença 580 vs 275 é telas roteadas vs todos os `.tsx` incluindo `_components`/dormentes.)

**Leituras honestas do inventário:**
- **casos.md é o buraco #1 da frota:** 26 arquivos pra 275 telas roteadas. Fiscal (7/20) e Financeiro (4/17 vivos) puxam; a maioria dos módulos tem **zero**. Sem casos.md não há contrato → não há teste que morde.
- **E2E é o buraco #2:** 4 telas cobertas por Playwright de comportamento numa frota de centenas. Oficina e Sells lideram (herança dos pilotos ADR 0264).
- **Scorecard é o ponto FORTE inesperado:** 219 scorecards já existem, com nota 16-dim, `baseline_anterior` (ratchet embutido), `graded_at` (frescor) e `gaps` rankeados. **Esta é a espinha dorsal de dados que Wagner pediu — já existe, falta orquestrar.**
- **Módulos em construção com quase nada:** Vestuario (1 tela/0 charter, EM PROD biz=4!) e ComunicacaoVisual (1/1/0). Régua diferente da de módulo maduro (Financeiro).
- **Pest volume ≠ Pest contrato:** Jana 133, Whatsapp 120 arquivos — mas o próprio ADR 0264 diz que a maioria é **estrutural** (prova que existe, não que funciona — L-24 presença≠correção).

## 2.2 A espinha dorsal de scorecards — formato REAL × o que falta

Wagner pediu: a máquina interage com os scorecards; eles são a espinha dorsal dos sinais vitais. Formatos reais conferidos em origin/main:

| Scorecard | Formato real hoje | Storage | Frescor? | O que falta pra virar espinha dorsal |
|---|---|---|---|---|
| **screen-grade 16-dim** (`scorecards/screens/*.yaml`, 219) | `nota` · `nivel` · `baseline_anterior` (ratchet) · `peso_real` · `graded_at` · `dimensoes{16}` · `gaps[]` rankeados · `source` | git (YAML/tela) | ✅ `graded_at` existe | **histórico append-only** (hoje sobrescreve; perde antes→depois); **decaimento de valor por idade** (nota de 60d vale menos — anti verde-stale) |
| **module-grade v4** (buckets, `scorecards/<bucket>.yaml`) | 4 buckets + Core D1+D8 (33pts multi-tenant/security) + lens 67pts; `php artisan module:grade-v4 --json` | git + comando | parcial | **agregação a partir das telas** (hoje mede o módulo direto; falta "pior tela puxa a nota do módulo") |
| **sdd-scorecard** (`governance/sdd-scorecard.json`) | 12 métricas por stream (SA/FV/KL/GT/MEM); `anchor_coverage` 84.8% (956 US, 59 SPECs); ratchet armado por métrica após 3 medições | git + CI `sdd-scorecard-publish.yml` | determinístico | já é forte; **plugar as métricas de tela/módulo** aqui como streams |
| **screen-coverage-baseline** (`.json`) | agregados `{charter,e2e,a11y,scorecard}` por módulo | git + gate CI | não | **frescor** e **por-tela** (hoje só conta presença) |

**Conclusão da espinha dorsal:** o dado JÁ existe e é rico. O que falta é (a) **timestamp/frescor de 1ª classe com decaimento**, (b) **histórico append-only** (antes→depois = prova do ganho, casa com R1 evidência), (c) **agregação tela→módulo pelo pior-caso**, (d) um **schema unificado** que o cron leia pra priorizar e o BRIEFING renderize.

## 2.3 Comparativo estado-da-arte × oimpresso (honesto)

| Dimensão (Fase 1) | Estado-da-arte | oimpresso hoje | Distância |
|---|---|---|---|
| Spec como contrato (SDD) | Spec Kit: spec→código→teste rastreável | ADR 0273 anchor-lint (84.8% US ancoradas) + SPEC/US-XXX-NNN + casos.md | **curta** (já bate; falta cobrir casos.md na frota) |
| Teste derivado de contrato | ACH/Spec Kit: asserção vem da spec | casos-gate G-2 exige UC↔teste; proibição explícita anti-tautológico | **curta** (política existe; falta escala) |
| Mutação = oráculo do "teste que morde" | Meta ACH, Infection MSI ratchet | **NÃO existe** Infection/mutação no projeto | **longa** — é o gap conceitual #1 |
| E2E self-healing de comportamento | Playwright Test Agents (Planner/Gen/Healer) | 4 telas cobertas; screen-qa-specialist descreve o ciclo mas é manual | **média** (padrão sabido, escala falta) |
| Regressão visual | Argos/Lost Pixel self-hosted | `toHaveScreenshot` + gate visual Wagner + visual-regression required (render-isolation) | **curta** |
| Catraca/ratchet | fitness functions contínuas + baseline | ADR 0256/0264/0155/0250 — catraca é núcleo do projeto | **oimpresso BATE/supera** (governança executável madura) |
| Orquestração autônoma da frota | loop engineering, cron agents, backlog machine | screen-qa/audit-to-backlog **manuais** (Wagner pede tela a tela); Zelador diário só piloto | **longa** — é o gap #2 (o pedido central) |
| Priorização risco×tráfego | RBT + telemetria de produção | scorecard tem nota; **falta cruzar com tráfego real e criticidade fiscal** | **média** |
| Traceability viva spec↔test↔backlog | TraceLLM + backlog como matriz viva | anchor-lint + tasks MCP + audit-to-backlog existem, mas **não fechados em loop automático** | **média** |
| Sinais vitais / maturidade de módulo | scorecards por bucket (Port/Cortex/OpsLevel) | module-grade v4 buckets + ADR 0105 sinal-cliente | **curta** (falta a *escada* explícita + frescor que degrada) |

**Veredito honesto:** o oimpresso **não está atrás** em governança executável — em catraca/ratchet/fitness-functions ele está à frente da maioria das PMEs e no nível das referências. O atraso é concentrado em **3 lugares**: mutação-oráculo (longa), orquestração autônoma da frota (longa — é o pedido), e E2E/casos.md em escala (média). O resto é orquestrar o que já existe, não reinventar.

---

# SEÇÃO 3 — DESENHO DA MÁQUINA: "Módulo Vivo" (governança como metabolismo)

Não é "uma máquina de QA". É o **loop metabólico** que faz cada módulo nascer com órgãos de governança, amadurecer por níveis objetivos, exibir sinais vitais, e degradar visivelmente se parar de ser cuidado.

## 3.1 A ESCADA DE MATURIDADE DE MÓDULO (M0→M3)

Critérios OBJETIVOS de promoção. Casa com module-grade v4 (buckets) + ADR 0105 (só cresce com sinal de cliente qualificado). Cada módulo tem sua posição no inventário.

| Nível | Nome | Critério de PROMOÇÃO (objetivo, medido pela espinha dorsal) | Exemplo hoje |
|---|---|---|---|
| **M0** | Embrião / docs-only | Tem `module.json` + BRIEFING.md + SPEC.md esqueleto. Sem tela viva ou telas dormentes. **Régua leve** (não cobra casos/E2E). | ComunicacaoVisual, OficinaAuto (parcial) |
| **M1** | Construção | ≥1 tela viva roteada · charter em 100% das telas vivas · SPEC com US ancoradas (anchor-lint sem `sem_campo`) · Pest de contrato nas US críticas. Sinal de cliente qualificado (ADR 0105) pra subir. | Vestuario (em prod mas M1 por cobertura), Whatsapp |
| **M2** | Vivo em prod | 100% telas vivas com **charter + casos.md + teste-de-contrato** (casos-gate=0) · dominio-gate=0 (enum⇔dicionário) · scorecard fresco (<30d) em todas as telas · nota média ≥ meta-do-bucket · smoke biz=1 verde. | Financeiro (quase — casos 4/17), Fiscal |
| **M3** | Maduro / auto-mantido | M2 + **E2E Playwright no caminho crítico** de cada UC crítico · **mutation-score ≥ baseline** no código Tier-0 do módulo · **zero drift spec↔código por N ciclos** (anchor-lint 100%) · **BRIEFING regenerado automaticamente** · nenhuma tela com scorecard stale. O módulo **se obriga sozinho**. | (nenhum ainda — é o alvo) |

**Regra de degradação (anti verde-stale):** um módulo M2/M3 que **para de ser cuidado** (scorecards envelhecem além do limiar) **rebaixa visivelmente** o sinal vital — nunca fica "verde parado". Frescor expira → nível efetivo cai até re-medição. Isso é o coração do metabolismo.

## 3.2 SINAIS VITAIS POR MÓDULO (o prontuário)

Agregados a partir da espinha dorsal de scorecards. Regra: **pior tela puxa** (média esconde risco).

| Sinal vital | Fonte de dado | Regra de agregação |
|---|---|---|
| **Nota** (0-100) | screen-grade 16-dim das telas | mín(telas) exibido ao lado da média; nível do bucket compara com meta |
| **Frescor** | `graded_at` de cada scorecard | idade da tela MAIS VELHA; >30d dinheiro / >60d dormente = flag stale |
| **Drift spec↔código** | anchor-lint (ADR 0273) | % US com âncora viva; `sem_campo` > 0 = drift |
| **Cobertura de contrato** | casos-gate G-1/G-2 | telas com charter+casos+teste ÷ telas vivas |
| **E2E crítico** | specs Playwright | UCs críticos cobertos ÷ UCs críticos declarados |
| **Mutation-score** (se adotado) | Infection MSI no código Tier-0 | MSI ≥ baseline por módulo |
| **Idade do último ciclo do zelador** | log do cron | dias desde a última passagem do metabolismo |

**Onde Wagner consulta:** (1) **BRIEFING.md por módulo** (1 página, já existe skill brief-update) renderiza o prontuário — regenerado a cada ciclo; (2) **Cockpit / dashboard governance** (`Pages/governance`, já tem telas) mostra a frota inteira com semáforo por módulo; (3) **Daily Brief** já tem linha "CHARTERS APODRECENDO" — estende pra "MÓDULOS COM SINAL VITAL CAINDO". **Como contesta uma nota:** a nota é LLM-as-judge versionada; Wagner abre issue/comment citando a dimensão; re-grade dispara. **Como aprova promoção de nível:** o scorecard É a evidência — Wagner vê antes→depois no histórico append-only e ratifica a subida M1→M2→M3 (gate humano).

## 3.3 O METABOLISMO — o loop fechado por módulo (a→g)

```
            ┌─────────────────────────── CADÊNCIA (batimento proporcional à criticidade) ───────────────────────────┐
            │                                                                                                        │
            ▼                                                                                                        │
  (a) INVENTÁRIO/DIAGNÓSTICO ──► (b) GAPS RANKEADOS ──► (c) BATCH DE TASKS ──► [GATE HUMANO: Wagner aprova 1×] ──► (d) EXECUÇÃO
      telas×charter×casos×             por impacto×          proposto no MCP        (publication-policy;              sessão LIMPA
      teste×spec-drift×scorecard       esforço × critic.     (audit-to-backlog)     nunca task/commit sozinho)       POR TELA
      [cron/scheduled agent]           [cron]                [cron propõe]          [HUMANO]                         (screen-qa-specialist)
                                                                                                                          │
  (g) PRÓXIMA VOLTA detecta ◄── (f) BRIEFING REGENERADO ◄── (e) GATES/CATRACAS registram o ganho ◄──────────────────────┘
      drift novo                     (brief-update)             (scorecard antes→depois = prova R1;
      [cron]                         [cron/CI]                   casos-gate/dominio-gate/screen-coverage
                                                                 travam; CT 100 roda Pest/E2E) [CI]
```

**Quem executa cada etapa:**

| Etapa | Executor | Onde roda | Gate humano? |
|---|---|---|---|
| (a) inventário/diagnóstico | **cron / scheduled agent** (o metabolismo) | CT 100 (nunca Hostinger) | não |
| (b) gaps rankeados | cron (lê espinha dorsal + tráfego + criticidade) | CT 100 | não |
| (c) batch de tasks proposto | cron via `audit-to-backlog` | MCP (`tasks-create` com `parent_audit`) | **SIM — Wagner aprova 1× (publication-policy)** |
| (d) execução por tela | **screen-qa-specialist** (sessão limpa/tela, como `aplicar-prototipo`) | sessão Claude Code; Pest/E2E no CT 100 | gate visual Wagner p/ Edit de `.tsx` |
| (e) gates/catracas registram ganho | **CI** (casos-gate, dominio-gate, screen-coverage, visual-regression) | GitHub Actions + CT 100 | não (automático) |
| (f) BRIEFING regenerado | cron/CI via `brief-update` | CI | não |
| (g) próxima volta | cron (detecta drift novo, reabre (a)) | CT 100 | não |

**Regra Tier 0 dura no loop:** o cron **NUNCA** commita/mergeia sozinho em área Tier-0 (dinheiro/PII/multi-tenant/fiscal), **NUNCA** cria task sem o aprovar-1× do Wagner, e smoke é **sempre biz=1** (ADR 0101). O humano fica no ponto de **escrita** (task, commit, promoção de nível). O cron faz o trabalho **read-only + proposta**.

## 3.4 CADÊNCIA proporcional à criticidade (o batimento)

| Classe de módulo | Batimento | Racional |
|---|---|---|
| **Caminho do dinheiro / fiscal** (Sells, Financeiro, RecurringBilling, NfeBrasil, Fiscal) | **diário** | risco Tier-0; erro custa dinheiro real (ex.: incidente num_uf) |
| **Vertical em prod** (Vestuario/ROTA LIVRE, Repair) | **2-3×/semana** | cliente real; cuidado com biz=4 (smoke biz=1) |
| **Módulo maduro estável** (Governance, Jana) | **semanal** | já M2/M3; batimento mantém frescor |
| **Em construção** (ComunicacaoVisual, OficinaAuto) | **semanal** (régua M0/M1 leve) | não cobrar casos/E2E antes de ter tela viva |
| **Dormente** | **mensal** | só detecta se apodreceu; frescor expira e degrada visível |

## 3.5 PRIORIZAÇÃO de telas cross-módulo (qual primeiro)

Score de prioridade por tela = função de **(criticidade × (100 − nota) × frescor_penalidade × tráfego)**:
- **criticidade:** dinheiro/fiscal (peso 4) > vertical-prod (2) > resto (1). Deriva do dicionário de domínio + camada do módulo.
- **(100 − nota):** tela pior sobe. Lê direto do scorecard.
- **frescor_penalidade:** scorecard stale multiplica (nota velha ≠ nota confiável).
- **tráfego:** ROTA LIVRE = monitor 1280, 99% do volume → telas que Larissa toca sobem. (Onde não houver telemetria, usar proxy: rotas mais linkadas no sidebar.)

Resultado: telas do **caminho do dinheiro** (Sells/Create, Financeiro/ContasReceber, NfeBrasil emissão, RecurringBilling boleto) entram **primeiro**; módulos em construção entram com régua M0/M1 (não competem por não terem tela viva).

## 3.6 ORÇAMENTO de token/custo (escala pra frota, não 17 telas)

- **Custo por ciclo de tela** (screen-qa-specialist sessão limpa): estimar **~1 sessão Opus dedicada/tela** para telas que precisam de casos+E2E+scorecard novo. Telas já M2 com scorecard fresco = **ciclo barato** (só re-check determinístico Node, **zero LLM** no caminho crítico — é o que Knowledge Survival já faz).
- **Regra de PARADA (quando a máquina para):**
  1. **Budget noturno esgotado** (token-budget goal-scoped, estilo Codex Goal Mode) — para e retoma no próximo batimento.
  2. **Tela já verde+fresca PULA o ciclo** — só entra na fila quem tem gap ou stale. Isso derruba o custo da frota drasticamente (219 scorecards já existem).
  3. **Gate humano pendente** — se Wagner não aprovou o batch anterior, o cron **não empilha** trabalho novo (evita fila fantasma).
- **Escala real:** frota ~275 telas roteadas; **57 já sem charter, ~250 sem casos.md**. O trabalho de fundo é finito (fechar casos.md + E2E crítico); depois vira **manutenção incremental barata** (só telas tocadas re-entram, padrão ratchet). Custo cai de "campanha" pra "batimento".

## 3.7 COMO a máquina se auto-avalia (anti gate-teatro)

Os 2 modos de morte conhecidos e a defesa:

**(a) Gate-teatro (verde sem morder — ADR 0271):**
- **Mutation-score é o juiz do juiz.** Um teste que não mata mutante do código Tier-0 do módulo NÃO conta como cobertura de contrato, por mais verde que fique. Infection MSI como catraca cirúrgica no código de regra de negócio (não na suíte inteira — custo).
- Toda métrica de cobertura tem **par de qualidade** (Jellyfish paired-indicators, já citado no ADR 0160): cobertura-de-casos capeada por mutation-score.

**(b) Frota gerando testes tautológicos em massa (pior que nada):**
- **Contrato-first obrigatório:** o teste gerado pelo screen-qa cita a âncora (casos.md/SPEC/ADR/charter) — asserção que vem da implementação é rejeitada no gate pré-adoção (proibição §Ideias descartadas já vigente).
- **Mutation como filtro de aceitação:** ACH mostra que teste bom mata mutante *relevante*. Teste tautológico não mata mutante → é barrado antes de entrar no baseline.

## 3.8 NASCIMENTO e MORTE (ciclo de vida completo)

- **Nascimento:** `RUNBOOK-criar-modulo.md` (8 peças) ganha **órgãos de governança de série** — BRIEFING + SPEC esqueleto + dicionário de domínio + entrada no scorecard fleet. Módulo nasce **M0** já registrado no metabolismo.
- **Morte planejada:** o agente `deprecar-modulo` (já existe) executa a morte — lápide, congela scorecards como histórico, remove do batimento. A escada conecta: um módulo só sobe com sinal de cliente (ADR 0105); some de sinal → degrada → candidato a deprecação consciente.

---

# SEÇÃO 4 — Gaps rankeados por impacto × esforço (estimates recalibrados ADR 0106, fator 10x IA-pair)

| # | Gap | Impacto | Esforço (IA-pair) | Pré-req bloqueante? |
|---|---|---|---|---|
| 1 | **Espinha dorsal unificada**: schema de scorecard de tela com `graded_at` + histórico append-only + decaimento de frescor + agregação tela→módulo (pior-caso) | **alto** (é o dado de tudo) | ~4-6h (219 scorecards já existem; é schema + `scripts/qa/*.mjs` de agregação) | não |
| 2 | **Cron/scheduled agent "metabolismo"** que roda (a)→(b)→(c): diagnóstico fleet → gaps rankeados → propõe batch via audit-to-backlog (gate Wagner) | **alto** (é o pedido central: autonomia) | ~6-8h (orquestra peças que existem; risco = priorização + parada) | depende de #1 (lê a espinha dorsal) |
| 3 | **Fechar casos.md na frota** (contrato-first) por ratchet — começar caminho-do-dinheiro | **alto** (sem contrato, teste é teatro) | trabalho de fundo finito; ~1 sessão/tela; screen-qa já faz | não (casos-gate já existe) |
| 4 | **Mutation-oracle (Infection) cirúrgico** no código Tier-0 (num_uf, FSM, cálculo de valor) + MSI ratchet por módulo | **alto** (mata gate-teatro) | ~4-6h setup + tuning (custo de execução no CT 100 é o risco) | não (mas valida no CT 100) |
| 5 | **E2E Playwright em escala** via Test Agents (Planner/Gen/Healer) nos UCs críticos cross-módulo | **médio-alto** | ~1 sessão/UC crítico; self-healing reduz manutenção | depende de #3 (UC vem de casos.md) |
| 6 | **Escada M0→M3 + sinais vitais no BRIEFING/cockpit** (render do prontuário; degradação por frescor) | **médio** | ~4-6h (brief-update existe; é render + regra de decaimento) | depende de #1 |
| 7 | **Priorização com tráfego real** (telemetria de qual tela Larissa toca) | **médio** | ~3-4h (proxy sidebar já dá 80%; telemetria real é bônus) | não |

**Recomendação concreta:** **comece pelo #1 (espinha dorsal unificada)** — alto-impacto, sem pré-req bloqueante, e é o dado que #2/#6 consomem. Os 219 scorecards já existem; o trabalho é dar-lhes frescor de 1ª classe + histórico append-only + agregação tela→módulo. Sem essa fundação, o cron (#2) não tem o que ler pra priorizar, e os sinais vitais (#6) não têm de onde agregar. **Próxima ação hoje:** definir o schema unificado do scorecard de tela (adicionar `history[]` append-only + `freshness_decay` + `module_rollup` pior-caso) e escrever o `scripts/qa/vital-signs.mjs` que agrega os 219 YAMLs por módulo — read-only, determinístico, zero LLM no caminho crítico, gêmeo dos guards existentes.

---

# SEÇÃO 5 — Riscos e o que NÃO fazer (amarrado às ideias descartadas)

| Risco | Amarra (proibição/ADR) |
|---|---|
| Gerar teste derivado do **código** (tautológico) em massa | §Ideias descartadas 2026-06-05: asserção tem que vir de contrato externo citado. Mutation-oracle (#4) é a defesa mecânica. |
| Criar **gate de "presença no diff"** (ex.: "casos.md mudou") | §Ideias descartadas 2026-07-01: presença ≠ correção. Enforcement é UC+teste que quebra quando comportamento some. |
| Abrir **roadmap paralelo** ao AUTOMATION-ROADMAP.md | §Ideias descartadas 2026-06-05 + gate T6. Esta proposta **estende** o roadmap como nova Onda. |
| **Promover gate de qualidade a required** sem reabrir ADR 0314 | Lei vigente: required = só Tier-0. Escada/mutation/E2E ficam **advisory** (rodam, mostram vermelho, promovíveis por calendário ADR 0275). NÃO propor flip a required aqui. |
| Cron **commitar/criar task sozinho** em Tier-0 | publication-policy + R10. Gate humano Wagner no ponto de escrita é IRREVOGÁVEL. |
| Smoke em **biz=4** (ROTA LIVRE) | ADR 0101 — sempre biz=1. |
| Rodar Pest/E2E **local ou Hostinger** | Proibição Tier-0 — CT 100 only. |
| **Verde-stale** (módulo verde parado sem re-medição) | Anti-pattern central deste desenho: frescor expira e degrada visível (Knowledge Survival ADR 0256 sentinela). |
| Baseline de coverage/mutation **stale** (buraco conhecido) | Airtable/Codecov: baseline por-tela versionado + quarentena de flaky; mutation baseline armado por módulo após N medições (padrão sdd-scorecard). |

---

# SEÇÃO 6 — Onde isso pluga no canon

**1. Nova Onda no `AUTOMATION-ROADMAP.md`** (estender, nunca paralelo — gate T6):
> ⚠️ Nota: o AUTOMATION-ROADMAP.md está hoje `lifecycle: arquivado / accepted-historical` (morreu porque dizia "zero ondas" sendo falso). **Antes de estender, reabrir/ressuscitar o doc** (ou apontar a nova Onda para o roadmap SDD vivo `_ROADMAP.md` de governança) — decisão de Wagner sobre qual doc é o dono vivo do tema "automação de governança". Como está arquivado, a Onda nova provavelmente pertence ao **roadmap SDD ativo** (streams SA/FV/KL/GT), como stream nova **"MV — Módulo Vivo / metabolismo"**.

> **Onda MV — Módulo Vivo (metabolismo de governança fleet-wide)**
> Institui a Escada M0→M3, a espinha dorsal de scorecards com frescor, e o cron-metabolismo que fecha o loop a→g. Itens = os 7 gaps da Seção 4, cada um 1 PR isolado, ratchet.

**2. ADR nova (só PROPOSTA — não escrita aqui):**
> **ADR NNNN — "Módulo Vivo: escada de maturidade M0→M3 + metabolismo de governança fleet-wide"**
> - **Decisão:** todo módulo tem posição na escada, medida pela espinha dorsal de scorecards (fonte única, frescor de 1ª classe, agregação pior-caso), e é mantido vivo por um cron-metabolismo read-only que propõe trabalho (gate humano no ponto de escrita).
> - **Relaciona:** 0264 (trio+E2E+domínio), 0256 (catraca+sentinela+cadência), 0250 (screen-qa sustentável), 0160 (buckets), 0105 (sinal-cliente), 0273 (anchor spec↔código), 0106 (10x IA-pair).
> - **Respeita 0314:** gates novos nascem advisory; promoção a required só via reabertura da 0314.
> - **Tier 0:** cron nunca escreve em Tier-0 sozinho; smoke biz=1; CT 100.

---

## Anexo — proveniência da pesquisa (Fase 1 antes de ler canon)

Pesquisa WebSearch executada **antes** de abrir `memory/` (limpeza anti-contaminação): ACH/TestGen-LLM, Spec Kit, Infection/PIT, Playwright Test Agents/MCP, visual regression 2026, autonomous coding fleets/token budget, coverage ratchet/quarantine, risk-based prioritization, requirements traceability (TraceLLM), fitness functions. Só depois cruzei com canon (ADRs 0264/0256/0271/0314/0273/0160/0105 + screen-qa-specialist + AUTOMATION-ROADMAP + inventário origin/main).
