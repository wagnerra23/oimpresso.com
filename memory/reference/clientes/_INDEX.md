---
name: Índice de Clientes (memory/reference/clientes/)
description: Lista navegável de perfis cliente padronizados ADR proposal cliente-funcionario-perfis-coleta-sistematica. Ordenada por status comercial. Cada perfil em arquivo dedicado clientes/<slug>.md com frontmatter + 10 sections obrigatórias.
type: index
---

# Índice de Clientes

> Perfis padronizados conforme [ADR proposal cliente-funcionario-perfis-coleta-sistematica](../../decisions/proposals/cliente-funcionario-perfis-coleta-sistematica.md). Skeleton template em [`_TEMPLATE.md`](_TEMPLATE.md).
>
> **Single source of truth:** este índice NÃO duplica info dos perfis · só cross-link + tabela navegação por status.

## 🟢 Piloto ativo

Cliente em onboarding ativo · canary em curso ou planejado · co-design intenso.

| Slug | Razão social | biz | Vertical | Champions | Início canary |
|---|---|---:|---|---|---|
| [martinho-cacambas](martinho-cacambas.md) | MARTINHO CAÇAMBAS LTDA | 164 | OficinaAuto sub-vertical 3 (locação caçamba avulsa) | [lara](../funcionarios/martinho-cacambas/lara.md) · [dani](../funcionarios/martinho-cacambas/dani.md) | 2026-05-19 |

## ✅ Produção

Cliente em operação contínua · sem mudança estrutural pendente.

| Slug | Razão social | biz | Vertical | Champions | Volume |
|---|---|---:|---|---|---|
| [rotalivre](rotalivre.md) | LARISSA COMERCIO DE ARTIGOS DO VESTUARIO LTDA - ME | 4 | Modules/Vestuario CNAE 4781-4/00 | [larissa](../funcionarios/rotalivre/larissa.md) | 17.251+ vendas (~99% sistema) |

## ⏸️ Qualificado (aguardando sinal)

Cliente com sinal qualificado mas ainda não iniciou piloto · ADR 0105 conformidade.

(zero por ora — Vargas/Gold/Extreme/Zoom/Fixar/Mhundo/Produart são candidatos OfficeImpresso saudáveis mas sem perfil criado ainda · criar quando piloto iniciar)

## 🔒 Backlog feature-wish

Cliente sem sinal qualificado · ADR 0105 proíbe perfil ativo. Apenas referência histórica em research/clientes-legacy-officeimpresso/.

(zero — sinal qualificado obrigatório pra criar perfil em clientes/)

## ❌ Churned

Cliente que saiu do produto.

(zero)

---

## Convenções

- **Slug:** kebab-case curto · `rotalivre` (não `rota-livre`) · `martinho-cacambas` · `vargas-recapagem`
- **Status:** prospect | qualificado | piloto-ativo | producao | churned | feature-wish
- **Champions:** funcionários NÃO-dono que adotam o oimpresso (cross-link `funcionarios/<cliente>/<slug>.md`)
- **Cross-link bidirecional obrigatório:** cliente lista funcionários · funcionário aponta cliente_slug
- **Perfis legacy:** `research/clientes-legacy-officeimpresso/<N>-<slug>/` (histórico research) cross-linkados via frontmatter `perfil_legacy:` — não duplicar conteúdo

## Próximos perfis esperados (sequência sinal)

| Cliente candidato | Status sinal | Quando criar perfil |
|---|---|---|
| Vargas Recapagem | candidato OfficeImpresso saudável | Quando piloto iniciar (oficial Wagner) |
| Gold Comunicação Visual | candidato OfficeImpresso saudável | Quando piloto iniciar |
| Extreme · Zoom · Fixar · Mhundo · Produart | candidatos OfficeImpresso saudáveis | Conforme cada um avança |

## Refs

- [ADR proposal cliente-funcionario-perfis-coleta-sistematica](../../decisions/proposals/cliente-funcionario-perfis-coleta-sistematica.md) — esta estrutura
- [ADR 0061 — Conhecimento canônico git + MCP zero auto-mem](../../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)
- [ADR 0105 — Cliente como sinal qualificado](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)
- [ADR 0131 — Tiering memória canônico/local/segredo](../../decisions/0131-tiering-memoria-canonico-local-segredo.md)
- [dominios-verticais-oimpresso.md](../dominios-verticais-oimpresso.md) — mapa verticais
- [funcionarios/_INDEX.md](../funcionarios/_INDEX.md) — índice cruzado funcionários
