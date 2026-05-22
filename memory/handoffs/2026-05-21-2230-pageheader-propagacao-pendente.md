---
date: "2026-05-21"
slug: pageheader-propagacao-pendente-financeiro-completo
tldr: "Sessão 2026-05-21 entregou 100% pattern PageHeader v3 no Financeiro (13 PRs #1340-#1379 + 4 ADRs canon 0180/0182/0183/0184) + ponte caixa↔financeiro completa. PENDENTE — propagar pattern pros 8 módulos restantes via skill pageheader-canon: Sells/Crm/ProductCatalogue (vender) · Repair/OficinaAuto/Manufacturing/Compras/AssetManagement (operar) · NfeBrasil/NFSe (financas) · Essentials/Ponto (pessoas) · Governance/Cms/Connector/Officeimpresso (sistema). Cada módulo segue protocolo 5 fases da skill: descoberta → decisão botões → naming convention → implementação → validação visual browser MCP."
cycle: CYCLE-2026-Q2
authors: [W]
related_adrs:
  - "0180-sidebar-v3-5-grupos-ghosts-header"
  - "0182-pageheadertabs-canon-pattern-telas"
  - "0183-caixa-fisico-bridge-financeiro-canon"
  - "0184-errata-0183-nao-deprecar-cash-register-rotas"
---

# Handoff 2026-05-21 22:30 — PageHeader propagação pendente (8 módulos)

## TL;DR

Wagner pediu: *"salve as tarefas para revisar os header vou iniciar em outra sessao, fechar essa"*.

Sessão entregou **Financeiro 100%** (PageHeader canon em 11/12 telas + ponte caixa→fin_titulos completa). **Próximas waves**: propagar pattern pros outros 8 módulos do oimpresso usando skill `pageheader-canon` (protocolo 5 fases + gate validação visual obrigatório).

## Estado consolidado em main (HEAD `e1b5d062`)

### Componentes canon prontos pra reuso

