---
topic: "Auditoria multi-agente do IA OS de engenharia agêntica/governança vs estado-da-arte 2026 — veredito CONSOLIDAR"
name: 2026-06-20-arte-ia-os-engenharia-agentica
description: Auditoria multi-agente do IA OS de engenharia agentica/governanca vs estado-da-arte 2026 — nota geral ~80/100, veredito CONSOLIDAR
type: session
date: "2026-06-20"
related_adrs: [0053-mcp-server-governanca-como-produto, 0074-temporal-validity-bi-temporal-time-travel, 0094-constituicao-v2-7-camadas-8-principios, 0095-skills-tiers-convencao-interna, 0256-knowledge-survival-meia-vida-catraca-sentinela]
---

# Estado-da-arte: IA OS de engenharia agentica/governanca vs o melhor de 2026

> Escopo travado por Wagner: a camada de **engenharia agentica / governanca** — o aparato com que a empresa roda e constroi software via Claude Code (skills, hooks, ADRs/Constituicao v2, MCP server de governanca, SDD, arquitetura de memoria, gates). NAO e a IA dentro do produto (Jana/Copiloto/ADS), que fica para uma auditoria separada.

## Como foi feito (proveniencia)

- Workflow multi-agente `ia-os-engenharia-agentica-audit` (run `wf_1c33660c-913`), 2026-06-20.
- **19 agentes** / ~1.75M tokens / 712 tool-uses / ~19,5 min.
- Decomposicao em **9 dimensoes**. Por dimensao: (1) `audit-research-expert` leu o repo real + pesquisou SOTA 2026 (5-7 buscas web) + deu nota %; (2) um verificador cetico (`Explore`) releu o repo para **derrubar falsos-gaps** (coisa que a pesquisa achou faltar mas ja existe como hook/skill/ADR) e ajustar a nota; (3) sintese consolidou.
- **Notas usadas = AJUSTADAS** (pos-verificacao), nao as da pesquisa.
- Nota de processo: a sintese automatica omitiu a dimensao **Skills & Empacotamento (74)** das tabelas; ela foi reinserida abaixo a partir do output bruto. Reincluindo-a (peso ~0.8), a media ponderada cai de 81 para **~80** — nao muda o veredito.

---

## TL;DR — nota geral de maturidade

**Nota geral ponderada: ~80/100** (world-class para um time de 5 pessoas; a frente do SOTA em varios eixos de governanca).

Pesos: dimensoes fundacionais pesam mais porque um furo nelas invalida tudo acima (Constituicao v2: seguranca multi-tenant Tier 0 + contexto/memoria sao o chao; guardrails impedem o agente de quebrar o chao; SDD e multi-agente sao *aceleradores* — falham para "menos veloz", nao para "vazou tenant").

**Veredito honesto:** o oimpresso opera um IA OS de engenharia agentica que, em **governanca mecanizada** (gate-selftest "quem vigia os vigias", protection-drift contra democao invisivel, refutador G5 adversarial, knowledge-survival com meia-vida+catraca+sentinela), esta **a frente do estado-da-arte publico de 2026** — coisas que nem labs grandes entregam out-of-the-box. O furo recorrente, repetido em 6 das 8 dimensoes pontuadas, **nao e arquitetura: e o delta desenho->producao.** A infra de ponta existe e esta cabeada (nao sao stubs), mas opera sobre vazio (corpus de fatos ~6, anchor coverage 5.3%, evals/results/ com .gitkeep) ou em advisory eterno (anchor-gate, drift_alarms), com as ADRs que legislam o aparato ainda em `proposto`. Voce construiu um motor de F1 e ainda nao encheu o tanque nem ratificou o regulamento. A proxima fase nao e construir — e **encher, ratificar e endurecer o que ja existe.**

## Scorecard por dimensao (9)

