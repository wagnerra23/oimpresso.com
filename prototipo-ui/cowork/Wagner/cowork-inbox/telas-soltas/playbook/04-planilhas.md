---
sessao: "04"
titulo: Planilhas em Inertia
dono: "[CL]"
base: 5d9da472e955
---
# 04 · Planilhas — Blade → trio, forma do protótipo

**Alvo de forma:** `planilhas-page.jsx` (rotas `planilhas` e `planilha-nova` do protótipo): árvore de pastas recolhível, linha por planilha com "editada há", autor, compartilhamento e "Abrir" + kebab; cabeçalho com "Nova pasta" e "Criar planilha".
**Âncora:** `Modules/Spreadsheet/Http/Controllers/SpreadsheetController.php` (23.219 B) + `Services/SpreadsheetService.php` + rotas `Modules/Spreadsheet/Routes/web.php` (resource `sheets`, `add-folder`, `move-to-folder`, share). Views a substituir: `Resources/views/sheet/{index,create,show}.blade.php`.

1. Sessão própria por tela: **Index** primeiro (lista + pastas). `show` (editor) fica para 04b — o editor é JS de terceiro, não se reescreve na mesma onda.
2. Rota de fuga = Blade, atrás de flag (padrão MWART F5: aviso + canary 7d).
3. Multi-tenant: `MultiTenantIsolationTest` já existe — tem de seguir verde.

**Não verificado:** se o `retention.spreadsheet.php` tem executor. Fora do escopo — não tocar.