| Path | O quê | Status |
|---|---|---|
| `resources/js/Components/shared/PageHeaderTabs.tsx` | Componente ARIA tablist + overflow + auto-promoção ghost ativo | ✅ canon |
| `resources/js/Pages/Financeiro/_shared/FinanceiroSubNav.tsx` | Wrapper que lê shell.menu via usePage() | ✅ template |
| `resources/js/Pages/Financeiro/_shared/FinanceiroPrimaryButton.tsx` | Botão primary com hue OKLCH do grupo (não magenta canon UPOS) | ✅ template |
| `app/Sidebar/SidebarMenuItem.php` + DTOs | Contrato PHP (Fase 1 ADR 0180) — 5 grupos canon + 3 topo | ✅ canon |
| `Modules/Financeiro/Http/Controllers/DataController.php` | Pattern declarativo `shortcut`/`primary`/`ghosts` no Menu::modify | ✅ template |
| `.claude/skills/pageheader-canon/SKILL.md` | Protocolo executável 5 fases pro sub-agent | ✅ canon |
| `memory/requisitos/_DesignSystem/pageheader-matriz-diferencas.md` | Matriz F1-F12 fixas + V1-V9 variáveis | ✅ canon |
| `memory/decisions/0180-sidebar-v3-5-grupos-ghosts-header.md` | Sidebar v3 canon | ✅ aceito |
| `memory/decisions/0182-pageheadertabs-canon-pattern-telas.md` | Pattern header 3 zonas obrigatório | ✅ aceito |
| `memory/decisions/0183-caixa-fisico-bridge-financeiro-canon.md` | Ponte caixa→fin_titulos + 15 pegadinhas | ✅ aceito |
| `memory/decisions/0184-errata-0183-nao-deprecar-cash-register-rotas.md` | NÃO deprecar rotas /cash-register/* | ✅ aceito |

### Hues OKLCH por grupo (canon `cockpit/shared.ts SIDEBAR_GROUP_HUE`)

| Grupo v3 | Hue | Cor | Módulos |
|---|---|---|---|
| **vender** | 60 | amarelo | Sells · Crm · ProductCatalogue · Vestuario · Woocommerce |
| **operar** | 350 | magenta | Repair · OficinaAuto · Manufacturing · Compras · AssetManagement |
| **financas** | 145 | verde ✅ feito | Financeiro · NfeBrasil · NFSe · PaymentGateway · RecurringBilling · Fiscal |
| **pessoas** | 295 | roxo claro | Essentials · Ponto |
| **sistema** | 200 | azul-acinzentado | Governance · ADS · Auditoria · Cms · Connector · Officeimpresso · Superadmin |
| **ia** (topo) | 220 | azul | Jana · KB · Brief · SRS |
| **atendimento** (topo) | 30 | laranja | Whatsapp · ConsultaOs |
| **equipe** (topo) | 270 | roxo | TeamMcp · ProjectMgmt |

## Tarefas pendentes (4 waves paraleláveis)

### 🟡 Wave VENDER — 3 módulos
- [ ] `Modules/Sells` — telas POS · Sales · Drafts · Returns · Reservations · etc
- [ ] `Modules/Crm` — Clientes (Cliente drawer já tem #1339-#1351 misturado; precisa unificar pattern)
- [ ] `Modules/ProductCatalogue` — Produtos · Variations · Brands · etc

**Subcomponente reuse:** criar `Pages/Sells/_shared/VendasSubNav.tsx` + `VendasPrimaryButton.tsx` (template = Financeiro)

### 🟡 Wave OPERAR — 5 módulos
- [ ] `Modules/Repair` — Ordens Serviço · Job Sheets · Status · Devices · Models
- [ ] `Modules/OficinaAuto` — Veículos · Ordens Auto
- [ ] `Modules/Manufacturing` — Receitas · Produções · Apontamento
- [ ] `Modules/Compras` — Compras · Cotações · Fornecedores (charter já tem PR #1366)
- [ ] `Modules/AssetManagement` — Ativos · Equipamentos · Aluguéis

**Subcomponente reuse:** criar `Pages/Operacao/_shared/OperacaoSubNav.tsx` (compartilhado entre Repair + Manufacturing + Compras + Estoque)

### 🟡 Wave PESSOAS + FISCAL — 4 módulos
- [ ] `Modules/Essentials` (HRM) — Colaboradores · Documentos · Aprovações
- [ ] `Modules/Ponto` — Marcações · Folha · Escalas · Aprovações · Intercorrências
- [ ] `Modules/NfeBrasil` — NF-e · Emissão · Inutilização · Manifesto
- [ ] `Modules/NFSe` — Notas serviço (ghost de Fiscal)

**Subcomponente reuse:** `Pages/Rh/_shared/RhSubNav.tsx` + `Pages/Fiscal/_shared/FiscalSubNav.tsx`

### 🟡 Wave SISTEMA — 6 módulos
- [ ] `Modules/Governance` — Module Grades · Auditorias · Policies
- [ ] `Modules/ADS` — Decisões · Brain · Aprovações HITL
- [ ] `Modules/Auditoria` — Timeline LGPD
- [ ] `Modules/Cms` — Páginas · Templates · Media
- [ ] `Modules/Connector` — API clients · Webhooks
- [ ] `Modules/Officeimpresso` — Computadores · Licenças desktop legacy

**Subcomponente reuse:** `Pages/Sistema/_shared/SistemaSubNav.tsx` (compartilhado)

## Como retomar (próxima sessão)

### 1. Ler artefatos canon antes de começar

```bash
# Skill protocol
cat .claude/skills/pageheader-canon/SKILL.md

# ADRs canon
cat memory/decisions/0180-sidebar-v3-5-grupos-ghosts-header.md
cat memory/decisions/0182-pageheadertabs-canon-pattern-telas.md

# Matriz diferenças permitidas vs obrigatórias
cat memory/requisitos/_DesignSystem/pageheader-matriz-diferencas.md
```

### 2. Comando pra começar wave

```
# Próxima sessão Wagner pode disparar:
/pageheader-canon <Modulo>
```

Skill ativa automaticamente protocolo 5 fases:
1. **DESCOBERTA** — agente lê DataController + Pages/<Mod>/**/*.tsx, mapeia sub-views + grupo v3 + primary contextual + ações features
2. **TABELA DE DECISÃO** — classifica cada botão (duplicado-com-ghost REMOVE / features → overflow / primary → Zona R / multi-tipo → split-button / per-linha INTACTO)
3. **NAMING** — labels ≤2 palavras + primary verbo+objeto ≤3 palavras
4. **IMPLEMENTAÇÃO** — DataController + SubNav + PrimaryButton + telas + charter + PR ≤300 LOC
5. **VALIDAÇÃO VISUAL OBRIGATÓRIA** — browser MCP script JS canon valida 6 checks (C1-C6); se ⚠️ abre PR fix imediato

