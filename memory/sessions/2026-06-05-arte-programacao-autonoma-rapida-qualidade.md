# Estado da arte — programar um sistema inteiro mais rápido, autônomo e COM qualidade

> **Data:** 2026-06-05 · **Tipo:** estado-da-arte (pesquisa + comparação com oimpresso + gaps) · **Pergunta do Wagner:** *"Como programar mais rápido sistema inteiro. Acho que com testes especializado? Como isso funciona, pesquise quem consegue programar autônomo com qualidade. Qual nome da técnica? E pontue as melhores com notas e porquê."*

---

## ⭐ Tabela 1 — As técnicas pontuadas (notas e porquê)

> Nota = quanto cada técnica entrega **velocidade COM qualidade** num sistema inteiro (não protótipo). Ponderação: impacto em qualidade autônoma 40% · ganho de velocidade 30% · maturidade/prova 2026 20% · facilidade de adotar 10%.

| # | Técnica | Nota | Por quê (1 linha) |
|---|---|:---:|---|
| 🥇 1 | **SDD — Spec-Driven Development** | **95** | A spec vira o código-fonte. Guarda-chuva de tudo. Kiro: feature 40h→<8h; Spec Kit: ~10× menos "refaz do zero". Padrão enterprise 2026. |
| 🥈 2 | **TDAD — Test-Driven Agentic Development** | **92** | TDD + grafo AST mostrando ao agente QUAIS testes a mudança quebra. **-70% regressão** (6.08%→1.82%). É o "teste especializado" que você intuiu. |
| 🥉 3 | **PBT — Property-Based Testing (2 agentes)** | **90** | Testa *invariantes* (ex: baixa reduz saldo no valor exato), não exemplos. **+23–37% pass@1** vs TDD. Ouro pra regra de negócio com lei. |
| 4 | **Orquestração multi-agente fan-out** | **88** | 1 orquestrador → N subagents isolados em paralelo. **O salto vem daqui, não do modelo sozinho** (Opus+subagent > Opus). |
| 5 | **TDD agêntico (Red-Green-Refactor)** | **85** | Teste é o critério de "pronto" que impede alucinação. Maduro. Cuidado: num único contexto FALHA — precisa subagent por fase. |
| 6 | **Verificação por evidência** | **84** | Modelo *diferente* tenta refutar; CI é o árbitro, não a narração. "Quem faz não dá a nota." |
| 7 | **Mutation testing com LLM** | **72** | Injeta bugs artificiais pra medir a FORÇA real da suíte (cobertura mente). Caro → uso trimestral em módulos críticos. |
| ⚠️ — | **Vibe coding** (anti-padrão p/ sistema) | **40** | Rápido pra demo, **dívida técnica + drift** em produção. Só serve pra explorar/prototipar. |

## ⭐ Tabela 2 — Quem programa autônomo COM qualidade (agentes pontuados · ATUALIZADO 2026-06-05)

> Nota ponderada: **corretude em tarefa real (SWE-bench Pro c/ scaffold padronizado Scale SEAL) 40% · autonomia long-horizon 25% · qualidade/manutenibilidade 20% · custo/controle 15%.**
> ⚠️ **Duas colunas porque há dois "Pro":** o **Pro-SEAL** (Scale, scaffold IDÊNTICO p/ todos, limite 250-turn = honesto) e o **Pro-público/Verified** (cada vendor tuna o próprio agente = inflado/"gamed"). Pondero pelo **SEAL**.

