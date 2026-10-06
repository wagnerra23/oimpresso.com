---
sessao: "03"
titulo: Split de comissão auditado (activity log)
dono: "[CL]"
base: 23c3f080aa94
prefixo: app/Transaction.php (array logOnly) · app/Http/Controllers/SellCommissionSplitController.php (docblock) · tests/Feature/Sells/
nao_toca: resources/js/ · Modules/
---
# 03 · Split de comissão auditado

**Achado (medido no `main` @23c3f080aa94, 2026-10-06):** o docblock do `SellCommissionSplitController` diz *"Audit log via Spatie ActivityLog (já configurado em Transaction model)"*, mas `app/Transaction.php` `getActivitylogOptions()` faz `logOnly([status, total_before_tax, final_total, contact_id, location_id, transaction_date, payment_status])` + `logOnlyDirty()`: **nem `commission_split` nem `commission_agent` estão na lista.** Trocar a divisão de comissão ou o comissionado da venda **não gera auditoria**, só `Log::info` em arquivo.

**Conserto (1 linha + teste):** incluir `'commission_split'` e `'commission_agent'` no `logOnly` do `Transaction` (log name segue `sales.transaction`, ADR 0127). Corrigir o docblock do controller para não prometer o que não fazia. **Prefixo ampliado:** `app/Transaction.php` (só o array `logOnly`).

## Casos de uso
### UC-COM-01 · Trocar split ou comissionado fica registrado com antes → depois · `must` `[T0]`
- **Aceite:** Dado venda com split 70/30 · Quando mudo para 100/0, limpo (null) ou troco o `commission_agent` · Então existe um registro de auditoria com usuário, data, valor antigo e novo, só no business da venda. Controle positivo: salvar o mesmo split não gera registro.
- **Teste:** `CommissionSplitAuditTest` — `UC-COM-01`
- **Contrato:** ADR 0192 · ADR 0093
- **Regressão que defende:** vendedor perdendo comissão sem saber quem mudou.

### UC-COM-02 · Split de outro business não é alcançável · `must` `[T0]`
- **Aceite:** Dado venda do biz A · Quando usuário do biz B faz PATCH → 404. mecânico ou balconista de outro business → 422. Controle positivo: mesmo business → 200. (Já coberto por `CommissionSplitEditorTest`? Conferir e só citar; não duplicar.)
- **Teste:** `CommissionSplitEditorTest`
- **Contrato:** ADR 0093
- **Regressão que defende:** vazamento entre empresas.

Terminou: `_saida-03.md`. Pare.
