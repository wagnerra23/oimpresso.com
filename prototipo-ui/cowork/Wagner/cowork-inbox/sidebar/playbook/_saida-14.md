---
sessao: "14"
titulo: CORPO · ícone por sub-tela (campo opcional no SidebarGhost)
autor: "[CL]"
data: 2026-09-25
base: wagnerra23/oimpresso.com@main (depois do #7960, thread 13)
---

# _saida-14 · Ícone da sub-tela

## Feito

Entregue **3 de 4** itens do "Faz". O item que falta tem a razão logo abaixo.

| # | o que | onde |
|---|---|---|
| 1 | `?string $icon = null` como 4º parâmetro opcional; validado em kebab-case; **só sai no `toArray()` quando declarado**. As chamadas existentes e o shape de 3 chaves não mudam | `app/Sidebar/SidebarGhost.php` |
| 2 | `icon?: string` no tipo do ghost | `resources/js/Components/cockpit/shared.ts` |
| 3 | `GHOST_ICON_MAP` + `findGhostIcon()`. Nome desconhecido devolve `null` (sem ícone, não cai no `Hash`) | `Sidebar.tsx` |
| 4 | preencher o ícone num DataController (Vendas) | **não feito**, ver abaixo |

- **Vocabulário do mapa:** os 39 nomes que o protótipo usa em ghosts (`data.jsx`, contados: `cash` 16 · `cog` 11 · `chart` 10 · …), cada um apontando para um Lucide. Reusei os ícones já importados e acrescentei 14.
- **Sem CSS novo:** `.sb-item` já é `flex` com `gap: 10px`, então o ícone ocupa o lugar dele sozinho.

## Não feito, e por quê

- **Preencher em Vendas.** A ficha diz *"o de Vendas, que tem mais ghosts"*. Medi e **não é assim**: os ghosts de Vendas não moram num DataController, e sim em `app/Http/Middleware/AdminSidebarMenu.php`:470, como array cru, e são só 2 (Catálogo QR, WooCommerce). E **nenhum DataController está no `prefixo`** desta thread (`SidebarGhost.php`, `shared.ts`, `Sidebar.tsx`, `tests/Feature/Sidebar/`). Pela Lei 1, não toquei. A prova de ponta a ponta ficou no teste de contrato: o ícone sai do `SidebarGhost` e chega no `SidebarMenuItem::toArray()['ghosts']`. O render cobre o outro lado. Qual módulo recebe ícone primeiro é decisão do Cowork/[W].
- **Prova `execucao` JSON:** o avaliador de recibo não foi portado (ADR 0397), como nas threads 10, 12 e 13.

## Recibos

- **Pré-condição:** `SidebarGhost.php` contém `?string $icon`.
- **Contrato (Pest)** `SidebarMenuItemContractTest.php`: +4 casos (sem ícone mantém 3 chaves · com ícone serializa a 4ª · ícone chega no item · ícone fora do kebab-case é recusado). **Esse arquivo não rodava em lane nenhuma**, e liguei ele na lane sqlite (é objeto de valor puro, sem banco). Sem isso, o "`--filter=Sidebar` com o teste de contrato" da prova não executava no CI. Não rodei local (sem PHP aqui); o veredito vem do CI do PR.
- **Render (vitest)** `tests/Feature/Sidebar/ghost-icone.spec.tsx`: 3 casos (com ícone · ícones diferentes · controle sem ícone e com nome desconhecido). **3/3** verdes, e junto com os vizinhos (ghosts-teto, item-ativo, rail) dá **17/17**. **Mordida:** com o `Sidebar.tsx` do main, **2 dos 3 falham**. O controle passa nos dois lados, como deve. Restaurei por cópia e o sha256 bateu (`268b67433172e05c`).
- **tsc:** a catraca `typecheck:baseline:check` fica sem regressão.

## Prefixo tocado

`app/Sidebar/SidebarGhost.php` · `resources/js/Components/cockpit/shared.ts` · `resources/js/Components/cockpit/Sidebar.tsx` · `tests/Feature/Sidebar/` · este `_saida-14.md`. Fora do prefixo, só `.github/ci-sqlite-pest.list` (para o teste de contrato rodar). Nada do `nao_toca` foi alterado.