| # | Agente/Sistema | **Pro-SEAL** / Verified | Nota | Por quê |
|---|---|---|:---:|---|
| 🥇 1 | **GPT-5.5 (Codex CLI)** — lançado 23/abr/2026 | **58.6%** / 88.7% | **94** | **Novo #1.** Líder no Pro honesto E no Verified (88.7%, +1.1 vs Opus 4.7). Também #1 Terminal-Bench 2.0 (82%). OpenAI assumiu a ponta em abril. |
| 🥈 2 | **Claude Opus 4.6 + subagent de busca** | **57.5%** / — | **91** | Prova a tese: **orquestração faz o número subir, não o modelo sozinho.** |
| 🥉 3 | **GPT-5.3-Codex** | **57.0%** / 85.0% | **90** | Forte em long-horizon multi-arquivo (107 linhas / 4.1 arquivos). |
| 4 | **Claude Code (Opus 4.8 + Dynamic Workflows)** | ~46% (SEAL) · 69.2% (público) / 87.6% | **90** | Melhor *sistema* agêntico end-to-end: subagents isolados, skills, hooks, até 1.000 subagents, 1M context. **É o stack que o oimpresso roda** — controle+governança imbatíveis. |
| 5 | **Gemini 3.1 Pro** — novo entrante | **54.2%** / 80.6% | **86** | Subiu forte: 3º tier de corretude + 1M context + grátis via Antigravity 2.0. |
| 6 | **Claude Mythos Preview** | **45.9%** (SEAL) · 77.8% (público) / 93.9% | **82** | 93.9% Verified é recorde, mas o gap p/ 45.9% no SEAL **denuncia scaffold gaming**. Hype de número. |
| 7 | **OpenHands / Cline (open-source)** | — / ~70% | **77** | Melhor OSS self-hosted (Cline 5M+ installs, zero markup). On-prem/sem vendor lock. Qualidade abaixo dos frontier. |
| 8 | **Devin Desktop (Cognition)** — relançado 02/jun/2026 | — / 45.8% | **70** | Mais autônomo end-to-end (planeja→testa→abre PR), retirou o nome Windsurf. Mas benchmark unassisted fraco. Marketing > números. |

> 📉 **Leitura crítica (atualizada):** trocando scaffold inflado → padronizado (SEAL), o teto cai de ~88-93% → **~58%**. **Ninguém programa "sistema inteiro sozinho" ainda** — o estado-da-arte honesto é **~58% das issues difíceis** resolvidas autonomamente. Quando alguém te mostrar "93%", pergunte: *é SEAL ou é o agente tunado pro benchmark?*
>
> 🔄 **O que mudou desde a 1ª versão (mai→jun/2026):** (1) **GPT-5.5 tomou o #1** de Claude no Verified e Pro-SEAL; (2) **Gemini 3.1 Pro** entrou no top tier; (3) **Devin** virou **Devin Desktop**; (4) ficou claro que o leaderboard "público" é gamed por scaffold — só o **Scale SEAL** é comparável.

---

## TL;DR (resposta direta)

1. **Não existe UMA técnica — existe um STACK de 4 camadas** que, juntas, é o que hoje permite "programar sistema inteiro autônomo com qualidade":
   - **SDD — Spec-Driven Development** (a spec vira o artefato; o código é o "build")
   - **TDD/PBT agêntico** (o teste é o critério de saída do agente — "testes especializados" que o Wagner intuiu)
   - **Orquestração multi-agente fan-out** (split-and-merge — 1 orquestrador + N subagents paralelos)
   - **Loop de verificação por evidência** (um modelo *diferente* tenta refutar; CI é o árbitro, não a narração do agente)

2. **O nome curto que o mercado usa** pra esse combo é **Spec-Driven Development (SDD)** — guarda-chuva que engloba o resto. A parte de "testes especializados" tem nome próprio: **TDAD — Test-Driven Agentic Development** (paper ACM AIWare 2026) e **PBT — Property-Based Testing** com agentes.

3. **A intuição do Wagner está certíssima:** o que destrava velocidade COM qualidade é o **teste virar o prompt**. O teste é simultaneamente (a) a especificação precisa do comportamento, e (b) o critério objetivo de "pronto" que impede o agente de alucinar. Sem isso = "vibe coding" = rápido pra demo, dívida técnica depois.

4. **O oimpresso JÁ está no top ~5% mundial nessa disciplina** — MWART (5 fases), charters, ADRs, SPEC.md (US-XXX-NNN), Pest biz=1/biz=99, regra "claim sem evidência", coordenador-paralelo, CI gates. O gap real **não é metodologia, é mecânica de teste**: falta **TDAD (impact analysis por grafo)**, **PBT** e **mutation testing**. Ver §6.

---

## 1. O nome da técnica (e a árvore de nomes)

