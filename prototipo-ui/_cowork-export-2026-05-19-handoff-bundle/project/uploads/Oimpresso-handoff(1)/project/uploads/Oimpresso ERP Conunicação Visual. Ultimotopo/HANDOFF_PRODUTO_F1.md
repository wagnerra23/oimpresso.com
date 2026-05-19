# Handoff Produto F1 + F3 → Claude Code

**Status:** F1 protótipo pronto **+** F3 scaffold (controller real + rota) baseado em código investigado em `wagnerra23/oimpresso.com@main`.
Cole o prompt abaixo no Claude Code para commitar em `wagnerra23/oimpresso.com@main`.

**Branch alvo:** `feat/prototipo-produto-f1` (criar a partir de `main`).

## ⚠️ Correção de arquitetura aplicada

Investigação real (commit `001cde4e5eb7`) revelou:
- **Não existe `Modules\Produto`.** Produto é UltimatePOS herdado em `app/`.
- `App\Product`, `App\Category`, `App\Variation`, `App\Brands` ficam direto em `app/`.
- BOM real = `Modules\Manufacturing\Entities\MfgRecipe`.
- Tabela de preço = `App\SellingPriceGroup` (sem multiplicador nativo — TODO decidir com [W]).
- Histórico = `transaction_sell_lines` join `transactions` últimos 30d.

Caminhos finais no repo:
- `prototipo-ui/prototipos/produto/` — 4 arquivos do protótipo Cowork
- `app/Http/Controllers/ProdutoUnificadoController.php` — controller F3 (real namespaces)
- `routes/web.php` — patch (instruções no `.patch.md`)
- `resources/js/Pages/Produto/Unificado/Index.tsx` — página Inertia/React F3

---

## URLs públicas (válidas ~1h)

### F1 — Protótipo Cowork

- `Produto Unificado.html` — https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/prototipos/produto/Produto%20Unificado.html?t=d1e51cf48227a302a5a15aa86b7534740b81338e5cb04337b418491e7d9be6cb.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1778362293&direct=1
- `produto-app.jsx` — https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/prototipos/produto/produto-app.jsx?t=d1e51cf48227a302a5a15aa86b7534740b81338e5cb04337b418491e7d9be6cb.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1778362293&direct=1
- `produto-data.jsx` — https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/prototipos/produto/produto-data.jsx?t=d1e51cf48227a302a5a15aa86b7534740b81338e5cb04337b418491e7d9be6cb.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1778362293&direct=1
- `produto-icons.jsx` — https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/prototipos/produto/produto-icons.jsx?t=d1e51cf48227a302a5a15aa86b7534740b81338e5cb04337b418491e7d9be6cb.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1778362293&direct=1

### F3 — Backend + Frontend (real namespaces UltimatePOS)

- `ProdutoUnificadoController.php` — https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/app/Http/Controllers/ProdutoUnificadoController.php?t=46f8f52a9de545659a88ffae842d91a64176a573e093221385a8b10af899299c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1778365593&direct=1
- `routes/web.php.patch.md` — https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/routes/web.php.patch.md?t=46f8f52a9de545659a88ffae842d91a64176a573e093221385a8b10af899299c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1778365593&direct=1
- `Pages/Produto/Unificado/Index.tsx` — https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/resources/js/Pages/Produto/Unificado/Index.tsx?t=46f8f52a9de545659a88ffae842d91a64176a573e093221385a8b10af899299c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1778365593&direct=1

> F3 ainda tem `// TODO [CL]:` em pontos onde não pude confirmar do GitHub remoto:
> - agregações 30d (sell_lines join — provavelmente cache via job)
> - SellingPriceGroup multiplicador (decisão de schema com [W])
> - `MfgRecipe` caminho exato em `Modules/Manufacturing/Entities/`
> - permission middleware (`can:product.view`)

---

## Prompt pronto pra Claude Code (copiar TUDO daqui pra baixo)

```
Você é Claude Code no repo wagnerra23/oimpresso.com. Execute esta entrega de F1+F3 do módulo Produto sem perguntar nada.

1. git checkout main && git pull origin main
2. git checkout -b feat/prototipo-produto-f1

3. Baixe F3 (controller real + rota + página Inertia):
   curl -fL -o "app/Http/Controllers/ProdutoUnificadoController.php" "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/app/Http/Controllers/ProdutoUnificadoController.php?t=46f8f52a9de545659a88ffae842d91a64176a573e093221385a8b10af899299c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1778365593&direct=1"
   mkdir -p resources/js/Pages/Produto/Unificado
   curl -fL -o "resources/js/Pages/Produto/Unificado/Index.tsx" "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/resources/js/Pages/Produto/Unificado/Index.tsx?t=46f8f52a9de545659a88ffae842d91a64176a573e093221385a8b10af899299c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1778365593&direct=1"

4. Baixe e LEIA o patch de rotas, depois aplique manualmente em routes/web.php (raiz):
   curl -fL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/routes/web.php.patch.md?t=46f8f52a9de545659a88ffae842d91a64176a573e093221385a8b10af899299c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1778365593&direct=1" | tee /tmp/produto-routes.md
   # Aplique a rota Route::get('/products/unificado', ...) em routes/web.php seguindo as instruções do .md

5. Baixe F1 (protótipo Cowork):
   mkdir -p prototipo-ui/prototipos/produto
   curl -fL -o "prototipo-ui/prototipos/produto/Produto Unificado.html" "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/prototipos/produto/Produto%20Unificado.html?t=d1e51cf48227a302a5a15aa86b7534740b81338e5cb04337b418491e7d9be6cb.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1778362293&direct=1"
   curl -fL -o "prototipo-ui/prototipos/produto/produto-app.jsx" "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/prototipos/produto/produto-app.jsx?t=d1e51cf48227a302a5a15aa86b7534740b81338e5cb04337b418491e7d9be6cb.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1778362293&direct=1"
   curl -fL -o "prototipo-ui/prototipos/produto/produto-data.jsx" "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/prototipos/produto/produto-data.jsx?t=d1e51cf48227a302a5a15aa86b7534740b81338e5cb04337b418491e7d9be6cb.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1778362293&direct=1"
   curl -fL -o "prototipo-ui/prototipos/produto/produto-icons.jsx" "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/prototipos/produto/produto-icons.jsx?t=d1e51cf48227a302a5a15aa86b7534740b81338e5cb04337b418491e7d9be6cb.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1778362293&direct=1"

6. Confira se App\SellingPriceGroup, App\TransactionSellLine e Modules\Manufacturing\Entities\MfgRecipe existem; resolva imports do controller. Se algum nome divergir, ajuste e me reporte.

7. Dois commits separados:
   git add prototipo-ui/prototipos/produto/
   git commit -m "feat(prototipo-produto): F1 protótipo Cockpit V2"
   git add app/Http/Controllers/ProdutoUnificadoController.php resources/js/Pages/Produto/Unificado/ routes/web.php
   git commit -m "feat(produto): F3 ProdutoUnificadoController + Inertia page (UltimatePOS namespaces, TODOs marcados)"
   git push -u origin feat/prototipo-produto-f1

8. Abrir PR contra main com título 'feat(prototipo-produto): F1 + F3 Catálogo Unificado'. NÃO mergeie — Wagner aprova após F1.5 e F2.

Reporte: branch, número do PR, e qualquer TODO/import que precisou ajustar.
```
