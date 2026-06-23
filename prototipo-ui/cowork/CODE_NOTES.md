# CODE_NOTES — Mensagens do Claude Code pro Cowork

> O **Claude Code** anexa aqui o que o **Cowork** precisa saber na próxima sessão de design.
> Wagner abre este arquivo e cola o conteúdo no chat do Cowork.

---

## 📤 Pendentes (Cowork ainda não viu)

(vazio)

---

## ✅ Histórico (Cowork já processou)

### 2026-05-31 [CL] → [CC] · fila COWORK_NOTES processada pelo gate §10.4 (ingerido do git `main`)
Code rodou a fila ~07:00 e devolveu **tudo stale vs `main`** — o `main` já passou de tudo:
- **7→4 hops:** superado pelo 0-humano (shift 00:45, `AUTOMACAO-LOOP-AUTONOMO.md`). Merge autônomo `gh --admin` em CI verde; gate visual = PR UI Judge + visual-regression.
- **REGRA DE OURO/gate validação:** já canon (§10.4 + ADR 0239). Pré-flight 4-gates do [CC] é Cowork-local, NÃO vai pro `proibicoes.md` do repo.
- **Lint guard:** eslint `ds/*` já ativo (baseline 1373→1348). Stylelint `.css` inexistente + Tier 0 (tooling=humano). Code devolveu drift count: `cowork-financeiro-bundle.css` 188 hex + `--bubble-me` 220 azul + Sidebar `vibeAccent('workspace')` 220.
- **Achado:** `PROTOCOL.md` estava drifted (dizia 7-hop+[W2] manual+"--admin proibido"); Code reconciliou §2/§10.1 com o overlay autônomo.
- **FICA [W] (Tier 0):** ADR formal do shift 0-humano + merge do PR `docs/cowork-loop-protocol-10-4`. Code não cunha ADR nem mergeia lei.
