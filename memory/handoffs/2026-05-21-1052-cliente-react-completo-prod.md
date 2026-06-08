---
date: "2026-05-21"
slug: cliente-react-completo-prod
tldr: "Sessão Cliente completo em prod — 7 telas React + 8 tabs Show + 10 PRs (#1298-1307), 2 bugs pré-existentes consertados (Inertia↔ajax collision + total_paid schema)"
authors: [W, C]
cycle: CYCLE-06
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0104-processo-mwart-canonico-unico-caminho
  - 0107-emendation-0104-visual-comparison-gate-f3
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0149-mwart-screen-pattern-reuse-cowork
prs: [1298, 1299, 1300, 1301, 1302, 1305, 1306, 1307]
---

# Handoff — Cliente React 100% em prod (8 tabs + 7 telas)

**Sessão:** ~5h focada 100% em Cliente. Wagner trigger original: "pode ativar o cliente react quero testar". Evoluiu pra ativação completa + 2 bugfixes críticos + Wave 5 paridade Show + 6 sub-ondas finais.

## Estado prod (`.env` Hostinger)

```
MWART_CLIENTE_INDEX=true
MWART_CLIENTE_SHOW=true
MWART_CLIENTE_CREATE=true
MWART_CLIENTE_EDIT=true
MWART_CLIENTE_IMPORT=true
MWART_CLIENTE_LEDGER=true
MWART_CLIENTE_MAP=true
```

Middleware `RedirectLegacyContacts` em `app/Http/Kernel.php` segue **comentado** (Wagner reverteu localmente — fica desligado até decisão).

## 7 telas Cliente React funcionais em prod

| Tela | URL canon | URL legacy | Confirmado smoke |
|---|---|---|:-:|
| Index | `/cliente` | `/contacts?type=customer` | ✅ |
| Show | `/cliente/{id}` | `/contacts/{id}` | ✅ (8 tabs) |
| Create | — | `/contacts/create?type=customer` | ✅ |
| Edit | — | `/contacts/{id}/edit` | ✅ |
| Import | — | `/contacts/import` | ✅ |
| Ledger | — | `/contacts/ledger?contact_id={id}` | ✅ |
| Map | — | `/contacts/map` | ✅ |

## Cliente/Show.tsx — 8 tabs canon

1. **Extrato** (LedgerTab) — range datas + Formato 1/2/3 + Local + Aplicar (Inertia router.visit pra /contacts/ledger inline SPA) + PDF + E-mail
2. **Vendas** (SalesTab) — paginação Inertia partial reload `only:['sales']` + filtros range/status/q
3. **Pagamentos** (PaymentsTab) — self-fetch `/contacts/payments/{id}` AJAX legacy
4. **Documentos & Notas** (DocumentsTab) — upload + autosave 1500ms `/note-documents`
5. **Atividades** (ActivitiesTab) — Spatie\Activitylog forSubject, latest 100
6. **Pessoas** (PessoasContatoTab) — User where crm_contact_id (Modules/Crm contact_login)
7. **Assinaturas** (SubscriptionsTab) — transactions is_recurring=1 + recur_parent_id NULL
8. **Pontos** (RewardPointsTab) — condicional business.enable_rp + history rp_earned/rp_redeemed

**Header:** ContactPicker dropdown (35 contatos do biz, busca + cap 50 + badge atual) + Editar + Aplicar desconto + ActionsMenu (Pagar/Excluir/Deactivate/atalhos).

**StatCards:** 4 cards (Total vendido / A receber / Total comprado / Saldo abertura) via Inertia::defer.

## 10 PRs mergeadas (ordem cronológica)

