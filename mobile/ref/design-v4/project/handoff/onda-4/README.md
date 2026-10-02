# Onda 4 — Offline visível + notificações

**Achado:** o app já tem fila offline completa (`lib/mutation-queue.ts`: guarda a mutação que falha por rede, reenvia ao reconectar e a cada 60 s, chave de idempotência para pagamento e NF-e). O problema não é a fila — é que **as telas não contam**: mostram "Erro ao salvar" para algo que foi guardado.

| Arquivo | Ação |
| --- | --- |
| `mobile/lib/notify.ts` | **Substituir** (versão da Onda 2 + `useNotifyError`). |
| `mobile/hooks/use-pending-sync.ts` | **Criar.** Contagem da fila por rota; marca registro pendente. |
| `mobile/components/oi/OiPendingNote.tsx` | **Criar.** Aviso inline para Fiscal/Pagamentos. Exportar no barrel. |
| `mobile/components/offline-banner.tsx` | **Substituir.** Tokens do DS (sai `#fbbf24`/`#1f2937`), contagem também offline, "Sincronizando…", falhas do servidor. |
| `USO.md` | Como ligar nas telas. |
| `NOTIFICACOES-BACKEND.md` | Especificação do que falta no servidor. **Risco médio — decisão do Wagner.** |
