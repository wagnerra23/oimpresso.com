# Drawer 760 Pattern — Referência canônica oimpresso

> **1-pager executivo do pattern de edição/visualização de entidades cadastrais do oimpresso.**
> **Audiência:** Wagner (dono), Felipe/Maiara/Eliana (time MCP), sub-agents Claude/Codex futuros.
> **Atualizar sempre que ADR 0179/0185 ou skill `pageheader-canon` mudarem.**

## TL;DR — o pattern em 1 parágrafo

Toda **entidade cadastral** do oimpresso (Cliente, Produto, ServiceOrder, Vehicle, DeviceModel, RecurringBilling/Plano, TransactionPayment) usa **drawer lateral 760px FIXO** dentro do `Pages/<Modulo>/Index.tsx`, com **N tabs** (Identificação · Endereço · Comercial · Classificação · OSs · IA Brain B · Auditoria LGPD) + **autosave on blur** (debounce 800ms, optimistic UI + rollback 4xx/5xx) + **redirect 302** das URLs legacy (`/<modulo>/{id}` e `/<modulo>/{id}/edit` → `?contact_id={id}&tab=identificacao`). Substitui `Edit.tsx` e `Create.tsx` separados. Pattern aprovado por Wagner 2026-05-22 ("eu adoro esse estilo, registre o padrão"), nota de auditoria estado-da-arte **76,4/100** (faixa "bom"; com Wave H pré-replicação → 88, mundo-classe).

## Estado da arte (auditoria 2026-05-22)

**Nota ponderada 15 dimensões: 76,4 / 100** (faixa "bom").

| Mundo-classe (≥90) | Bom (80-89) | Atrás (<60) |
|---|---|---|
| D15 Multi-tenant Tier 0 = **98** | D1 Coerência visual = 85 | D8 Concorrência = 40 |
| D5 LGPD Spatie ActivityLog = **90** | D2 Autosave on blur = 80 | D10 Performance lazy = 55 |
| | D4 IA Brain B Jana = 85 | D12 Mobile <1024 = 30 |
| | D11 Deeplink URL = 80 | D13 Print PDF = 45 |

**3 surpresas raras BR** (nenhum ERP BR Bling/Tiny/Omie entrega):
- Multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md))
- LGPD Art 18 plug-and-play via Spatie ActivityLog ([ADR 0127](../decisions/0127-modules-auditoria-undo-activity-log.md))
- IA Brain B democratizada em 7 entidades cadastrais

Dossier completo: [memory/sessions/2026-05-22-arte-drawer-760-vs-mundo.md](../sessions/2026-05-22-arte-drawer-760-vs-mundo.md).

## Geometria canônica (NÃO alterar)

```
┌──── viewport Larissa biz=4 1280×1024 ────────────────────────────────┐
│ ┌───────┬──────────┬───────────────────────────────────────────────┐ │
│ │ side  │  lista   │  drawer 760px FIXO                            │ │
│ │ 240   │  260     │                                               │ │
│ │       │          │  ┌─ Tabs ARIA (top) ─────────────────────┐    │ │
│ │       │          │  │ Identif · Contato · End · Com · IA   │    │ │
│ │       │          │  └──────────────────────────────────────┘    │ │
│ │       │          │  ┌─ Tab content (autosave on blur) ─────┐    │ │
│ │       │          │  │ Inputs + Selects + Cards IA + ...    │    │ │
│ │       │          │  │                                      │    │ │
│ │       │          │  └──────────────────────────────────────┘    │ │
│ │       │          │                                               │ │
│ └───────┴──────────┴───────────────────────────────────────────────┘ │
│         240    +    260    +    760    =    1260  (cabe 1280)       │
└──────────────────────────────────────────────────────────────────────┘
```

**Não negociável** ([ADR 0179](../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md) §Q5):
- Largura 760px **fixa** (não responsiva, não expansível)
- Persona target: Larissa biz=4 1280×1024 não-técnica
- 3 mecanismos pra encaixar info: sub-tabs aninhadas / scroll horizontal interno / cards colapsáveis (`<Accordion>`)
- Se não cabe → **mismatch de pattern** → entidade é workflow → migra pra FOCO V2

## Matriz de elegibilidade — quando aplicar Drawer 760

| Tipo de tela | Pattern canon | Critério objetivo |
|---|---|---|
| **Entidade cadastral** | **DRAWER 760** + N tabs + autosave | ≥5 campos identitários + alta freq edição + IA/Auditoria fazem sentido |
| **Workflow transacional** | **PAGE FOCO V2** (form fat sem SubNav) | Form >20 campos OU layout >1024px OU upload+progress OU wizard multi-step |
| **Cadastro técnico simples** | **PAGE FOCO V1** (Voltar inline) | ≤10 campos + baixa freq + sem IA/Auditoria |

