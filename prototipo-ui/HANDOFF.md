# HANDOFF.md — estado vivo do loop

> **Sobrescrito a cada sync.** Não é log — é "onde estamos agora".
> Histórico vive em [SYNC_LOG.md](SYNC_LOG.md).

---

## Estado atual: 2026-05-09 — SETUP

**Fase global:** aguardando primeiras respostas de Claude Design em [COWORK_NOTES.md](COWORK_NOTES.md).

### Em voo agora

| Tela | Fase | Responsável | Bloqueador |
|---|---|---|---|
| _(setup do diretório)_ | F0 | [W] / [CD] | aguardando respostas das 3 perguntas em COWORK_NOTES.md |

### Próxima da fila

Ver [TELAS_REVIEW_QUEUE.md](TELAS_REVIEW_QUEUE.md). P0 sugerida: `Sells/Create`.

### Métricas rápidas

- Telas em F3 há +7d: 0 (loop saudável)
- Protótipos sem critique-score: 0 (ainda nenhum protótipo)
- Merges sem a11y-report: 0

### O que [W] precisa fazer

1. Aguardar respostas de [CD] em [COWORK_NOTES.md](COWORK_NOTES.md)
2. Adicionar primeiro pedido P0 usando template `## YYYY-MM-DD HH:MM [W] → [CC]`
3. Trigger Cowork pra produzir protótipo

### O que [CL] precisa fazer

Nada agora. Aguarda Cowork exportar primeiro `prototipos/<tela>/` e Wagner aprovar screenshot pra entrar em F3.
