---
date: "2026-07-26"
topic: "Grade de réguas COMPLETA (12 dimensões, 3 eixos) vs acima-do-mercado — 1 acima-de-categoria / 22 à-frente-por-integração / 12 refutadas; 1º REFUTADO_TB do histórico; eixo SERVIR-O-NEGÓCIO é o pior (4,5) e piorou na janela"
authors: [C]
prs: [4790, 4794]
outcomes:
  - "Rodada COMPLETA das 12 dimensões via workflow reguas-do-sistema.js — 88 agentes, 88 done / 0 erro, 4,97M tokens, base wt-reguas (HEAD 19a903931). Foi a 3ª tentativa: as 2 anteriores morreram (interrupção + teto de uso) matando Verificar/Grade/Persistir; esta fechou e PERSISTIU no ledger memory/reguas/."
  - "Placar (2 colunas, anti-'0 acima'): 24 claims → 1 ACIMA-DE-CATEGORIA · 22 à-frente-por-integração · 11 empatadas · 12 refutadas na peça · 1 REFUTADO_TB. O REFUTADO_TB é o PRIMEIRO do histórico (era 0 em 81 vereditos, 8 runs até 07-18) — a emenda §5 2026-07-19, que reformulou a pergunta de Integração pra ganhar braço discriminativo, PASSOU no 1º full pós-emenda. Disparou pelo braço (i): incremento nomeado era identidade, não capacidade."
  - "Anti-Goodhart (regra 17): 2 claims plantadas absurdamente falsas na MESMA corrida, 2 medidas, 2 derrubadas, 0 carimbadas (goodhart_ok). Os vereditos não estão sob suspeita de carimbo."
  - "O único ACIMA-DE-CATEGORIA: âncora de design computada do contrato (ancora.mjs::resolveAncora) + anchor-content-check required contra âncora shell/fantasma. Evidência do oposto no líder: issue #337 do Figma Code Connect ('Add a validation for non-existent node-id') ABERTO — publish sucede com nó removido. 3 limites honestos declarados (confiança termina no charter; recorte estreito; distância pode ser de calendário, não de categoria)."
  - "Notas fechadas pela composição determinística em apenas 3 de 12 dimensões: orquestracao-adversarial 6,5 · seguranca-do-agente 6,0 · inteligencia-de-negocio 4,5. As outras 9 saíram SEM NOTA e a grade se recusou a inventá-las (compor à mão seria a agregação de vereditos incomensuráveis que o §5 proíbe)."
  - "⚠️ O retrato gravado se declara modo 'full' tendo 9/12 dimensões sem nota — a rodada usou o script ANTIGO (carregado antes do conserto). É exatamente o defeito que o PR #4794 corrige (modo full-parcial + campo cobertura + eixos_nao_medidos). Não retoquei o artefato da máquina."
  - "Eixo 3 SERVIR-O-NEGÓCIO é o pior e PIOROU: Jana-BI nunca chegou na Larissa, 2 rotas do hub de IA respondem mock (ChatController:533 + PainelController:54), e o ratio negócio÷governança subiu 77% → 78% com o alarme ligado e ninguém atuando — 'sensor sem atuador é dashboard'."
  - "Top-3 roubos mais baratos somam <30min: (1) 12 rótulos de [W] fecham a calibração do juiz — sem isso TODO veredito adversarial correlaciona com nada mensurável; (2) publicar taxa de cache hit no agent-cost-per-pr (o dado já está lá, ~10 linhas) = maior lever de custo; (3) re-rodar --snapshot local pra manchete virar custo÷PR sobrevivente."
  - "Ledger persistido: 57 fraquezas (+6) e 49 claims (+22), com fraquezas registradas nas 11 dimensões."
related_adrs:
  - 0330-mapa-dos-niveis-estado-real-2026-07-constituicao
  - 0333-emenda-0330-eixo-rodar-e-observar-submedido
  - 0327-anchor-content-required-emenda-0314
  - 0336-gates-design-promocao-por-mordida-provada-emenda-0314
  - 0344-two-strikes-cobre-processo
---

# GRADE DE RÉGUAS — IA OS oimpresso vs quem põe a barra em 2026

**Data: 2026-07-26** · Para: [W] · 12 dimensões, 3 eixos · repo vivo verificado em `wt-reguas` (HEAD `19a903931`)

**As 12 dimensões (declaradas, não só as que renderam texto):**

- **Eixo 1 — CONSTRUIR-E-GOVERNAR:** spec-governanca · design-to-code · memoria-conhecimento · catalogo-modulo-opiniao-codigo · orquestracao-adversarial · evals-outcome · erp-ia-produto
- **Eixo 2 — RODAR-E-OBSERVAR (add pela ADR 0333):** observabilidade-agente · qualidade-drift-ia-producao · seguranca-do-agente · custo-eficiencia
- **Eixo 3 — SERVIR-O-NEGÓCIO (ponto cego da ADR 0334):** inteligencia-de-negocio

---

## 1. PLACAR HONESTO

**24 claims de superioridade submetidas ao refutador. Duas colunas distintas — não colapse as duas num número só:**

| Coluna | Qtd | O que significa |
|---|---|---|
| **Acima-de-categoria** (ACIMA_CONFIRMADO) | **1** | Nenhum peer publicado faz a coisa, nem por outro caminho |
| **À-frente-por-integração** (DIFERENCIAL_SISTEMA) | **22** | A peça tem peer; o **TODO montado no mesmo contexto**, não |
| Empatadas | **11** | Mesa de entrada — o mercado faz igual |
| Refutadas | **12** | Alguém publicado faz igual ou melhor. Não re-alegar. |
| Refutado-também-no-todo (REFUTADO_TB) | **1** | Nem a integração sobrevive: o "incremento" era identidade |

**Anti-Goodhart (regra 17 — o controle negativo do próprio refutador):** 2 claims absurdamente falsas foram **plantadas dentro da mesma corrida**, com o mesmo prompt e schema das reais. **2 medidas, 2 derrubadas, 0 carimbadas.** O braço negativo dispara — os vereditos desta rodada não estão sob suspeita de carimbo.

**Notas por dimensão (fechadas pela composição determinística):**

| Dimensão | Nota |
|---|---|
| orquestracao-adversarial | **6,5** |
| seguranca-do-agente | **6,0** |
| inteligencia-de-negocio | **4,5** |
| As outras 9 | **sem nota nesta rodada** |

