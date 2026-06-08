# 01 — Template canônico de Page Charter

> **Spec do template usado em todo `*.charter.md` ao lado de `*.tsx`.**
> Frontmatter + 7 seções obrigatórias + tabela de Pest GUARD ao final.
> Aprovado em [ADR 0101](../../decisions/0101-sistema-charter-capterra-governanca-escopo.md).

---

## Frontmatter (10 chaves obrigatórias)

```yaml
---
page: /caminho/da/rota                  # rota web canônica (string)
component: resources/js/Pages/X/Y.tsx   # path relativo do .tsx (string)
owner: wagner                           # username (Wagner|Felipe|Maíra|Luiz|Eliana[E])
status: live | wip | sunsetting         # ciclo de vida da tela
last_validated: 2026-MM-DD              # data ISO da última revisão owner
parent_module: Repair                   # módulo dono (PascalCase)
parent_capterra: memory/requisitos/X/CAPTERRA-FICHA.md   # opcional, link
related_adrs: [0101]                    # ADRs ligadas
tier: A | B | C                         # A=prod crítica, B=estável, C=legacy
charter_version: 1                      # incrementa só em supersede (append-only)
---
```

⚠️ **CI bloqueia merge se** faltar `owner`, `status`, `last_validated` ou `tier`.

---

## 7 seções obrigatórias

```markdown
## Mission (1 frase)

## Goals — Features (faz)

## Non-Goals — Features (NÃO faz)        ← anti-alucinação enforced

## UX Targets                             ← usabilidade quantitativa

## UX Anti-patterns                       ← modal indevido, paginação errada, etc

## Automation Hooks                       ← o que a tela dispara automático

## Automation Anti-hooks                  ← o que a tela NUNCA dispara

## Métricas vivas (Pest GUARD)            ← lista de tests que enforcam o charter
```

### Regras por seção

- **Mission** — 1 frase, sem "e" composto. Se tiver dois objetivos, escolher 1 + apender outro como Goal.
- **Goals** — bullet list, verbo no presente ("Listar", "Mostrar", "Editar"). Cada bullet ≤ 1 linha.
- **Non-Goals** — bullet list começando com `❌`. Cada item vira um Pest test `it("não faz X")`.
- **UX Targets** — quantitativos sempre que possível (p95 < 800ms, ≤ 3 cliques, cabe em 1280px).
- **UX Anti-patterns** — `❌` + razão curta (2-5 palavras).
- **Automation Hooks** — endpoint, listener, job, cron disparado pela tela.
- **Automation Anti-hooks** — `❌` + razão curta. Cada item vira Pest test.
- **Métricas vivas** — lista de `ClasseTest::método()` no formato `ModuleCharterTest::it_does_X()`.

---

## Convenção de path

| Tipo | Path |
|---|---|
| Charter de tela Inertia | `resources/js/Pages/<Mod>/<Page>/Index.charter.md` (mesmo dir) |
| Charter de tela Blade legacy (Tier C) | `Modules/<Mod>/Resources/views/<page>.charter.md` |
| Charter de feature/mission | `memory/charters/mission-<slug>.charter.md` |

---

## Append-only: como supersede

Charter aceito **NUNCA é editado em-place**. Mudanças significativas:

1. Bumpar `charter_version` no original → marca como histórico
2. Criar `Index.charter-v2.md` com nova versão + `supersedes: [v1]` no frontmatter
3. CI lê só o arquivo de maior version

Edição mínima permitida: bumpar `last_validated` quando dono confirma "ainda vale".

---

## Exemplo canônico

Ver [resources/js/Pages/Repair/Dashboard/Index.charter.md](../../../resources/js/Pages/Repair/Dashboard/Index.charter.md) — entregue junto a [ADR 0101](../../decisions/0101-sistema-charter-capterra-governanca-escopo.md).

---

## Validação automática (F1 entrega — `02-charter-fetch-tool.md` consome)

Tool MCP `charter-fetch` valida ao carregar:
- Frontmatter parseável (yaml válido)
- 4 chaves obrigatórias presentes
- 7 seções H2 presentes (regex `^## (Mission|Goals|Non-Goals|UX Targets|UX Anti-patterns|Automation Hooks|Automation Anti-hooks|Métricas vivas)`)
- Cada Non-Goal e Anti-hook tem prefixo `❌`

Falha de validação retorna estrutura de erro (não exception) — UX amigável ao agente.
