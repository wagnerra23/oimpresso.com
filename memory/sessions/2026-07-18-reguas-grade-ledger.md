---
date: "2026-07-18"
topic: "LEDGER de proveniência do placar da grade de réguas 2026-07-18 (correção #7 do juiz) — 23 claims com vereditos contados do journal wf_e0a3d488-20c; delta vs 07-10; reconciliação §1×§8; disclosure REFUTADO_TB"
authors: [C]
prs: []
outcomes:
  - "23 refutadores CONTADOS no journal (5 REFUTADO · 18 EMPATADO) = exatamente o placar da grade; 23 integrações (23 DIFERENCIAL_SISTEMA · 0 REFUTADO_TB); pareamento claim-a-claim 23/23 sem sobras; 24 verificadores; 83 agentes no total."
  - "O '26 claims' do retrato 2026-07-10 é IRREPRODUZÍVEL dos journals sobreviventes: o run de 07-10 manhã testou 9 claims (journal contado), a re-grade 07-10 testou 20 — nenhum artefato sobrevivente enumera 26. Mapeamento 1:1 26→23 impossível; delta entregue por famílias temáticas contra os runs contados."
  - "Reconciliação das duas listas de '5 refutadas' da grade emitida: a canônica é a do §1 (bate com o journal); 4 dos 5 itens do §8 são refutações de rodadas ANTERIORES (lápides §5 de 07-09, 07-10 e 07-17) — só 'registro-de-refutações' é desta rodada."
  - "Disclosure re-contado: 0 REFUTADO_TB nesta rodada E em TODOS os journals sobreviventes na máquina — 81 vereditos de integração em 8 runs (07-10→07-18), 81/81 DIFERENCIAL_SISTEMA. O '~63 históricos' do juiz adversarial fica publicado com proveniência dele; minha re-contagem própria dá 58 anteriores + 23 desta rodada."
related_adrs:
  - 0330-mapa-dos-niveis-estado-real-2026-07-constituicao
  - 0329-doutrina-documentacao-executavel-fonte-unica
  - 0318-ragas-eval-real-mata-tautologia-ct100-staging
  - 0105-cliente-como-sinal-guiar-sem-mandar
---

# LEDGER de proveniência — placar da grade de réguas 2026-07-18

> **Por que existe:** correção #7 do juiz do passe adversarial same-day ([session adversarial](2026-07-18-reguas-adversario-grade.md)): o placar "0/23 acima · 23/23 integração · 18 empatadas · 5 refutadas" da [grade completa](2026-07-18-reguas-grade-completa.md) era **inauditável** sem enumeração claim-a-claim. Este ledger enumera, com **números contados** (nunca estimados) e proveniência declarada linha a linha.
>
> **Fonte primária:** journal do run original `wf_e0a3d488-20c` (83 agentes, 2026-07-18) — 166 linhas JSONL (83 `started` + 83 `result`), claims extraídas dos prompts em `agent-<id>.jsonl`, vereditos extraídos do JSON de retorno de cada agente. Fases contadas por posição+prompt: **1 dossiê · 11 pesquisadores (1/dimensão) · 23 refutadores · 23 integrações · 24 verificadores · 1 sintetizador**. O journal vive fora do git (efêmero, na máquina do [W]) — este ledger é o registro durável.

## 0. Contagens brutas do journal desta rodada

| Fase | N contado | Vereditos contados |
|---|---|---|
| Dossiê | 1 | — |
| Pesquisadores (1 por dimensão) | 11 | — |
| **Refutadores** (1 por claim) | **23** | **5 REFUTADO · 18 EMPATADO · 0 ACIMA_CONFIRMADO** |
| **Integrações** (1 por claim) | **23** | **23 DIFERENCIAL_SISTEMA · 0 REFUTADO_TB** |
| Verificadores (fraquezas no repo) | 24 | — (cobertura 24 de 76, `slice(0,24)` — bug já reportado na grade) |
| Sintetizador | 1 | — |
| **Total** | **83** | — |

**N refutadores REAL = 23 = placar da grade** (sem diferença). Pareamento refutador↔integração por título de claim: **23/23, sem sobras nem órfãos**. As 11 dimensões têm todas ≥1 claim (spec-governança 4 · orquestração-adversarial 3 · memória 2 · design→código 2 · evals-outcome 2 · erp-ia-produto 2 · observabilidade 2 · segurança 2 · inteligência-de-negócio 2 · qualidade-drift 1 · custo-eficiência 1).

## 1. As 23 claims desta rodada (enumeração canônica)