1. **#1298** Wave 5 paralela paridade Show — 12 arquivos novos (6 components + 6 tests via coordenador-paralelo)
2. **#1299** Fix Inertia ajax collision — helper `isLegacyAjax()` substitui 8 ocorrências `request()->ajax()` em ContactController. Inertia partial reload setava X-Requested-With: XMLHttpRequest e caía em branches DataTable JSON cru. **Bug pré-existente PR #1289**
3. **#1300** Fix KPIs Index `total_paid` — subquery `transaction_payments`. Coluna `total_paid` nunca existiu no schema `transactions`. **Bug pré-existente PR Wave 1 Index**
4. **#1301** Fix SalesPaginator `total_paid` — mesmo padrão, subquery scalar. **Bug minha PR #1298**
5. **#1302** Onda Final.A Contact picker header
6. **#1305** Onda Final.C Tab Pessoas
7. **#1306** Onda Final.D Tab Assinaturas
8. **#1307** Onda Final.E+F Reward Points + Ledger inline SPA

Onda Final.B (Tab Atividades) commitada direto em main (commit `0aefef42a`) por engano de shell cwd reset.

## ADRs criadas

- **0177** MWART exceção Cliente/Show Wave paralela (visual regression override + F1.5 satisfeito)

## Bugs pré-existentes descobertos (lição)

1. **Inertia partial reload colide com `request()->ajax()` legacy** — todo Controller que tem `if (request()->ajax())` antes do branch Inertia render vai ter o mesmo problema. ContactController consertado, mas outros podem ter (SellController, PurchaseController, etc.). **Próxima sessão deveria varrer.**
2. **`transactions.total_paid` não existe no schema UltimatePOS** — accessor via `transaction_payments` (1:N). Código assumiu coluna virtual. **Próxima sessão deveria varrer Controllers que fazem `DB::raw('total_paid')` ou `select('total_paid')`.**

## Onda Final 100% completa

Todos os 6 gaps remanescentes Show fechados:
- A: Contact picker (1h estimado)
- B: Tab Atividades (2h)
- C: Tab Pessoas (2h)
- D: Tab Assinaturas (1h)
- E: Tab Reward Points (1h)
- F: Ledger inline SPA (3h — simplificado pra router.visit)

## Próximo cycle (próxima sessão)

**Wagner sinalizou:** "posso passar nova template do design?" — quer aplicar template visual. Esperando o template + escopo (qual tela, HTML Cowork, mockup, ou ajuste pontual). Processo canon ADR 0114 cowork-loop.

**Backlog técnico latente:**
- Varrer outros Controllers com mesmo bug `request()->ajax()` (SellController, PurchaseController, etc.)
- Varrer outros usos de `transactions.total_paid` no projeto
- Aplicar template visual novo (escopo a definir)
- Considerar mergear ou descartar `feat/dashboard-rewrite-cockpit-v2` (Wagner estava trabalhando)

## Artefatos gerados

- 6 sub-components em `resources/js/Pages/Cliente/_show/`
- 9 testes Pest em `tests/Feature/Cliente/Show/`
- 1 helper `isLegacyAjax()` + 1 helper `buildClienteSalesPaginator()` em `app/Http/Controllers/ContactController.php`
- 1 charter v2 em `resources/js/Pages/Cliente/Show.charter.md`
- 1 SPEC + 1 RUNBOOK + 1 visual-comparison em `memory/requisitos/Cliente/`
- 2 session logs em `memory/sessions/2026-05-21-*`
- 1 ADR (0177 MWART exceção)

## Notas

- Wagner reverteu localmente `Show.tsx` + charter + Wave1Show tests + Kernel `RedirectLegacyContacts` numa janela da sessão — fica deslocalizado mas merges remotos prevaleceram. PR #1296 (middleware activate) fechada.
- Branch local `feat/cliente-show-final-e-f-paralelo` ainda existe (último trabalho). Pode deletar próxima sessão.
- 3 arquivos untracked do Wagner (`Home/HomeCharts.tsx`, `HomeDues.tsx`, `HomeStockAlert.tsx`) — não meus, não toquei.
- Branch atual: `feat/cliente-show-final-e-f-paralelo` (já mergeada via #1307, pode checkout main).
