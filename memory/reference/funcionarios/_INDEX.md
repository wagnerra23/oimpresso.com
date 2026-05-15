---
name: Índice de Funcionários (memory/reference/funcionarios/)
description: Lista navegável de perfis funcionário padronizados ADR proposal cliente-funcionario-perfis-coleta-sistematica. Ordenada por cliente. Cada perfil em arquivo dedicado funcionarios/<cliente-slug>/<funcionario-slug>.md com frontmatter + 6 sections obrigatórias.
type: index
---

# Índice de Funcionários

> Perfis padronizados conforme [ADR proposal cliente-funcionario-perfis-coleta-sistematica](../../decisions/proposals/cliente-funcionario-perfis-coleta-sistematica.md). Skeleton template em [`_TEMPLATE.md`](_TEMPLATE.md).
>
> **PII real (CPF/email/telefone) NÃO vai em git** · cross-link `pii_vault_ref: vault://<cliente>/<funcionario>` aponta pra Vaultwarden ([ADR 0131](../../decisions/0131-tiering-memoria-canonico-local-segredo.md)).

## Por cliente

### martinho-cacambas (biz=164) — piloto ativo

| Slug | Nome | Role | Papel canary | Sistema atual |
|---|---|---|---|---|
| **[jair](martinho-cacambas/jair.md)** | **Jair** | **Dono majoritário · #1 decisor** | Decisor principal (endossou 2026-05-14) | Delphi (não opera) |
| **[kamila](martinho-cacambas/kamila.md)** | **Kamila** | **ESPOSA do Jair · #2 decisora (quem manda depois)** · operação POS+vendas | Continua Delphi · pausou Highsoft · propôs dual-system | Delphi |
| [martinho](martinho-cacambas/martinho.md) | Martinho | Sócio · dá nome empresa · **pai da Lara** | Decisor secundário (aprovou 2026-05-13) | Delphi (não opera) |
| **[lara](martinho-cacambas/lara.md)** | **Lara** | **Filha do Martinho · responsável ESTOQUE** | **champion-oimpresso** | Delphi → oimpresso 2026-05-19 |
| **[dani](martinho-cacambas/dani.md)** | **Dani / DANIELLI** | **Financeiro** | **champion-oimpresso** | Delphi → oimpresso 2026-05-19 |
| [rodrigo](martinho-cacambas/rodrigo.md) | Rodrigo da Silva | Vendedor externo | Continua Delphi (cutover Fase 4 PWA) | Delphi + Google Form |
| [eduardo](martinho-cacambas/eduardo.md) | Eduardo | Vendedor externo | Continua Delphi (cutover Fase 4 PWA) | Delphi + Google Form |

> Outros operadores Delphi biz=164 (id=290..299) — Andre · Evandro · Luiza Correa · Junior · Teste — **sem perfil dedicado** (não-champions · entram conforme uso real). Mecânicos campo (Leonardo · Leoni · Arthur · Ramon) — não operam sistema (Google Form).

### rotalivre (biz=4) — produção

| Slug | Nome | Role | Papel | Sistema atual |
|---|---|---|---|---|
| **[larissa](rotalivre/larissa.md)** | **Larissa Fernandes** | **Dona/operadora** | cliente piloto vivo (desde 2021) | oimpresso (99% volume) |

> WR2 Sistemas (admin externo Wagner · id=9) · Vendas (id=11) · Caixa (id=72) — **sem perfil dedicado** (não-champion · admin externo + operadores secundários). Catalogados no perfil cliente [rotalivre](../clientes/rotalivre.md).

## Convenções

- **Slug:** `first-name-lowercase` único por cliente — `larissa` · `jair` · `kamila` · `lara` · `dani`
- **Path:** `funcionarios/<cliente-slug>/<funcionario-slug>.md`
- **Frontmatter obrigatório:** `slug` · `cliente_slug` · `first_name` · `relacao` · `role_operacional` · `papel_canary` · `pii_vault_ref`
- **Cross-link bidirecional obrigatório:** funcionário aponta `cliente_slug` · cliente lista funcionários
- **`nome_completo_real`:** marcar `TBD-perguntar-wagner` quando incerto · **NUNCA escrever sobrenome real em git**
- **Champion destacado:** marcar com `**negrito**` no índice + perfil cliente