## 7 entidades cadastrais escopo

| # | Módulo | Tela | Pattern atual | Pattern canon | Tabs |
|---|---|---|---|---|---|
| 1 | Cliente | Index | ✅ DRAWER 760 (prod biz=1) | manter | 8 tabs |
| 2 | OficinaAuto/ServiceOrders | Create+Edit | PageHeader legacy | DRAWER 760 | 7 tabs (incl FSM dropdown) |
| 3 | OficinaAuto/Vehicles | Create+Edit | PageHeader legacy | DRAWER 760 | 6 tabs |
| 4 | Produto | Create+Edit | sem header | DRAWER 760 | 8 tabs (incl variações/fiscal) |
| 5 | RecurringBilling/Planos | Create+Edit | sem header | DRAWER 760 | 5 tabs |
| 6 | Repair/DeviceModels | Create+Edit | PageHeader legacy | DRAWER 760 | 4 tabs |
| 7 | TransactionPayment | Edit | sem header | DRAWER 760 | 4 tabs |

## 4 tabs reutilizáveis (template DRY = Cliente)

| Tab canon | Conteúdo | Reuso | Source path Cliente |
|---|---|---|---|
| **IdentificacaoTab** | CPF/CNPJ + Nome + Doc principal + Status + masks BR (br-mask.ts/br-validate.ts) | per-entidade adapta campos | `resources/js/Pages/Cliente/_drawer/IdentificacaoTab.tsx` (611 linhas) |
| **EnderecoTab** | CEP + lookup ViaCEP cache Redis 90d + 5 campos endereço | reusa `Modules/Crm/Services/BrLookupService` | `resources/js/Pages/Cliente/_drawer/EnderecoTab.tsx` |
| **IATab** | 3-4 cards Brain B (Resumo / Segmento / Próxima Ação / determinístico) | adapta endpoints `LaravelAiSdkDriver` | `resources/js/Pages/Cliente/_drawer/IATab.tsx` |
| **AuditoriaTab** | Timeline LGPD Art 18 eventos Spatie ActivityLog `forSubject(Entity)` | reusa `Modules/Auditoria/Services/AuditEntryService` — zero código novo | `resources/js/Pages/Cliente/_drawer/AuditoriaTab.tsx` |

Tabs específicas por entidade (5-8 típico): cada sub-agent identifica conforme blueprint do módulo.

## Backend obrigatório por entidade (template)

| Endpoint | Método | Função |
|---|---|---|
| `/{modulo}/{id}/{tab}` | PATCH | Autosave on blur debounce 800ms + optimistic + rollback 4xx/5xx + `updated_at` check (Wave H) |
| `/{modulo}/{id}` | GET 302 | Redirect → `/{modulo}?contact_id={id}&tab=identificacao` |
| `/{modulo}/{id}/edit` | GET 302 | Redirect → mesmo destino acima |
| `/cep/{cep}` | GET | Lookup ViaCEP via `BrLookupService` cache Redis 90d |
| `/cnpj/{cnpj}` | GET | Lookup BrasilAPI via `BrLookupService` cache Redis 30d |
| `/{modulo}/{id}/ia/resumo` | POST | Brain B Jana resumo cliente (LaravelAiSdkDriver) |
| `/{modulo}/{id}/ia/segmento` | POST | Brain B Jana classificação segmento |
| `/{modulo}/{id}/ia/proxima-acao` | POST | Brain B Jana sugestão próxima ação |
| `/{modulo}/{id}/auditoria` | GET | Pull Spatie ActivityLog `forSubject(Entity)` |

**Multi-tenant Tier 0:** TODOS endpoints com `business_id` global scope ([ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md)). Pest cross-tenant biz=1 vs biz=99 obrigatório.

## Anti-patterns PROIBIDOS (Tier 0)

❌ Criar `Edit.tsx` ou `Create.tsx` separado pra entidade cadastral
❌ Botão "Salvar" no drawer (autosave on blur)
❌ Modal/Sheet centralizado em vez de drawer lateral
❌ Drawer largura ≠ 760px (não expansível, não responsivo, não per-entidade)
❌ Mais de ~10 tabs (use overflow `⋯ Mais`)
❌ Show.tsx full-page convivendo com drawer (sunset zero)
❌ Tab "Novo X" (drawer abre-fechado modo create OU abre populated modo edit)

## Wave H — obrigatória ANTES de replicar nas 6 entidades

Auditoria estado-da-arte 2026-05-22 identificou 4 gaps P0 que **devem ser aplicados no template Cliente PRIMEIRO** (15h IA-pair), senão escalam 7×:

