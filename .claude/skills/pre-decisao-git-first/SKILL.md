---
name: pre-decisao-git-first
description: ATIVAR ANTES de interromper o Wagner com uma dúvida durante o desenvolvimento — sempre que for usar AskUserQuestion, escrever "não sei se...", "qual você prefere...", "devo usar X ou Y?", "onde fica...", "como o projeto faz...", "isso já existe?", OU sentir vontade de parar e perguntar no meio de uma atividade. Força resolver a dúvida PRIMEIRO no que o projeto já decidiu — git history, PRs (abertos e fechados), código existente, ADRs/SPECs/handoffs canon — antes de gastar o turno do Wagner. Só pergunta ao humano DEPOIS de esgotar a busca, e aí junto com o resumo do que já checou. Separa "dúvida de fato/precedente" (resolve sozinho no git) de "decisão subjetiva de negócio" (essa continua indo pro Wagner). Tier B auto-trigger. Refs ADR 0070 (git canônico), ADR 0094 (§Princípio 1 context-as-product + §7 transparência), skill mcp-first, skill wagner-request-refiner.
tier: B
trigger: description-matching
parent_adr: "0095"
related_adrs: ["0061", "0070", "0094", "0095"]
---

# pre-decisao-git-first — Tier B auto-trigger

> **Princípio:** antes de gastar um turno do Wagner com dúvida, pergunte ao **git** primeiro. O projeto já decidiu muita coisa — a resposta costuma estar em commit, PR, ADR, SPEC ou charter. Interromper o humano com pergunta cuja resposta já está no canon é desperdício + sinal de degradação.
>
> **O que esta skill NÃO faz:** ela não manda você deixar de perguntar. Ela manda você **esgotar o git/canon** antes de perguntar, e distinguir os dois tipos de dúvida abaixo.

## Os dois tipos de dúvida (a distinção é o coração da skill)

| Tipo | Exemplos | Quem responde |
|---|---|---|
| **Dúvida de FATO / precedente** | "esse padrão já existe no projeto?", "qual o nome canônico de X?", "onde fica a tela Y?", "o projeto usa defer ou eager aqui?", "já tem PR mexendo nisso?", "qual convenção de nomes?", "esse módulo já foi decidido?" | **git / canon** — resolve sozinho. NÃO pergunta ao Wagner. |
| **Decisão SUBJETIVA de negócio** | "prioriza A ou B pro cliente?", "qual meta de faturamento?", "posso mergear em prod agora?", "o cliente aceita mudar esse fluxo?", "vale a pena o esforço?" | **Wagner** — pergunta (continua valendo "pedido vago = pergunta antes", CLAUDE.md). |

> Regra prática: se a resposta é **descobrível** (existe em algum lugar do repo/histórico), é dúvida de fato → busca. Se a resposta depende de **preferência, prioridade, autorização ou contexto do mundo real que só o humano tem** → pergunta.

## Quando ativar

ANTES de:
- Chamar `AskUserQuestion`
- Escrever no chat "não tenho certeza se...", "você prefere...", "devo fazer X ou Y?", "onde está...", "como o projeto faz..."
- Assumir que algo **não existe** e propor criar do zero
- Parar uma atividade em andamento pra pedir esclarecimento de algo factual

## Ordem fixa de busca (5 fontes — pare assim que achar)

### 1. Estado consolidado do projeto (se ainda não carregou na sessão)
```bash
# Brief primeiro (skill brief-first) — muita dúvida some com o estado consolidado
# mcp brief-fetch  →  cycles/goals/handoff/decisões recentes em ~3k tokens
```

### 2. ADRs / SPECs / handoffs canon (decisões já tomadas)
```bash
# Tool MCP preferida (skill mcp-first):
#   decisions-search query:"<tema>"     → ADR que já decidiu
#   memoria-search query:"<tema>"       → fato do business
# Fallback filesystem:
git grep -rn "<palavra-chave>" memory/decisions/ memory/requisitos/ memory/reference/ 2>&1 | head -20
```

### 3. Código existente (o padrão já pode estar implementado)
```bash
# Use Grep/Glob (não cat/find). O projeto imita módulos de referência —
# antes de perguntar "como fazer X", procure X já feito:
#   Grep pattern:"<função|classe|rota|componente>" glob:"Modules/**/*.php"
#   Glob "resources/js/Pages/**/<Tela>.tsx"
# Módulos referência canônica: Modules/Jana, Modules/Repair, Modules/Project (ADR 0011)
```

### 4. Git history (a decisão pode estar num commit)
```bash
git log --oneline -20 -- <caminho-relevante>          # o que mudou nesse arquivo/pasta
git log --all --oneline -S "<termo>" | head -20       # quem introduziu esse termo (pickaxe)
git log --all --grep "<tema>" --oneline | head -20    # commits que citam o tema na mensagem
git show origin/main:<caminho>                         # ⚠️ base pode estar stale — leia canon via origin/main
```
> ⚠️ Se o guard de freshness avisou "BASE STALE", valide existência/canon **sempre** via `origin/main` (`git show origin/main:<path>`, `git ls-tree origin/main <path>`), nunca contra o working tree.