## Papéis canary canônicos

| Papel canary | Significado |
|---|---|
| `decisor-principal` | Tem palavra final na migração (dono majoritário) |
| `decisor-secundario` | Aprova mas valida formal vem do principal |
| `continua-legacy-com-poder-decisao-2` | Continua sistema legacy MAS tem poder de decisão #2 (ex: Kamila esposa do Jair) |
| `champion-oimpresso` | NÃO-dono que adota oimpresso · feedback presencial obrigatório |
| `cliente-piloto-vivo` | Cliente único piloto vivo do oimpresso novo (status `producao`) |
| `continua-legacy` | Permanece sistema legacy no canary inicial |
| `observador` | Não opera mas acompanha |
| `nao-opera-sistema` | Funcionário em papel não-administrativo (mecânico campo) |

## Hierarquia familiar/decisão por cliente

### martinho-cacambas (biz=164)

```
Jair (dono majoritário · #1) ────casado com──── Kamila (esposa Jair · #2 decisora · operação POS Delphi)
  │
  └── sócio com ── Martinho (sócio · dá nome empresa · #3 secundário) ── pai de ── Lara (filha do Martinho · responsável ESTOQUE · champion oimpresso)

Dani / DANIELLI (financeiro · champion oimpresso · sem relação familiar com donos)
```

**Wagner corrigiu 2026-05-14 noite:** *"Kamila esposa do Jair. não esqueça ela quem manda depois do Jair"*. Cuidados:
- Kamila NÃO é filha do Jair · é **esposa**
- Lara NÃO é filha do Jair · é **filha do Martinho** (sócio)
- Kamila e Lara NÃO são irmãs (Kamila = esposa do Jair · Lara = filha do Martinho)
- Hierarquia decisão: Jair > Kamila > Lara/Dani · Wagner sempre valida com Kamila se Jair indisponível

## Pendências P0 Wagner segunda 2026-05-19 (`TBD-perguntar-wagner`)

| Funcionário | Pendência |
|---|---|
| [lara](martinho-cacambas/lara.md) | Dados pessoais reais (nome completo · CPF · email · telefone) + criar user `lara-164` em prod biz=164 |
| [dani](martinho-cacambas/dani.md) | **Confirmar mapeamento Dani=DANIELLI** (user id=297 existe · validar se é a mesma pessoa) + dados pessoais reais |
| [jair](martinho-cacambas/jair.md) | Decidir se cria user `jair-164` ou mantém sem login (perfil decisor não-operacional) |
| [martinho](martinho-cacambas/martinho.md) | Idem Jair (decidir se cria user `martinho-164`) |
| [kamila](martinho-cacambas/kamila.md) | Dados pessoais reais (user já existe id=292) |
| [eduardo](martinho-cacambas/eduardo.md) | Dados pessoais reais se for entrar na Fase 4 PWA |
| [larissa](rotalivre/larissa.md) | Cross-check `nome_completo_real` (Larissa Fernandes parcial · validar com Wagner) |

## Refs

- [ADR proposal cliente-funcionario-perfis-coleta-sistematica](../../decisions/proposals/cliente-funcionario-perfis-coleta-sistematica.md) — esta estrutura
- [ADR 0093 — Multi-tenant isolation Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0131 — Tiering memória canônico/local/segredo](../../decisions/0131-tiering-memoria-canonico-local-segredo.md) — PII em Vaultwarden
- [clientes/_INDEX.md](../clientes/_INDEX.md) — índice cruzado clientes
- [vaultwarden-credenciais.md](../vaultwarden-credenciais.md) — onde guardar PII real