| Gap | Effort | O quê |
|---|---|---|
| #1 Optimistic locking | 6h | `updated_at` check + HTTP 409 + toast "outro user editou" |
| #2 React.lazy + Inertia::defer per-tab | 4h | Bundle 118KB upfront → ~30KB initial |
| #3 popstate handler | 2h | Back button fecha drawer antes navegar |
| #5 Focus trap + Esc + autofocus | 3h | WCAG 2.2 AA (Radix Dialog/Sheet patterns) |

**Output Wave H:** template Cliente sobe nota **76,4 → 88/100** (mundo-classe). Replicação herda baseline 88.

## Workflow de adoção do pattern por entidade

Sub-agent que pegar wave de uma entidade DEVE:

1. ✅ Ler esta referência + [ADR 0179](../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md) + [ADR 0185](../decisions/0185-drawer-760-canon-entidades-cadastrais.md) + skill [`pageheader-canon`](../../.claude/skills/pageheader-canon/SKILL.md) Fase 4-bis
2. ✅ Confirmar entidade É cadastral conforme matriz (≥5 campos identitários)
3. ✅ Verificar Wave H já aplicada no template Cliente (gaps P0 OK)
4. ✅ Listar tabs propostas + estrutura `_drawer/` ANTES de codar
5. ✅ Skill `wagner-understand` pré-implementação OBRIGATÓRIA pra Waves >15h IA-pair
6. ✅ Verificar armadilhas críticas em [ADR 0185 §Preocupações adicionais](../decisions/0185-drawer-760-canon-entidades-cadastrais.md)
7. ✅ Migration aditiva ALTER TABLE (não criar tabela paralela)
8. ✅ Pest charter test em viewport 1280×1024 obrigatório
9. ✅ Smoke browser MCP per-tab (skill `pageheader-canon` Fase 5 + checks D1-D6 drawer)
10. ✅ Charter `Pages/<Modulo>/Index.charter.md` bump `drawer_pattern: 760px-lateral`
11. ✅ PR atômico per entidade (commit-discipline; encadear PRs como Wave A-G Cliente)
12. ✅ Brief-update após merge ([skill brief-update](../../.claude/skills/brief-update/SKILL.md))

## Refs canon (8 documentos)

- [ADR 0179](../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md) — Cliente Drawer 760 canon source (Wave A-G prod biz=1)
- [ADR 0185](../decisions/0185-drawer-760-canon-entidades-cadastrais.md) — Escala pra 7 entidades cadastrais (+ Wave H + Wave I)
- [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0127](../decisions/0127-modules-auditoria-undo-activity-log.md) — Spatie ActivityLog LGPD Art 18
- [ADR 0182](../decisions/0182-pageheadertabs-canon-pattern-telas.md) — PageHeader 3 zonas canon
- [Skill pageheader-canon](../../.claude/skills/pageheader-canon/SKILL.md) Fase 4-bis — matriz 3 patterns
- [Auditoria estado-da-arte 2026-05-22](../sessions/2026-05-22-arte-drawer-760-vs-mundo.md) — nota 76,4/100 + 10 gaps priorizados
- [RUNBOOK Cliente Wave A-G](../requisitos/Crm/RUNBOOK-Cliente-drawer-760px.md) — receita executável detalhada

## Referência viva — código canônico

| Path | Linhas | Função |
|---|---|---|
| `resources/js/Pages/Cliente/Index.tsx` | ~74KB | Listagem + drawer 760 + 8 tabs (canon source) |
| `resources/js/Pages/Cliente/_drawer/IdentificacaoTab.tsx` | 611 | Template autosave debounce 800ms + rollback |
| `resources/js/Pages/Cliente/_drawer/IATab.tsx` | ~420 | Template 4 cards Brain B Jana |
| `resources/js/Pages/Cliente/_drawer/AuditoriaTab.tsx` | ~290 | Template Spatie ActivityLog LGPD |
| `Modules/Crm/Http/Controllers/ClienteAutosaveController.php` | ~500 | Template endpoints PATCH autosave |
| `Modules/Crm/Services/BrLookupService.php` | ~180 | ViaCEP/BrasilAPI cache Redis 90d/30d |

## Última atualização

**2026-05-22** — Wagner 2026-05-22: "registre o padrão eu adoro esse estilo. salve tudo formalize o melhor". Pattern formalmente canonizado. Wave H crítica (15h pré-replicação) inserida na ADR 0185. PRs entregues: Cliente Wave A-G (#1339-#1358) prod biz=1 + Wave Ponto FOCO V1 (#1381) fora-escopo. Próximo: Wave H + Wave Produto F1.