| Nome | O que é | Maturidade 2026 |
|---|---|---|
| **SDD — Spec-Driven Development** | Spec é o artefato canônico; código é output compilado da spec. Guarda-chuva. | ⭐ Padrão de fato enterprise 2026 |
| **TDD agêntico (Red-Green-Refactor com agente)** | Escreve teste que falha → agente implementa o mínimo pra passar → refatora. O teste é o "exit criteria". | ⭐ Maduro |
| **TDAD — Test-Driven Agentic Development** | TDD + grafo AST código↔teste + impact analysis: mostra ao agente QUAIS testes a mudança afeta. Reduz regressão -70%. | 🟡 Research→produção (paper 2026) |
| **PBT — Property-Based Testing (com 2 agentes)** | Em vez de exemplos, define *invariantes/propriedades*; agente Generator escreve código, agente Tester gera contraexemplos. +23–37% pass@1 vs TDD. | 🟡 Emergente forte |
| **Mutation testing com LLM** | Injeta "mutantes" (bugs artificiais); se os testes não pegam, a suíte é fraca. Meta usa em escala. | 🟡 Nicho high-assurance |
| **Vibe coding** | Prompt solto, sem spec/teste. Rápido pra protótipo, **dívida técnica e drift** em produção. | ⚠️ Anti-padrão pra "sistema inteiro" |

> **Consenso 2026 (InfoWorld, Augment, New Stack):** vibe coding e SDD são **complementares** — vibe pra *explorar/prototipar*, SDD+TDD pra *endurecer e shippar*. "Vibe coding gets you something fast. Spec-driven gets you something *right*."

---

## 2. Como funciona o "teste especializado" (a parte que o Wagner intuiu)

A ideia central: **o teste deixa de ser verificação no fim e vira a ESPECIFICAÇÃO no começo.**

```
┌─ RED ───────────┐   ┌─ GREEN ──────────┐   ┌─ REFACTOR ───────┐
│ Escreve teste   │ → │ Agente implementa│ → │ Agente limpa     │
│ que FALHA       │   │ o MÍNIMO p/ passar│   │ sem quebrar teste│
│ (= a spec)      │   │ (= exit criteria)│   │                  │
└─────────────────┘   └──────────────────┘   └──────────────────┘
```

**Por que isso dá qualidade + velocidade ao mesmo tempo:**
- O teste é um *prompt preciso* → o LLM foca em comportamento testável, não em código inflado.
- "Tests give agentic agents reliable exit criteria" → o agente itera SOZINHO até passar, sem depender de palpite ("whims") nem de você revisar cada passo.
- Commits minúsculos e frequentes → cada verde é um checkpoint reversível.

**3 descobertas de pesquisa que mudam COMO fazer (não óbvias):**

1. **TDD em UMA janela de contexto FALHA.** Se o mesmo agente escreve o teste, implementa e refatora no mesmo contexto, "a análise do test-writer vaza pro implementador". **Solução: subagent isolado por fase** (test-writer só vê o requisito; implementer só vê o teste falhando). → Isso é exatamente o pattern de **subagents** que o oimpresso já usa.

2. **TDD como "instrução procedural" PIORA modelos menores** (regressão 6%→9.94%!). O que ajuda não é mandar "faça TDD", é **dar o CONTEXTO de quais testes a mudança impacta** → é a tese do **TDAD** (grafo AST + impact analysis, -70% regressão).

3. **PBT > TDD em corretude:** definir *propriedades/invariantes* (ex: "todo título financeiro baixado reduz o saldo devedor em exatamente o valor pago") + agente Tester que caça contraexemplos = +23–37% pass@1. Mais forte que exemplos input/output.

---

## 3. Quem programa autônomo COM qualidade — leituras-chave

> 👉 O ranking pontuado está na **Tabela 2** no topo (atualizado 2026-06-05). Aqui só as conclusões.

**Leituras-chave do ranking:**
- **O número que importa caiu de ~88-93% pra ~58%** quando se troca scaffold inflado (Verified / Pro-público) por padronizado (Pro-SEAL, Scale, 250-turn). Ninguém "programa sistema inteiro sozinho" ainda — o estado-da-arte honesto é **~58% das issues difíceis** resolvidas autonomamente.
- **GPT-5.5 tomou o #1 (abr/2026)** tanto no Verified (88.7%) quanto no Pro-SEAL (58.6%) — a liderança trocou de mãos desde a 1ª versão deste doc.
- **O salto vem da ORQUESTRAÇÃO, não do modelo isolado:** Opus 4.6 sozinho < Opus 4.6 + subagent de busca (57.5%). Valida o pattern `coordenador-paralelo` + waves do oimpresso.
- **Cuidado com scaffold gaming:** Mythos mostra 93.9% (Verified) mas 45.9% (SEAL). Sempre pergunte qual benchmark. O leaderboard público é tunável pelo vendor; o SEAL não.
- **"Melhor sistema" ≠ "maior número":** Claude Code lidera em qualidade/governança/controle — o que importa pra um ERP multi-tenant com lei (Portaria 671, LGPD), não é corrida de pass@1.

