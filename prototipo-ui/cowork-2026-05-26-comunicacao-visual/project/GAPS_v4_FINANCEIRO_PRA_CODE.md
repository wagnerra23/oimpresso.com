# GAPS_v4_FINANCEIRO_PRA_CODE.md
# Executa v3 + audita as 4 telas extras (Fluxo / Conciliação / DRE / Plano de contas)
# 2026-05-20 · Cowork → Code

> **Objetivo:** num único PR, (a) sincronizar os 9 arquivos canônicos do protótipo (v3) E (b) auditar se as 4 telas extras já têm rota Inertia + Page TSX em prod. Code NÃO inventa controllers/Models — apenas reporta gaps em `CODE_NOTES.md` pro Cowork acionar PR separado depois.

---

## O que muda em relação ao v3

- v3 só consertava a parte visual (overwrite dos 9 arquivos do protótipo)
- v4 acrescenta **passo de auditoria** das 4 telas extras (Fluxo / Conciliação / DRE / Plano de contas)
- Se faltarem rotas/Pages → Code escreve diagnóstico em `CODE_NOTES.md` (não tenta criar)
- Tudo num **único prompt zero-touch** pro Wagner colar uma vez

---

## Prompt único pro Claude Code (Wagner cola UMA VEZ)

```
@claude Executa GAPS_v4 do Financeiro. Faz tudo nesta ordem, num PR só, sem perguntar.

═══════════════════════════════════════════════════════════════
PARTE 1 — Sincronizar 9 arquivos canônicos do protótipo (v3)
═══════════════════════════════════════════════════════════════

1. git checkout main && git pull
2. git checkout -b fix/financeiro-v4-sync-prototipo

3. Baixar e sobrescrever os 9 arquivos (Cowork v2, 18-20/05/2026):

   curl -L -o prototipo-ui/financeiro.css 'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/financeiro.css?t=71a021dde9e6089f758b477035562ae63a32ad11cf7d344d1003b04b56912a43.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779277129&direct=1'

   curl -L -o prototipo-ui/financeiro-app.jsx 'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/financeiro-app.jsx?t=71a021dde9e6089f758b477035562ae63a32ad11cf7d344d1003b04b56912a43.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779277129&direct=1'

   curl -L -o prototipo-ui/financeiro-data.jsx 'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/financeiro-data.jsx?t=71a021dde9e6089f758b477035562ae63a32ad11cf7d344d1003b04b56912a43.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779277129&direct=1'

   curl -L -o prototipo-ui/financeiro-telas-extras.jsx 'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/financeiro-telas-extras.jsx?t=71a021dde9e6089f758b477035562ae63a32ad11cf7d344d1003b04b56912a43.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779277129&direct=1'

   curl -L -o prototipo-ui/financeiro-curation.jsx 'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/financeiro-curation.jsx?t=71a021dde9e6089f758b477035562ae63a32ad11cf7d344d1003b04b56912a43.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779277129&direct=1'

   curl -L -o prototipo-ui/financeiro-ai.jsx 'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/financeiro-ai.jsx?t=71a021dde9e6089f758b477035562ae63a32ad11cf7d344d1003b04b56912a43.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779277129&direct=1'

   curl -L -o prototipo-ui/financeiro-output.jsx 'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/financeiro-output.jsx?t=71a021dde9e6089f758b477035562ae63a32ad11cf7d344d1003b04b56912a43.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779277129&direct=1'

   curl -L -o prototipo-ui/financeiro-icons.jsx 'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/financeiro-icons.jsx?t=71a021dde9e6089f758b477035562ae63a32ad11cf7d344d1003b04b56912a43.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779277129&direct=1'

   curl -L -o prototipo-ui/fsm-stepper.jsx 'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/fsm-stepper.jsx?t=71a021dde9e6089f758b477035562ae63a32ad11cf7d344d1003b04b56912a43.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779277129&direct=1'

4. Verificação visual:

   wc -l prototipo-ui/financeiro.css         # esperar 1296
   wc -l prototipo-ui/financeiro-app.jsx     # esperar 1134
   grep -c "fin-drawer-tabs\|fin-conferido-toggle\|fin-ai-anomalia\|fin-audit-row\|fin-frescor" prototipo-ui/financeiro.css
   # esperar >= 30
   grep -c "TelaFluxo\|TelaConciliacao\|TelaDRE\|TelaPContas" prototipo-ui/financeiro-telas-extras.jsx
   # esperar >= 4

   Se algum check falhar → abort, reporta no PR description e para aqui.

═══════════════════════════════════════════════════════════════
PARTE 2 — Auditar as 4 telas extras (Fluxo / Concil / DRE / PContas)
═══════════════════════════════════════════════════════════════

5. Auditar Routes do módulo:

   echo "--- ROUTES AUDIT ---" > /tmp/financeiro-audit.txt
   echo "" >> /tmp/financeiro-audit.txt
   echo "## routes/web.php (root) — financeiro hits:" >> /tmp/financeiro-audit.txt
   grep -n "financeiro" routes/web.php >> /tmp/financeiro-audit.txt 2>&1 || echo "(zero matches)" >> /tmp/financeiro-audit.txt
   echo "" >> /tmp/financeiro-audit.txt
   echo "## Modules/Financeiro/Routes/web.php:" >> /tmp/financeiro-audit.txt
   cat Modules/Financeiro/Routes/web.php >> /tmp/financeiro-audit.txt 2>&1 || echo "(arquivo não existe)" >> /tmp/financeiro-audit.txt

6. Auditar Pages Inertia:

   echo "" >> /tmp/financeiro-audit.txt
   echo "## resources/js/Pages/Financeiro/ — TSX files:" >> /tmp/financeiro-audit.txt
   ls -la resources/js/Pages/Financeiro/ >> /tmp/financeiro-audit.txt 2>&1 || echo "(pasta não existe)" >> /tmp/financeiro-audit.txt

7. Auditar Controllers:

   echo "" >> /tmp/financeiro-audit.txt
   echo "## Modules/Financeiro/Http/Controllers/ — Controllers:" >> /tmp/financeiro-audit.txt
   ls -la Modules/Financeiro/Http/Controllers/ >> /tmp/financeiro-audit.txt 2>&1 || echo "(pasta não existe)" >> /tmp/financeiro-audit.txt

8. Auditar Entities (Models reais — não inventar):

   echo "" >> /tmp/financeiro-audit.txt
   echo "## Modules/Financeiro/Entities/ — Models reais:" >> /tmp/financeiro-audit.txt
   ls -la Modules/Financeiro/Entities/ >> /tmp/financeiro-audit.txt 2>&1 || echo "(usa Models/ talvez)" >> /tmp/financeiro-audit.txt
   ls -la Modules/Financeiro/Models/ >> /tmp/financeiro-audit.txt 2>&1 || true

9. Resumir gaps por tela:

   echo "" >> /tmp/financeiro-audit.txt
   echo "═══════════════════════════════════" >> /tmp/financeiro-audit.txt
   echo "## STATUS POR TELA" >> /tmp/financeiro-audit.txt
   echo "═══════════════════════════════════" >> /tmp/financeiro-audit.txt
   for tela in "Unificado:fluxo-caixa,unificado" "Fluxo:fluxo-caixa" "Conciliacao:conciliacao" "DRE:dre,relatorios" "PlanoContas:plano-de-contas,plano-contas"; do
     name=$(echo $tela | cut -d: -f1)
     paths=$(echo $tela | cut -d: -f2)
     echo "" >> /tmp/financeiro-audit.txt
     echo "### $name" >> /tmp/financeiro-audit.txt
     echo "- Page TSX: $(test -f resources/js/Pages/Financeiro/${name}.tsx && echo '✅' || echo '🔴 falta')" >> /tmp/financeiro-audit.txt
     echo "- Controller: $(ls Modules/Financeiro/Http/Controllers/${name}Controller.php 2>/dev/null && echo '✅' || echo '🔴 falta')" >> /tmp/financeiro-audit.txt
     for p in $(echo $paths | tr ',' ' '); do
       echo "- Rota /financeiro/$p: $(grep -r "financeiro/$p" Modules/Financeiro/Routes/ routes/ 2>/dev/null | head -1 | cut -c1-100 || echo '🔴 não encontrada')" >> /tmp/financeiro-audit.txt
     done
   done

10. Copia o relatório completo pra CODE_NOTES.md do repo (vai aparecer no PR pro Cowork ler):

    cp /tmp/financeiro-audit.txt prototipo-ui/CODE_NOTES.md.append
    # OU se preferir, abrir prototipo-ui/CODE_NOTES.md e anexar bloco em "📤 Pendentes"

═══════════════════════════════════════════════════════════════
PARTE 3 — Commit + PR (NÃO inventa controllers nem Models)
═══════════════════════════════════════════════════════════════

11. git add prototipo-ui/financeiro*.{css,jsx} prototipo-ui/fsm-stepper.jsx prototipo-ui/CODE_NOTES.md.append

12. git commit -m "fix(financeiro): sync 9 arquivos canônicos Cowork v2 + auditoria 4 telas extras

    PARTE 1 - Visual sync:
    - financeiro.css 1296 linhas / 129 classes .fin-*
    - financeiro-app.jsx 1134 linhas com <nav className=fin-drawer-tabs>
    - financeiro-telas-extras.jsx com TelaFluxo/Concil/DRE/PContas
    - financeiro-{data,curation,ai,output,icons}.jsx + fsm-stepper.jsx
    - Resolve drawer sem abas + textos colados + sparkline + frescor pills
    
    PARTE 2 - Auditoria reportada em CODE_NOTES.md.append:
    - Status por tela (Unificado / Fluxo / Conciliacao / DRE / PlanoContas)
    - Gaps de routes, controllers e Pages TSX
    - NÃO cria controllers/Models neste PR (R-FIN-001 + LICOES_F3 anteriores)
    
    Próximos PRs (a partir do gap reportado):
    - PR separado: rotas + Pages Inertia das 4 telas extras (se gap)
    - PR separado: FinanceiroDemoSeeder (mock data Maio 2026)
    
    Refs: GAPS_v4_FINANCEIRO_PRA_CODE.md no Cowork"

13. git push -u origin fix/financeiro-v4-sync-prototipo

14. gh pr create --base main --title "fix(financeiro): sync v2 visual + auditoria 4 telas extras" --body "$(cat <<'EOF'
    ## Resumo
    - PARTE 1: sobrescreve 9 arquivos canônicos do protótipo (drawer abas, FSM, frescor, audit timeline, sparkline)
    - PARTE 2: auditoria das 4 telas extras (Fluxo/Conciliacao/DRE/PlanoContas) — relatório em CODE_NOTES.md.append

    ## Por que não cria controllers/Models neste PR
    Lições de PR anterior (LICOES_F3_FINANCEIRO_REJEITADO.md):
    - Não inventar Models (Titulo / TituloBaixa / ContaBancaria / Categoria são os reais)
    - Não esquecer tenant scope (R-FIN-001 Tier 0)
    - Não sobrescrever controller existente em prod
    - Não inventar middleware

    Por isso: este PR é SÓ visual + auditoria. PRs futuros (separados) escrevem controllers/seeders com base no relatório.

    ## Como testar
    1. /financeiro/unificado → drawer com abas Detalhes/✦ IA, sparkline no hero, frescor pills
    2. ls prototipo-ui/financeiro*.{css,jsx} | wc -l → 8 arquivos
    3. Ver CODE_NOTES.md.append pra status das 4 telas extras
    EOF
    )"

15. Reporta no chat aqui o output do gh pr create (URL do PR) E o conteúdo de
    /tmp/financeiro-audit.txt — Wagner cola pro Cowork escrever próximos PRs.

═══════════════════════════════════════════════════════════════
REGRAS DE OURO (não negociáveis)
═══════════════════════════════════════════════════════════════

❌ NÃO criar Models / Controllers / Migrations neste PR
❌ NÃO inventar nome de Model (Titulo / TituloBaixa / ContaBancaria / Categoria são os reais)
❌ NÃO mexer em UnificadoController.php (já está em prod, PRs #355/#358)
❌ NÃO criar Seeder neste PR (vai em PR separado)
❌ NÃO marcar PR como "F3 completo" (este é visual + audit, não scaffolding)
✅ Sobrescrever inteiro os 9 arquivos do protótipo (não merge incremental)
✅ Reportar gaps por escrito em CODE_NOTES.md.append (Cowork lê e decide próximos)
✅ Manter business_id scope em qualquer query que rodar
✅ Verificar com grep que CSS tem >= 30 menções a fin-drawer-tabs etc

Se algo der erro de schema/permissão durante a verificação → ABORT e reporta. Não tenta consertar inventando.
```

