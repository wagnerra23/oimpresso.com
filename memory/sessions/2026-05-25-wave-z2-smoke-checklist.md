---
slug: 2026-05-25-wave-z2-smoke-checklist
title: "Wave Z-2 — Smoke checklist Integração Vendas × Oficina prod biz=1"
type: smoke-checklist
date: 2026-05-25
related_adrs: [0192, 0093, 0143, 0121]
related_prs: [1497, 1500, 1502, 1505, 1508, 1509, 1510, 1511, 1513]
deploy_script: scripts/deploy-wave-z2-integracao-vendas-oficina.sh
status: pendente-wagner
---

# Wave Z-2 — Smoke checklist Integração Vendas × Oficina prod biz=1

Use APÓS rodar `scripts/deploy-wave-z2-integracao-vendas-oficina.sh`.

Marque cada item ao validar manualmente em Brave Wagner@WR2 Sistemas biz=1. Anexar evidence (screenshot, SQL output, console log) em cada item validado.

## Setup (1×)

- [ ] SSH no Hostinger
- [ ] `cd /home/oimpresso/oimpresso.com`
- [ ] `bash scripts/deploy-wave-z2-integracao-vendas-oficina.sh` (deploy automatizado)
- [ ] Confirma 6 passos Y/N executados com sucesso
- [ ] Backup MySQL salvo em `~/backups/wave-z2/transactions-pre-deploy-*.sql`
- [ ] `php artisan migrate:status` mostra 2 entries Wave Z-2 (`add_source_and_os_ref_to_transactions` + `add_cancelled_at_to_transactions`)
- [ ] Vite build (`npm run build`) sem erros

## Bloco A — Schema (validação SQL pós-migrate)

| # | Item | Evidence |
|---|---|---|
| A1 | `DESCRIBE transactions;` mostra coluna `source` ENUM('balcao','oficina','online') DEFAULT 'balcao' NULL | |
| A2 | `DESCRIBE transactions;` mostra coluna `os_ref` VARCHAR(20) NULL | |
| A3 | `DESCRIBE transactions;` mostra coluna `commission_split` JSON NULL | |
| A4 | `SHOW INDEX FROM transactions WHERE Key_name='idx_transactions_source';` retorna 1 row (composite `business_id, source, transaction_date`) | |
| A5 | `DESCRIBE transactions;` mostra coluna `cancelled_at` TIMESTAMP NULL | |
| A6 | `SELECT COUNT(*) FROM transactions WHERE business_id=1 AND source IS NULL;` deve ser 0 (legacy default='balcao' retroativo) | |

## Bloco B — Observer dispara CREATE (terminal transition)

Cenário: criar JobSheet de teste biz=1, mover pra stage `entregue_completo`, conferir Transaction criada.

| # | Item | Evidence |
|---|---|---|
| B1 | Criar OS test biz=1: `/repair/job-sheets/create` → preencher cliente + 1 peça + serviço básico → salvar | |
| B2 | Anotar OS ID gerado (OS-NNNN) | |
| B3 | Mover OS via FSM action até stage terminal `entregue_completo` (recebido → diagnóstico → execução → pronto → entregue) | |
| B4 | `SELECT id, source, os_ref, payment_status, business_id FROM transactions WHERE os_ref='OS-{ID}';` retorna 1 row | |
| B5 | Confere: `source='oficina'`, `os_ref='OS-{ID}'`, `payment_status='due'`, `business_id=1`, `type='sell'` | |
| B6 | Observer idempotente: rodar mesma OS pelo mesmo path (re-trigger sem mudança) NÃO duplica Transaction (`SELECT COUNT(*) FROM transactions WHERE os_ref='OS-{ID}';` ainda = 1) | |
| B7 | `tail -100 storage/logs/laravel.log \| grep JobSheetObserver` mostra log entry com `business_id` + `os_ref` | |

## Bloco C — Sells/Index UI (coluna Origem + saved tree + KPI breakdown + listener)

Abrir `https://oimpresso.com/vendas` logado Wagner@WR2 SC biz=1.

| # | Item | Evidence |
|---|---|---|
| C1 | Tabela vendas mostra nova coluna **Origem** entre status e ações | |
| C2 | Vendas legacy renderizam pill "Balcão" (default retroativo) | |
| C3 | Vendas derivadas Wave Z-2 (`source=oficina`) renderizam pill "Oficina" com cor diferente | |
| C4 | Pill Origem tem ícone visual distinguível por source (balcão/oficina/online) | |
| C5 | Clicar `↗ #OS-NNNN` na pill abre Repair drawer da OS correspondente (cross-link) | |
| C6 | Saved tree lateral mostra branch **"Por origem"** novo (Balcão · Oficina · Online) expansível | |
| C7 | Clicar "Oficina" no tree filtra tabela só vendas com `source=oficina` | |
| C8 | KPI hero topo mostra breakdown por origem quando Foco=Faturamento ativo (3 sub-cards: Balcão R$ X · Oficina R$ Y · Online R$ Z) | |
| C9 | Listener `oimpresso:open-venda` cross-módulo: chamar `window.dispatchEvent(new CustomEvent('oimpresso:open-venda', {detail: {ref: 'V-NNNN'}}))` no DevTools abre drawer/sheet da venda | |

