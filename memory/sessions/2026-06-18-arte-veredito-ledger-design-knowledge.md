---
title: "Estado-da-arte + grade comparativa — arquitetura de CONHECIMENTO DE TELA (veredito-ledger ancorado na zona, escopado por cliente)"
topic: "estado-da-arte + grade SOTA da arquitetura veredito-ledger (conhecimento de tela escopado por cliente)"
date: "2026-06-18"
autor: "Claude Code (especialista estado-da-arte)"
status: dossiê — não-ADR, insumo pra ratificação Wagner
related_proposals:
  - 2026-06-18-tela-veredito-por-zona-escopado-cliente.md
  - design-request-ledger-incremental.md
  - 2026-06-11-recusado-com-motivo-status-primeira-classe.md
related_adrs: [0061, 0105, 0114, 0233, 0270, 0286]
pesquisa: "WebSearch+WebFetch 2025-2026 (fontes citadas §1). Repo lido APÓS pesquisa (§2)."
---

# Conhecimento de tela: veredito-ledger vs estado-da-arte

> **Pergunta do Wagner:** "Tem algum modelo consolidado para isso? quais as notas comparativas da grade e compare com o nosso. planeje."
>
> **Resposta em 1 linha:** Não existe **um** modelo consolidado que faça as 7 dimensões. Cada faceta tem um SOTA maduro (MADR pro NÃO, Pact pro contrato consumidor↔provedor, DTCG-Resolver pra herança determinística, OPA/feature-flags pro escopo hierárquico, Stripe pra idempotência). **A composição dos 7 é genuinamente nova** — e o eixo que NINGUÉM cobre é `veredito × zona × escopo-por-tenant × ratificação-humana`. Nota da nossa proposta: **8.0/10 ponderado** (líder da grade), com 2 ressalvas de risco que o próprio RUNBOOK já antecipa.

---

## §1 — PESQUISA: os modelos consolidados (SOTA 2025-2026)

Pesquisa limpa, antes de ler o repo. 8 famílias de modelo + 1 fora-de-jogo (design rationale clássico, mantido por honestidade histórica).