Não invento as 9 faltantes. Elas têm fraquezas verificadas com nota própria (seção 3), mas a composição não fechou nota de dimensão para elas — e compor eu mesmo seria exatamente a agregação de vereditos incomensuráveis que o §5 proíbe.

**Leitura do placar:** o eixo 3 (servir o negócio) é o pior do sistema, com folga. O eixo 2 tem duas dimensões medidas e as duas ficam abaixo de 7. O eixo 1 não fechou nota — mas é onde vivem 20 dos 22 diferenciais.

---

## 2. DIFERENCIAIS REAIS

Regra dura desta seção: **nenhuma peça isolada é re-inflada.** Cada item abaixo já teve sua peça refutada ou empatada; o que sobrevive é o TODO montado dentro de um ERP vertical multi-tenant em produção, com o agente aplicando a régua a si mesmo.

### 2.1 O único ACIMA-DE-CATEGORIA: âncora de design computada do contrato, com gate required contra âncora-fantasma

`prototipo-ui/ancora.mjs::resolveAncora` resolve a fonte visual de uma tela a partir do `related_prototype` do charter; `anchor-content-check` (required desde a emenda ADR 0327 à 0314) **reprova o PR quando a âncora resolvida é shell, placeholder ou vazia**.

A busca achou o oposto no líder: o **issue #337 do Figma Code Connect ("Add a validation for non-existent node-id") está ABERTO**, e o texto descreve o buraco com todas as letras — nó removido no Figma, link stale no código, `publish` **sucede mesmo assim**. Chromatic/Storybook Connect linka story↔frame e falha por formato de URL, nunca por substância. O `design.md lint` do Google pega broken reference **dentro** da spec, não a aresta tela→artefato, e não é required de merge.

**Limite honesto (três, e nenhum é decorativo):** (1) a confiança termina no charter — não há oráculo formal acima dele, residual já registrado no §5 2026-06-30; (2) o recorte é estreito: afrouxar para "valida referência de design" empata com o Google no dia em que o #337 for implementado — a distância pode ser de calendário, não de categoria; (3) parte do "acima" vem de o mundo Figma não ter o modo de falha "âncora shell" na mesma forma.

### 2.2 Governar a AUTORIDADE de bloqueio, não a configuração dos gates

34 required contexts + 1 ruleset, cada um pinado em `governance/required-checks-baseline.json` com **histórico datado de quem autorizou, qual PR e qual critério**; promoção só com mordida provada (ADR 0336 DR-2, ≥2 mordidas reais) ou emenda nominal; demoção só por ADR append-only; `protection-drift.mjs` reconcilia o baseline declarado contra a proteção viva.

**Limite:** cada eixo tem peer — Chromium CQ gradua experimental→blocking por evidência de flake/regressão (com N medido, mais instrumentado que o nosso ≥2 manual); terraform-provider-github pina contexts como código com `plan` como drift-detector; OpenSSF Allstar enforça drift de branch protection. O incremento é a **amarração motivo↔autoridade** e a **demoção por não-morder** (verde que não pode ficar vermelho é teatro) — nenhum peer pratica a segunda.

### 2.3 Ledger negativo com CATRACA, não com prosa

`memory/proibicoes.md` §5 (50+ lápides, "o que foi tentado · por que caiu · O LIMITE") + `LICOES_CODE.md` com campos lidos por máquina (`Classe`/`Ocorrências`/`Gate`) + regra two-strikes + hook lido no início de toda sessão + a declividade rara: **cobertura só-advisory NÃO conta como coberta** (ADR 0344).

**Limite duro (a peça está REFUTADA e fica refutada):** o **Lore Protocol** (arXiv 2603.15566, mar/2026, open-source) já faz registro append-only de alternativas rejeitadas em git trailers (`Rejected:`/`Constraint:`), lido por qualquer agente; e o **Negative Knowledge as Failure-aware Shared Memory** (arXiv 2606.21024) faz o banco de falhas tipado que o agente é obrigado a ler antes de propor. O que ninguém publica é **reincidência virando enforcement** com o predicado "advisory não fecha". E isso só se sustenta **enquanto o contador dispara** — ver 3.3, o `Classe` é slug livre.

### 2.4 Meta-enforcement dentro do repo do produto

`gate-selftest` required (fixture boa/ruim por catraca, provando que a catraca morde) + 16 testes `settings-*-registration.test.mjs` que provam que o hook está **acoplado ao matcher certo** + `hooks-manifest-generate.mjs` (manifesto derivado, nunca escrito à mão) + `baseline-tamper-guard` + `ledger-hash-chain.mjs` (checkpoints estilo Rekor, adulteração retroativa detectável sem violar append-only).

**Limite:** fixture boa/ruim é prática de vendor de linter (Semgrep, OPA) — refutado e não re-alegado. `hack/verify-*` do Kubernetes cobre drift de artefato gerado; Rekor cobre tamper-evidence. O incremento é o **par**: prova de que a guarda é **invocada** (não apenas correta) encadeada a um recibo tamper-evident, sobre a superfície que o próprio agente-autor pode reescrever.

### 2.5 Controle negativo dentro da própria corrida de julgamento

