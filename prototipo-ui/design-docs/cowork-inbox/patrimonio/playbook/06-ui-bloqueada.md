---
sessao: "06"
titulo: A UI inteira — 46 arquivos travados em uma pergunta
dono: "[W]"
base: cb475c0ca2f4
prefixo: — (nenhum)
nao_toca: resources/js/Pages/**
depende: D-ENDERECO
---
# 06 · UI — BLOQUEADA

## O estado, medido hoje
- `resources/js/Pages/`: busca `(?i)(patrimonio|asset)` = **0 de 794 arquivos**.
- `Modules/AssetManagement`: **0 `Inertia::render`**. As 6 rotas são `Route::resource` Blade.
- `memory/requisitos/Patrimonio/SCOPE.md`: **`migracao_ui: bloqueado-escopo`**.

Nenhuma tela React existe — e o `main` **diz por escrito** que não deve existir ainda.

## A pergunta que trava (D-ENDERECO)
**Patrimônio é módulo próprio (`Pages/Patrimonio/**`) ou seção do Estoque (`Pages/Estoque/Patrimonio/**`)?**
ADR 0180 chama Patrimônio de "ghost de Estoque" · ADR 0182 escreve "Estoque (AssetManagement+)" · o SCOPE não decide. **Errar o endereço custa refazer 12 arquivos**, porque muda import, rota, breadcrumb, sidebar e o caminho dos charters.

## O que está travado
7 Pages + 7 charters + 7 casos + 2 `_shared` + 6 `_components` (**29**) · 7 `.contract.json` · 6 controllers → Inertia + `Routes/web.php` · 3 testes de tela · ADR do endereço + `SCOPE.md` = **46 arquivos**.
Os outros 20 do módulo **não dependem disto** — são as threads 01–05.

## O que NÃO fazer
- Não criar `Pages/Patrimonio/Index.tsx` "pra adiantar": Page sem rota é órfã, e se o endereço for Estoque, é retrabalho garantido.
- Não converter controller pra Inertia antes da ADR — a rota muda junto com o endereço.
- Não usar o protótipo `patrimonio-page.jsx` como autorização: ele é **alvo**, não decisão. Ter desenho não é ter endereço.

## Prova
Respondida a D-ENDERECO, esta thread se reescreve como **frente**: 5–7 threads, uma por tela, cada uma com sua ficha do §13.2 — 7 telas não cabem numa thread só. Até lá, `bloqueada`, e **não conta como pendência do Code**.