| Dimensao | Nota pesquisa | Nota ajustada | Veredito | Resumo (1 linha) |
|---|---|---|---|---|
| Contexto & Memoria | 82 | **87** | CONSOLIDAR | Pipeline RAG SOTA + memoria-como-produto governado; falta encher corpus e shipar bi-temporal. |
| Orquestracao Multi-Agente | 82 | **87** | CONSOLIDAR | Harness programatico com schema JSON + isolamento validado; falta cost-cap e trace causal. |
| Guardrails Deterministicos | 88 | **82** | CONSOLIDAR | Meta-testing de catracas + anti-democao lideres mundiais; falta ledger de decisoes e CI dos hooks. |
| Governanca/ADRs/Constituicao | 82 | **79** | CONSOLIDAR | Indice gerado + supersede atomico; furado por 56 titulos base64, 13 colisoes, ADRs-lei em proposto. |
| Avaliacao & Verificacao Adversarial | 74 | **78** | CONSOLIDAR | 7 skeptics + split deterministico/judge; calibracao kappa e red-team nunca rodaram. |
| Observabilidade & Anti-Drift | 72 | **76** | EVOLUIR | Anti-drift de governanca mundial + Langfuse vivo; falta online-eval em trafego e error-budget. |
| Seguranca, Autonomia & HITL | 72 | **76** | CONSOLIDAR | HITL 4-niveis + PolicyEngine firewall + multi-tenant Tier 0; falta tamper-evidence, sandbox, defesa injecao-indireta. |
| Spec-Driven Development | 72 | **76** | EVOLUIR | Anchor grammar maquina-parseavel raro; cobre so 5.3% das US, gate ainda advisory. |
| Skills & Empacotamento | 72 | **74** | CONSOLIDAR | Tiers + drift-detect + telemetria viva; falta context:fork, fechar loop tier->promocao, plugin bundling. |

## Onde voce JA e world-class (ou a frente do SOTA)

Diferenciais raros mesmo em times grandes — verificados por leitura de arquivo, nao declarados:

1. **Meta-testing das proprias catracas (`gate-selftest.mjs` — "quem vigia os vigias").** Para cada gate, fixture boa TEM que passar verde e fixture ruim TEM que falhar pelo motivo certo. Mutation-testing aplicado a governanca. Nenhum framework SOTA pesquisado (NeMo, Guardrails AI, OPA stock) entrega isso.
2. **Defesa contra democao invisivel (`protection-drift.mjs` + `required-checks-baseline.json`).** O buraco real de qualquer sistema de gates — admin remove um required check em 1 clique e nenhum diff aparece — esta fechado com baseline congelado + watchdog de canario >48h.
3. **Memoria como PRODUTO GOVERNADO, nao storage engine.** Mem0/Letta/Zep entregam retrieval; voce entrega audit log imutavel por chamada + RBAC Spatie + PII redactor no sync + quotas + soft-delete LGPD + capacidade externa vendavel (cliente conecta Claude Desktop com filtro `business_id`). Nenhum framework OSS de memoria tem essa camada (ADR 0053).
4. **Refutador G5 adversarial em SESSAO FRESCA.** Verificacao de todo lote IA >10 arquivos por um verifier SEM contexto do gerador (`error_rate<2%`, `pii_hits=0`), ledger append-only. Verifier-critic + Agent-as-a-Judge de elite — nasceu de incidente real.
5. **Knowledge-survival mecanico (ADR 0256).** Meia-vida + catraca + sentinela `memory-health.mjs` com 9 checks — incluindo dois que o SOTA nem descreve: Check G (todo workflow `.github` DEVE estar no `gates-registry.json`) e Check I (toda licao aponta um gate ou se declara "nao-mecanizavel:").
6. **Harness multi-agente programatico com schema JSON por worker** (`agent()`/`parallel()`/`phase()` + schemas), context-isolation endurecido, validado empiricamente (16 implementadores, 73/73 Pest, zero conflitos).
7. **Honestidade metrologica institucionalizada.** `not_yet_measured` em vez de fabricar 0; ratchet arma metrica so apos 3 medicoes reais; FLOOR por intersecao de 3 runs; baseline "1a medicao da fonte, nunca do plano". Defesa direta contra o anti-padrao #1 do SOTA (score que parece estavel e nao e).

## Top 10 gaps priorizados (impacto x 1/esforco)