### 5. Pull Requests — abertos E fechados (alguém já pode estar/ter feito)
```bash
gh pr list --state all --search "<tema>" --limit 20                 # PRs que tocam o tema
gh pr list --state open --limit 30                                  # tem PR em voo mexendo nisso?
gh search prs --repo wagnerra23/oimpresso.com "<tema>" --limit 20   # busca ampla
gh pr view <N>                                                      # ler decisão/discussão do PR
gh pr diff <N>                                                      # ver o que já foi feito
```
> PR fechado sem merge também é resposta: já tentaram e **rejeitaram** aquele caminho (ex: `LICOES_F3_FINANCEIRO_REJEITADO.md`). Descobrir isso evita repetir erro.

## Fluxo de decisão

```
Surgiu dúvida durante desenvolvimento
        │
        ├─ É subjetiva/autorização/prioridade? ──► SIM ─► pergunta ao Wagner (não force git)
        │                                              (mas junte contexto: veja tipo 2)
        └─ É factual/descobrível? ──► SIM
                │
                ├─ Busca nas 5 fontes (para assim que achar)
                │
                ├─ ACHOU ─► segue trabalho + CITA a fonte
                │           ("segundo ADR 0143 / PR #1085 / commit abc123, faço assim")
                │
                └─ NÃO achou após esgotar ─► SÓ ENTÃO pergunta ao Wagner,
                                             já dizendo o que checou (ver template abaixo)
```

## Como perguntar DEPOIS de esgotar a busca

Nunca pergunte "cru". Mostre a busca — economiza o turno do Wagner e prova diligência:

```
Procurei antes de perguntar:
- decisions-search "<tema>" → nada relevante
- git log --all -S "<termo>" → nenhum precedente
- gh pr list --state all "<tema>" → PR #NNN fechado sem merge (tentaram Y, rejeitaram)
- Modules/Jana e Modules/Repair → padrão não cobre este caso

Não encontrei decisão canon. A dúvida real é: <pergunta objetiva>.
Opção A: … · Opção B: … — qual seguir?
```

## Anti-padrões (o que esta skill previne)

- ❌ Chamar `AskUserQuestion` sobre algo que `git grep` / `gh pr list` responderia em 5s
- ❌ "Vou criar do zero" sem antes checar se já existe (código, PR aberto, ADR)
- ❌ Repetir um caminho que um PR fechado já rejeitou (não leu o histórico)
- ❌ Perguntar convenção de nome/estrutura sem abrir o módulo de referência (ADR 0011)
- ❌ Assumir que uma fase/feature está "pendente" sem checar prod/PRs (lição pre-adr-introspect)

## Quando NÃO aplicar (não vire desculpa pra nunca perguntar)

- Decisão **irreversível ou de risco** (deploy prod, `gh pr merge`, mudança Tier 0) → aprovação humana continua obrigatória (R10 PROTOCOLO-WAGNER), git não substitui.
- **Prioridade / preferência / trade-off de negócio** que só o Wagner detém → pergunta (git não tem essa resposta).
- Pedido **genuinamente vago** de escopo → a regra "pedido vago = pergunta antes" (CLAUDE.md) segue valendo. Aqui a busca no git serve pra **enriquecer** a pergunta, não pra evitá-la.

## Integração com outras skills

| Skill | Relação |
|---|---|
| [mcp-first](../mcp-first/SKILL.md) | Fontes 1-2 usam tools MCP (`decisions-search`, `memoria-search`, `brief-fetch`) antes do filesystem |
| [brief-first](../brief-first/SKILL.md) | Fonte 1 — muita dúvida some com o brief carregado |
| [wagner-request-refiner](../wagner-request-refiner/SKILL.md) | Pareada — refiner estrutura o pedido; esta resolve dúvidas factuais que surgem durante a execução |
| [pre-adr-introspect](../pre-adr-introspect/SKILL.md) | Mesma família ("checa antes de criar") — aquela é escopada a ADR/schema; esta é geral a qualquer dúvida em atividade |

## Refs

- [ADR 0070 — Git canônico / tasks Jira-style](../../../memory/decisions/0070-jira-style-task-management-current-md-removed.md) (git é fonte de verdade viva)
- [ADR 0094 — Constituição v2](../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) (§Princípio 1 context-as-product · §Princípio 7 transparência)
- [ADR 0061 — Conhecimento canônico git+MCP, zero auto-mem](../../../memory/decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)
- [ADR 0095 — Skills tiers convenção interna](../../../memory/decisions/0095-skills-tiers-convencao-interna.md)
- [PROTOCOLO-WAGNER-SEMPRE.md](../../../memory/reference/PROTOCOLO-WAGNER-SEMPRE.md) (R10 aprovação humana — o que git NÃO substitui)
