# RUNBOOK — Fiscal/Nfe (cockpit NF-e · NFC-e)

> **Tela:** `/fiscal/nfe`
> **Module:** `Modules/Fiscal` (thin agregador) + `Modules/NfeBrasil` (lê via Service)
> **Page:** [`resources/js/Pages/Fiscal/Nfe.tsx`](../../../resources/js/Pages/Fiscal/Nfe.tsx) + `_components/{FxShell,NotaDrawer}.tsx` + `_lib/fiscal-helpers.ts`
> **Charter:** [`Nfe.charter.md`](../../../resources/js/Pages/Fiscal/Nfe.charter.md) (Mission / Goals / Non-Goals / Anti-hooks)
> **Controller:** [`NfeCockpitController`](../../../Modules/Fiscal/Http/Controllers/NfeCockpitController.php)
> **Permissão:** `fiscal.nfe.view`
> **PR origem:** #1183 (feat Fiscal cockpit NF-e + design Cowork KB-9.75 sub-página 2)
> **Status:** F1 — implementado · F3 ainda não rodou em prod (aguarda merge + smoke biz=1)

## 1. Objetivo

Dar à pessoa fiscal (Eliana contadora + Wagner operador) a **lista navegável de NF-e/NFC-e emitidas** com status SEFAZ legível, janela legal de cancelamento visível, e detalhe acionável via drawer com mapa SEFAZ guiado ("Jana sugere"). Substitui UI atual fragmentada de `Pages/NfeBrasil/Transactions/NfceStatus`.

## 2. Estrutura da tela

```
FxShell (wrapper das 7 sub-páginas Fiscal)
├── Hero (título + crumb + env badge + ⌘K placeholder + actions)
├── SubNav horizontal (7 chips: Cockpit/NF-e/NFS-e/DF-e/Eventos/Cert/SPED — 6 disabled)
├── Body
│   ├── SubTabs (NFe 55 / NFCe 65 / Entrada)
│   ├── Filtros chip-row (Todas / Autorizadas / Rejeitadas / Janela 24h / Processando) + search
│   └── Tabela paginada (Deferred Inertia partial)
│       │   colunas: Número (mono) · Chave/Destinatário · Status SEFAZ + pílula temporal · Valor · Emissão
│       └── click → NotaDrawer slide-in
└── Footer cheatsheet sticky (atalhos)

NotaDrawer (slide-in 480px, ESC fecha)
├── Header (modelo · número · chave truncada)
├── Body
│   ├── Status SEFAZ (pill + pílula cancel + pílula CC-e + motivo se rejeitada)
│   ├── SefazActionCard "Jana sugere" (só se cstat rejeitado com receita cadastrada)
│   ├── Destinatário (Nome / CNPJ ou CPF / UF / Itens)
│   └── Operação (Venda / Emissão / Valor / Modelo)
└── Footer (Reconsultar SEFAZ + XML + DANFE + Cancelar (se 24h) + Retransmitir (se rejeitada))
   ↑ todos disabled no PR #1 — ativação em PR #4 (US-FISCAL-004)
```

## 3. Fluxo do usuário

1. Usuário acessa `/fiscal/nfe` via sidebar "Fiscal" (entrada order=84 — ver `DataController::modifyAdminMenu`)
2. Tela carrega counts eager (chip badges) + rows deferred (skeleton fallback durante busca)
3. Usuário aplica filtro `status=rejeitadas` → `router.visit` partial reload (only:[rows,counts,filters])
4. Usuário pressiona `J`/`K` pra navegar cursor na tabela → linha highlighted com `.fx-row-focus`
5. Usuário pressiona `Enter` → drawer slide-in com detalhe da nota focada
6. Se cstat rejeitado (110/220/539/691/778), drawer mostra receita "Jana sugere" com passos numerados + botão CTA
7. Usuário pressiona `ESC` → drawer fecha

## 4. Permissões