| # | Gap | Dimensao | Impacto | Esforco | Quick-win? | Veredito |
|---|---|---|---|---|---|---|
| 1 | 56 de 277 titulos de ADR renderizam como `!!binary <base64>` no indice gerado (`adr-index-generate.mjs` nao decodifica YAML base64) | Governanca | Alto | Baixo | SIM | CONSOLIDAR |
| 2 | Hooks (camada que de fato bloqueia o agente em tempo real) NAO rodam em CI — `test-all-hooks-smoke.ps1` e local-only | Guardrails | Alto | Baixo | SIM | CONSOLIDAR |
| 3 | Enforcement R10 (`block-pr-without-approval.mjs`) existe mas NAO registrado em `settings.json` + ActionGate WARN-ONLY | Seguranca | Alto | Baixo | SIM | CONSOLIDAR |
| 4 | Bi-temporal event-time desenhado (ADR 0074, ~2 dias) mas so uni-temporal em prod | Contexto/Memoria | Alto | Baixo | SIM | CONSOLIDAR |
| 5 | Anchor-gate segue ADVISORY — a catraca de rastreabilidade spec->codigo nao morde no merge | SDD | Alto | Medio | ~ | CONSOLIDAR |
| 6 | Corpus de fatos semanticos quase vazio (~6 fatos, Recall@3~0.125) — toda a infra RAG opera no vacuo | Contexto/Memoria | Alto | Medio | ~ | CONSOLIDAR |
| 7 | Ledger estruturado de DECISOES de hook ausente — impossivel medir FP<10% para promocao advisory->required | Guardrails | Alto | Medio | ~ | EVOLUIR |
| 8 | Calibracao do juiz vs humano (Cohen's kappa) nunca medida — regua sem regua; `evals/results/` vazio | Avaliacao | Alto | Medio | ~ | CONSOLIDAR |
| 9 | Cost-cap/circuit-breaker programatico por run + telemetria de tokens por subagent ausente (~15x tokens) | Multi-Agente | Alto | Medio | ~ | EVOLUIR |
| 10 | Audit log append-only mas NAO tamper-evident (sem hash-chain SHA-256; padrao ja existe em `MarcacaoService`) | Seguranca | Alto | Medio | ~ | CONSOLIDAR |

*Mencoes honrosas fora do top-10:* online-eval em trafego vivo (Observabilidade, alto/alto), defesa contra prompt-injection indireta (Seguranca, alto/alto), drift-alarm de qualidade automatico (Observabilidade, alto/medio), fechar o loop tier->promocao nas ~19 skills sem tier (Skills, alto/baixo), 13 colisoes de numero de ADR (Governanca, medio/medio).

## Falsos-gaps derrubados na verificacao

A pesquisa achou que faltava, mas a verificacao encontrou no repo (importante para confiar no resto):

- **Telemetria de skills "nao roda".** FALSO. `mcp_skill_telemetry` em producao desde 2026-05-06 (via `BriefFetchController`/`BriefFetchTool`). Cobertura de tier tambem era subestimada: **51/70 skills (73%)** declaram tier, nao os 39% alegados.
- **Langfuse "intencional-futuro / nao deployado".** FALSO. ADR 0132 `accepted` 2026-05-10, deployado no CT 100 (postgres+clickhouse+web+worker), `LangfuseClient` ativo recebendo spans.
- **Pre-commit ausente e "fraqueza".** FALSO — decisao consciente documentada (ADR 0224): ~80% do fluxo e via Claude Code, defesa em profundidade ja cobre via hooks locais + 34 gates CI.
- **Cost-cap/circuit-breaker "e so narrativa de prompt".** PARCIALMENTE FALSO. ADR 0094 §2 define circuit-breaker 3-niveis (baseline/warning 2.5x/halt 5x) + `HealthCheckCommand` diario; gen_ai tokens via OTEL. Gap real = cap *por-run/por-subagent em tempo real*.
- **Durable execution / checkpoint-resume "nao existe".** PARCIALMENTE FALSO. `sdd-fase-2.js` usa git worktrees como checkpoints resumiveis. Fragil a crash de maquina, robusto a timeout de sessao.
- **Hash-chain tamper-evidence "inexistente".** PARCIALMENTE FALSO. SHA-256 hash-chain ja implementado e provado em `Modules/Ponto/Services/MarcacaoService.php` (`verificarIntegridade()`); falta portar para `mcp_audit_log`. Reduz o gap #10 a reuso, nao invencao.
- **Synthesis pass formal "nao existe" em multi-agente.** PARCIALMENTE FALSO. `sdd-avaliador-processo.js` ja orquestra sintese formal de 7 streams JSON numa scorecard. Falta generalizar + citation pass separado.
- **Drift Copiloto->Jana.** GENUINO (nao e falso-gap): `jana-recall-flow/SKILL.md` cita `Modules/Copiloto` 18x mas `grep` no codigo real retorna 0 (so existe `Modules/Jana`). Quick-win de confianca.

## Roadmap em 3 ondas

### Onda 1 — Quick wins (alto impacto / baixo esforco · 1-2 semanas) — tudo CONSOLIDAR

- **Corrigir o decoder base64 em `adr-index-generate.mjs`** (gap #1) — 56 titulos ilegiveis na fonte unica queryable; conserto de uma funcao.
- **Registrar `block-pr-without-approval.mjs` em `settings.json` + ActionGate strict nos paths criticos** (gap #3) — codigo do hook ja existe; R10 deixa de depender do modelo lembrar (EU AI Act Art.14, deadline ago/2026).
- **Rodar `test-all-hooks-smoke.ps1` em CI** (gap #2) — um job no `gate-selftest.yml`; fecha a assimetria gates-testados-mas-hooks-nao (quebrou 4 hooks em mai/2026).
- **Shipar bi-temporal event-time (ADR 0074)** (gap #4) — ~2 dias ja estimados; destrava a vitoria de manchete do Zep (+18.5% acc em knowledge-updates).
- **Portar hash-chain do `MarcacaoService` para `mcp_audit_log`** (gap #10) — reuso de codigo provado; audit append-only -> tamper-evident.
- **Corrigir o drift Copiloto->Jana nas skills** — baixissimo esforco, alta confianca.
- **Fechar o campo `tier` nas ~19 skills sem tier** (Skills) — convencao 0095 deixa de driftar de si mesma.

### Onda 2 — Endurecer e medir o que tem (2-6 semanas)

- **Backfill do corpus de fatos + cron de `ExtrairFatosDaConversaJob`** (gap #6) — destrava o ROI de TODA a pipeline RAG ja construida.
- **Promover anchor-gate advisory->required pelo calendario ADR 0275** (gap #5) — depois do backfill de coverage.
- **Ledger estruturado de decisoes de hook** (gap #7) — espelhar `mcp_audit_log` para hooks; pre-condicao de promover gate (FP<10%).
- **Cost-cap/circuit-breaker por-run + telemetria de tokens por subagent** (gap #9).
- **1a serie de calibracao kappa (judge vs Wagner) + popular `evals/results/`** (gap #8) — todo score do aparato e precisao sem acuracia conhecida ate isso rodar.
- **1o red-team programado** (EVAL_PROTOCOL Onda 3) — medir a fracao REAL de injecoes que os gates seguram.
- **Resolver as 13 colisoes de numero de ADR** + ratificar ADRs-lei em `proposto` (0072/0074/0256/0257/0258) + drift-alarm de qualidade automatico.
- **Ativar subsistema DB-primary de versionamento+evals de skills (ADR 0076)** — versoes imutaveis + labels + test_runs.

### Onda 3 — Estrategico (EVOLUIR · construir novo · trimestre+)

- **Online-eval por-trace em trafego de producao** — LLM-as-judge continuo em 5-10% + heuristicos em 100%, score no span. Fronteira de valor do SOTA 2026; maior gap real de observabilidade.
- **Defesa dedicada contra prompt-injection INDIRETA** (tool-output / RAG-poisoning / tool-poisoning MCP) — hoje protege a ACAO (PolicyEngine), nao o CONTEUDO recuperado. OWASP LLM01 +340% a/a; ADS roteia acoes a partir de eventos externos.
- **Error-budget / burn-rate por SLI de agente** com THROTTLE/FREEZE automatico — governo de autonomia (reliability != accuracy).
- **Isolamento de runtime (microVM/gVisor/Firecracker)** para codigo executado por agente — tier minimo SOTA 2026; hoje a contencao e por allowlist logica.
- **`context:fork`/`agent` nas skills pesadas** + capability tree/discovery indexada das 69 skills + `plugin.json` bundling.
- **Trace causal hierarquico multi-agente** (lead->subagent->tool->custo) + eval de TRAJETORIA + crosswalk formal NIST AI RMF / ISO 42001 (destrava auditoria externa de clientes pagantes).

## Recomendacao final

No agregado, o IA OS deve **CONSOLIDAR** — com EVOLUIR cirurgico em 2-3 frentes. O padrao e inequivoco em 6 das 8 dimensoes pontuadas: a verificacao derrubou sistematicamente os "gaps" mais alarmantes ao encontrar a infra ja construida e cabeada (telemetria de skills rodando, Langfuse deployado, hash-chain provado, circuit-breaker definido, durable-execution via worktrees, synthesis pass formal). O que voce tem e um aparato de governanca agentica que, em mecanizacao, esta a frente do estado-da-arte publico de 2026 para um time de qualquer tamanho — raro numa empresa de 5 pessoas. A divida nao e arquitetural, e de **operacao e ratificacao**: encher corpus vazio, ligar advisory->required, registrar hooks que ja existem, rodar evals ja especificados, ratificar ADRs-lei em `proposto`. A tentacao de construir o proximo sistema brilhante e o maior risco — o ROI mais alto esta em encher o tanque do motor de F1 que voce ja montou. Construa novo (EVOLUIR) apenas onde o SOTA expoe buraco genuino que escala mal com o time MCP entrando: online-eval em trafego vivo, defesa contra injecao indireta e error-budget de autonomia.