Título curto extraído do prompt real do refutador; razão em 1 linha citando o peer do retorno real. `#refut/#integ` = posição no journal (auditável).

| # | Claim (título curto) | Dimensão | Refutador | Razão (peer citado pelo refutador) | Integração |
|---|---|---|---|---|---|
| C1 | gate-selftest vigia-dos-vigias (fixture boa/ruim, required no merge) `#13/#42` | orquestração-adversarial | **REFUTADO** | Mutation testing em CI (Stryker `--break-at`, mutmut `--CI`) + policy-tests (OPA `opa test`, Semgrep `--test`, Sentinel) já fazem controle-negativo required | DIFERENCIAL_SISTEMA |
| C2 | Isolamento multi-tenant Tier 0 por TESTE de arquitetura no CI (NoHardcodeBusinessId + Pest biz=1×99) `#14/#44` | erp-ia-produto | EMPATADO | Policy-as-code de tenant-isolation como gate CI (padrão OPA/Semgrep; Blaxel/DZone; SAP/Salesforce) | DIFERENCIAL_SISTEMA |
| C3 | Registro append-only de REFUTAÇÕES de processo (§5) consultado ANTES de propor `#15/#36` | spec-governança | **REFUTADO** | ProjectMem (arXiv 2606.12329): log append-only git-native de decisions/failed-attempts com gate determinístico PRÉ-AÇÃO; + GateMem | DIFERENCIAL_SISTEMA |
| C4 | §5 como "negative eval set" de ideias de arquitetura `#16/#41` | evals-outcome | EMPATADO | Prática ADR "Alternatives Considered / Rejected" documentada 2026 como guardrail de agente lido no pré-flight | DIFERENCIAL_SISTEMA |
| C5 | Heartbeat que prova o FLUXO lendo a API do PRÓPRIO destino (langfuse_trace_uptime_24h) `#17/#49` | observabilidade-agente | **REFUTADO** | Dead-man's-switch para pipeline OTel (OneUptime 2026-02) + Datadog Data Observability/Freshness monitors — verificam no destino, não confiam no coletor | DIFERENCIAL_SISTEMA |
| C6 | Doutrina anti-presence-gate explícita (presença ≠ correção) `#18/#47` | spec-governança | **REFUTADO** | Compliance/security-theater canon + mutation testing (Stryker/PIT, SonarQube "test without assertion") + OPA/Sentinel negative-testing | DIFERENCIAL_SISTEMA |
| C7 | Meta-guard de falácia-de-composição (Fase Integração DIFERENCIAL_SISTEMA vs REFUTADO_TB) `#19/#40` | orquestração-adversarial | EMPATADO | Refute-or-Promote (arXiv 2604.19049, stage-gated + Cross-Model Critic) + doutrina system-level-vs-component na literatura de eval agêntico | DIFERENCIAL_SISTEMA |
| C8 | Proveniência da âncora de design computada do charter (`ancora.mjs` + hook fail-closed) `#20/#38` | design→código | EMPATADO | Figma Dev Mode MCP + Code Connect: fonte trusted-by-construction elimina o input stale em vez de guardá-lo; Supernova | DIFERENCIAL_SISTEMA |
| C9 | Conhecimento-de-processo acoplado ao merge-gate (casos-gate/anchor-lint/memory-health required) `#21/#48` | memória-conhecimento | EMPATADO | Dosu (freshness error-budget que congela merge + floor de página crítica) e Swimm enterprise (bloqueia PR até doc stale remediado) | DIFERENCIAL_SISTEMA |
| C10 | Eval virado RECURSIVAMENTE contra o próprio processo (SDD scorecard + module-grade + gate-selftest) `#22/#39` | evals-outcome | EMPATADO | getDX (DX Core 4/DORA-for-AI) + Braintrust/LangSmith replay-regression gates + CodeRabbit/Codacy | DIFERENCIAL_SISTEMA |
| C11 | Loop design→código git-source AUTO-APLICADO (Figma bloqueado fail-closed) `#23/#45` | design→código | EMPATADO | AutonomyAI (design-to-code como pipeline git-native token-bound com PR no CI) + Supernova/Knapsack drift→auto-PR + Chromatic/Storybook | DIFERENCIAL_SISTEMA |
| C12 | Registro persistente de refutações consultado ANTES de agir (variante memória do §5) `#24/#43` | memória-conhecimento | EMPATADO | Protocolo Lore (arXiv 2603.15566): log append-only via trailers de commit com anti-pattern filtering consultado antes de editar | DIFERENCIAL_SISTEMA |
| C13 | Eval contínuo fidelidade+PII-leak DENTRO do ERP como health-check diário (jana:health-check) `#25/#50` | erp-ia-produto | EMPATADO | odoo-copilot-evals (eval de copilot de ERP vertical) + RAGAS/Arthur/Confident AI/Langfuse com gate no response path | DIFERENCIAL_SISTEMA |
| C14 | Auto-aplicação recursiva da governança (o agente cita o próprio §5 pra se barrar) `#26/#46` | spec-governança | EMPATADO | "Recursive governance" nomeada (Frontiers 2026) + meta-governança horizontal (Microsoft) — sem gêmeo de PRODUÇÃO na forma integrada | DIFERENCIAL_SISTEMA |
| C15 | Constituição/spec LIGADA-AO-GATE + gate-selftest que vigia o vigia `#27/#37` | spec-governança | EMPATADO | `semgrep --test`/`--validate` (`ruleid:`/`ok:`) + OPA/Conftest policy-tests; Spec Kit tem constituição-em-prosa | DIFERENCIAL_SISTEMA |
| C16 | Verificação adversarial ancorada em contrato versionado + §5 anti-re-proposta, na PRÓPRIA governança `#28/#51` | orquestração-adversarial | EMPATADO | Padrão lessons.md/anti-pattern library (Anthropic best practice; orchestrator.dev) + SentinelOne Adversarial | DIFERENCIAL_SISTEMA |
| C17 | Online-eval (judge ~5% traces, crc32) com PII redigida PRÉ-egress + gates LGPD off-default `#29/#52` | observabilidade-agente | **REFUTADO** | Langfuse masking pré-egress + self-hosted zero-egress; LiteLLM+Presidio; Datadog Sensitive Data Scanner | DIFERENCIAL_SISTEMA |
| C18 | Red-team de injection 3 camadas com honestidade sobre o que cada uma prova `#30/#56` | segurança-do-agente | EMPATADO | garak/NVIDIA (report por probe sem gatear) + promptfoo (PR-gate com ratchet) + PyRIT (behavioral) | DIFERENCIAL_SISTEMA |
| C19 | Anti-tautologia ARMADA no próprio instrumento (ADR 0318 deletou gate 1.0 + caveat + guard de re-baseline) `#31/#53` | qualidade-drift-IA-prod | EMPATADO | Pitfall publicado (arXiv 2501.00269, 2407.12873) + doutrina vanity-metric/Goodhart 2026; na MEDIÇÃO real de prod o mercado está à frente | DIFERENCIAL_SISTEMA |
| C20 | Alarme reflexivo "governança comendo o negócio?" no fluxo de merges (negocio-vs-governanca-ratio) `#32/#57` | inteligência-de-negócio | EMPATADO | Swarmia Investment Balance + LinearB Work Breakdown + Jellyfish Allocation-to-Toil (sem a versão reflexiva-do-próprio-OS publicada) | DIFERENCIAL_SISTEMA |
| C21 | Custo estimado carimbado DENTRO do corpo do PR (`agent-cost-per-pr` via governance-pr-summary) `#33/#55` | custo-eficiência | EMPATADO | Mavvrik (session attribution) + Finout FinOps-for-AI-Agents (incl. unallocated overhead) + Anthropic Analytics API + Portkey/CloudZero | DIFERENCIAL_SISTEMA |
| C22 | Porta de admissão de backlog no SINAL do cliente (ADR 0105) `#34/#58` | inteligência-de-negócio | EMPATADO | Teresa Torres (Opportunity Solution Tree obrigatória antes do backlog) + Cagan/SVPG + Jira Product Discovery/Productboard | DIFERENCIAL_SISTEMA |
| C23 | Meta-check "a defesa REALMENTE roda" (settings-backstop-registration prova corpus-verde ≠ defesa ligada) `#35/#54` | segurança-do-agente | EMPATADO | BAS — SafeBreach/Picus/Cymulate ("control efficacy", Gartner AEV desde 2024) | DIFERENCIAL_SISTEMA |

