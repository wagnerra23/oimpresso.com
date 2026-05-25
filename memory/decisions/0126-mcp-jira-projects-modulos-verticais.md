---
id: 0125
slug: mcp-jira-projects-modulos-verticais
title: Habilitar ComunicacaoVisual + Vestuario + OficinaAuto como projects canônicos no MCP
status: proposto
date: "2026-05-10"
deciders: [wagner]
supersedes: []
superseded_by: []
related: ["0070-jira-style-task-management-current-md-removed", "0094-constituicao-v2-7-camadas-8-principios", "0105-cliente-como-sinal-guiar-sem-mandar", "0121-oimpresso-modular-especializado-por-vertical"]
lifecycle: canon
---

# 0125 — Habilitar ComunicacaoVisual + Vestuario + OficinaAuto como projects canônicos no MCP

## Contexto

[ADR 0070](0070-jira-style-task-management-current-md-removed.md) introduziu o sistema Jira-style de tasks (`mcp_jira_projects` + `mcp_tasks` + `mcp_workflows`). O `McpDefaultsSeeder` (ver `Modules/Jana/Database/Seeders/McpDefaultsSeeder.php`) registra 18 projects canônicos: COPI, NFSE, FIN, INFRA, PONTO, CMS, MEMCOFRE, CRM, ACCO, REC, NFE, OFFICE, ADS, REPAIR, CONSULTA, GROW, EVO, TR.

[ADR 0121](0121-oimpresso-modular-especializado-por-vertical.md) formalizou a arquitetura "núcleo comum + Modules/<Vertical>" com 3 verticais ativas/em construção:

- **Modules/Vestuario** — cliente piloto ROTA LIVRE (biz=4 SC, em produção 2+ anos). SPEC.md existente.
- **Modules/ComunicacaoVisual** — em construção, piloto previsto Q3 (1 dos 6 saudáveis OfficeImpresso). SPEC.md existente.
- **Modules/OficinaAuto** — backlog feature-wish até sinal qualificado per [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md). SPEC.md ausente até hoje (criado nesta rodada).

Nenhuma das 3 está em `mcp_jira_projects`. Tentativa de criar US via tool MCP `tasks-create module:ComunicacaoVisual` em 2026-05-10 retornou erro `Sem 'module' canônico, é obrigatório passar 'project'`.

## Decisão

Adicionar 3 projects canônicos ao `mcp_jira_projects` via:

1. **Migration dedicada** `2026_05_10_120000_seed_modulos_verticais_mcp_jira_projects.php` (idempotente via `updateOrInsert`, com proteção `down()` que falha se já houver tasks atribuídas).
2. **Edição espelhada** do `McpDefaultsSeeder.php` (mantém consistência se Wagner rodar `db:seed --refresh` no futuro).
3. **Criação do SPEC.md mínimo** em `memory/requisitos/OficinaAuto/` (necessário pra `tasks-create` aceitar o módulo per validação do tool MCP).

Keys:

| key | name | status governança | descrição |
|-----|------|-------------------|-----------|
| `COMVIS` | ComunicacaoVisual | em construção | Vertical comunicação visual / gráfica rápida — piloto Q3 (saudável OfficeImpresso a confirmar) |
| `VEST` | Vestuario | em produção | Vertical vestuário / loja de roupa — piloto ROTA LIVRE (Termas do Gravatal/SC, biz=4) |
| `AUTO` | OficinaAuto | backlog feature-wish | Vertical oficina mecânica — aguarda sinal qualificado per [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) |

## Consequências

**Positivas:**
- US e tasks de feature/integração desses módulos podem ser criadas via MCP normalmente quando virarem demanda real.
- Pesquisa de prospecção (24 UFs ComVis + 10 UFs Vestuario + 10 UFs OficinaAuto rodadas em 2026-05-10) ganha home pra eventuais leads qualificados virarem US no SPEC.md correspondente.
- Fica explícito no canon que oimpresso é multi-vertical especializado (ADR 0121), não horizontal genérico.

**Atenção:**
- Adicionar `AUTO` no canon **não autoriza** criação de US ativas — ADR 0105 ainda manda. US-AUTO-* só nasce quando piloto pagante reportar dor concreta. Habilitar canon antecipadamente é só remoção de fricção operacional.
- Se Modules/OficinaAuto nunca virar produto (Martinho Caçambas confirmar fora do ICP, ou outro sinal não chegar), project `AUTO` fica `archived` no futuro. Não vira lixo silencioso.

**Risco:**
- Migration idempotente — re-rodar é seguro. Rollback (`migrate:rollback`) tem proteção: falha com mensagem clara se já tiver tasks atribuídas.

## Alternativas consideradas

1. **Não habilitar canon — manter markdown-only** ([feedback memory](https://github.com/...)): funciona pra outbound comercial, mas trava criação de US legítimas quando lead virar feature request.
2. **Habilitar só ComunicacaoVisual + Vestuario, deixar OficinaAuto fora**: criaria assimetria — qualquer mapeamento ou US de OficinaAuto continuaria sem home. Custo zero adicionar os 3 juntos.
3. **Renomear keys pra prefixo VERT-***: rejeitada — o canon usa keys curtas (3-8 chars). VEST/AUTO/COMVIS seguem padrão.

## Refs

- [ADR 0070](0070-jira-style-task-management-current-md-removed.md) — Jira-style task management
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 mãe
- [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — cliente como sinal qualificado
- [ADR 0121](0121-oimpresso-modular-especializado-por-vertical.md) — modular especializado por vertical
- Migration: `Modules/Jana/Database/Migrations/2026_05_10_120000_seed_modulos_verticais_mcp_jira_projects.php`
- Seeder atualizado: `Modules/Jana/Database/Seeders/McpDefaultsSeeder.php`
- SPEC OficinaAuto novo: `memory/requisitos/OficinaAuto/SPEC.md`
