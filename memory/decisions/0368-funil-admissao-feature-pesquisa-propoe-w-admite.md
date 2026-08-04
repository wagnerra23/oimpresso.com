---
slug: 0368-funil-admissao-feature-pesquisa-propoe-w-admite
number: 368
title: "Funil de admissão de feature — a pesquisa de mercado propõe, [W] admite ou recusa com motivo; rotina é a função que a feature entrega"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-08-04"
accepted_via: "Wagner 2026-08-04, textual: 'a pesquisa de mercado é as features iniciais, vão ser aprovadas se eu aprovar, ou recusar e explicar porque. posso solicitar acrescentar uma feature. antes de entrar tem que verificar pesquisar e estabelecer um plano. assim mantem no padrão. ela fica esperando aprovação até alguem decidir, alguem com senha para isso' + 'rotina é procedure ou funções' + 'pode fazer' sobre a tabela A–E de decisões pendentes (emenda à 0105 · autoridade só [W] · estado próprio no enum · recusa gravada no inventário · vocabulário admitida/recusada). O aceite cobre a POLÍTICA. O código (migration do enum, seção no template, lint) vai em PR próprio, com evidência."
module: governance
quarter: 2026-Q3
tags: [governance, feature, intake, aprovacao, hitl, capterra, backlog, sinal, rotina, vocabulario]
supersedes: []
superseded_by: []
related: [0089-capterra-driven-module-evolution, 0105-cliente-como-sinal-guiar-sem-mandar, 0306-strangler-spec-anchored-reconstrucao-sdd, 0070-jira-style-task-management-current-md-removed, 0264-governanca-executavel-trio-dominio-e2e, 0336-gates-design-promocao-por-mordida-provada-emenda-0314]
pii: false
---

# ADR 0368 — Funil de admissão de feature

## Contexto

O fluxo já existia em partes, e as partes não se conheciam.

A [ADR 0089](0089-capterra-driven-module-evolution.md) (aceita 2026-05-06) já descreve nos passos 7–8 exatamente o funil: *"pergunta a Wagner quais aprovar (CLI hoje, tela amanhã)"* → *"cria tasks no MCP para os aprovados"*. Ele roda: **11 `CAPTERRA-INVENTARIO.md`** existem, com capacidades classificadas P0–P3. O que nunca existiu foi o **estado** entre a pergunta e a criação da task — na prática a decisão acontecia no chat e evaporava.

Três obstáculos medidos em `origin/main` @ `72c9424f0a4` (2026-08-04):

1. **A [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) barra feature de pesquisa.** Ela diz, literal: *"Backlog só recebe item se cumpre 1 desses 4 critérios. Hipótese sem sinal não entra — vira anotação em ADR de feature wish, não US ativa."* Feature derivada de benchmark é, pela régua vigente, especulação interna.
2. **A palavra "aprovado" já está ocupada — no mesmo documento.** No `CAPTERRA-INVENTARIO.md`, `✅ APROVADO` significa *"a capacidade já existe no sistema"*, não *"[W] aprovou"*. Dois sentidos lado a lado é ambiguidade garantida.
3. **Não existe estado de espera.** O enum de `mcp_tasks` é `backlog · todo · doing · review · done · blocked · cancelled` (migration `2026_05_04_180015`). O que se usava era o proxy `status: blocked` + `owner: wagner` — que mistura *"esperando decisão humana"* com *"travado por dependência técnica"*. Não há permission de aprovação.

## Decisão

### 1. A pesquisa de mercado é a fonte primária de features candidatas

`capterra-senior` (FICHA) → `/comparativo` (INVENTÁRIO) produzem as candidatas. **[W] pode acrescentar candidata fora da pesquisa** — entrada por chat, como todo pedido (ver `how-trabalhar.md` §"Pedido de tela/feature").

### 2. Três peças antes de ir a voto — sem elas a candidata não é apresentada

| peça | onde já vive | pergunta que responde |
|---|---|---|
| **verificar** | `CAPTERRA-INVENTARIO.md` | já existe no nosso sistema? (`✅ existente` / `🟡 parcial` / `❌ ausente`) |
| **pesquisar** | `CAPTERRA-FICHA.md` | como os melhores fazem, e por quê a premissa deles vale aqui |
| **plano** | `features/<slug>/{requirements,plan,tasks}.md` ([ADR 0306](0306-strangler-spec-anchored-reconstrucao-sdd.md)) | o que exatamente será feito, e como se prova |

Candidata sem as três **não vai a voto** — a decisão fica sem base e o "recusar e explicar" fica sem objeto.

> ⚠️ A peça *pesquisar* carrega a trava do §5 2026-07-16: **traduzir premissa, não copiar solução**. O que o concorrente resolve pode não ser problema nosso — e anti-padrão importado vira lei errada.

### 3. Estado próprio: a candidata espera, e a espera é visível

Nasce um estado de espera no enum de `mcp_tasks`, distinto de `blocked`. `blocked` = travado por dependência técnica; o estado novo = **esperando decisão de quem tem autoridade**. Sem isso, o Daily Brief não consegue separar "o que depende de você" de "o que depende de outra coisa".

