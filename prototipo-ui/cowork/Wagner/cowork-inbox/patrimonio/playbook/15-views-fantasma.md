---
sessao: "15"
titulo: as 8 chamadas de view que não têm arquivo
dono: "[CL]"
base: f8e6e02876fc
prefixo: "— (thread de medição: não escreve código)"
nao_toca: "*"
depende: "14"
---
# 15 · `view('assetmanagement::show')` — e não existe `show.blade.php`

## A · IDENTIDADE
- **âncoras (sítios medidos):** `AssetController.php:520` · `AssetAllocationController.php:371` · `AssetMaitenanceController.php:373` · `RevokeAllocatedAssetController.php:202` e `:213` · `AssetSettingsController.php:150`, `:228`, `:239`.
- **contra-âncora (a árvore de views, 17 arquivos):** a raiz de `Modules/AssetManagement/Resources/views/` tem **só** `index.blade.php` (**203 B**). Não há `show.blade.php`, `create.blade.php` nem `edit.blade.php` na raiz — os create/edit reais moram em `asset/`, `asset_allocation/`, `asset_maintenance/`, `asset_revocation/` e `settings/`.

## B · NÃO INVENTAR
- **Não criar view nenhuma.** Esta thread **mede**; criar `show.blade.php` "pra não quebrar" seria fabricar tela sem charter, sem caso e sem [W].
- **Não concluir por leitura de árvore.** Árvore ausente é indício forte, não veredito: `View::exists`, namespace do módulo (`ServiceProvider::loadViewsFrom`) e `view.paths` podem resolver noutro lugar. **Rodar o oráculo**, não deduzir.
- **Vazio só é evidência se a medição aconteceu** (§5). Comando que falha = `não medido`, nunca `não existe`.

## C · O QUE MEDIR (por sítio)
1. **A view resolve?** `php artisan tinker` → `View::exists('assetmanagement::show')`, idem `::create`, `::edit`. Um comando, três respostas.
2. **O sítio é alcançável?** Cada um é um método de `Route::resource` (`show`/`create`/`edit`) — logo tem rota por construção. Medir com HTTP real (Pest/feature) na rota correspondente e registrar o **status**: 200 · 302 (guarda de permissão antes do render) · 500 (`View not found`).
3. **Se 500:** há guarda antes que torne o caminho inalcançável na prática? (`can(...)`, `abort`, redirect). Guarda que sempre nega ⇒ defeito latente, não incidente.
4. **`RevokeAllocatedAssetController:213`** (`::edit`) é caso especial: a pasta `asset_revocation/` tem `create` e `index`, **não** tem `edit` — dois motivos possíveis de falha no mesmo sítio.
5. **`AssetSettingsController`** concentra 3 dos 8 (`:150` create, `:228` show, `:239` edit) e é a tela que já migrou o `index` (`:92`). Medir se o Blade restante é morto ou vivo muda o tamanho da thread `20`.

## D · COMO VALIDAR
1. `_saida-15.md` com **uma linha por sítio**: arquivo · linha · view chamada · `View::exists` · status HTTP medido · veredito (`quebra` / `inalcançável` / `resolve noutro path`).
2. **Caso de sanidade obrigatório antes de qualquer veredito** (§5-bis): medir também **uma** view que você sabe existir (`assetmanagement::asset.index`, 11.242 B) e mostrar que o mesmo instrumento devolve `true`/200. Sonda que só devolve vermelho não mediu nada.
3. Zero arquivo de app alterado (`git diff --stat` só com `_saida-15.md`).
4. Se algum sítio for **quebra alcançável**, ele vira **thread própria** com número novo no patch do índice — não se conserta aqui, e não se junta à `16`.
5. PLACAR no corpo do PR.

## 4-ter · EXECUÇÃO
- **ARQUIVOS A EDITAR:** nenhum de app. Só `_saida-15.md`.
- **REUSAR:** `SmokeRoutesTest.php` do módulo como forma de exercitar rota (ele já tem 7 casos verdes e serve de baseline; **não** somar caso novo aqui — medição não vira teste antes do veredito).
- **CRIAR:** nada.
- **NÃO TOCAR:** `*`.
- **PASSO A PASSO:** 1) `View::exists` nos 3 nomes + o controle positivo · 2) HTTP nos 8 sítios · 3) tabela no `_saida` · 4) declarar o que **não** foi medido.
- **DADO:** nenhum.
- **PARAR SE:** o ambiente não subir e você só tiver a árvore — aí o veredito é **`não medido`** e a thread fecha assim, com o motivo. Melhor um `não medido` honesto que um "não existe" deduzido.

## PRÉ / PÓS
- **antes:** 8 sítios chamando 3 nomes de view que não aparecem na árvore; ninguém sabe se são 500 ou código morto.
- **depois:** veredito por sítio, com instrumento validado por controle positivo.
- **quebra:** se já existe `_saida-15.md`, **não execute** — leia e reporte.

## PROVA
`_saida-15.md` com 8 linhas + o caso de sanidade · zero diff em `Modules/` e `resources/js/`.
