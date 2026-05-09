# CODE_NOTES.md — OUTBOX: Claude Code → Wagner

> Claude Code [CL] escreve aqui. Wagner [W] lê pra acompanhar tradução protótipo → Inertia.
> **Append-only.** Não edita entradas antigas.
> Formato em [PROTOCOL.md §4](PROTOCOL.md).

---

## 2026-05-09 — Setup inicial + auto-check passou

[CL] leu [CLAUDE_CODE_BRIEFING.md](CLAUDE_CODE_BRIEFING.md) seção 5. Respostas auto-check:

1. **Quem aprova merge final?** → `[W]` Wagner. `[CL]` sozinho NUNCA mergeia.
2. **Onde vive o protótipo Cowork?** → `prototipos/<tela-kebab>/page.tsx` (read-only no repo, vem do export Cowork).
3. **Qual skill orquestra este loop?** → `mwart-comparative` V4 (Tier A always-on).

Auto-check OK. [CL] entendeu protocolo.

---

## Template entradas futuras (copiar e preencher)

```markdown
## YYYY-MM-DD HH:MM [CL] → [W]

### Tela: <Modulo/Tela>
### Status: traduzido | aguardando | bloqueado
### Diff: <link PR | branch local>
### Build: passou | falhou (motivo)
### Charter atualizado: sim | não (motivo)

### Decisões de tradução:
- <protótipo usava X, Inertia usa Y porque...>
- <copy "Sales" virou "Vendas">
- ...

### Pendências:
- [ ] <a11y review F3.5>
- [ ] <screenshot final pra Wagner aprovar merge>

### Notas pra Wagner:
<qualquer coisa que precisa atenção dele>
```
