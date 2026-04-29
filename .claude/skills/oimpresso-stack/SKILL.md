---
name: oimpresso-stack
description: Use ao iniciar trabalho no oimpresso ou ao entrar num módulo novo. Carrega o primer da stack canônica (Laravel 13.6, PHP 8.4, Inertia v3, multi-tenant, padrão modular UltimatePOS). Substitui leitura repetida de §1-§5 do CLAUDE.md.
---

# Stack canônica oimpresso (primer)

ERP gráfico brasileiro pra setor comunicação visual (gráficas rápidas, plotters, fachadas, brindes). Construído sobre **UltimatePOS v6** com módulos próprios.

**Cliente principal de produção:** ROTA LIVRE (`business_id=4`, Larissa).

## Stack REAL

- Laravel **13.6** + PHP 8.4 (Herd local; FPM Hostinger)
- MySQL Laragon local · DB prod `u906587222_oimpresso`
- Inertia **v3** + React 19 + Tailwind 4
- Pest v4
- nWidart/laravel-modules ^10
- `spatie/laravel-html` ^3.13 com shim `App\View\Helpers\Form`
- Spatie laravel-permission (roles `{Nome}#{biz_id}`)

## Stack-alvo IA (canônica ADR 0035 + 0036 + 0048)

- **Camada A:** `laravel/ai` ^0.6.3 (oficial fev/2026)
- **Camada B:** ~~Vizra ADK~~ → **rejeitado** (ADR 0048 — quebrou L13)
- **Camada C:** `MemoriaContrato` + `MeilisearchDriver` default + `NullDriver` dev
- **MCP server:** `mcp.oimpresso.com` (CT 100 Proxmox) — ADR 0053
- **Tooling:** Boost + MCP + Scout + Horizon + Telescope + Pail

## Padrão arquitetural

- Modular monolith, DDD leve
- Append-only onde a lei exige (Portaria 671/2021 — Ponto)
- `business_id` global scope **obrigatório** (multi-tenant)
- Módulos de referência canônica: `Modules/Jana/`, `Modules/Repair/`, `Modules/Project/` — antes de criar/ajustar arquivo, olhe equivalente e imite (ADR 0011)
- Stack middlewares UltimatePOS: `['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin']`

## O que NÃO fazer

- **Não modifique tabelas core UltimatePOS** (`users`, `business`, `employees`, etc.) — use bridge tables
- **Não UPDATE/DELETE em `ponto_marcacoes`** — append-only por lei (use `Marcacao::anular()`)
- **Não crie nova dep** sem registrar ADR
- **Não responda em inglês** — Wagner e cliente são brasileiros
- **Não remova shim `App\View\Helpers\Form`** — depende de ~6.433 chamadas em ~460 Blade
- **Identificadores MySQL >64 chars** sempre passar nome explícito em índices compostos
- **Delphi contrato IMUTÁVEL** — `/connector/api/*` nunca quebra request/response shape

## O que SEMPRE fazer

- **PT-BR em tudo** — texto, commit, comentário, label. Código (classes/métodos) em inglês OK; domínio negócio em PT (`Marcacao`, `Intercorrencia`, `BancoHoras`)
- **Citar lei** quando aplicável (Art. 66 CLT, Portaria 671/2021, LGPD)
- **Preservar imutabilidade** de marcações e movimentos banco horas
- **`business_id` scopado** em TODA query (skill `multi-tenant-patterns` ativa em Entity/Controller)
- **Escrever testes** Pest pra regras CLT, isolamento multi-tenant, contratos
- **Antes de criar/mudar estrutura módulo**, olhar `Modules/Jana/` e imitar

## Onde está cada coisa

```
D:\oimpresso.com\
├── CLAUDE.md           # primer slim
├── CURRENT.md          # estado vivo (Cycle ativo)
├── TASKS.md            # backlog completo
├── INFRA.md            # acesso SSH + deploy + Proxmox
├── DESIGN.md           # padrão visual/UX
├── memory/             # memória persistente
│   ├── 08-handoff.md   # contexto canônico mais recente
│   ├── decisions/      # 53 ADRs (Nygard format)
│   ├── sessions/       # logs cronológicos
│   ├── requisitos/     # SPECs por módulo
│   └── comparativos/   # análise competitiva
└── Modules/
    ├── Copiloto/       # IA chat + metas + custos
    ├── Financeiro/     # AP/AR + dashboard
    ├── PontoWr2/       # ponto eletrônico CLT
    └── Jana/           # módulo referência canônica
```

## Time + propriedade

**Modo solo Wagner (ADR 0047)** — todas as tasks owner [W]. Outros membros (Felipe/Maíra/Luiz/Eliana) referenciados em ADRs antigas mas atualmente inativos.

**Cliente Copiloto:** Larissa Fernandes (ROTA LIVRE biz=4)
**Cliente PontoWr2:** Eliana(WR2) — eliana@wr2.com.br (≠ Eliana[E] esposa Wagner)