## Bloco D — Repair drawer card "Esta OS gerou venda"

Abrir `https://oimpresso.com/repair/producao-oficina` logado biz=1. Clicar OS criada no Bloco B (já em stage `entregue_completo`).

| # | Item | Evidence |
|---|---|---|
| D1 | Drawer abre lateral direita | |
| D2 | Card "Esta OS gerou venda" renderiza visível (verde/azul · destaca) | |
| D3 | Card mostra ref venda `#V-NNNN` clicável | |
| D4 | Card mostra breakdown peças/serviço (valor R$ total + sub-totais) | |
| D5 | Card mostra fiscal badge (NF-e emitida / NFS-e emitida / sem nota) | |
| D6 | Atalho "Abrir" → abre Sells SaleSheet da venda derivada | |
| D7 | Atalho "Imprimir" → dispara `window.print()` ou modal preview | |
| D8 | Atalho "Compartilhar" → Web Share API nativa (mobile) OR clipboard fallback (desktop) com toast confirmação | |
| D9 | Card NÃO aparece pra OS sem Transaction associada (stage anterior a terminal OU `source=balcao` legacy) | |

## Bloco E — Sells/Caixa nova rota /vendas/caixa

Abrir `https://oimpresso.com/vendas/caixa` logado biz=1.

| # | Item | Evidence |
|---|---|---|
| E1 | Página `/vendas/caixa` renderiza sem 404 | |
| E2 | Header mostra saudação + data + caixa atual (turno aberto/fechado) | |
| E3 | Seção **"Por origem"** mostra 3 barras de progresso (Balcão · Oficina · Online) | |
| E4 | Cada barra mostra valor total R$ + % do total do dia | |
| E5 | Cada barra clicável → drill-down lista vendas daquela origem | |
| E6 | Layout coexiste com `Sells/Index.tsx` antigo (rota antiga `/vendas` continua funcionando) | |
| E7 | Atalhos KB-9.75 preservados (`?` cheat-sheet) | |

## Bloco F — Cross-link bidirecional (Sells ↔ Repair)

| # | Item | Evidence |
|---|---|---|
| F1 | Em Sells/Index, clicar `↗ #OS-NNNN` da coluna Origem → abre Repair/ProducaoOficina com drawer aberto na OS correspondente | |
| F2 | Em Repair drawer (OS já entregue), clicar "Abrir" no card "Esta OS gerou venda" → abre Sells SaleSheet correspondente | |
| F3 | URL muda em ambas direções (SPA preserva back/forward) | |
| F4 | Evento `oimpresso:open-venda` aparece em DevTools Console quando cross-link dispara | |
| F5 | Multi-tenant Tier 0 (ADR 0093): tentar acessar OS de outro business via URL retorna 404 (NÃO 403) | |

## Bloco G — Reverse hook (OS reaberta cancela Transaction)

Cenário: pegar OS do Bloco B (já em `entregue_completo`), reabrir pra stage não-terminal, conferir Transaction soft-cancelled.

| # | Item | Evidence |
|---|---|---|
| G1 | Anotar Transaction ID criada no Bloco B (`SELECT id FROM transactions WHERE os_ref='OS-{ID}';`) | |
| G2 | Reabrir OS via FSM action: mover de `entregue_completo` pra stage anterior (ex: `em_execucao` ou `pronto_para_retirar`) | |
| G3 | `SELECT id, cancelled_at FROM transactions WHERE id={TX_ID};` retorna row com `cancelled_at IS NOT NULL` (timestamp ~now) | |
| G4 | `tail -100 storage/logs/laravel.log \| grep "JobSheetObserver.*reverse"` mostra log entry | |
| G5 | Re-completar OS (mover de volta pra `entregue_completo`) → cria **NOVA** Transaction (ID diferente), NÃO restaura a cancelled | |
| G6 | `SELECT COUNT(*) FROM transactions WHERE os_ref='OS-{ID}';` agora retorna 2 (Tx1 cancelled + Tx2 nova) | |
| G7 | Sells/Index oculta vendas com `cancelled_at IS NOT NULL` por default (filtro pode mostrar) | |
| G8 | Multi-tenant: reverse hook só toca Transactions do mesmo `business_id` que a OS | |

## Bloco H — commission_split editor (Sells/Edit)

Abrir `/vendas/edit/{TX_ID}` de venda derivada (Bloco B).