---

## 4. Como "programar sistema inteiro" mais rápido — o stack completo (4 camadas)

```
┌──────────────────────────────────────────────────────────────┐
│ CAMADA 1 · SPEC-DRIVEN (a spec é o código-fonte)             │
│ Spec Kit (93k★) / Kiro (Requirements→Design→Tasks) / BMAD     │
│ → AWS Kiro: feature de 40h shipada em <8h humanas             │
│ → GitHub: ~10× menos ciclos "regenera do zero"                │
├──────────────────────────────────────────────────────────────┤
│ CAMADA 2 · TESTE COMO ESPECIFICAÇÃO                          │
│ TDD agêntico + TDAD (grafo AST, -70% regressão) + PBT (+37%) │
│ → teste é o exit criteria; subagent isolado por fase         │
├──────────────────────────────────────────────────────────────┤
│ CAMADA 3 · ORQUESTRAÇÃO MULTI-AGENTE (fan-out)               │
│ Split-and-merge: 1 orquestrador → até 10 subagents paralelos │
│ → Dynamic Workflows (até 1.000 subagents, mai/2026)          │
│ → cada subagent contexto isolado = qualidade + velocidade    │
├──────────────────────────────────────────────────────────────┤
│ CAMADA 4 · VERIFICAÇÃO POR EVIDÊNCIA                         │
│ Modelo DIFERENTE tenta refutar · CI = árbitro · não narração │
│ → "quem faz não é quem dá a nota"                            │
└──────────────────────────────────────────────────────────────┘
```

**Ganhos medidos (fontes 2026):**
- Kiro: 40h → <8h humanas (spec-first).
- Spec Kit: ~10× menos ciclos de "joga fora e refaz".
- TDAD: regressão 6.08% → 1.82% (-70%); resolução de issue 24% → 32%.
- PBT multi-agente: pass@1 96.3% (HumanEval) / 91.8% (MBPP); +23–37% vs TDD.

---

## 5. Os 3 erros que matam a velocidade (catalogados na pesquisa)

1. **Vibe coding pra sistema grande** → "código plausível que deriva da intenção, alucina APIs e apodrece conforme escala". Foi o que motivou o SDD nascer em 2025.
2. **TDD num único contexto** → as fases se contaminam; o LLM "não consegue de fato seguir TDD". Tem que isolar em subagents.
3. **Mandar "faça TDD" sem dar contexto de impacto** → piora (regressão sobe!). Tem que dar o GRAFO de quais testes a mudança toca (TDAD), não a ordem procedural.

E o erro de orquestração: **fan-out sem schema validation entre subagent e orquestrador** → JSON malformado de 1 subagent corrompe o merge. Regra: "escreva o prompt do subagent como documentação de API — preciso, completo, inequívoco." (O oimpresso já faz isso: "áreas isoladas + comparar e não duplicar + Tier 0 no prompt".)

---

## 6. Comparação com o oimpresso — onde já estamos no top, onde tem gap

| Camada do stack mundial | O que o oimpresso JÁ tem | Nota atual | Gap |
|---|---|---|---|
| **SDD** | SPEC.md (US-XXX-NNN), ADRs Nygard append-only, charters (`.charter.md`), MWART 5 fases, BRIEFING.md por módulo | **90/100** | Quase nada — somos referência. Faltaria só "spec compila pra tasks automaticamente" estilo Kiro |
| **TDD agêntico** | Pest v4, biz=1/biz=99 cross-tenant, FSM tests, regra "F2 baseline antes de mexer" | **75/100** | TDD existe mas **não é red-first forçado por hook**; teste ainda é escrito junto/depois |
| **TDAD (impact por grafo)** | — (nada) | **15/100** | 🔴 **GAP REAL #1** — não há grafo AST código↔teste nem "rode só os testes impactados" |
| **PBT** | — (nada; tudo é example-based) | **10/100** | 🔴 **GAP REAL #2** — invariantes de negócio (saldo, estoque, FSM) seriam ouro pra PBT |
| **Mutation testing** | — (nada) | **5/100** | 🟡 GAP #3 — mediria a FORÇA real da suíte (cobertura mente) |
| **Orquestração fan-out** | `coordenador-paralelo`, waves N agents, split-and-merge manual, áreas isoladas Tier 0 | **85/100** | Falta Dynamic Workflows (script JS orquestrando 100s de subagents) |
| **Verificação por evidência** | Regra "claim sem evidência" Tier 0, hooks `block-claim-without-evidence`, CI gates (governance/mwart/infra-contract), smoke prod obrigatório | **92/100** | Somos referência mundial aqui. Falta só "subagent refutador" automático (modelo diferente revisa) |

