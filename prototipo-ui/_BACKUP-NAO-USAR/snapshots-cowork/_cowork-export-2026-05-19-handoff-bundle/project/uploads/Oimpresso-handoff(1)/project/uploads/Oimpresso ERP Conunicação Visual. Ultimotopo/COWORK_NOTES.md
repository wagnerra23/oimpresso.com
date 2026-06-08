# COWORK_NOTES — Mensagens do Cowork pro Claude Code

> O **Cowork** anexa aqui pedidos, decisões e contexto que o **Claude Code** precisa saber na próxima sync.
> O Claude Code, ao processar uma sync, **lê este arquivo, age conforme, e marca cada item como [PROCESSADO YYYY-MM-DD] no final**.
> Mensagens muito antigas processadas vão pro fim do arquivo em "Histórico".

---

## 📥 Pendentes

### 2026-04-27 — Sync inicial
**Contexto:** primeira ida do protótipo pro repo. Cowork está em ~88% do escopo do shell + Fases 2-3 (OS, Clientes, Orçamentos, Produtos) prontas.

**Pedidos para o Claude Code:**

1. **Sync inicial** — extrair zip do Cowork em `prototipo-ui/`, branch `feat/prototipo-ui-cockpit`, abrir PR, mergear na main.

2. **Verificar estrutura esperada** após sync:
   - `prototipo-ui/Oimpresso ERP - Chat.html` (entry)
   - `prototipo-ui/README.md`
   - `prototipo-ui/CLAUDE_CODE_BRIEFING.md` (este briefing)
   - `prototipo-ui/SYNC_LOG.md`
   - `prototipo-ui/COWORK_NOTES.md` (este arquivo)
   - `prototipo-ui/memory/HANDOFF.md`
   - 12+ arquivos `.jsx` e `styles.css`

3. **Decidir:** `LARAVEL_REPO_CONTEXT.md` que está dentro do export — esse arquivo é redundante com o `CLAUDE.md` raiz do repo. Apaga após confirmar que `CLAUDE.md` raiz está atualizado.

4. **Anexar primeira entrada em SYNC_LOG.md** descrevendo o sync inicial.

5. **Confirmar** com Wagner que a integração está funcionando: leia este arquivo, escreve confirmação em `CODE_NOTES.md`, peça pra Wagner colar pro Cowork.

**Status atual do protótipo (lê HANDOFF.md pra detalhes):**
- Fase 1 (shell): ✅ pronto
- Fase 2 (OS piloto): ✅ pronto (lista, detalhe, Nova OS, Aprovar arte, bulk export, atalhos)
- Fase 3 (Clientes/Orçamentos/Produtos): ✅ pronto (CRUD básico, KPIs, filtros)
- Fase 4 (Produção): 🔴 só placeholder
- Fase 5 (decommission Blade): 🔴 não iniciada

---

## ✅ Histórico (processadas)

(vazio)
