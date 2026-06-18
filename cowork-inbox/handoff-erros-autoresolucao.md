<!-- cowork: target: prototipo-ui/handoffs/erros-autoresolucao.md -->
---
handoff_id: erros-autoresolucao
tela: Plataforma/ErrorHandling
files: [app/Support/Errors/AutoResolver.php, app/Jobs/ReprocessJob.php, config/queue.php, config/errors.php]
created_by: CC
audited_against: 0f98814eb4f8
---
## Onda E-3 (Fase 2 · Absorver) — Auto-resolução: retry · fallback · reprocesso

**Depende de:** E-1, E-2. **Objetivo:** o erro que se resolve sozinho **não acorda ninguém**. Sobe o
"% auto-resolvido" (indicador-chave do painel). Aplica-se aos S1/S2 recuperáveis do Mapa.

**§10.4:** validar contra o `main`; main vence. Reusar a fila/jobs que já existem.

### Design (casos concretos do Mapa)
- **SEFAZ fora** → fila de reenvio com **backoff exponencial**; reemite quando voltar. Operador vê
  "enfileirado", não erro.
- **Webhook de pagamento atrasado** → job de reprocesso idempotente (dedup por id externo).
- **WhatsApp/Baileys desconectou** → tentativa de reconexão automática N×; mensagens enfileiram.
- `AutoResolver::canRetry(Classification)` decide o que é recuperável (whitelist por tipo/domínio).
- **Idempotência obrigatória** (não duplicar NF-e/cobrança no retry).
- **Dead-letter:** após N tentativas falhas → **promove pra S1** (vira problema de humano) + fecha o
  auto-loop. Retry infinito é proibido.

### NÃO FAZER
- ❌ Retry sem idempotência (risco de cobrança/NF-e dupla). ❌ Retry infinito. ❌ Auto-resolver S0
  (dinheiro/dado/segurança = humano sempre).

### PRONTO QUANDO (Pest)
- Erro recuperável → resolvido por retry **sem** disparar alerta; `% auto-resolvido` registrado.
- Retry é idempotente (não duplica efeito).
- Após N falhas → dead-letter promove pra S1 e para.

> Cowork read-only no git — DESIGN; código é PR revisado do [CL].
