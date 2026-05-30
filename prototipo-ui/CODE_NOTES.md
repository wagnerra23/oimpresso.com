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

## 2026-05-30 09:43 [CL] → [W] — Sync memória Cowork→Git (espinha + índice temático + ADRs)

**Branch:** `feat/memoria-espinha-indice-tematico` (base `main`). PR aberto pra sua revisão — **não mergeei** (publication-policy). Link do PR no fim desta entrada.

Processei a ponte `PROMPT_PARA_CODE_MEMORIA.md`. Tudo num PR só.

### ✅ Sincronizado
- `prototipo-ui/STATUS.md` — espinha viva (fiel ao Cowork; ver ⚠️ drift DS abaixo)
- `prototipo-ui/CARTA_DESIGN_CC.md` — carta subordinada (ref ADR ajustada → 0237)
- `memory/INDEX_TEMATICO.md` — índice temático (renomeado de `MEMORY_INDEX.md`; **backfill completo 0042–0235**)
- `memory/LICOES_CC.md` · `memory/sessions/2026-05-30-ds-harmonizacao-espinha.md`
- `memory/decisions/0236-…` + `0237-…` (as 2 ADRs, renumeradas) + `README.md` (2 linhas + ponteiro)

### ⚠️ AÇÃO PRA VOCÊ NO COWORK — renumeração de ADR
Os números `0200`/`0201` **já estavam ocupados no git** por ADRs reais e DIFERENTES:
- **0200 real** = `contacts` adota canon sync bidirecional
- **0201 real** = Receita Federal + SEFAZ ConsultaCadastro

Por isso (ADR 0028, monotônica) renumerei as ADRs da sessão pros próximos livres:
- **ADR 0200 (Cowork) → `0236`** — Harmonização DS + v4.2
- **ADR 0201 (Cowork) → `0237`** — Carta de Design [CC] subordinada

👉 **No Cowork, atualize STATUS.md / MEMORY_INDEX.md / sessão / CARTA pra citar `0236` e `0237`** (não 0200/0201).

### ℹ️ Escopo estendido (além do que o prompt pediu)
Seed dizia "faltam 0042–0189". O git já tem **239 ADRs (até 0235)** — completei o índice temático até **0235**, senão nasceria defasado. Colisões históricas de número (0101, 0102, 0119, 0141, 0170×3, 0178, 0180, 0195, 0216, 0235) marcadas `(colisão)`; resolvê-las é trabalho à parte.

### ⚠️ Possível drift de DS pra você decidir (NÃO mexi)
`STATUS.md` diz "DS canônico = v4.1 / v4.2 proposto". Mas o git aceitou **ADR 0235 — DS v4 roxo universal + Claude Design como owner da UI** (2026-05-29). Vale reconciliar a numeração (v4.1/v4.2 Cowork × "DS v4" git) pelo loop F0. Deixei STATUS fiel ao Cowork.

### 🔗 PR
https://github.com/wagnerra23/oimpresso.com/pull/1990

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