### 3. Sub-agents paralelos (recomendado)

4 sub-agents em paralelo (1 por wave) = ~1.5-2 dias wall-clock pra zerar 18 módulos restantes.

## Bloqueio conhecido (a investigar próxima sessão)

⚠️ **`/financeiro/caixa` validação pendente** — em biz=1 não há caixa fechado histórico pra validar fluxo end-to-end. Quando alguém fechar 1 caixa (Maiara #10, Felipe #4, Wagner #2 estão `Aberto`):

1. Observer dispara → `fin_titulo origem='caixa'` criado
2. `/financeiro/caixa` linha #N vira ✅ verde (status="lancado")
3. Click drill-down → `/financeiro/unificado?titulo=N`
4. Validar metadata JSON: `user_name`, `location_name`, `breakdown{cash,card,cheque,other,total}`

Se algo falhar, ver `storage/logs/laravel.log` linhas `[adr_0183][caixa]` (Listener loga warn).

## PRs entregues nesta sessão (referência rápida)

### ADR 0180 (Sidebar v3) — 11 PRs
- #1340 ADR 0180 + protótipo + dossiê
- #1341 MenuItemContract DTO + 22 Pest
- #1343 Sidebar.tsx 11→5 keys + LEGACY_GROUP_MAP
- #1345 PageHeaderTabs.tsx slot canon
- #1350 LegacyMenuAdapter + DataController Financeiro
- #1352/#1353 Wave A+B (Crm/ProductCatalogue/Repair/Oficina/Manufacturing/Compras)
- #1354 SCOPE.md Compras (desbloqueia check-scope)
- #1359/#1360/#1361 Wave C+D+E (Jana/KB/SRS/TeamMcp/ProjectMgmt/NfeBrasil/NFSe/Essentials/Ponto/Governance/ADS/Auditoria/Cms/Connector/Officeimpresso/AssetManagement)
- #1363 Fase 5 piloto Financeiro/Unificado

### ADR 0182 (PageHeader canon) — 7 PRs
- #1364 tweak header inline
- #1365 tweak primary direita + overflow + label Financeiro
- #1366 propagação 11 telas Financeiro
- #1367 hidePrimary fix
- #1368 refine overflow botões
- #1369 ADR 0182 canon pattern 3 zonas
- #1370 canon completo (auto-promove + hue 145 + labels curtos + matriz + skill)
- #1371 split-button dropdown Unificado (Receber/Pagar/OCR)
- #1372 skill protocol 5 fases + validação visual obrigatória

### ADR 0183/0184 (Caixa ↔ Financeiro) — 7 PRs
- #1373 fix cr.location_id SQL
- #1374 fix parent_id SQL
- #1375 ADR 0183 ponte canon + 15 pegadinhas
- #1376 PR A migration schema
- #1377 PR B Observer + Listener + Service + 6 Pest
- #1378 PR C UI status integração + backfill manual
- #1379 ADR 0184 errata + link modal POS

## Próximo passo sugerido

**Quando retomar**, comandar uma das 4 waves:

```
/pageheader-canon Sells    # wave vender (alta prioridade — Larissa usa diariamente)
/pageheader-canon Repair   # wave operar (também alto uso oficina/serviços)
```

OU sub-agents paralelos:

```
Spawn 4 sub-agents em paralelo:
- Wave VENDER: Sells + Crm + ProductCatalogue
- Wave OPERAR: Repair + OficinaAuto + Manufacturing + Compras + AssetManagement
- Wave PESSOAS+FISCAL: Essentials + Ponto + NfeBrasil + NFSe
- Wave SISTEMA: Governance + ADS + Auditoria + Cms + Connector + Officeimpresso
```

Cada sub-agent segue skill `pageheader-canon` (protocolo 5 fases + Fase 5 validação visual OBRIGATÓRIA).

## Métricas alvo pós-propagação

| Métrica | Baseline | Meta |
|---|---|---|
| Telas com pattern 3 zonas | 11/40+ (27%) | 100% |
| Hue OKLCH harmônica entre módulos | só Financeiro | todos 8 grupos |
| Larissa decora 1x e transfere | só /financeiro/* | qualquer módulo |
| Tempo aprendizagem inter-tela (smoke) | medir baseline | -50% |
| CI gate `pageheader:health` (futuro F3) | inexistente | ativo warn-only |