Claims plantadas absurdamente falsas injetadas **na mesma corrida de produção**, mesmo prompt/schema/modelo, taxa de discriminação obrigatoriamente exibida ao lado do placar (`refuter-canary-check.mjs` + fixtures na catraca `refuter-canary` do gate-selftest, #4559).

**Limite:** honeypot in-band é padrão de anotação há mais de uma década (MTurk/Appen/Scale) e o **Springdrift** (arXiv 2604.04660) já faz canary por requisição dentro do dispatch de produção com evento de degradação — a peça está **refutada**. O que sobrevive é o QC do juiz **preso a meta-gate required do repo do produto** e o resultado publicado no mesmo placar que decide os chips.

### 2.6 O braço adversarial se mede como órgão que pode estar morto

O ledger registra `refutado_tb_acumulado = 0 em 81 vereditos / 8 runs` e trata o 0/81 como **defeito do medidor**, não como saúde — emenda §5 2026-07-19 reformulou a pergunta de integração para poder reprovar, com disclosure obrigatório da não-mordida.

**Limite, e é o mais importante da grade:** o **Azure SRE Agent** já sinaliza regras "never firing"; higiene de alertas SRE já chama always-green de dead code. A peça está empatada. E a emenda **HABILITA** o negativo, **não prova** que ele dispara — proibido dizer "agora discrimina" antes do placar do próximo full. Nesta rodada o negativo disparou 1× (REFUTADO_TB no alarme negócio÷governança) — primeiro ponto fora do 0/81, mas 1 ponto não é distribuição.

### 2.7 Custo alocado ao artefato de entrega, com o resíduo decomposto e o conserto errado travado

81,22% de cobertura; 19,33% publicado como **overhead genuíno decomposto**, não zerado; e as duas correções sedutoras (join por SHA, Anthropic Analytics API) mortas com recibo — 5 das 6 branches de maior resíduo **não têm PR algum**, logo não há aresta para achar.

**Limite:** custo-por-PR-mergeado é produto de prateleira (Anthropic Contribution Metrics, Faros, Jellyfish, DX) e publicar unallocated overhead é doutrina FinOps de manual — **refutado**. Sobra o loop: a refutação virou lápide append-only que barra a próxima proposta do próprio agente que gasta.

### 2.8 O instrumento que se recusa a produzir número, e diz isso no nome

`jana-ragas-gate.yml` se auto-intitula "smoke (tautológico — plumbing, não qualidade)"; a ADR 0318 matou o gt-vs-gt; e desde 07-17 o `--update-baseline` do drift-sentinel fora de `--mock` está **bloqueado por guard** (exige env-var consciente) + bite-test + deprecação formal aberta (US-COPI-143).

**Limite:** o lm-evaluation-harness já se auto-posiciona como sanity-check; a literatura de null-model já prova degeneração e recusa reportar. **Empatado.** O incremento é a **barreira executável** contra a ação que lavaria a métrica degenerada em número de aparência legítima.

---

## 3. GRADE DAS FRAQUEZAS

**Aviso de leitura:** as notas por fraqueza vêm do passe de verificação contra o repo vivo. **Elas não foram compostas em nota de dimensão** — só 3 dimensões fecharam nota nesta rodada (seção 1).

### EIXO 1 — CONSTRUIR-E-GOVERNAR

#### spec-governanca — *sem nota de dimensão*

| Fraqueza | Régua (quem + prática) | Critério objetivo | Nota | Evidência | Próximo degrau |
|---|---|---|---|---|---|
| Enforcement é monocultura Claude Code | Cursor hooks 1.7+ (`preToolUse`/`beforeShellExecution`), Kiro `.kiro/hooks`, Rel(AI)Build (arXiv 2606.26924 — compila a definição pra 7 alvos de IDE) | O bloqueio determinístico viaja junto do repo pra outra ferramenta? | **7** | `ls .cursor .kiro .codex` = ausente; grep por `beforeShellExecution` = só menção de pesquisa. **MAS** 34 required + `enforce_admins` são tool-agnostic por construção, e `.githooks/pre-commit` existe (opt-in, `core.hooksPath` não instalado — SPEC Infra:904 confessa). Resíduo portável real: 4 regras de conteúdo (BOM, merge markers, routes-string, BRL) | Instalar `core.hooksPath` no onboarding + coluna derivada "sobrevive fora do Claude Code?" no `_HOOKS-INDEX` (já gerado) |
| Zero automação por evento do repo | AWS Kiro (hooks declarativos on-save/on-commit/on-schema-change) | Manutenção dispara por evento, sem depender de intenção do agente? | **8** | **Premissa falsa.** `gates-registry.json`: classe `automacao`=21; 25 workflows com `schedule`, 47 com `push`, ~60 com `paths`, 7 abrindo auto-PR/issue; `system-map.yml` rodou `schedule/success` 2026-07-26T11:43; `screen-smoke-after-merge` encadeado por `workflow_run` (967 runs); `briefing-coverage-required` é REQUIRED (ADR 0348). Resíduo: **"on schema change" não existe** (`grep Database/Migrations .github/workflows` = 2 nomes hardcoded) | Gatilho em `**/Database/Migrations/**` + revogar formalmente o `last_tested` (contrato morto há 14 meses, ADR 0330:39) |

#### design-to-code — *sem nota de dimensão*

| Fraqueza | Régua | Critério | Nota | Evidência | Degrau |
|---|---|---|---|---|---|
| Unidade de captura é a tela, não o componente | Chromatic/Storybook (baseline por componente), Figma Code Connect (cobertura por primitivo-folha) | Regressão de primitivo aparece direto, não pela tela que o usa? | **6** | Zero Storybook confirmado. **MAS** existe workbench: `/showcase/components` (`_Showcase/Components.tsx`, 505 linhas, 15+ componentes isolados) — **órfão, 0 gates o visitam**; fidelidade protótipo↔componente em 2 componentes (`statusBadgeFidelity`, `pageHeaderTabsFidelity`, hard-fail em CI, advisory); a11y-axe em 9 primitivos; `layout-primitives-guard` **required**; `component-registry.json` 37/40 mapped | Apontar o VRT existente (`visreg-states.json`) pro `/showcase/components` — chokepoint pronto, zero mecanismo novo |
| Comparador é pixel, não estrutura | Applitools Visual AI (estrutura, menor FP), Percy Visual Review Agent (~40% de ruído classificado) | Diferença semântica separada de ruído de anti-aliasing? | **8** | A metade "3 baselines stale advisory" é **FALSA e datada**: matriz 100% enforcing desde 2026-07-14 (#4243/#4248). Comparadores estruturais vivos: `style-fingerprint.mjs` (25 campos de computed style, 5 passadas, `compararEstados`), `design-diff.mjs`, `render-proto-baseline.mjs` + 5 gates estruturais em CI. Limite REAL: compare contra tela viva só roda manual (ADR 0290 recusou render pareado em CI, 3×) | Cadência manual-agendada do `--compare` (não CI pareado — 0290 é lei) |

#### memoria-conhecimento — *sem nota de dimensão*

| Fraqueza | Régua | Critério | Nota | Evidência | Degrau |
|---|---|---|---|---|---|
| Retrieval da própria canon não é medido | LoCoMo (1.540 q), LongMemEval (500), BEAM, MemoryArena (>80%→~45% sem memória) | Existe número sobre a própria memória? | **6,5** | **Existe, com número:** `jana:recall-eval` (golden 27 queries, recall@5 **0,815** semantic / **0,074** keyword) + RAGAS real (gold N=51, `context_recall_avg` **0,3839**) + `eval:adr-discovery` (30 q) + ablação contrafactual **commitada** (+22% custo, Δ0pp, cancelou 297 runs). Escala 1-2 ordens abaixo do mercado; e nenhum aparece no `PAINEL-SISTEMA.md` (grep = 0 hits) | Estender `system-map.mjs` com seção "evals do próprio conhecimento" — a invisibilidade é a fraqueza, não a ausência |
| Sem invalidação temporal de fato | Zep/Graphiti (bi-temporal, invalida aresta contraditada) | "O que era verdade em maio?" tem resposta? | **7** | **Bi-temporal existe nos dois planos:** ADR 0295 shipada (tool MCP `memoria-historica` com `as_of`) + `topico.schema.json` com `valid_from`/`valid_until` (modelo Zep) e `dependentSchemas` que **proíbe expiração sem âncora**; contradição doc↔doc coberta por Checks O/R/T/Q do `memory-health`; proveniência por fato em `claims[].evidence[]`, gate **required** (ADR 0346). Real: **3 tópicos em 3.251 docs** (0,09%), `SupersedeDetector` OFF, nenhum `as_of` pra `memory/**` | Adoção forward-only dos tópicos + ligar o `SupersedeDetector` |

#### catalogo-modulo-opiniao-codigo — *sem nota de dimensão*

| Fraqueza | Régua | Critério | Nota | Evidência | Degrau |
|---|---|---|---|---|---|
| Zero validação por outcome do parecer | CodeScene Code Red (39 codebases, ~2× tempo / ~15× defeitos) | Um `discordo` prediz defeito real? | **6,5** | O probe **existe e roda** (`funcao-scorecard-outcome-probe.mjs`, wirado em CI advisory). Rodado ao vivo hoje: N=37, r=**0,149**, ρ=**0,084**, recall 1/2, precision 1/19 — **não reproduz** os números da proposta (r=0,26, recall 2/2). `outcome-log.jsonl` prospectivo **não existe**; 1 único arquivo graduado | Criar o outcome-log prospectivo + trocar números fixos por ponteiro+recibo nos docs (§5 2026-07-17) |
| κ vs gold humano inexistente | Galileo/Future AGI (κ juiz↔humano ≥0,6 produção, ≥0,8 forte) | Existe denominador humano? | **8,5** | **JA_EXISTE_TOTAL.** Rodado: `funcao-scorecard-humano.mjs --score` → 7/9 = **77,8%**, **κ=0,591**, [W] cego, no ledger (#4659, 07-21). A fraqueza é cópia de uma linha do próprio doc **2 linhas antes do desmentido**. Resíduos: κ 0,009 abaixo da barra, N=9, 1 rodada; e **κ não é persistido** no ledger (`grep kappa` no ledger = 0) | Mover `kappa` pro `ledger_entry` (2 linhas) — sem isso não dá pra acumular rodadas |

#### orquestracao-adversarial — **NOTA 6,5**

| Fraqueza | Régua | Critério | Nota | Evidência | Degrau |
|---|---|---|---|---|---|
| Fase Debate existe e ninguém invoca | Cognition (clean-context reviewer, ~2 bugs/PR), Open Code Review (AGREE/CHALLENGE/CONNECT/SURFACE) | Agente reage ao achado do outro? | **7** | `grep faseDebate` = só o template. **MAS** reação cruzada roda em 4 pontos: `adr-0296-fault-proof:130` ("você é o CÉTICO DO CÉTICO"), `sequencial` com `PRIOR`, `reguas:331` Integração + guard anti-composição, `refuter-canary`. Ausentes de verdade: **CONNECT** (zero) e **SURFACE sobre o conjunto de achados** | CONNECT + SURFACE na síntese (onde a união de achados já está em mãos) — **nunca** nas 3 lentes do pr-critic |
| Cross-model é same-vendor na prática | Amp Oracle (o3, cross-vendor), Agent HQ (Claude+Codex na mesma task) | O 2º juiz é de outro fornecedor? | **6** | Única corrida: `claude-sonnet-5` (Anthropic × Anthropic), concordância **0,38**, 5 de 8 claims derrubadas. `OPENAI_API_KEY` **já está no repo** (3 workflows). 3 defeitos novos: a trava cross-vendor **não cobre o modo `--verdicts`** (o que foi usado); o selftest em CI **não testa a trava** e o nome do step afirma que testa; os 5 DIVERGE_DERRUBA **nunca voltaram** ao `claims.json` | Uma corrida cross-vendor real + campo `vendor` no artefato + fechar o loop no ledger |

#### evals-outcome — *sem nota de dimensão*

| Fraqueza | Régua | Critério | Nota | Evidência | Degrau |
|---|---|---|---|---|---|
| ADR 0266 (EVAL-001) é artefato, não prática | Anthropic (evals em CI a cada mudança), Braintrust (trace→caso em 1 clique) | O golden set congelado roda? | **5,5** | `prototipo-ui/evals/` tem **1 commit** e `results/` só `.gitkeep` — verdade. **MAS** a função migrou: RC-04→`Casos-coverage · ratchet` (required), RC-05→`Dominio-dict · ratchet` (required), EVAL-003→`prompt-injection-corpus` (controle-negativo provado), EVAL-002 item 1→`outcome-metrics.mjs` (advisory, 24/24 verdes) | Lápide em `prototipo-ui/evals/` apontando pros descendentes — o diretório é fóssil com nome oficial |
| EVAL-002 (`RUBRICA_W.md`) não existe | Galileo (κ juiz↔humano recalibrado a cada troca de modelo) | Delta judge-vs-[W] tem leitura? | **6,5** | `RUBRICA_W.md` de fato não existe. **MAS** o KPI tem 1ª leitura: ledger `tipo:"juiz"` 7/9=77,8%, κ 0,591, cego, #4659; rubrica real vive em `FUNCAO-SCORECARD-METODO.md §1`. Falta o braço **TELA** (screen-qa é LLM-judge com **zero** denominador humano) e cadência | Calibrar o juiz de TELA (5/semana) + 2ª rodada (r2 selada, #4507, esperando 12 rótulos de [W]) |

#### erp-ia-produto — *sem nota de dimensão*

| Fraqueza | Régua | Critério | Nota | Evidência | Degrau |
|---|---|---|---|---|---|
| IA read-only por construção | QuickBooks (5 agentes que FAZEM), D365 Payflow, Joule Agents, Odoo 19/20 | A IA age, com confirmação humana? | **5** | `ChatCopilotoAgent.php:184` "somente leitura" — verdade. **MAS não é "por construção":** mutação via IA já é live com RBAC por tool (`AuthorizesMcpMutation` em 9 tools, incl. `LgpdEsquecerTitularTool` que APAGA); ADS tem PolicyEngine/RiskEngine/ConfidenceEngine/HITL 4 níveis + auditoria. O gap foi **decidido** (ADR 0145, 2026-05-15, auto-nota 62/100) e **abortado na execução**: `find *cobradora*` = 2 arquivos de papel, ZERO código; US-ADS-070 "Bridge ADS→FSM" nunca construída | Retrospectiva na 0145 + emendar a célula L2 da 0330 (a dormência do ADS é o que bloqueia o produto, não só a governança) |
| Mock em rota live | Nenhum líder embarca resposta fabricada em caminho alcançável | Cliente logado alcança mock? | **5** | `/ia/cockpit` (não `/ia/dashboard`) sem feature-flag, no nav; **2ª instância não citada:** `PainelController:54 buildMockPayload()` em `/ia/painel`. **Achado novo:** o gate **required** `No-mock-in-prod` é **estruturalmente cego** — `scanMockArrays` só varre 2000 chars após `Inertia::render(` e o mock está em método helper; e não varre `.tsx` | Estender `no-mock-in-prod.mjs` pra seguir método helper (dono existe, não abrir gate novo) |

### EIXO 2 — RODAR-E-OBSERVAR

#### observabilidade-agente — *sem nota de dimensão*

| Fraqueza | Régua | Critério | Nota | Evidência | Degrau |
|---|---|---|---|---|---|
| Span órfão — não existe cadeia | OTel GenAI SemConv (invoke_agent→chat→execute_tool), Langfuse, Braintrust | A trajetória é uma árvore? | **6** | `OtlpHttpHandler:88-89` gera trace/span aleatório por log — verdade **só do shim OTLP**. A árvore existe: SDK OTel ligado (`OtelServiceProvider:63`), `OtelHelper::span` com `activate()/detach()` em **537 call sites**, propagação W3C cross-process (`PropagateTraceparent`), `RetrievalSpanBuilder` com 9 spans e `parentSpanId`. Furo real: 3 transportes desconexos, `parentObservationId` = **0 ocorrências**, retrieval nunca exportado (`traceId = null`, auto-documentado) | Ler `Span::getCurrent()` no shim + ligar o `traceId` do decorator (1 linha) |
| Só existe o span `chat` | OTel GenAI (`invoke_agent`/`execute_tool` são os load-bearing) | Dá pra responder "chamou a tool errada?" | **6,5** | `grep invoke_agent` = 0. **MAS** `LangfuseAgentTelemetryListener` emite trace+generation por invocação dos 14 agents (= invoke_agent semântico) e `mcp_audit_log` grava custo+latência+tenant por `tools/call`. Faltam spans de tool-use in-agent e correlação entre os 3 transportes | `execute_tool` nas MCP tools + reconciliar os transportes sob o mesmo trace id |

#### qualidade-drift-ia-producao — *sem nota de dimensão*

| Fraqueza | Régua | Critério | Nota | Evidência | Degrau |
|---|---|---|---|---|---|
| Zero eval no tráfego real do cliente | LangSmith/Braintrust (1-10% do tráfego, score no trace) | Score sobre resposta entregue a cliente? | **5** | Confirmado: `config.php:767 'enabled' => false`. Mecanismo pronto e correto (amostragem 5% determinística, PII redigida ANTES do juiz, Ollama zero-egress que PULA em vez de fabricar 0.0). Bloqueio é **não-código**: flip LGPD [W] + `ollama pull`. Mitigação parcial existente: `pii_leak_in_assistant_responses` varre **100%** do tráfego diário | Ligar com portão de calibração acoplado (~20 rótulos/semana) — sem isso é trocar teatro tautológico por teatro com casas decimais |
| Os 2 evals de staging não rodam sozinhos | Prática de canário agendado (RAGAS/DeepEval) | O alarme tem invocador? | **7** | **Desatualizado por 7 dias.** Órfã `governance/ragas-real-trend` tem **3 semanas válidas**, `ran_at` 07-19T06:07 e **07-26T06:06** (dois domingos, ~7min após o cron). E **MORDEU hoje**: `gate_status: fail`, `context_recall 0,3461` < piso 0,36. `ragas_real_uptime` saiu de `not_yet_measured` pra **measured 66,7%** | Armar o `ragas_real_uptime` (3 medições já bastam) + campainha pro `gate_status: fail` (hoje vive só no log do host) |

#### seguranca-do-agente — **NOTA 6,0**

| Fraqueza | Régua | Critério | Nota | Evidência | Degrau |
|---|---|---|---|---|---|
| Zero controle de ambiente no caminho real | Anthropic containment (seatbelt/bubblewrap, netns+proxy, `allowUnsandboxedCommands:false`) | O agente que roda de verdade está contido? | **6,5** | `.claude/settings.json` tem exatamente 3 chaves: `$schema`, `permissions`, `hooks`. Zero `sandbox`. Devcontainer com egress default-deny existe e é **opt-in**, confessando que não protege o desktop. **Mas "chokepoint fantasma" é FALSO:** `devcontainer-firewall.yml` tem path-filter + cron + **controle-negativo** (sem firewall, example.com TEM que dar 200, senão falha). Falta adoção, não invocação | Chave `sandbox` no settings versionado com `failIfUnavailable` |
| Modelo de permissão não versionado | Anthropic Enterprise (`managed-settings.json` + `allowManagedPermissionRulesOnly`, deploy MDM) | Dá pra provar qual é a postura de segurança de cada dev? | **5,5** | `permissions` tem **só `allow`** (9 entradas), **zero `deny`**. Toda a deny-list real vive em `settings.local.json` gitignored e per-dev; o `.example` só semeia o token MCP. Os 16 testes de registro cobrem **hooks**, não `permissions`. Mitigação: `block-destructive.mjs` (8 categorias) É versionado e red-teamed | Versionar a deny-list (decisão [W], como a auditoria 07-10 já concluiu) — com F/M/L/E entrando, isso vira dano operacional |

#### custo-eficiencia — *sem nota de dimensão*

| Fraqueza | Régua | Critério | Nota | Evidência | Degrau |
|---|---|---|---|---|---|
| "Custo é só valor cultural, sem número" | DX AI Cost Management (custo/PR por time e contribuidor, jun/2026) | Existe custo observável por unidade de entrega? | **8** | **Premissa stale.** Mecanismo vivo desde 07-12 (#4195), snapshot com cobertura 80,67%, mediana US$19,26/PR, resíduo decomposto. **Raiz da recorrência:** `reguas-do-sistema.js:51` ainda carrega o texto "hoje é só valor cultural do Wagner, sem número" — o instrumento propaga a premissa velha | Corrigir a linha 51 do próprio workflow + emendar a célula da ADR 0333 |
| O número publicado ainda é o de atividade | Régua 2026: "cost per merged, **non-reverted** PR" | O denominador é outcome? | **8** | O join de sobrevivência **shipou** (#4534, 07-19) e rodou ao vivo: US$40,47/PR mergeado → **US$43,61/PR sobrevivente**, sobretaxa US$3,14, CFR 7,2%. Mas o snapshot commitado (07-17) **não tem** o bloco `sobrevivencia` — e o render do CI diz honestamente `_n/d_` + o comando do conserto. **Gap maior não apontado:** a injeção per-PR (skill passo 7) tem **0 invocações** no mundo real | Re-rodar `--snapshot` local (1 comando) + bite-test da injeção per-PR |

### EIXO 3 — SERVIR-O-NEGÓCIO

#### inteligencia-de-negocio — **NOTA 4,5** (a pior do sistema)

| Fraqueza | Régua | Critério | Nota | Evidência | Degrau |
|---|---|---|---|---|---|
| Distribuição zero — a Jana-BI nunca chegou na Larissa | Business Central 2026 (copiloto incluído na licença de todo usuário), Odoo 19 (IA no core) | O dono do negócio usa? | **4** | Citação [W] confirmada ("está só em teste"). **MAS** o gate `charter-live-signal` é **required** ("live = evidência, não palavra") e o `anchor-lint` tem o veredito `nao_servido` — o detector literal desta fraqueza já existe. `prod-flags.json` ganhou Jana/Chat e Jana/Dashboard biz 1,4 (#4730, 07-23) e **rebaixou** Painel/Pro/Memoria pra draft. **Buraco real e pior:** `route-hits.json` está **15 dias stale**, coleta default OFF, e é **tenant-blind por design** — mesmo fresco não responde "a Larissa usou" | Métrica de adoção por **tenant×feature** + reanimar o export de route-hits |
| Mock em rota live no hub de IA | (mesma régua) | — | **5** | Ver erp-ia-produto acima — mesma raiz, dois donos | (mesmo degrau) |

---

## 4. JÁ FEITO desde o último retrato (07-18 → 07-26)

Crédito antes de cobrança. Nada abaixo entra em "roubar" nem em chips.

| Item | Prova | Fecha |
|---|---|---|
| **Os evals da Jana passaram a rodar sozinhos e MORDERAM** | Órfã `ragas-real-trend` com 3 semanas; `ran_at` 07-19 e 07-26 (~7min pós-cron); `gate_status: fail` com `context_recall 0,3461 < 0,36`; `ragas_real_uptime` = **measured 66,7%** | Metade de US-COPI-140 — o alarme deixou de ser mudo |
| **Custo por PR SOBREVIVENTE** (denominador de outcome) | #4534 (07-19), rodado: US$40,47 → US$43,61, sobretaxa US$3,14, CFR 7,2% | A régua publicada de 2026 (merged **e não revertido**) |
| **Calibração humana do juiz de função** | Ledger `tipo:"juiz"`, 7/9 = 77,8%, κ 0,591, [W] cego (#4659, 07-21) | O "κ vs humano inexistente" |
| **Controle negativo do refutador** (`refuter-canary`) + braço cross-model | #4559 / #4560 (07-19) | A fraqueza `orq-anti-goodhart` (nota 5,0) — **não re-listar** |
| **LC-08 ganhou sonda que morde** (instrumento errado vs porta viva) + 2ª porta (Grep por `glob`) + par P3 (data de git log em clone raso) | #4771, #4772, #4779, #4789 | 3 dos 9 sub-comportamentos da classe mais reincidente |
| **LC-09 medido e REPROVADO** (100% FP em corpus de 769) + LC-10/LC-11 novas + fix `semGate` | #4790, `2d39eb231` | O loop de aprendizado — a rejeição por medição é o produto |
| **fact-anchor ampliado medido e REPROVADO** (~64% FP) | #4777 | Impede o gate ruim de nascer |
| **`sdd-from-source`** (3 camadas, ADR 0351) + piloto Produto/Edit + B1-controle | #4767, #4782 | Shift-left do SDD |
| **Produto: cross-tenant retorna 404, não 500** (Tier 0) + contrato PUT failing-first | #4769, #4780 | Tier 0 real, com teste vermelho antes |
| **Régua com persistência incremental** (sobrevive a morte parcial) | `cf983a5e4` + `reguas-workflow.test.mjs` | A grade que produziu este documento |
| **Estados isolados do `visual-regression` 100% enforcing** | #4243/#4248 (07-14) | A dívida "3 baselines stale advisory" — **morta** |
| **34 required contexts** (não 24) — `anchor-content-check` (0327), `layout-primitives-guard` (0339), `briefing-coverage` (0348), `Tópico` (0346), `deadlink-gate` | `required-checks-baseline.json` | O bookkeeping da ADR 0330, que está stale |
| Higiene de CI: DS gate confundia CANCELADO com FALHO; `tasks-index --check` fiado; `catalog-graph --mermaid` | #4783, #4784, #4788 | Vermelho falso e máquina sem fluxo |

---

## 5. ONDE A RÉGUA É VOCÊ (empates a defender)

11 empates. Empate não é derrota — é **mesa de entrada mantida**, e cada um destes custa manutenção contínua para não virar atraso:

1. **Autoridade de bloqueio como artefato versionado** — par: Chromium CQ + terraform-provider-github + Allstar. Defender: a **demoção por não-morder** é o pedaço que ninguém pratica; se parar, viramos ratchet-everything.
2. **Ledger negativo ligado à catraca** — par: Cursor Rules (advisory) + AGrail (gate) + COE Amazon (contador). Defender: o `Classe` precisa de vocabulário fechado ou o contador nunca dispara.
3. **Instrumento que recusa número tautológico** — par: lm-eval-harness. Defender: a barreira executável do `--update-baseline`.
4. **DTCG com prova de equivalência por escopo de cascata** — par: Terrazzo (`scopeSelector`) + snapshot/`git diff --exit-code`. **Atrás em enforcement**: o `ds-tokens-build-sync` é advisory e o próprio header admite que JSON editado sem rebuild envenena o loop.
5. **`lapide-recheck` (frescor da memória negativa)** — par: EMSE outdated-code-references + skill Documentation Freshness + STALE benchmark (arXiv 2605.06527, dimensão Premise Resistance).
6. **Contador vivo de sicofância** — par: Azure SRE Agent (never-firing rules).
7. **Métricas com cegos declarados no artefato** — par: HF metric cards + doutrina DORA (não agregar). Nosso delta: cego como **campo de runtime** + trava versionada.
8. **Camada B UNGUARDED versionada** — par: Chromium TestExpectations / pytest xfail / fixtures `expect: allow` de OPA-Semgrep.
9. **Job de CI que se recusa a fingir que mede** — par: dbt source freshness / Prometheus staleness markers / Grafana No-data.
10. **Meta-enforcement no repo do produto** — par: `hack/verify-*` do Kubernetes + Rekor/Trillian.
11. **Alarme negócio÷governança** — par: Jellyfish/Swarmia (allocation configurável) + arXiv 2606.28235 (governar o repositório, não o agente) + arXiv 2607.13070 (standing invariants).

**O único REFUTADO_TB da rodada é o item 11** — e a refutação é dura e vem do nosso próprio dado: o ratio piorou de 77% (07-17) para **78% (07-26)**, com a semana quase inteira em governança. Um invariante que degrada 8+ dias sem mudar o comportamento do agente é, funcionalmente, um dashboard. Sensor sem atuador.

---

## 6. O QUE ROUBAR — TOP 8 (impacto ÷ esforço)

Só o que **não** shipou. Verificado contra o repo hoje.

| # | Roubo | De quem | Onde plugar | Esforço | Por que primeiro |
|---|---|---|---|---|---|
| **1** | **Fechar a rodada 2 de calibração do juiz de claims** — folha cega e gabarito selados desde #4507, faltam 12 rótulos | Doutrina de eval 2026: "judge sem calibração humana é o erro nº 1" | `memory/reguas/2026-07-17-calibracao-juiz-r2/` | ~20 min de [W], zero engenharia | Sem isso, **todo veredito adversarial desta grade** correlaciona com nada mensurável |
| **2** | **Publicar a taxa de cache hit** (`cache_read ÷ input`) no `--json`/`--brief`, por modelo | KV-cache prefix como #1 lever de custo multi-turn; Coinbase 5%→60% ≈ metade do gasto | `agent-cost-per-pr.mjs` (já **lê e precifica** cache_read/cache_creation; `grep cache_hit` = 0) | ~10 linhas | O maior lever de custo do sistema, e o dado já está na mão |
| **3** | **Re-rodar `--snapshot` local** e trocar a manchete pra custo÷PR **sobrevivente** | Régua 2026 do denominador de outcome | `scripts/governance/data/agent-cost-per-pr-snapshot.json` (`sobrevivencia` = false) | 1 comando | O código já faz; só a invocação falta |
| **4** | **Apontar o VRT existente pro `/showcase/components`** | Chromatic (unidade = componente), Figma Code Connect (primitivo-folha cascateia) | `tests/Browser/visreg-states.json` → rota do showcase | Baixo | Workbench com 15+ componentes isolados existe e **0 gates o visitam** |
| **5** | **Uma corrida cross-VENDOR de verdade** + gravar `vendor` no artefato + fechar o loop no `claims.json` | Amp Oracle (o3), Agent HQ | `reguas-cross-model.mjs` (a trava não cobre `--verdicts`; `OPENAI_API_KEY` já no repo) | Baixo | O teste **mais fraco possível** já derrubou 5 de 8 claims. O forte nunca foi feito |
| **6** | **Vocabulário FECHADO para `Classe`** do LICOES_CODE (enum versionado + AJV) | Negative Knowledge (arXiv 2606.21024) — schema tipado, curador se compromete | `memory/LICOES_CODE.md` + schema `memory/**` já existente | Baixo | Com slug livre, a mesma família entra como 2 classes e o **two-strikes nunca dispara** |
| **7** | **Matar o mock em rota live e entregar a Jana-BI ao dono** | Business Central (copiloto incluído), Odoo 19 (IA no core) | `ChatController:533` + `PainelController:54` + escopo pequeno pra biz=4/164 | Médio | É a pior nota do sistema (4,5) e nenhum sinal nasce antes do uso |
| **8** | **Sandbox nativo no caminho real + deny-list versionada** | Anthropic containment + Enterprise `managed-settings.json` | `.claude/settings.json` (hoje: 9 `allow`, 0 `deny`, 0 `sandbox`) | Médio | Com F/M/L/E entrando, cada dev terá a própria postura e ninguém prova qual é |

---

## 7. CHIPS SUGERIDOS (1 por fraqueza real, com a ressalva do adversário embutida)

1. **spec/monocultura** — instalar `core.hooksPath .githooks` no onboarding + coluna derivada "sobrevive fora do Claude Code?" no `_HOOKS-INDEX` (já gerado). *Ressalva:* NÃO criar gate/índice novo de "paridade cross-tool" — duplica régua consolidada (§5 2026-07-09) e a maioria dos hooks é agent-behavior, não portável por definição.
2. **spec/evento** — gatilho em `**/Database/Migrations/**` + revogar formalmente o `last_tested` por ADR. *Ressalva:* não é presence-gate — o evento **executa o gerador**, não checa presença.
3. **design/componente** — apontar o VRT existente pro `/showcase/components`. *Ressalva:* NÃO importar Storybook; e nada de razão/nota agregada de fidelidade (§5 2026-07-17).
4. **design/estrutura** — usar `style-fingerprint` como camada de desempate DENTRO do `visual-regression` (prod × baseline nossa). *Ressalva:* não é proto×prod pareado — ADR 0290 morreu 3×.
5. **memoria/retrieval** — estender `system-map.mjs` com "evals do próprio conhecimento". *Ressalva:* extensão do dono; nenhum catálogo por-pergunta (§5 2026-07-25), nenhum mapa novo (§5 2026-07-23).
6. **memoria/temporal** — ligar o `SupersedeDetector` (flag OFF) e adotar tópicos forward-only. *Ressalva:* nada de campo de frescor auto-declarado (`last_validated`/`verificado_em` já lapidados).
7. **catalogo/outcome** — criar o `outcome-log.jsonl` prospectivo + trocar números fixos por ponteiro+recibo nos docs. *Ressalva:* o probe **não reproduz** os números da proposta (r 0,26→0,149) — corrigir antes de citar.
8. **catalogo/κ** — persistir `kappa` no `ledger_entry` (2 linhas). *Ressalva:* não promover a gate — é medição, não portão (ADR 0314/0336).
9. **orq/debate** — CONNECT + SURFACE **na síntese**, com braço de controle sem-debate na mesma corrida. *Ressalva:* NUNCA nas 3 lentes do pr-critic (cegueira é by-design anti-contaminação, job advisory por lei, custo por PR) — candidato a §5.
10. **orq/cross-model** — corrida cross-vendor + `vendor` no artefato + selftest que de fato exercita a trava. *Ressalva:* o nome do step hoje afirma cobertura que não roda (§5 2026-07-16).
11. **evals/EVAL-001** — lápide em `prototipo-ui/evals/` apontando pros descendentes. *Ressalva:* não ressuscitar o diretório; a função migrou e está em required.
12. **evals/juiz** — calibrar o juiz de **TELA** (screen-qa hoje tem zero denominador humano). *Ressalva:* rótulo humano é o oráculo, não presença de campo.
13. **erp/ação** — retrospectiva na ADR 0145 + emendar a célula L2 da 0330 (ADS dormente é o que bloqueia o produto). *Ressalva:* não abrir roadmap paralelo — a 0145 é o dono, com waves e critério de aborto.
14. **erp/mock** — estender `no-mock-in-prod.mjs` pra seguir método helper. *Ressalva:* medir FP **antes** de estender a `.tsx` — vocabulário sintático já deu 130 FP em árvore limpa.
15. **obs/cadeia** — ler `Span::getCurrent()` no shim OTLP + ligar o `traceId` do decorator de retrieval. *Ressalva:* 1 linha cada; o resto é reconciliação de transportes, não motor novo.
16. **qualidade/tráfego** — ligar o eval online 5% **com portão de calibração acoplado** + consumidor do score no `jana:health-check`. *Ressalva:* advisory, nunca gate (ADR 0336); sem os rótulos é teatro com casas decimais.
17. **seguranca/ambiente** — chave `sandbox` + deny-list versionada. *Ressalva:* decisão [W], não gate — a auditoria 07-10 já classificou como "mexe no modelo de permissão".
18. **custo/premissa** — corrigir `reguas-do-sistema.js:51` + emendar a célula da ADR 0333. *Ressalva:* apontar pro dono do número, nunca restatear o número (§5 2026-07-17).
19. **negocio/distribuição** — métrica de adoção por **tenant×feature** + reanimar o export de `route-hits` (15 dias stale, tenant-blind por design). *Ressalva:* o chip entra no backlog da ADR 0334, não em régua nova.

---

## 8. REJEITADOS → §5 (proibições)

**A) As 12 claims REFUTADAS — não re-alegar sem data + fonte de comparação fresca:**
heartbeat tri-estado (Grafana/Datadog/Prometheus/Nagios já separam NoData × Error × valor) · corpus de injection dos próprios vetores (Promptfoo redteam faz as duas metades no modo servido) · fixture de calibração ancorada em incidente próprio (SWE-bench/SWE-Factory: gabarito = teste fail→pass do próprio repo) · honeypot in-band no juiz (MTurk/Appen/Scale; Springdrift arXiv 2604.04660) · contrafactual do próprio corpus (Augment AuggieBench, Vercel, postmortem Anthropic com achado negativo publicado) · grounding por tools tipadas (Odoo 19: isolamento no framework, mais forte que por-tool) · resíduo decomposto + lápide (FinOps Allocation + ADR practice; MOJ combina os dois) · ledger negativo append-only (Lore Protocol arXiv 2603.15566, open-source) · auto-invalidação do instrumento (lm-eval-harness, SWE-bench Verified) · eval da própria IA com piso (Salesforce CRMArena/Testing Center — e publica o número ruim) · custo atribuído ao artefato (Anthropic Contribution Metrics, com fonte server-side melhor que a nossa) · piso derivado do ruído do juiz (earezki noise-band + k=5, arXiv 2603.17172).

**B) Mecanismos rejeitados nesta rodada (candidatos a lápide nova):**
- **Plugar debate nas 3 lentes do `pr-critic`** — a cegueira entre lentes é by-design anti-contaminação (`critica.mjs:12-13`), o job é advisory por lei (ADR 0314) e o custo é por-PR. Debate vai na **síntese**, onde a união de achados já está em mãos.
- **Guard de "paridade cross-tool"** por presença de arquivo `.cursor`/`.kiro` — duplica régua consolidada e mede presença, não bloqueio.
- **Gate exigindo snapshot de custo fresco no diff** — presence-gate (§5 2026-07-01/09). O artefato já se autodenuncia com `_n/d_` + comando + banner de staleness.
- **Estender `no-mock-in-prod` a `.tsx` sem medir FP antes** — família do guard sintático (130 FP medidos em 2026-07-16; 100% FP no detector LC-09 em 2026-07-25).
- **Promover `component-registry-check` a required** — já lapidado (2026-07-17): 0 mordidas, superfície congelada, DR-2 da 0336.
- **Reincidência-pós-gate como métrica** — já rejeitada por medição nesta janela (`2d39eb231`).

**C) Reafirmação da lápide-mãe:** proibido reportar "0 acima" a partir de refutação slice-a-slice. Esta rodada tem **1 acima-de-categoria e 22 à-frente-por-integração** — e 1 REFUTADO_TB, que é a prova de que o braço negativo não é carimbo.

---

## 9. LEITURA FRIA

O sistema constrói e governa a si mesmo melhor do que qualquer coisa que eu encontrei publicada — 22 dos 24 diferenciais só existem porque estão montados no mesmo contexto, e o único acima-de-categoria (gate required contra âncora de design fantasma) tem o líder do setor com o issue equivalente **aberto** desde antes. O eixo que serve o cliente é o pior do sistema (4,5) e piorou na janela: a Jana-BI nunca chegou na mão da Larissa, duas rotas do hub de IA ainda respondem mock, e o ratio negócio÷governança subiu de 77% para 78% com o alarme ligado e ninguém atuando — sensor sem atuador é dashboard. Dos oito roubos que valem, três custam menos de meia hora somados (12 rótulos seus, 10 linhas de cache hit, 1 comando de snapshot) e destravam respectivamente todo o denominador adversarial, o maior lever de custo e a única métrica de custo que fala em outcome.