### 4. Autoridade: só [W], e sem cerimônia nova

A decisão é de **[W]**. Não se cria permission Spatie nova agora: com um único aprovador, permission é cerimônia sem função. Quando houver 2º aprovador, isto se reabre — e aí é permission de verdade, não convenção.

### 5. Duas saídas, e a recusa **exige** motivo

- **`admitida`** → vira task no MCP + US no SPEC, e segue o fluxo normal.
- **`recusada`** → **motivo escrito obrigatório**, gravado **ao lado da capacidade** no `CAPTERRA-INVENTARIO.md`.

Recusa sem motivo registrado é a garantia de que a mesma capacidade volta daqui a três meses e consome a decisão de novo. É o mesmo princípio do §5 de [`proibicoes.md`](../proibicoes.md), aplicado a produto em vez de mecanismo.

### 6. Vocabulário — desambigua sem tocar no legado

| eixo | termos | quem escreve |
|---|---|---|
| **decisão humana** | `admitida` · `recusada` · (espera) | [W] |
| **estado do sistema** | `✅ existente` · `🟡 parcial` · `❌ ausente` | a skill, ao regenerar |

O rótulo `✅ APROVADO` dos inventários existentes vira `✅ existente` **forward-only**: novo inventário nasce certo, os 11 atuais só mudam quando forem regenerados por trabalho real. Varrer os 11 em lote acorda gate diff-aware sobre dívida alheia (§5 2026-07-12 + emenda 2026-07-27).

### 7. Emenda à ADR 0105 — a admissão de [W] é o 5º critério de sinal

A 0105 permanece válida e **não é editada** (append-only). Esta ADR a **emenda** acrescentando um critério:

> **5º critério — admissão explícita de [W]** sobre candidata que passou pelas três peças do §2 (verificar + pesquisar + plano).

A trava que impede isso de virar backdoor para "acho que seria legal": **as três peças são pré-condição**, não formalidade. Hipótese sem verificação, sem pesquisa e sem plano continua sendo feature wish — exatamente como a 0105 determina.

### 8. Rotina = a função (procedure) que a feature entrega

Decidido por [W] 2026-08-04: *"rotina é procedure ou funções"*. Rotina é o verbo operacional do ERP — *baixar título*, *conciliar OFX*, *fechar caixa*.

**Não nasce artefato novo para ela.** A rotina é declarada no `requirements.md` da feature, e cada rotina **gera ≥1 AC (EARS) e ≥1 UC** no `casos.md` da tela onde é operada. A regra que impede o campo de nascer morto: quem materializa é o **gerador** (`feature:init` emite a seção) e quem cobra é o **lint** que já existe (`feature-lint`), não a lembrança de quem escreve.

> Precedente que justifica a trava: das 3 features reais hoje, **uma não preencheu nem as `Clarifications`** — campo com dono, template e três linhas de instrução ao lado. Campo sem gerador e sem contador vira placeholder.

## Consequências

- O funil ganha estado persistente e auditável; a decisão deixa de morrer no chat.
- O Daily Brief passa a poder separar "esperando [W]" de "travado".
- Recusa vira conhecimento acumulado em vez de conversa perdida.
- Custo: uma migration de enum + uma seção de template + contagem no lint. Nada disso bloqueia merge — o funil é de **entrada de trabalho**, não gate de CI.

## O que esta ADR NÃO decide

- **Não promove gate a required.** `feature-lint` segue advisory: 3 features é massa insuficiente, e a régua da [ADR 0336](0336-gates-design-promocao-por-mordida-provada-emenda-0314.md) exige mordida provada.
- **Não define o critério de entrada dos 553 gaps de dimensão** dos scorecards de tela. A fonte primária desta ADR é a pesquisa de mercado; se os gaps também entrarem no mesmo funil, é decisão [W] posterior — e precisa de filtro, porque 553 × 3 arquivos = 1.659 documentos.
- **Não cria permission Spatie** (ver §4).
- **Não muda o fluxo de produção** (US → feature → tela → casos → teste → âncora). Isto é o portão de **entrada**; o eixo de produção segue como está.

## Reversibilidade

Alta. O estado novo do enum é aditivo (nenhum registro existente muda de valor); o vocabulário é forward-only; a emenda ao 5º critério se revoga com ADR nova. O único item com custo de reversão real seria a permission — e por isso ela **não** foi criada.

## Validação / estado

- ADR 0089 §7–8 verificado em `origin/main` @ `72c9424f0a4`; 11 inventários existem.
- Enum de `mcp_tasks` verificado na migration `2026_05_04_180015_extend_mcp_tasks_for_jira_style.php`.
- Colisão de vocabulário verificada em `memory/requisitos/Cliente/CAPTERRA-INVENTARIO.md` (`✅ APROVADO | 7 | 37%`).
- Ausência de permission de aprovação: varredura em `Modules/Governance/Http/Controllers/` e `Modules/Jana/Services/TaskRegistry/` — nenhum `can()`/`permission` de aprovação.
