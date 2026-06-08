# PROMPT PARA CLAUDE CODE — Sync Vendas + Financeiro KB-9.75
# Cole o conteúdo abaixo (entre os marcadores) no Claude Code

═══════════════════════════════════════════════════════════════════
SYNC: Vendas + Financeiro · método KB-9.75 completo · 2026-05-18
═══════════════════════════════════════════════════════════════════

## Contexto

Cowork aplicou Método KB-9.75 em DOIS módulos completos:
- **Vendas**: 5,6 → 9,75/10 (4 refinos + polish · 7 arquivos)
- **Financeiro**: 7,5 → 9,75/10 (3 refinos · 11 arquivos)

Total: 18 arquivos a sincronizar com `prototipo-ui/` no repo.

3 arquivos novos no Vendas (Refino #1-4):
- `vendas-shortcuts.jsx` — cheat-sheet J/K/R/F/B/E/X/?
- `vendas-ai.jsx` — IA copiloto (resumir/histórico/sugerir)
- `vendas-curation.jsx` — comentários inline + audit + troubleshooter + linkify
- `vendas-output.jsx` — transcript PDF + apresentação + mensagem + arte
- `vendas-tweaks.jsx` — TweaksPanel densidade/drawer/SLA/paleta

3 arquivos novos no Financeiro (Refino #1-3):
- `financeiro-curation.jsx` — useFinComments + Conferido + audit + frescor
- `financeiro-ai.jsx` — anomalia + party history + month digest
- `financeiro-output.jsx` — troubles + fechamento 12 passos + apresentação

Arquivos modificados:
- `vendas-page.jsx`, `vendas-extras.jsx` (sub-rotas com Esc-back)
- `financeiro-app.jsx`, `financeiro-data.jsx`, `financeiro-icons.jsx`, `financeiro-telas-extras.jsx`
- `data.jsx` (sidebar ganhou fin-fluxo + fin-dre)
- `app.jsx` (rotas fin-fluxo / fin-dre)
- `styles.css` (+~3000 linhas de CSS dos refinos)
- `Oimpresso ERP - Chat.html` (8 novos script tags)

## Tarefa pra Claude Code

1. Criar nova branch `feat/vendas-financeiro-kb-9.75` a partir de `main`
2. Baixar os 18 arquivos via curl pra `prototipo-ui/`
3. Commit com mensagem clara
4. Push e abrir PR contra `main` apontando ADR-0114 (loop formalizado)
5. Anexar entrada em `prototipo-ui/COWORK_NOTES.md` confirmando sync

## Comandos (copia e cola tudo de uma vez)

```bash
cd ~/oimpresso.com

# branch nova
git checkout main && git pull origin main
git checkout -b feat/vendas-financeiro-kb-9.75

# baixa todos os arquivos
cd prototipo-ui

# ── Vendas (7 arquivos) ───────────────────────────────────────────
curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/vendas-page.jsx?t=6ee400515a6e30835ce102481819f6632f7d1f76344de82be8b69b295043039c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779108627&direct=1" -o vendas-page.jsx

curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/vendas-extras.jsx?t=6ee400515a6e30835ce102481819f6632f7d1f76344de82be8b69b295043039c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779108627&direct=1" -o vendas-extras.jsx

curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/vendas-shortcuts.jsx?t=6ee400515a6e30835ce102481819f6632f7d1f76344de82be8b69b295043039c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779108627&direct=1" -o vendas-shortcuts.jsx

curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/vendas-ai.jsx?t=6ee400515a6e30835ce102481819f6632f7d1f76344de82be8b69b295043039c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779108627&direct=1" -o vendas-ai.jsx

curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/vendas-curation.jsx?t=6ee400515a6e30835ce102481819f6632f7d1f76344de82be8b69b295043039c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779108627&direct=1" -o vendas-curation.jsx

curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/vendas-output.jsx?t=6ee400515a6e30835ce102481819f6632f7d1f76344de82be8b69b295043039c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779108627&direct=1" -o vendas-output.jsx

curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/vendas-tweaks.jsx?t=6ee400515a6e30835ce102481819f6632f7d1f76344de82be8b69b295043039c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779108627&direct=1" -o vendas-tweaks.jsx

# ── Financeiro (7 arquivos) ───────────────────────────────────────
curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/financeiro-app.jsx?t=6ee400515a6e30835ce102481819f6632f7d1f76344de82be8b69b295043039c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779108627&direct=1" -o financeiro-app.jsx

curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/financeiro-data.jsx?t=6ee400515a6e30835ce102481819f6632f7d1f76344de82be8b69b295043039c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779108627&direct=1" -o financeiro-data.jsx

curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/financeiro-icons.jsx?t=6ee400515a6e30835ce102481819f6632f7d1f76344de82be8b69b295043039c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779108627&direct=1" -o financeiro-icons.jsx

curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/financeiro-telas-extras.jsx?t=6ee400515a6e30835ce102481819f6632f7d1f76344de82be8b69b295043039c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779108627&direct=1" -o financeiro-telas-extras.jsx

curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/financeiro-curation.jsx?t=6ee400515a6e30835ce102481819f6632f7d1f76344de82be8b69b295043039c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779108627&direct=1" -o financeiro-curation.jsx

curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/financeiro-ai.jsx?t=6ee400515a6e30835ce102481819f6632f7d1f76344de82be8b69b295043039c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779108627&direct=1" -o financeiro-ai.jsx

curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/financeiro-output.jsx?t=6ee400515a6e30835ce102481819f6632f7d1f76344de82be8b69b295043039c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779108627&direct=1" -o financeiro-output.jsx

# FSM stepper compartilhado (novo · cross-módulo)
curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/fsm-stepper.jsx?t=281a39cffd1f34014cbd058a9d777717a5d78563a476d68ac529032529db065e.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779131727&direct=1" -o fsm-stepper.jsx

# ── Shell + dados gerais (4 arquivos) ────────────────────────────
curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/data.jsx?t=6ee400515a6e30835ce102481819f6632f7d1f76344de82be8b69b295043039c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779108627&direct=1" -o data.jsx

curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/app.jsx?t=6ee400515a6e30835ce102481819f6632f7d1f76344de82be8b69b295043039c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779108627&direct=1" -o app.jsx

curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/styles.css?t=6ee400515a6e30835ce102481819f6632f7d1f76344de82be8b69b295043039c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779108627&direct=1" -o styles.css

curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/Oimpresso%20ERP%20-%20Chat.html?t=6ee400515a6e30835ce102481819f6632f7d1f76344de82be8b69b295043039c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779108627&direct=1" -o "Oimpresso ERP - Chat.html"

cd ..

# valida: cada arquivo deve ter >100 bytes
ls -la prototipo-ui/vendas-*.jsx prototipo-ui/financeiro-*.jsx prototipo-ui/styles.css

# commit
git add prototipo-ui/
git status
git commit -m "feat(prototipo-ui): aplica KB-9.75 em Vendas e Financeiro

Vendas: 5,6 → 9,75/10 (4 refinos + polish)
- vendas-shortcuts.jsx (novo): cheat-sheet J/K/R/F/B/E/X/?
- vendas-ai.jsx (novo): IA copiloto · resumir/histórico/sugerir
- vendas-curation.jsx (novo): comentários inline + audit + troubleshooter + linkify
- vendas-output.jsx (novo): transcript PDF + apresentação + msg + arte
- vendas-tweaks.jsx (novo): TweaksPanel densidade/drawer/SLA/paleta
- vendas-page.jsx, vendas-extras.jsx: header limpo, breadcrumb back, foco/visões

Financeiro: 7,5 → 9,75/10 (3 refinos)
- financeiro-curation.jsx (novo): useFinComments + Conferido + audit + frescor
- financeiro-ai.jsx (novo): anomalia + party history + month digest
- financeiro-output.jsx (novo): troubles + fechamento 12 passos + apresentação
- financeiro-app.jsx: 4 filter checkboxes + density inline + back btns
- financeiro-data.jsx: cross-link #V- #PC- #BL- nos descs
- data.jsx, app.jsx: sidebar fin-fluxo + fin-dre routes

Cross-link bidirecional Vendas ↔ Financeiro:
- VdLinkify estendido pra #BL- #PC- #R- #P-
- Drawer da venda ganha 'Lançamentos financeiros vinculados'

Re: ADR-0114 (loop formalizado)
"
git push -u origin feat/vendas-financeiro-kb-9.75

# abre PR via gh CLI
gh pr create \
  --title "feat(prototipo-ui): KB-9.75 em Vendas e Financeiro · 9,75/10 ambos" \
  --body "## Resumo

Aplicação do Método KB-9.75 em DOIS módulos completos.

### Vendas: 5,6 → 9,75 (4 refinos + polish)
- Refino #1 Fundação · SLA pill, J/K row-nav, ⌘K prefixos, tree saved-views, responsive
- Refino #2 IA · resumir pedido, histórico cliente, sugerir produto, palette empty IA
- Refino #3 Curadoria+Guia · comentários inline, audit trail, troubleshooter, linkify
- Refino #4 Distribuição · transcript PDF, apresentação fullscreen, mensagem, art-slot
- Polish · Foco/Visões, sticky header, empty states, hover-reveal actions, Tweaks panel

### Financeiro: 7,5 → 9,75 (3 refinos)
- Refino #1 Curadoria · Conferido toggle Eliana, comentários, audit, frescor pill
- Refino #2 IA · anomalia outlier, party context, month digest 4-card snapshot
- Refino #3 Guia+Saída · 4 troubleshooters, trilha fechamento 12 passos, modo apresentação

### Cross-link bidirecional
- \\\`VdLinkify\\\` estendido pra \\\`#BL-\\\` \\\`#PC-\\\` \\\`#R-\\\` \\\`#P-\\\`
- Drawer da venda ganha seção 'Lançamentos financeiros vinculados'
- Drawer do Financeiro tem \\\`desc\\\` com \\\`#V-\\\` clicável

### Reuso conseguido
- ~70% dos componentes do Financeiro vieram do Vendas
- \\\`KBTroubleshooterDialog\\\` reusado em ambos com customTroubles distintos
- CSS \\\`.vd-ai-*\\\` compartilhado entre módulos

### Arquivos
- 7 arquivos NOVOS (\\\`vendas-{shortcuts,ai,curation,output,tweaks}.jsx\\\`, \\\`financeiro-{curation,ai,output}.jsx\\\`)
- 11 arquivos modificados (vendas-page, vendas-extras, financeiro-app, financeiro-data, financeiro-icons, financeiro-telas-extras, data, app, styles.css, Chat.html)

### Testar
Abra \\\`prototipo-ui/Oimpresso ERP - Chat.html\\\` localmente. Navegue: Vendas → abre venda V-7821 → drawer com tab '✦ IA' funcional → footer com '? Resolver' e '▶ Apresentar' e 'Transcript'. Financeiro → '☑ Fechamento' abre trilha 12 passos.

Re: ADR-0114 (loop Cowork formalizado)" \\
  --base main \\
  --label "prototipo-ui"

# anexa entrada em COWORK_NOTES.md
cat >> prototipo-ui/COWORK_NOTES.md <<'EOF'

---

### 2026-05-18 — Vendas + Financeiro · método KB-9.75 completo

**Branch:** `feat/vendas-financeiro-kb-9.75`
**Score:** Vendas 9,75/10 · Financeiro 9,75/10
**Reuso:** ~70% dos componentes do Financeiro vieram do Vendas

**Pra Code:**
1. Mergear o PR após screenshot approval (Wagner)
2. Atualizar `SYNC_LOG.md` apontando este sync como v2 da aplicação do método
3. Atualizar `TELAS_REVIEW_QUEUE.md`: marcar Sells/Index, Sells/Create e Financeiro/Unified como **done · A+** (≥9,5)

**Próximas aplicações sugeridas:**
- CRM (persona Bruna · score atual ~6,5)
- Compras (persona Wagner+Bruna · liga com Financeiro já feito)
- Equipe / Chat interno (persona times · multi-canal)

[PROCESSADO YYYY-MM-DD]
EOF

git add prototipo-ui/COWORK_NOTES.md
git commit -m "docs(cowork): anexa entrada sync 2026-05-18 KB-9.75 Vendas+Financeiro"
git push
```

═══════════════════════════════════════════════════════════════════
FIM DO PROMPT — Wagner cola tudo de uma vez, Code executa.
═══════════════════════════════════════════════════════════════════