| # | Item | Evidence |
|---|---|---|
| H1 | Form mostra seção **"Divisão de comissão"** | |
| H2 | 2 selects: mecanico_id (lista users role mecanico) + balcao_id (lista users role balcão) | |
| H3 | 2 inputs numéricos: mecanico_pct + balcao_pct (% 0-100) | |
| H4 | Validation client-side: se `mecanico_pct + balcao_pct !== 100` mostra erro inline | |
| H5 | Validation server-side: POST com total ≠ 100 retorna 422 + erro field-level | |
| H6 | `balcao_id=NULL` aceito quando `mecanico_pct=100` (100% mecânico, sem balcão) | |
| H7 | Salvar com split válido: `SELECT commission_split FROM transactions WHERE id={TX_ID};` retorna JSON `{"mecanico_id": X, "mecanico_pct": Y, "balcao_id": Z, "balcao_pct": W}` | |
| H8 | Salvar com `commission_split=NULL` (deixar vazio) aceito | |
| H9 | Charter compliance: NÃO promete shape `[{user_id, role, pct}]` array — só tupla mecânico/balcão (ADR 0192 escopo conservador) | |

## Smoke close (após 8 blocos validados)

- [ ] Screenshot Sells/Index com coluna Origem + saved tree + KPI breakdown → `prototipo-ui/screenshots/wave-z2-sells-index-origem-prod-biz1-2026-05-25.png`
- [ ] Screenshot Repair drawer com card "Esta OS gerou venda" → `prototipo-ui/screenshots/wave-z2-repair-drawer-card-prod-biz1-2026-05-25.png`
- [ ] Screenshot Sells/Caixa seção "Por origem" → `prototipo-ui/screenshots/wave-z2-caixa-por-origem-prod-biz1-2026-05-25.png`
- [ ] Append `prototipo-ui/SYNC_LOG.md`:
  ```
  2026-05-25 HH:MM [W2] approved smoke wave-z2 prod biz=1 — Integração Vendas × Oficina OK (ADR 0192)
  ```
- [ ] Atualizar `memory/requisitos/Sells/Index-r3-integracao-vendas-oficina-visual-comparison.md` frontmatter:
  - `status: ready-for-screenshot-approval` → `status: validated-prod`
  - `approved_by: Wagner 2026-05-25 HH:MM BRT prod biz=1`
- [ ] (Opcional) Skill `brief-update` → regenera `memory/requisitos/Repair/BRIEFING.md` + `memory/requisitos/Sells/BRIEFING.md`
- [ ] Commit + push em main:
  ```bash
  git add prototipo-ui/screenshots/ prototipo-ui/SYNC_LOG.md memory/requisitos/Sells/*.md memory/requisitos/Repair/BRIEFING.md
  git commit -m "docs(wave-z2): smoke prod biz=1 aprovado + 3 screenshots + visual-comparison validated"
  git push
  ```

## Rollback rápido se algo quebrar

1. SSH Hostinger
2. Restaurar DB: `mysql -u<usr> -p <db> < ~/backups/wave-z2/transactions-pre-deploy-*.sql`
3. Reverter migrations: `php artisan migrate:rollback --step=2`
4. Reverter código: `git reset --hard <HEAD-pre-pull>` (ID logado no Passo 2 do script)
5. Limpar caches: `php artisan optimize:clear`
6. Reportar em handoff novo + abrir issue GitHub se bug crítico

## Critério de fechamento Wave Z-2

Wave Z-2 = **COMPLETA** quando:

- ✅ 2 migrations Wave Z-2 aplicadas (`migrate:status` confirma)
- ✅ JobSheetObserver dispara CREATE em terminal transition (Bloco B verde)
- ✅ JobSheetObserver dispara REVERSE em reabertura (Bloco G verde)
- ✅ Sells/Index renderiza coluna Origem + tree + KPI breakdown (Bloco C verde)
- ✅ Repair drawer renderiza card "Esta OS gerou venda" (Bloco D verde)
- ✅ Sells/Caixa renderiza seção "Por origem" (Bloco E verde)
- ✅ commission_split editor funciona (Bloco H verde)
- ✅ Cross-link bidirecional Sells ↔ Repair (Bloco F verde)
- ✅ 3 screenshots aprovados por Wagner
- ✅ SYNC_LOG.md tem `[W2] approved smoke wave-z2 prod biz=1`
- ✅ Nenhum bug crítico em 7 dias canary biz=1

Após canary 7 dias verde, sub-onda futura pode:
- Habilitar biz=4 Larissa (vestuário · ROTA LIVRE)
- Habilitar biz=3+ outros multi-tenant
- Considerar review triggers ativos (ADR 0192) — split 3+ pessoas, fiscal pendente, performance Observer >50ms

## Refs

- [ADR 0192 · Auto-faturar OS → Venda via JobSheetObserver](../decisions/0192-auto-faturar-os-venda-jobsheet-observer.md)
- [ADR 0093 · Multi-tenant isolation Tier 0 IRREVOGÁVEL](../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0143 · FSM Pipeline LIVE prod biz=1](../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
- [Plano F3 6 ondas](2026-05-25-plano-f3-integracao-vendas-oficina.md)
- [Pattern Cliente Wave Z-2](2026-05-21-wave-z-2-smoke-checklist.md)
- [Script deploy](../../scripts/deploy-wave-z2-integracao-vendas-oficina.sh)