**Veredito:** o oimpresso está **acima da média enterprise em metodologia/governança** (camadas 1 e 4 = top mundial). O gap NÃO é processo — **é mecânica de teste** (camadas 2-3 de teste): faltam as 3 técnicas que multiplicam qualidade autônoma → **TDAD, PBT, mutation**.

---

## 7. Top 5 ações — impacto × esforço (recalibrado fator 10x ADR 0106)

| # | Ação | Impacto | Esforço | Por quê agora |
|---|---|---|---|---|
| **1** | **PBT nas invariantes Tier 0** (FSM: nenhuma transição direta em `current_stage_id`; Financeiro: baixa reduz saldo no valor exato; estoque: reserva+consumo = conservação) via `pestphp/pest-plugin-property` ou Eris | 🔥🔥🔥 | 🟢 baixo | Invariantes de negócio JÁ existem como regras Tier 0 — só falta expressá-las como propriedades testáveis. ROI altíssimo num ERP com lei |
| **2** | **TDAD-lite: hook "rode só os testes impactados"** — grafo simples arquivo→teste (já temos convenção `Modules/X/Tests/`), agente roda subset antes de declarar pronto | 🔥🔥🔥 | 🟡 médio | -70% regressão na pesquisa. Casa com regra "mexeu, registra" e CT 100. Acelera loop (não roda suíte inteira) |
| **3** | **Red-first forçado por hook** — bloquear Edit de implementação sem teste falhando commitado antes (igual MWART F2) | 🔥🔥 | 🟢 baixo | Transforma TDD agêntico de "cultura" em "mecânica". Já temos infra de hooks PreToolUse |
| **4** | **Subagent refutador no `/ultrareview`** — modelo diferente tenta REFUTAR o "está pronto" antes do merge | 🔥🔥 | 🟢 baixo | "Quem faz não dá a nota." Fecha a camada 4 com a única peça que falta |
| **5** | **Mutation testing trimestral** (`infection/infection`) só nos módulos Tier 0 (FSM, Financeiro, multi-tenant) | 🔥 | 🟡 médio | Audita se a suíte realmente pega bugs. Caro pra rodar sempre → cron trimestral no CT 100 |

---

## 8. Fontes

