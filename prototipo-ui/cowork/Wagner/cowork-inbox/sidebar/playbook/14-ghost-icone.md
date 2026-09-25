---
sessao: "14"
titulo: CORPO · ícone por sub-tela (campo opcional no SidebarGhost)
dono: "[CL]"
criado: 2026-09-25
base: wagnerra23/oimpresso.com@main (árvore 034e476895cb · lido 2026-09-25 13:48 UTC)
onda: 2 — protótipo → vivo · decisão [W] 2026-09-25 ("pode fazer esses")
---

# 14 · Ícone da sub-tela

## Abertura
Sessão limpa · `/onda sidebar --thread NN` · ler só esta ficha + o objeto dela no §7 + as âncoras abaixo, **relidas no main**. Escreve `Sidebar.tsx` → serial com 08–12 (Lei 1).

## Decisão
[W] 2026-09-25: sub-tela ganha ícone, como no protótipo. Resolve RESIDUO-7. Depende da **10** (lista de ghosts reescrita lá).

## Alvo — protótipo
`sidebar.jsx` → `GhostList` renderiza cada ghost pelo `ItemRow`, com o ícone declarado no dado (`data.jsx`: `{ id, icon, label }`). Sem ícone → linha sem ícone (não quebra).

## Âncora — vivo
- `app/Sidebar/SidebarGhost.php` (1.527 B, lido inteiro): construtor `key/label/href` + `toArray()` com as 3 chaves. **Acrescentar `?string $icon = null`** como 4º parâmetro opcional — chamadas existentes não mudam.
- `resources/js/Components/cockpit/shared.ts` → `ShellMenuItem.ghosts`: `Array<{ key?; label; href }>` → `+ icon?: string`.
- `Sidebar.tsx` → ghost em `SidebarMenuItem`: resolver o nome por um mapa Lucide pequeno, no mesmo estilo de `MENU_ICON_MAP`/`findMenuIcon`; nome desconhecido → **sem ícone** (não cair em `Hash`, que é ruído numa sub-lista).
- `tests/Feature/Sidebar/SidebarMenuItemContractTest.php` trava o shape — **atualizar junto**, nunca contornar.

## Faz
O campo opcional nos 3 lugares + render. Preencher o ícone em **um** DataController como prova de ponta a ponta (o de Vendas, que tem mais ghosts); os demais entram quando cada módulo quiser.

## Prova
- pré-condição: `SidebarGhost.php` contém `?string $icon`.
- fecha: `execucao` — `--filter=Sidebar` com o teste de contrato atualizado + teste de render (ghost com e sem ícone).

## Fechar
`_saida-14.md` e PARE.
