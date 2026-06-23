# SYNC_LOG — Cowork ↔ Claude Code

> Toda vez que o **Claude Code** sincronizar `prototipo-ui/` com um export novo do Cowork, anexa uma entrada aqui.
> Toda vez que o **Cowork** quiser deixar mensagem pro Code, escreve em `COWORK_NOTES.md` (não aqui).

Formato:

```
## YYYY-MM-DD HH:MM — sync por <quem> (Cowork→Code | Code→produção)
- Branch: <nome>
- Commits: <N>
- Arquivos mudados: <lista resumida>
- PR: <link>
- Notas: <o que vale destacar>
- COWORK_NOTES processadas: <sim/não>
```

---

## 2026-05-30 — reconciliação pós-auditoria 55/100 (Cowork [CC])
- Branch: — (faxina local do Cowork; nada commitado no git por [CC])
- Arquivos mudados: `_arquivo/legado/` (+479: uploads/backups/scraps/memory-para-github · D4); `memory/decisions/_PROPOSTA-ds-harmonizacao.md` + `_PROPOSTA-ratificacao-design.md` (despromovidos de 0200/0201 · D2); `CLAUDE.md.proposto` (navy→STALE/ADR 0235 · D5); `MEMORY_INDEX.md` (hierarquia fonte-única + correção 0200/0201 · D6); `STATUS.md` (soberania→ADR 0238); `_arquivo/INDEX.md` v1.1.
- Notas: aplica a grade D2–D8 do `CODE_NOTES.amendment-faxina-followup` (55→mira ≥80). Append-only: tudo movido/lápide, nada apagado. ADR 0238 numerada pelo Code, aguarda merge [W].
- COWORK_NOTES processadas: sim (prompt-cowork-ler-git + amendment-faxina-followup)

## 2026-05-30 — conformidade ADR 0239 R4 (Cowork [CC])
- Branch: — (Cowork local)
- Arquivos mudados: `Design System v4.html` devolvido à raiz (era over-archive da faxina); `_arquivo/INDEX.md` v1.2 (+ seção "DS — versões antigas (links)").
- Notas: ADR 0239 (git SSOT do DS · lida nesta sessão) R4 = exatamente 1 spec vigente na raiz. v3 + v4.2 permanecem arquivados. Append-only.