**Rankings / benchmarks (atualizados jun/2026):**
- [SWE-Bench Leaderboard Mai 2026 — GPT-5.5 lidera 88.7% Verified / 58.6% Pro](https://www.marc0.dev/en/leaderboard)
- [SWE-Bench Pro 2026 Ranking: Opus 4.7 vs GPT-5.5 vs GPT-5.3-Codex — QCode](https://qcode.cc/en/swe-bench-pro-2026-ranking)
- [SWE-Bench Pro Public (Scale SEAL, scaffold padronizado)](https://labs.scale.com/leaderboard/swe_bench_pro_public)
- [SWE-Bench Pro Leaderboard — llm-stats (público, Mythos 77.8%)](https://llm-stats.com/benchmarks/swe-bench-pro)
- [Best AI Coding Agents 2026 (Claude Code/Codex/Devin/Cline ranked) — Blink](https://blink.new/blog/best-ai-coding-agents-2026)
- [GPT-5.5 vs Gemini 3.1 Pro — DataCamp](https://www.datacamp.com/blog/gpt-5-5-vs-gemini-3-1-pro)
- [SWE-bench Pro Leaderboard 2026 — Morph (Why 46% Beats 81%)](https://www.morphllm.com/swe-bench-pro)
- [SWE-Bench Pro — Scale AI (contamination-resistant)](https://scale.com/blog/swe-bench-pro)
- [SWE-bench Leaderboard 2026 — CodeAnt](https://www.codeant.ai/blogs/swe-bench-scores)
- [14 Best AI Coding Agents 2026 — Morph](https://www.morphllm.com/best-ai-coding-agents-2026)
- [Best AI Agents for Software Dev Ranked — MarkTechPost](https://www.marktechpost.com/2026/05/15/best-ai-agents-for-software-development-ranked-a-benchmark-driven-look-at-the-current-field/)

**Spec-Driven Development:**
- [Spec-Driven Development: Definitive 2026 Guide — BCMS](https://thebcms.com/blog/spec-driven-development)
- [GitHub Spec Kit docs](https://github.github.com/spec-kit/)
- [AWS Kiro vs GitHub Spec Kit — Medium](https://medium.com/system-design-mastery-series/aws-kiro-vs-github-spec-kit-the-honest-comparison-every-developer-needs-right-now-8284412d7668)
- [9 Best AI Tools for SDD 2026 — MarkTechPost](https://www.marktechpost.com/2026/05/08/9-best-ai-tools-for-spec-driven-development-in-2026-kiro-bmad-gsd-and-more-compare/)
- [Vibe coding or spec-driven development? — InfoWorld](https://www.infoworld.com/article/4166817/vibe-coding-or-spec-driven-development-how-to-choose.html)

**TDD / TDAD / PBT / mutation:**
- [TDAD: Test-Driven Agentic Development (arXiv 2603.17973)](https://arxiv.org/abs/2603.17973)
- [TDAD reduz regressões em 70% — thelgtm.dev](https://thelgtm.dev/tdad-test-driven-agentic-development-reducing-code-regressions-by-70/)
- [Use Property-Based Testing to Bridge LLM Code Gen and Validation (arXiv 2506.18315)](https://arxiv.org/pdf/2506.18315)
- [LLMs Are the Key to Mutation Testing — Engineering at Meta](https://engineering.fb.com/2025/09/30/security/llms-are-the-key-to-mutation-testing-and-better-compliance/)
- [Test-Driven Development with Agentic AI — Coding Is Like Cooking](https://coding-is-like-cooking.info/2026/03/test-driven-development-with-agentic-ai/)
- [Forcing Claude Code to TDD: Agentic Red-Green-Refactor — alexop.dev](https://alexop.dev/posts/custom-tdd-workflow-claude-code-vue/)

**Orquestração / Claude Code:**
- [Best practices for Claude Code — Anthropic](https://www.anthropic.com/engineering/claude-code-best-practices)
- [Dynamic Workflows: até 1.000 subagents — MarkTechPost](https://www.marktechpost.com/2026/05/28/anthropic-ships-claude-opus-4-8-alongside-dynamic-workflows-and-cheaper-fast-mode-with-workflows-capped-at-1000-subagents/)
- [Split-and-Merge Pattern — MindStudio](https://www.mindstudio.ai/blog/claude-code-split-and-merge-pattern-sub-agents)
- [The Code Agent Orchestra — Addy Osmani](https://addyosmani.com/blog/code-agent-orchestra/)

---

*Gerado pela skill estado-da-arte. Não executa código nem commita produção — entrega conhecimento decisório. Próximo passo natural: Wagner escolhe quais das 5 ações viram US no backlog MCP (`tasks-create`).*

---

### Trilha do tempo (append-only)

- **2026-06-05 (v1):** versão inicial — SDD/TDAD/PBT + ranking agentes (GPT-5.3-Codex no topo do Pro ~56.8%).
- **2026-06-05 (v2):** Wagner pediu "tabela primeiro" → tabelas com notas movidas pro topo.
- **2026-06-05 (v3):** Wagner pediu "pesquise os atualizados" → dados refrescados jun/2026: **GPT-5.5 assumiu #1** (88.7% Verified / 58.6% Pro-SEAL), **Gemini 3.1 Pro** entrou no top tier (54.2% Pro), **Devin→Devin Desktop** (relançado 02/jun), e separação explícita **Pro-SEAL (honesto) vs Pro-público (gamed por scaffold)**. Tabela 2 reordenada; §3 colapsada pra evitar números duplicados/stale.
