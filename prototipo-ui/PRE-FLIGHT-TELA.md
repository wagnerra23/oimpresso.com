# PRE-FLIGHT-TELA.md — o resolvedor de pré-requisitos por tela

> **Pra que serve:** impedir que o Claude Design (ou qualquer agente) **invente** (token, Model, componente, padrão) e **repita erro** já catalogado, ao fazer/gradear uma tela. É a peça que faltava entre o `GOLDEN-REFERENCE.md` (o exemplo) e o `SCREEN-GRADE-METODO.md` (a nota).
> **Origem:** 2026-05-30 — Wagner: *"como o design deveria ler os pré-requisitos para fazer cada tela, para ele não inventar e não repetir os erros?"*.
> **Linhagem estado-da-arte (absorvido):** GitHub **Spec-Kit** *context-grounding hooks (read-only probing)* · **Kiro** spec-driven 3-fases · Anthropic **context engineering** *just-in-time retrieval* + *"manter erro no contexto previne repetir"* · Figma **Code Connect** *reusar componente real em vez de inventar*. Aqui tudo é **code-first** (fonte = charter + código, não Figma).

---

## Princípio (a regra que mata invenção)

> **O agente NUNCA monta o próprio contexto de cabeça.** É aí que entram invenção e erro repetido. Ele **roda o resolvedor** que, dado o caminho da tela, devolve o **pacote exato** de pré-requisitos. Lê o pacote → trabalha. Nada fora do pacote pode ser inventado.

Isso é *just-in-time context* (Anthropic): o agente carrega identificadores leves (caminho da tela) e o resolvedor materializa só o necessário no momento da decisão (ADR 0233).

---

## O resolvedor — 4 blocos, cada linha = pergunta → fonte canônica → ação se faltar

### Bloco A · IDENTIDADE (resolve a partir do caminho da tela)
| Pergunta | Fonte canônica | Se faltar |
|---|---|---|
| **Arquétipo?** (form/lista/dashboard/kanban/detalhe/relatório/drawer) | golden do arquétipo + `padroes-tela/PT-0X` | criar o golden do tipo ANTES |
| **Persona dona?** | `_DesignSystem/personas-por-modulo.yml` + ADR UI-0016 | herda persona do módulo |
| **Peso Real?** (quanto investir) | ADR 0232 (contribuição R$ [redacted Tier 0]M) | calcula pelo módulo |

### Bloco B · NÃO INVENTAR (Figma Code Connect, code-first)
| Pergunta | Fonte | Se faltar |
|---|---|---|
| **Contrato da tela?** | `<Tela>.charter.md` (Mission/Goals/Non-Goals) | **PARA** — gera charter draft (`charter-write`) |
| **Componentes disponíveis?** | `REGISTRY_DS_COMPONENTES.md` → `@/Components/ui` | usa o registry, **nunca** hand-roll |
| **Tokens?** | **DS v4 roxo** `primary` (ADR 0235, `resources/css/inertia.css`) — **zero `blue-*`/hex cru** | nunca inventa paleta |
| **Models/Controller/rotas reais?** | pré-flight `CLAUDE_COWORK_PRIMER §3` (Glob Models, Read 1 Controller, copiar middleware) | não inventa `ChartOfAccount` — usa o real |
| **Estrutura de referência?** | golden do arquétipo (`GOLDEN-REFERENCE.md`) | copia, não recria |

### Bloco C · NÃO REPETIR ERRO (Anthropic: erro no contexto)
| Fonte de erro catalogado | Cobre |
|---|---|
| `LICOES_F3_FINANCEIRO_REJEITADO.md` | 21 anti-padrões (6 meta + 15 técnicos) |
| Anti-patterns do **próprio charter** | ex: tabs `border-b-2`, `font-bold` em h1 |
| `PRE-MERGE-UI.md` (AP1-AP8) + `memory/proibicoes.md §UI` | cherry-pick bundle, BOM PowerShell, cor crua |

### Bloco D · COMO VALIDAR (definição de pronto)
| Gate | Fonte |
|---|---|
| 10 regras binárias + 16-dim grade | `GOLDEN-REFERENCE.md` + `SCREEN-GRADE-METODO.md` |
| Testes anti-regressão + smoke biz=1 + screenshot | charter `Tests` + ADR 0101 |
| `ds:report` zero violações `ds/*` | ADR 0209 (ratchet) |

---

## Os 4 upgrades do estado-da-arte (já embutidos acima)

1. **Resolvedor executável, não checklist** — alvo: virar hook/skill `screen-preflight` que roda *read-only probing* (Spec-Kit) e devolve o pacote. Hoje é spec; a wiring vira tool.
2. **REGISTRY como índice consultável** — `@/Components/ui` indexado (Code-Connect-like) pra o agente não conseguir referenciar componente inexistente.
3. **Erros auto-injetados** — `LICOES_F3` + anti-patterns do charter entram no contexto automaticamente (Anthropic memory), não dependem de "lembrar de abrir".
4. **Tokens v4 como SSOT** — `primary` roxo é a única fonte de cor; azul hardcoded é violação medida.

---

## Como pluga no resto

- **Todo agente roda o Pré-Flight ANTES** de fazer/gradear a tela. Sem pré-flight resolvido → não trabalha.
- A grade (`SCREEN-GRADE-METODO.md`) tem a **dimensão 16 "Pré-Flight conformance"**: tem charter? só `@/ui`? tokens v4? zero anti-padrão repetido? — mede objetivamente "não inventou / não repetiu".
- Determinístico: `Sells/Create` → arquétipo `form` → golden form + charter Sells/Create + persona Larissa + tokens v4 + anti-padrões F3 + 4 gates. **Wagner não instrui nada — o pacote se monta.**