- **`fiscal.access`** — habilita entrada sidebar "Fiscal"
- **`fiscal.nfe.view`** — acesso a `/fiscal/nfe` (`NfeCockpitController::index` 403 se faltar)
- **`fiscal.nfe.acoes`** — futuro: habilita botões Cancelar/Retransmitir/CC-e (PR #4)
- **`superadmin`** — bypass total

Todas registradas em `Modules/Fiscal/Http/Controllers/DataController::user_permissions`.

## 5. Sidebar registration

`DataController::modifyAdminMenu()` injeta no `admin-sidebar-menu` (Menu Spatie facade):
- Order=84 (antes Financeiro 85)
- Ativado por (package subscription `fiscal_module` OR superadmin) AND `fiscal.access`
- Wagner regra IRREVOGÁVEL 2026-05-18: NUNCA hardcode `if (business_id === N)` — só package + permission

## 6. Multi-tenant Tier 0 (ADR 0093)

- `NfeEmissao` (Model lido) usa `HasBusinessScope` trait — global scope automático
- `Inertia::defer(fn () => $this->buildRowsPayload(...))` mantém scope quando lazy-loaded
- Pest test `NfeCockpitMultiTenantTest::it global scope HasBusinessScope esconde emissões cross-tenant na contagem do cockpit` valida (biz=1 vs biz=99, ADR 0101)

## 7. Janela legal de cancelamento

CONFAZ Ajuste SINIEF 07/2005 Art. 14:
- **NF-e (modelo 55):** 168h (7 dias) após emissão
- **NFC-e (modelo 65):** 24h após emissão

Helper `prazoCancel(nota, nowMs)` em `_lib/fiscal-helpers.ts` calcula urgência (ok >12h, warn 6-12h, crit <6h). Mostra pílula colorida na tabela e drawer.

## 8. Pesquisa SEFAZ codes (mapa "Jana sugere")

Cstat → receita determinística (não LLM):
- **100/104** Autorizada (ok)
- **110** Uso denegado → operação irregular, NÃO retransmitir
- **204/220/539** Duplicidade → inutilizar e retransmitir
- **691** NCM divergente → revisar cadastro do produto
- **778** CST/CFOP inválido → ajustar tributação per UF destino
- **999** Processando → aguardar reenvio automático

Lista completa em `NfeCockpitController::sefazCodes()` + `NotaDrawer::SEFAZ_ACTIONS`.

## 9. Próximos PRs do roadmap

| PR | Entrega | Status |
|---|---|---|
| #1 #1183 | NF-e · NFC-e (cockpit + drawer, leitura) | ✅ aberto |
| #2 | Cockpit sub-página 1 (KPIs + alertas + quick links) | 🔒 |
| #3 | ⌘K palette cross-fiscal | 🔒 |
| #4 | Ações mutação (Cancelar/Retransmitir/CC-e/Inutilizar) | 🔒 |
| #5 | NFS-e sub-página 3 | 🔒 |
| #6 | Manifesto DF-e sub-página 4 | 🔒 |
| #7 | Eventos + Cert/Cfg + SPED sub-páginas 5/6/7 | 🔒 |

## 10. Smoke pós-merge (biz=1 prod)

```bash
# 1. Deploy SSH Hostinger (cuidado worktree não-CT100)
# 2. Curl literal status code
curl -sv https://oimpresso.com/fiscal/nfe -H "Cookie: laravel_session=<sess_biz1>" 2>&1 | grep '^< HTTP'
# Esperado: < HTTP/2 200 (sidebar carrega + tela renderiza)

# 3. Curl negativo: sem permission
# Esperado: < HTTP/2 403

# 4. Browser MCP (per Wagner regra Tier 0 post-merge UI smoke):
#    abrir /fiscal/nfe → screenshot → relatar status visual
#    clicar em 1 linha → drawer abre
#    pressionar ESC → drawer fecha
```

## 11. Riscos conhecidos

- **R1:** Lista carrega lenta se business tem >10k notas — paginate 50 + index `emitido_em DESC` mitiga
- **R2:** `metadata->dest_name` vazio em notas antigas pré-Sprint 3 ARQ-019 — fallback "—" (próximo PR JOIN transactions)
- **R3:** Janela 24h timezone — Controller usa `now()` (app TZ), JS usa `Date.now()` (browser TZ). Risco minutos antes deadline. Próximo PR: passar `nowMs` server-rendered

---

**Última atualização:** 2026-05-20 (criação inicial PR #1183 — Fiscal cockpit NF-e sub-página 2 do design Cowork KB-9.75)
