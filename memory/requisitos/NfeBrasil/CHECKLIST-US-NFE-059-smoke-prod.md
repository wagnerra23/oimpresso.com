# Checklist — US-NFE-059 smoke prod end-to-end auto-emissão NFe55

> **Para Wagner executar.** Não automatizável (humano-limitado: cliente real + SEFAZ-prod + cert A1).
> **Pré-requisito:** cadeia FSM mergeada em main (PRs #501/#507/#508/#509/#510 — done 2026-05-10).
> **Estimate humano:** ~2-4h (depende disponibilidade cliente).

---

## 0. Escolher cliente

| Candidato | Pros | Contras |
|---|---|---|
| **Gold** (Comunicação Visual) | Manifestação Destinatário entregue (US-NFE-049/050/051/052 done); gráfica grande, emite 100% | Precisa explicar mudança pré-execução |
| **Vargas** (Autopecas) | Sinal qualificado real R$ [redacted Tier 0]M GMV | módulo Autopecas (planejado — não existe); arquitetura ainda não cobre OS auto |
| **Cliente novo CV** (Extreme/Zoom/Fixar/Mhundo/Produart) | Onboarding limpo desde início | Depende adoção pós outreach (sem ETA) |

**Recomendado: Gold** (caminho mais curto pra evidência real).

## 1. Pré-flight (15 min antes)

- [ ] Backup DB Hostinger: `mysqldump -u u906587222 -p u906587222_oimpresso transactions transaction_payments transaction_sell_lines nfe_emissoes transaction_documents > /tmp/backup-pre-smoke-$(date +%Y%m%d-%H%M).sql`
- [ ] Confirmar SEFAZ-prod online: https://www.nfe.fazenda.gov.br/portal/disponibilidade.aspx
- [ ] Cert A1 instalado pro business Gold em `storage/app/private/nfe_certs/{business_id}/` + senha em vault
- [ ] `nfe_business_configs` row pra Gold: `cstat=100` esperado, `ncm_default` setado, `tipo_emissao=1`
- [ ] **Avisar cliente Gold por WhatsApp** ~30min antes: "vou ativar emissão automática hoje, qualquer estranheza me chama"

## 2. Aplicar migrations FSM em prod (5 min)

```bash
# SSH Hostinger (warm-up + retry — auto-mem reference_hostinger §SSH)
for i in 1 2 3 4 5; do curl -s -o /dev/null --max-time 15 https://oimpresso.com/login; done
ssh -4 -o ConnectTimeout=900 -o ServerAliveInterval=3 -o ServerAliveCountMax=200 \
    -o ConnectionAttempts=5 -i ~/.ssh/id_ed25519_oimpresso -p 65002 \
    u906587222@148.135.133.115 'cd domains/oimpresso.com/public_html && git pull origin main && php artisan migrate --force'
```

- [ ] Output mostra 6 migrations rodadas: `2026_05_11_120001..5_create_sale_*` + `2026_05_11_140001_create_transaction_documents` + `2026_05_11_130001_create_stock_reservations` + `2026_05_11_150001_create_nfse_emissoes` + `2026_05_11_160001_add_fsm_columns_to_transactions`
- [ ] Validar via SQL: `SHOW TABLES LIKE 'sale_%'` retorna 5; `SHOW COLUMNS FROM transactions LIKE 'process_id'` retorna 1; `SHOW COLUMNS FROM transactions LIKE 'current_stage_id'` retorna 1

## 3. Seed processos FSM pro Gold (2 min)

```bash
# SSH Hostinger ainda aberto
php artisan fsm:seed-default-processes --business=<GOLD_BIZ_ID>
```

- [ ] Output: "Seeded 3 processes for business <ID>" (idempotente — re-rodar não duplica)
- [ ] SQL valida: `SELECT key,name FROM sale_processes WHERE business_id=<GOLD_BIZ_ID>` retorna 3 linhas (`venda_sem_nota`, `venda_com_nota_manual`, `venda_com_nota_auto`)
- [ ] SQL: `SELECT s.key, s.name, s.is_initial, s.is_terminal FROM sale_process_stages s JOIN sale_processes p ON s.process_id=p.id WHERE p.business_id=<GOLD_BIZ_ID> AND p.key='venda_com_nota_auto' ORDER BY s.sort_order` — esperado 5 stages: `rascunho`(initial) → `faturada` → `paga` → `emitida` → `enviada`(terminal)
- [ ] SQL: `SELECT a.key, a.target_stage_id, a.event_class FROM sale_stage_actions a JOIN sale_process_stages s ON a.stage_id=s.id JOIN sale_processes p ON s.process_id=p.id WHERE p.business_id=<GOLD_BIZ_ID> AND p.key='venda_com_nota_auto' AND a.key='emitir_nfe'` — esperado 1 linha com `event_class` preenchido (auto-trigger)

## 4. Configurar 1 contato cliente Gold (3 min)

Via UI `/contacts/{id}/edit` OU SQL direto:
```sql
UPDATE contacts SET fsm_default_process_key='venda_com_nota_auto'
WHERE business_id=<GOLD_BIZ_ID> AND id=<CLIENTE_TESTE_ID>;
```

- [ ] Confirmar contact tem CPF/CNPJ válido + endereço completo (NFe rejeita sem)
- [ ] Anotar `<CLIENTE_TESTE_ID>` pra session log

## 5. Executar venda real (5-10 min)

- [ ] Login UI `/sells/create` como vendedor Gold
- [ ] Selecionar `<CLIENTE_TESTE_ID>` + 1 produto baixo valor (R$ [redacted Tier 0]-50, minimizar exposição fiscal)
- [ ] Confirmar UI mostra processo "Venda Com Nota Automática" (badge ou select)
- [ ] Finalizar venda → SQL valida: `SELECT id, process_id, current_stage_id FROM transactions WHERE id=<NEW_TX_ID>` retorna `process_id` setado + `current_stage_id` = id do stage `rascunho`
- [ ] Faturar boleto via RecurringBilling (UI `/recurring-billing` ou comando)
- [ ] Marcar boleto como pago manualmente (UI `/financeiro` ou comando) → dispara `InvoicePaid` event

## 6. Observar pipeline (5 min)

Em paralelo durante step 5:
```bash
# Terminal 1: tail prod logs
ssh ... 'tail -f /home/u906587222/domains/oimpresso.com/public_html/storage/logs/laravel.log | grep -E "Emitir|FSM|Stage|cstat|InvoicePaid"'

# Terminal 2: Horizon dashboard CT 100 (ou logs)
tailscale ssh root@ct100-mcp 'docker logs -f horizon | grep -E "Emitir|NFSe|NFe"'
```

- [ ] Log mostra `InvoicePaid` event disparado
- [ ] Log mostra `EmitirNFeAoReceberPagamento` listener invocado
- [ ] Log mostra gate FSM resolvendo `emitir_nfe` action (não no-op silencioso)
- [ ] Log mostra `NfeService::emitir()` chamado
- [ ] Log mostra SEFAZ resposta `cstat=100` (autorizado)

## 7. Validar resultado (5 min)

- [ ] UI `/nfebrasil/emissoes` mostra nova NFe `status=authorized` pro cliente
- [ ] `nfe_emissoes` SQL: `SELECT chave_acesso, cstat, status FROM nfe_emissoes WHERE transaction_id=<NEW_TX_ID>` retorna `cstat=100`, `status=authorized`
- [ ] `transaction_documents` SQL: `SELECT doc_type, status FROM transaction_documents WHERE transaction_id=<NEW_TX_ID>` retorna `nfe55` `authorized`
- [ ] `sale_stage_history` SQL: `SELECT from_stage_id, to_stage_id, action_id FROM sale_stage_history WHERE transaction_id=<NEW_TX_ID> ORDER BY executed_at` mostra trilha completa (rascunho → faturada → paga → emitida)
- [ ] Email recebido pelo cliente (verificar inbox dele OU `/admin/emails-sent` se houver)
- [ ] PDF DANFE abre OK (clicar download em `/nfebrasil/emissoes/<id>/danfe`)

## 8. Session log (5 min)

Criar `memory/sessions/2026-MM-DD-smoke-us-nfe-059-gold.md` com:
- Data + hora UTC
- `<GOLD_BIZ_ID>` + `<CLIENTE_TESTE_ID>` + `<NEW_TX_ID>`
- Stack trace eventos (timestamps do log)
- Screenshot DANFE + email
- Tempo total real vs estimate
- Decisão: ativar pra outros clientes? Quando?

Commit + push em PR (não commit direto em main):
```bash
git checkout -b session/smoke-us-nfe-059-gold
git add memory/sessions/2026-MM-DD-*.md
git commit -m "docs(session): smoke US-NFE-059 — Gold cstat 100 ✅"
git push -u origin session/smoke-us-nfe-059-gold
gh pr create --base main --title "docs(session): smoke US-NFE-059 done — Gold cstat 100" --body "Smoke prod end-to-end concluído. ..."
```

## 9. Atualizar SPEC + MCP

- [ ] Edit `memory/requisitos/NfeBrasil/SPEC.md` US-NFE-059 → `status: done` + linha `done: YYYY-MM-DD · evidence: <session-log-link>`
- [ ] Tool MCP: `tasks-update task_id=US-NFE-059 status=done`
- [ ] Tool MCP: `tasks-comment task_id=US-NFE-059 comment="cstat 100 ✅ Gold biz=<ID> tx=<NEW_TX_ID>. Session: <link>"`

## Rollback (se algo falhar antes/durante)

### Falha de migration
- Restore backup: `mysql -u u906587222 -p u906587222_oimpresso < /tmp/backup-pre-smoke-*.sql`
- Reverter código: `ssh ... 'cd ... && git reset --hard HEAD~6'` (volta antes dos 5 PRs FSM)

### Falha SEFAZ (cstat != 100)
- NFe NÃO autorizada — sem efeito fiscal real, só log warning
- Investigar via `nfe_emissoes.error_msg` SQL
- Cliente NÃO recebe email (listener só dispara após `authorized`)

### NFe autorizada mas dados errados (CFOP/NCM/destinatário)
- **Cancelar via SEFAZ em ≤15min** ("cancelamento cortesia"): UI `/nfebrasil/emissoes/<id>/cancelar`
- Após 15min: precisa CC-e (carta de correção) ou inutilização — processo manual

### Voltar cliente pro fluxo legacy
```sql
UPDATE contacts SET fsm_default_process_key=NULL WHERE id=<CLIENTE_TESTE_ID>;
UPDATE sale_processes SET active=0 WHERE business_id=<GOLD_BIZ_ID> AND key='venda_com_nota_auto';
```
Cliente volta a NÃO emitir NFe automática.

---

## Refs

- US: [US-NFE-059](memory/requisitos/NfeBrasil/SPEC.md#us-nfe-059)
- ADR mãe FSM: [0129](memory/decisions/0129-state-machine-canonica-fsm-rbac.md)
- Caso prático: [Sells/CASO-PRATICO-OS-COMUNICACAO-VISUAL.md](memory/requisitos/Sells/CASO-PRATICO-OS-COMUNICACAO-VISUAL.md)
- PRs cadeia FSM: #501 #507 #508 #509 #510 (todos done em main 2026-05-10)
- Cycle: CYCLE-04