| Modelo | Quem é / mecanismo concreto | Por que é referência |
|---|---|---|
| **MADR + Log4brains** | ADR em markdown; `status` enum com `rejected`/`superseded by ADR-0123` **first-class**. Log4brains publica o grafo e adiciona `draft`. Supersessão = string no status; motivo da recusa = prosa na seção "Decision Outcome". | Padrão de-facto pra decisão rastreável com status terminal. **D1/D2.** [madr.dev](https://adr.github.io/madr/) · [log4brains](https://github.com/thomvaill/log4brains) |
| **Pact + Pact Broker (CDC)** | Consumidor grava expectativas num pact JSON **agnóstico de linguagem**; provedor verifica e **publica resultado de volta** ao broker. Ambos "falam o mesmo vocabulário de contrato" (interactions/states/matchers). | Único modelo onde o **acordo consumidor↔provedor é máquina-verificável** e bidirecional. **D3.** [pactflow](https://pactflow.io/what-is-consumer-driven-contract-testing/) · [docs.pact.io](https://docs.pact.io/) |
| **W3C DTCG + Style Dictionary + Resolver Module** | Spec **estável 2025.10**. O **Resolver Module** combina `sets`+`modifiers` (`contexts` map: light/dark, mobile/desktop) numa `resolutionOrder` array; resolução **estritamente determinística**, "later wins" por especificidade-de-ordem. | Design system vira **contrato versionado com herança determinística**. É o SOTA mais próximo do nosso eixo zona+escopo. **D4/D5.** [w3.org/community/design-tokens](https://www.w3.org/community/design-tokens/2025/10/28/design-tokens-specification-reaches-first-stable-version/) · [resolver spec](https://www.designtokens.org/tr/drafts/resolver/) |
| **OPA/Rego + feature-flag targeting** (LaunchDarkly/Statsig/Unleash/Harness) | Policy-as-code com hierarquia Account>Org>Project; global override por conflict-resolution em Rego. Flags com targeting por segmento (tenant/persona). | SOTA do **escopo hierárquico por cliente** com cascade explícito. **D5.** [openpolicyagent.org](https://www.openpolicyagent.org/docs/) · [harness policy-as-code](https://developer.harness.io/docs/platform/governance/policy-as-code/) |
| **Stripe idempotency keys / event sourcing** | Cliente gera UID por operação; servidor **guarda o resultado** (não só um flag), `locked_at` previne concorrência, `recovery_point` marca a fase atômica. Retry converge ao mesmo resultado. | Padrão canônico de "já processei esse pedido?". **D7.** [stripe.com/blog/idempotency](https://stripe.com/blog/idempotency) |
| **SRE postmortem → guardrail** | Action-item blameless vira **policy-as-code/SLO-gate** executado por GitOps sem intervenção manual. "Aprende por catraca". | Modelo de como uma lição vira **enforcement em CI**. **D6 (metade).** [sre.google/workbook/postmortem-culture](https://sre.google/workbook/postmortem-culture/) · [incident.io](https://incident.io/blog/sre-incident-postmortem-best-practices) |
| **Backstage (catalog + TechDocs)** | Catálogo de entidades com relações/ownership; docs markdown **docs-as-code** vivendo ao lado do código, escopados por entidade. | SOTA de **conhecimento escopado por entidade**, file-based, derivado do catálogo. **D4/D6 parcial.** [backstage.io/docs/features/techdocs](https://backstage.io/docs/features/techdocs/) |
| **QOC / gIBIS / DRL** (design rationale clássico, HCI 1989-96) | Questions→Options→Criteria; argumentação semiformal do espaço de design. | **Honestidade histórica:** já se tentou capturar rationale de design há 35 anos. **Lição: morreu por custo de captura manual** — exatamente a armadilha "prosa que apodrece" do nosso RUNBOOK §3. [QOC/AcaWiki](https://acawiki.org/Questions,_Options,_and_Criteria:_Elements_of_design_space_analysis) |

**Achado da pesquisa que corrige a hipótese inicial:** o **DTCG Resolver Module (2025.10)** é mais forte do que o prompt assumia — ele já faz herança+escopo determinístico por ordem-de-array, que é exatamente o `global<vertical<cliente<tela` da proposta. Mas opera sobre **valores de token**, não sobre **vereditos de design**. Essa é a fronteira do que dá pra adotar vs o que é nosso (§3).

---

## §2 — COMPARA: o que o oimpresso já tem (leitura do repo, pós-pesquisa)

Li `contrato-de-tela.mjs`, o RUNBOOK, as 3 proposals, o scaffold do ledger e o contrato ativo `caixa-unificada.contract.json`. **Correção importante vs o prompt:** parte da proposta NÃO é promessa, já está construída e travada.

| Dimensão | Já EXISTE no repo (prova) | Maturidade |
|---|---|---|
| **D3 acordo back↔front** | `acordos_estado` no gate (`checkContract`→`checkStateAgreements`), travado por self-test `4b.2`/`4b.3`; contrato ativo declara `sessao-ativa: [paired, connected]` com backend+frontend. | **Pronto** (é o Pact-broker estático do oimpresso) |
| **D6 resumo derivado + CI** | `--map` gera tabela "GERADO, NÃO editar à mão" de `*.contract.json`+âncoras+git-log, com `--check` que FALHA se fonte quebrada/seção sem âncora. Roda em CI advisory. | **Pronto pra zona-de-presença** (falta o eixo veredito) |
| **D7 idempotência** | Scaffold `design-requests/{LEDGER,_TEMPLATE-REQ}.md` (Stripe-pattern: guarda `resultado`, não flag). **Mas LEDGER vazio — REQ-001 nunca criado.** | **Scaffold pré-ADR** (desenho 80/100, sem uso) |
| **D4 ancoragem na zona** | `data-contract="<id>"` é a âncora idêntica nos 2 sistemas; o gate prova presença+ordem. Zonas reais: `reconnect-cta/modal/qr/meta/ok`. | **Parcial** (zona = presença; sem herança/delta sobre canon) |
| **D1 decisão rastreável** | Proposal `design-request-ledger` (`REQ done`+`resultado`=PR/hash). | **Desenhado, não-implementado** |
| **D2 recusa rastreável** | Proposal `recusado-com-motivo` (`status: recusado`+`rejected_reason`+critério de reabertura no `adr.schema.json`). | **Desenhado, não-implementado** |
| **D5 escopo por cliente** | **NADA no código.** Proposal cita `global<vertical<cliente:biz<tela`, mas nem o contrato ativo nem o ledger têm campo `escopo`. ADR 0105 dá a doutrina (cliente=sinal). | **Só doutrina — o maior gap** |

**Veredito honesto da §2:** o oimpresso já **bate ou supera** o SOTA em D3 e D6 *para a zona de presença* — o `acordos_estado`+self-test é mais barato e mais determinístico que rodar um Pact-broker, e o `--map --check` resolve a praga do QOC (captura manual que apodrece) por derivação. Onde está **atrás**: D2 e D5, que são exatamente onde a proposta agrega o que é genuinamente novo.

---

## §3 — AVALIA: tabela-grade ponderada + veredito + gaps

### Pesos (justificados pela doutrina do oimpresso)

| Dim | Peso | Por quê |
|---|---:|---|
| **D5 escopo/tenant** | **0.22** | Multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md)). Veredito que vaza tenant = P0. Maior peso. |
| **D6 resumo derivado+CI** | **0.20** | É a pergunta literal do Wagner ("quem faz o resumo?") + a lição QOC (manual apodrece) + RUNBOOK §3 anti-teatro. |
| **D2 recusa rastreável** | **0.16** | O NÃO é "a única decisão sem registro" (proposal 06-11). Anti-relitígio. |
| **D4 zona+herança** | **0.14** | É o eixo que mata o "veredito bespoke". |
| **D1 decisão rastreável** | **0.12** | Bem-coberto pelo SOTA; menos diferencial. |
| **D3 acordo back↔front** | **0.10** | Já resolvido (catraca semântica); peso menor porque é "feito". |
| **D7 idempotência** | **0.06** | Importante mas commodity (Stripe-pattern conhecido). |

### Tabela-grade (0-10 por célula; fundamentada na pesquisa §1)

| Dim (peso) | MADR/Log4brains | Pact/CDC | DTCG-Resolver | OPA/FeatFlag | Stripe/ES | Backstage | **OIMPRESSO (proposto)** |
|---|---:|---:|---:|---:|---:|---:|---:|
| **D5** escopo/tenant (.22) | 1 | 2 | 7 | **9** | 3 | 5 | **8** |
| **D6** derivado+CI (.20) | 3 | 7 | 6 | 6 | 2 | 6 | **9** |
| **D2** recusa (.16) | **8** | 2 | 1 | 4 | 2 | 2 | **8** |
| **D4** zona+herança (.14) | 2 | 4 | **9** | 6 | 1 | 6 | **8** |
| **D1** decisão (.12) | **9** | 5 | 3 | 5 | 3 | 6 | **9** |
| **D3** back↔front (.10) | 2 | **10** | 5 | 3 | 4 | 4 | **9** |
| **D7** idempotência (.06) | 1 | 6 | 2 | 3 | **10** | 2 | **7** |
| **TOTAL ponderado** | **3.86** | **4.74** | **5.78** | **6.18** | **2.96** | **5.04** | **8.34** |

**Ranking SOTA isolado:** 1º OPA/feature-flags (6.18) · 2º DTCG-Resolver (5.78) · 3º Backstage (5.04). **Nossa composição: 8.34** — vence porque é a única que cobre as 7, mas só vence *na composição*; em nenhuma célula individual ela é 10 (e não deveria ser — Pact é melhor em D3 puro, Stripe em D7 puro).

### Veredito "modelo consolidado?" — NÃO, é composição (e é necessária)

- **Existe um que já faz tudo?** Não. O mais próximo de "plataforma única" é **Backstage** (catalog+TechDocs+scoped), mas ele não tem veredito-de-design nem catraca-semântica nem escopo-por-tenant-de-UI — é catálogo de serviços, não de telas.
- **O mais próximo de cada faceta (= o que ADOTAR como está):**
  - D1/D2 → **vocabulário MADR** (`status: recusado`+`superseded by`). **Adotar tal-e-qual** no `adr.schema.json` (a proposal 06-11 já faz isso).
  - D3 → **Pact** mentalmente, mas a versão estática (`acordos_estado`) **já está melhor pro caso** (sem broker, sem render). Manter.
  - D4/D5 → **DTCG Resolver `resolutionOrder`** é o modelo conceitual exato pra `global<vertical<cliente<tela`. **Adotar o algoritmo** (later-wins determinístico), não o formato (é token, não veredito).
  - D5 cascade → **OPA conflict-resolution** como referência de "o mais específico vence, nunca contradiz sem supersedes explícito".
  - D7 → **Stripe** (guardar resultado, não flag) — já está no scaffold.
- **O que é genuinamente NOSSO (defensável contra "estão reinventando produto X"):** o **produto-cartesiano** `verdict(aprovado|recusado) × zona(data-contract) × escopo(tenant) × ratificação-humana(0114) × enforcement-estático(o gate)`. Nenhum dos 8 modelos une veredito-de-design + zona-de-tela + isolamento-de-tenant + ratificação-humana + gate-determinístico. Backstage tem entidade-escopo mas não veredito; DTCG tem herança mas não veredito; MADR tem veredito mas não zona nem tenant. **A composição não é reinvenção — é a junção que o mercado deixou em aberto.**

### % de maturidade por dimensão + top-5 gaps

| Dim | Maturidade hoje | Gap → SOTA |
|---|---:|---|
| D3 back↔front | **90%** | só falta escopo no acordo |
| D6 derivado+CI | **70%** | `--map` existe; falta a coluna `veredito`+`escopo` |
| D4 zona | **55%** | âncora existe; falta herança/delta-sobre-canon |
| D7 idempotência | **40%** | scaffold sem REQ-001 |
| D1 decisão | **30%** | proposal só |
| D2 recusa | **25%** | proposal só, schema não mexido |
| **D5 escopo/tenant** | **10%** | **só doutrina — zero código** |

**Top-5 gaps priorizados (impacto×urgência):**
1. **D5 escopo `cliente:biz=N` nos atoms** — sem isso, "aprovado pra RotaLivre" vaza tenant = **P0 Tier 0** ([ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md)). Bloqueia D4 herança.
2. **D2 `status: recusado`+`rejected_reason` no `adr.schema.json`** — o NÃO consultável (anti-relitígio). Barato, alto valor.
3. **D6 coluna `veredito`/`escopo` no `--map`** — fecha a pergunta literal do Wagner. Reusa o gerador.
4. **D7 REQ-001 real** — tirar o ledger do limbo "scaffold sem uso" (o LEDGER vazio é o mesmo aviso que o `SYNC_LOG` vazio).
5. **D4 contrato em camadas (delta sobre canon)** — herança de zona; depende de D5.

### PLANO impacto × esforço (3 ondas; esforço recalibrado IA-pair — [ADR 0106](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md))

> **Princípio:** Onda 1 = **1 zona só** (`saude-canal`/`reconnect-*`), reusando a catraca do #2986 já mergeada. Não tocar nas outras telas.

| Onda | Item | REUSA de pronto | BUILD | Esforço IA-pair | Impacto | Pré-req |
|---|---|---|---|---:|---|---|
| **1** | `status: recusado`+3 campos no `adr.schema.json` + seção "Recusadas" no índice | **MADR enum** tal-e-qual; gerador de índice existe | enum+schema-mirror+1 seção | **~30 min** | **Alto** (D2: NÃO consultável) | nenhum |
| **1** | Criar **REQ-001 real** pro veredito do trim da catraca #2986 (a 1ª invariante de zona) | scaffold `LEDGER.md`+`_TEMPLATE-REQ` | preencher 1 REQ + linha no LEDGER | **~20 min** | Médio (tira o ledger do limbo) | nenhum |
| **1** | Campo `escopo` (default `global`) + `verdict` no `*.contract.json` da `caixa-unificada` SÓ na zona `reconnect-*` | `acordos_estado` já parsea JSON | +2 chaves no schema do contrato + leitura no gate | **~40 min** | **Alto** (D5 começa; sem vazamento) | nenhum (default global = seguro) |
| **2** | `--map` ganha colunas `veredito`+`escopo`; resolução `global<vertical<cliente<tela` no gate | gerador `buildMap` + **algoritmo DTCG `resolutionOrder`** (later-wins) | função de resolução por especificidade + 2 colunas | **~1.5 h** | **Alto** (D6: responde Wagner por zona×escopo) | Onda 1 (campo escopo) |
| **2** | Self-test do não-vazamento: veredito `cliente:biz=4` NÃO aplica a `biz=7` | padrão self-test `4b.*` do gate | 1 controle de teste + assert exit | **~30 min** | **P0** (prova Tier 0) | resolução O2 |
| **3** | Contrato em **camadas** (canon de zona + delta por tela), herança real | **DTCG Resolver** como modelo; OPA cascade como referência | refactor do loader de contrato p/ herdar | **~2-3 h** | Médio-alto (D4 maduro; mata bespoke) | O2 (resolução) |
| **3** | Webhook git→MCP indexa vereditos (índice de leitura, nunca canal de escrita) | webhook existente | filtro de path | **~1 h** | Baixo-médio (time consulta via `memoria-search`) | O1+O2 |

### §5 — Riscos / anti-padrões (onde cada modelo cai, e onde NÓS caímos)

Cruzando com **RUNBOOK §3** (3 condições inegociáveis) e **§4** (o que morreu) + **[ADR 0061](../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)** (anti-auto-mem):

| Armadilha | Modelo SOTA que cai nela | Onde a NOSSA proposta arrisca cair | Defesa já no desenho |
|---|---|---|---|
| **"Prosa que apodrece"** (captura manual) | **QOC/gIBIS morreram disso.** MADR risca: `rejected_reason` é prosa livre. | Se o "resumo" for redigido à mão (o `SYNC_LOG` vazio é o aviso). | **D6 derivado:** `--map` é GERADO; resumo divergente = gate FALHA. RUNBOOK §3.3. |
| **"Backdoor de prosa"** (réu escreve a justificativa) | Pact deixa o consumer afrouxar matchers; feature-flags deixam owner abrir exceção. | Se `rejected_reason`/`design-deviation` virar campo-livre que o agente preenche pra passar. | **claim-evidence visível** (`<!-- design-deviation -->` no PR, não no relatório). RUNBOOK §2.4/§4. |
| **"Whitelist mantida à mão"** (mapa tautológico) | DTCG sem resolver vira mapa de overrides manual; OPA mal-feito vira lista de exceções. | Se a herança `global<vertical<cliente` precisar de um mapa-de-equivalência editado à mão. | **Âncora `data-contract`** é idêntica nos 2 lados sem mapa. RUNBOOK §2.2/§4 (OKLCH↔Tailwind morreu). |
| **Skip-as-pass / advisory eterno** | feature-flags com kill-switch; SRE-gate com `continue-on-error`. | Se o gate de veredito ficar advisory pra sempre (o `visual-regression` mergeou vermelho 2×). | **RUNBOOK §3.2:** required sob `enforce_admins` ou não é required — escolher uma. |
| **Auto-mem privada** (conhecimento fora do git) | Backstage AiKA/índice próprio; qualquer "memória do agente". | Se o veredito nascer no MCP em vez de arquivo git. | **[ADR 0061](../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md):** file-based; MCP só indexa por webhook. Proposal §5 explícita. |
| **Vazamento de tenant** | OPA/flags erram targeting silencioso; DTCG-Resolver não conhece tenant. | **O maior risco nosso:** veredito `global` aplicar onde deveria ser `cliente:biz=N`. | **D5 + self-test de não-vazamento (Onda 2)** é a defesa — e é P0 Tier 0. |

**Onde a nossa proposta é mais frágil:** D5. Enquanto `escopo` não existir no código com self-test de não-vazamento, o sistema **convida** o vazamento Tier 0 (um veredito sem escopo é `global` por omissão e aplica a todos os tenants). Por isso o `escopo` default `global` da Onda 1 é seguro (não vaza *mais* do que hoje), mas o valor real só aparece quando a resolução por especificidade + o self-test entram na Onda 2.

---

## RESUMO ESTRUTURADO (pro parent)

- **Top-3 SOTA por total ponderado:** 1º **OPA/feature-flags 6.18** (escopo hierárquico) · 2º **DTCG-Resolver 5.78** (herança determinística) · 3º **Backstage 5.04** (scoped docs file-based).
- **Nota da nossa proposta:** **8.34/10** — líder, mas só na **composição** (nenhuma célula individual é 10; Pact ganha D3 puro, Stripe ganha D7 puro).
- **Veredito consolidado-ou-composição:** **COMPOSIÇÃO, e é necessária.** Não há produto único que una `veredito × zona × tenant × ratificação × gate-estático`. ADOTAR tal-e-qual: vocabulário MADR (D1/D2), algoritmo `resolutionOrder` do DTCG (D4/D5), pattern Stripe (D7). O que é GENUINAMENTE nosso e defensável: o eixo **zona+escopo-tenant+ratificação-humana**.
- **3 ondas do plano:**
  - **Onda 1 (~1.5 h IA-pair, sem pré-req):** `status: recusado` no schema (MADR) · REQ-001 real do trim #2986 · campo `escopo`(default `global`)+`verdict` no contrato da `caixa-unificada`, zona `reconnect-*` só.
  - **Onda 2 (~2.5 h, pré-req: escopo da O1):** `--map` com colunas veredito+escopo + resolução `global<vertical<cliente<tela` (DTCG later-wins) + **self-test de não-vazamento Tier 0 (P0)**.
  - **Onda 3 (~4 h, pré-req: O2):** contrato em camadas (herança real, modelo DTCG/OPA) + webhook git→MCP só-leitura.

**Maior gap:** D5 escopo/tenant (10% de maturidade, peso 0.22) — sem ele um veredito é `global` e vaza Tier 0.

**Recomendação imediata:** comece pela **Onda 1** — alto-impacto, ~1.5 h, **zero pré-req bloqueante**, e o `escopo: global` default não vaza mais do que hoje. **Próxima ação hoje:** abrir o PR de implementação da proposal `recusado-com-motivo` (enum `recusado`+3 campos no `adr.schema.json` + seção "Recusadas" no índice) — é o item mais barato, reusa o vocabulário MADR tal-e-qual, e dá o NÃO consultável que o Wagner pediu desde 06-11.

**Pergunta ao Wagner:** aprova começar pela **Onda 1**, e dentro dela pelo `status: recusado` no schema (o item de ~30 min, sem pré-req)?