---

## Resultado esperado

Após Code rodar isso:

1. **PR aberto** com 9 arquivos sincronizados (visual do Financeiro fica certo)
2. **`CODE_NOTES.md.append`** dentro do PR contém audit das 4 telas extras
3. **Cowork lê o audit** e decide os próximos PRs (rotas faltantes, seeder, etc) — cada um com seu próprio briefing rigoroso seguindo LICOES_F3_FINANCEIRO_REJEITADO.md

## Telas cobertas neste PR

| Tela | Visual sync (v2) | Rota/Controller/Page TSX |
|---|---|---|
| Visão unificada (`/financeiro`) | ✅ neste PR | ⏳ audit reporta status |
| Fluxo de caixa | ✅ neste PR | ⏳ audit reporta status |
| Conciliação | ✅ neste PR | ⏳ audit reporta status |
| DRE / Relatórios | ✅ neste PR | ⏳ audit reporta status |
| Plano de contas | ✅ neste PR | ⏳ audit reporta status |

## Próximos PRs (a partir do audit, não neste)

- **PR-B:** rotas + Pages das 4 telas extras (se gaps reportados) — ler Entities antes
- **PR-C:** `FinanceiroDemoSeeder` (mock Maio 2026 — distribuição 60/40, 50% liquidado, 10-15% atrasado)
- **PR-D:** F3.5 Acessibilidade — `accessibility-review` WCAG 2.1 AA

---

## URLs (validade ~1h — me avisa se expirou)

URLs públicas dos 9 arquivos já estão dentro dos `curl` na PARTE 1 do prompt. Se Wagner colar daqui a >1h e Code retornar 404/expired, eu regero e mando v5.
