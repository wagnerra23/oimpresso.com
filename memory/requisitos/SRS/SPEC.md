# Especificação funcional — SRS (Software Requirements Specifications)

> **Status:** 🟡 PROPOSTA / placeholder — aguardando Wagner expandir escopo
> **Origem:** trigger "guarde no cofre" 2026-05-04 (Wagner)
> **Tipo:** módulo nWidart (`Modules/SRS/`)
> **Alias provisório:** `srs`

## Visão geral (a confirmar)

Módulo pra **gerenciar Software Requirements Specifications** dentro do oimpresso. Hipóteses de uso a serem validadas com Wagner:

| Hipótese | Caso |
|---|---|
| **A** Specs do PRÓPRIO oimpresso ERP | Substitui/complementa `memory/requisitos/<Mod>/SPEC.md` no git, com UI admin pra editar/versionar |
| **B** Specs de DESENVOLVIMENTO CUSTOM pra clientes | Gráficas que contratam dev personalizado (templates fiscais, integrações WMS, etc.) — entrega tem SRS formal |
| **C** Specs de IA/Copiloto | Estrutura formal pras specs que o Copiloto consome/gera (ex.: feature requests do cliente viram SRS) |
| **D** Outro | Definir |

## Decisões pendentes (Wagner decidir)

1. **Qual hipótese acima?** A/B/C/D
2. **Multi-tenant?** Specs por `business_id` ou globais (superadmin only)?
3. **Versionamento?** Append-only (LGPD) ou edit-in-place com histórico?
4. **Integração com TaskRegistry MCP (ADR 0069)?** SRS gera tasks automaticamente quando aprovada?
5. **Template padrão?** IEEE 830 / Volere / custom oimpresso?
6. **Approval workflow?** Quem aprova (cliente, Wagner, gestor)?
7. **Export/import?** PDF/Markdown/Word? Importar de Notion/Confluence?

## Personas (provisório)

| Persona | Acesso esperado |
|---|---|
| Wagner (superadmin) | CRUD completo + aprovar |
| Gestor business (cliente) | Ler + comentar specs do próprio business (se hipótese B) |
| Dev (Felipe/Maiara/Luiz) | Ler specs aprovadas + criar drafts |

## User stories (placeholder — preencher após decidir hipótese)

| ID | Título | Status |
|---|---|---|
| US-SRS-001 | Criar SRS draft | 🟡 placeholder |
| US-SRS-002 | Versionar SRS aprovada | 🟡 placeholder |
| US-SRS-003 | Listar SRS por business | 🟡 placeholder |
| US-SRS-004 | Export PDF/Markdown | 🟡 placeholder |
| US-SRS-005 | Aprovar/rejeitar SRS | 🟡 placeholder |
| US-SRS-006 | Comentários inline | 🟡 placeholder |
| US-SRS-007 | Gerar tasks automaticamente (TaskRegistry) | 🟡 placeholder |

## Stack canônica (assumindo padrão oimpresso)

- Laravel 13.6 + PHP 8.4 + Inertia v3 + React 19
- nWidart laravel-modules (ver [RUNBOOK-criar-modulo](../Infra/RUNBOOK-criar-modulo.md))
- Multi-tenant via `business_id` global scope (se hipótese B/C)
- Skill `criar-modulo` ativa quando for criar o scaffold

## Próximos passos

1. Wagner expande seção "Decisões pendentes" acima
2. Definir hipótese principal (A/B/C/D)
3. Preencher user stories com DoD por US
4. Criar ADR `memory/requisitos/SRS/adr/arq/0001-escopo-srs.md` documentando a decisão
5. Acionar skill `criar-modulo` ("crie o módulo SRS") → scaffold em `Modules/SRS/`

## Refs

- [RUNBOOK-criar-modulo](../Infra/RUNBOOK-criar-modulo.md) — passo-a-passo do scaffold
- [ADR 0011 — alinhamento Jana](../../decisions/0011-alinhamento-padrao-jana.md) — imitação canônica
- [ADR 0069 — TaskRegistry MCP](../../decisions/0069-taskregistry-mcp-tools-canonico-tasks-md-deprecated.md)
- Specs existentes pra imitar formato: [Copiloto/SPEC.md](../Copiloto/SPEC.md), [PontoWr2/](../PontoWr2/), [NFSe/SPEC.md](../NFSe/SPEC.md)