**Cross-check com o placar da grade:** 5 REFUTADO (C1, C3, C5, C6, C17) + 18 EMPATADO = 23 ✓ · 0 ACIMA_CONFIRMADO ✓ · 23/23 DIFERENCIAL_SISTEMA ✓ · 0 REFUTADO_TB ✓. O placar emitido é **fiel ao journal** — o defeito apontado pelo juiz era a inauditabilidade (falta desta enumeração), não infidelidade de contagem.

## 2. Delta vs retrato 2026-07-10 ("26 claims → 23")

**Fato duro primeiro: o "26" é irreproduzível.** O número "0 de 26 claims" registrado na lápide §5 2026-07-10 e no [session log de 07-10](2026-07-10-reguas-atrofia-inteligencia-negocio.md) **não bate com nenhum journal sobrevivente**:

| Run (journal contado) | Data (mtime) | Refutadores | Vereditos refut | Integrações |
|---|---|---|---|---|
| `wf_66ede1e7-c53` (41 ag — a grade do "0 de 26") | 2026-07-10 08:04 | **9** | 4 REFUTADO · 5 EMPATADO | 0 (fase Integração ainda não existia — nasceu no #4074, depois deste run) |
| `wf_46d58344-e79` (77 ag — re-grade pós-#4074) | 2026-07-10 11:13 | **20** | 8 REFUTADO · 12 EMPATADO | 20 (20 DS · 0 RTB) |
| `wf_5ae5c554-67f` (85 ag — grade 07-17, truncagem-silenciosa) | 2026-07-17 08:39 | **24** | — (não re-tabulado aqui) | 23 (23 DS · 0 RTB) |
| `wf_e0a3d488-20c` (83 ag — esta rodada) | 2026-07-18 | **23** | 5 REFUTADO · 18 EMPATADO | 23 (23 DS · 0 RTB) |

Nota de fidelidade sobre o próprio run de 07-10: o sintetizador daquele run reportou "0 acima · 2 empatadas · 3 refutadas · 3 não-testadas" — que também não bate com os 9 refutadores contados (4 R · 5 E). Ou seja, **já em 07-10 o placar publicado divergia do journal** — a mesma classe de defeito (composição infiel ao journal) que a regra 16 proposta pelo adversário de 2026-07-18 quer travar. De onde saiu "26" não é determinável dos artefatos sobreviventes; registro como número **não-reproduzido** (proveniência: prosa da sessão 07-10), sem inventar explicação.

**Por que 1:1 é impossível (estrutural, não falta de esforço):** as claims **não têm identidade persistente entre rodadas** — cada rodada os pesquisadores re-geram claims novas a partir do repo vivo (provado por contagem: 07-10 manhã 9 · 07-10 re-grade 20 · 07-17 24 · 07-18 23, com textos diferentes entre si). O delta honesto é por **família temática**, contra os runs contados de 07-10:

**Famílias que PERSISTEM (07-10 → 07-18):**
- Registro §5 de refutações (07-10 run1 #16 → 07-18 C3/C4/C12/C16 — a família se multiplicou em 4 claims)
- Proveniência de design por máquina (07-10 run1 #15; re-grade #12 → C8)
- Design-in-repo (07-10 run1 #10, **REFUTADO** lá → reformulada como C11 "loop AUTO-APLICADO", EMPATADO agora)
- Conhecimento acoplado a gate de merge (07-10 run1 #14 → C9)
- Adversarial na camada de processo (07-10 run1 #13; re-grade #11/#13 → C14/C16)
- Multi-tenant por teste de arquitetura (re-grade #18/#20 → C2)
- Cliente-como-sinal ADR 0105 (re-grade #19 → C22)
- Razão negócio-vs-governança (re-grade #16 → C20)
- Custo atribuído a artefato/PR (re-grade #17 → C21)
- Health-check custo+PII+drift embarcado (re-grade #1/#7 → C13)

**Famílias presentes em 07-10 e AUSENTES como claim em 07-18:** ADR append-only mecanizado (run1 #9, re-grade #8/#16) · Jana analista conversacional (run1 #11) · sentinelas de staleness como claim isolada (run1 #8) · doutrina 0329 teste ácido (run1 #12).

**Entraram em 07-18 sem par em 07-10:** meta-guard de falácia-de-composição C7 (nasceu do próprio #4074 de 07-10 — o mecanismo virou claim) · heartbeat do destino C5 · online-eval PII C17 (US-COPI-137, construída 07-17) · anti-tautologia armada C19 (guard de 07-17) · red-team injection C18 (#4070) · meta-check settings-backstop C23 (07-17) · anti-presence-gate C6 · gate-selftest C1 · eval recursivo C10.

## 3. Reconciliação das duas listas de "5 refutadas" (§1 × §8 da grade emitida)

A grade emitida (1ª emissão, `grade-emitida-2026-07-18.md`) traz **duas listas de 5** que só compartilham 1 item — a contradição apontada pelo juiz. Resolução:

**Lista canônica = a do §1** (refutadas NESTA rodada; bate 5/5 com o journal):

| §1 | Claim do journal | Peer |
|---|---|---|
| registro de refutações de processo | C3 `#15` | ProjectMem/GateMem |
| doutrina anti-presence-gate | C6 `#18` | theater-canon/mutation-testing/OPA |
| gate-selftest vigia-dos-vigias | C1 `#13` | Stryker/mutmut + Semgrep `--test`/OPA |
| heartbeat lendo a API do destino | C5 `#17` | OneUptime/Datadog freshness |
| online-eval PII-redigida | C17 `#29` | Langfuse mask/LiteLLM+Presidio/Datadog SDS |

**A lista do §8** ("design-in-repo, fixture-bite, memória-git, recusar-agregar-fidelidade, registro-de-refutações") **misturou rodadas**: só 1 item é desta rodada; os outros 4 são refutações de rodadas ANTERIORES, já com lápide própria em `memory/proibicoes.md` §5:

| Item do §8 | Classificação | Origem (data/lápide) |
|---|---|---|
| registro-de-refutações | **desta rodada** (= C3 do §1) | journal `#15`, ProjectMem |
| design-in-repo | rodada ANTERIOR | lápide §5 **2026-07-10** (slice "design-in-repo → Code Connect"); também REFUTADO no journal de 07-10 (`wf_66ede1e7` #10) |
| fixture-bite | rodada ANTERIOR | lápide §5 **2026-07-09** (tabela claims REFUTADAS: "fixture boa/ruim → Semgrep e OPA"); parente direto da C1 desta rodada, mas o nome/lápide é de 07-09 |
| memória-git | rodada ANTERIOR | lápide §5 **2026-07-09** ("memória canônica em git → Letta Context Repositories fev/2026") |
| recusar-agregar-fidelidade | rodada ANTERIOR | lápide §5 **2026-07-17** ("Chromatic já faz" — triagem tri-modal/intended-change) |

O §8 rotulava os 5 como "as 5 refutadas-na-peça" — **errado para 4 deles**; o correto é "reforço de lápides 2026-07-09/07-10/07-17 + 1 refutação desta rodada". A [grade corrigida](2026-07-18-reguas-grade-completa.md) já usa a lista do §1.

## 4. Disclosure de poder discriminativo do veredito de integração

- **Nesta rodada (contado no journal):** 23 vereditos de integração emitidos · **0 REFUTADO_TB** · 23 DIFERENCIAL_SISTEMA. Confere com o "0" da grade.
- **Histórico (minha re-contagem, journals sobreviventes nesta máquina, varridos em 2026-07-18):** 8 runs com fase Integração entre 2026-07-10 e 2026-07-18 → **81 vereditos, 81/81 DIFERENCIAL_SISTEMA, 0 REFUTADO_TB** (07-10 re-grade: 20 · 07-17: 23+2+1+2+1+9 = 38 em 6 runs · 07-18: 23). Antes desta rodada: **58**.
- **"~63 vereditos históricos desde #4074 sem nenhum REFUTADO_TB"** — número do **juiz adversarial 2026-07-18**, publicado aqui com essa proveniência; minha re-contagem independente deu **58** (base: journals que sobrevivem na máquina — runs purgados ou em outra máquina não entram). A diferença 58 vs ~63 fica registrada sem reconciliação forçada; **as duas bases sustentam a mesma conclusão**: o braço negativo do teste de integração **nunca disparou**.
- **Leitura honesta (a do juiz, endossada):** um veredito binário que só conhece um valor não discrimina; o valor informativo da fase Integração está nas RAZÕES por claim (a busca 3× pelo CONJUNTO com peers nomeados), não no binário. Reformular a pergunta com braço discriminativo = emenda da lápide §5 2026-07-10 — decisão [W] (pendência registrada na grade corrigida).

## 5. Limites deste ledger

- Claims C1–C23: títulos encurtados por mim a partir dos prompts reais (o texto integral vive no journal; posições `#n` permitem re-extração byte-exata enquanto o journal existir).
- Razões da coluna "refutador": condensadas a 1 linha do JSON real de cada agente — o peer citado é o do agente, não meu.
- O journal é **efêmero e fora do git** — se for purgado, este ledger vira a única trilha; por isso as posições, contagens e datas (mtime) estão todas materializadas aqui.
- Não re-tabulei os vereditos refutador-a-refutador do run 07-17 (`wf_5ae5c554`) — contei apenas fases e integrações (23 DS · 0 RTB); tabulação completa daquele run fica fora do escopo desta correção.
