---
sessao: "04"
titulo: Modo `hidden` + SidebarReopenHandle — promover do bundle Cowork pro shell
dono: "[CL]"
base: af09f7c3a0fd
prefixo: resources/js/Components/cockpit/Sidebar.tsx · Components/cockpit/shared.ts · resources/js/Layouts/AppShellV2.tsx · resources/css/cockpit.css
nao_toca: Pages/Financeiro/_cowork-bundle/** · Components/cockpit/useSidebarShortcut.ts · app/Sidebar/** · AdminSidebarMenu.php
depende: 01 (a11y do alvo corrigida antes de exportar) · RESÍDUO-3
---
# 04 · Modo `hidden` + alça de reabrir

## A · Por que esta thread existe
É **o único delta F1→F3 do pedido de 2026-08-28 que sobreviveu à releitura do `main`**. Medido nesta sha: `SidebarMode` em `Components/cockpit/shared.ts` só admite `'rail' | 'expanded'`; `AppShellV2` inicializa e alterna apenas entre esses dois; o único uso vivo de `SidebarReopenHandle` está em `resources/js/Pages/Financeiro/_cowork-bundle/shell-app.jsx:509-510` — ou seja, **só o Financeiro tem volta quando a sidebar some**.
(Nota de honestidade: a busca global por `SidebarReopenHandle` voltou **bounded** — o veredito de ausência no shell vem de ler `AppShellV2.tsx` e o tipo, não do zero-match.)

## B · Alvo (o protótipo responde *como*)
`prototipo-ui/cowork/sidebar.jsx:590-601` + `app.jsx:660-673, 950-962` + `styles.css:5219-5245`:
- `SidebarReopenHandle` = `button.sb-reopen-handle`, `title="Mostrar sidebar (⌘⇧\\)"`, `aria-label="Mostrar sidebar"`, chevron 12px `currentColor` stroke 2.2 — **um ícone e um nome acessível**, não um `div`.
- CSS: `position: fixed`, `top: 50%`, largura que cresce de ~20px pra 26px no hover, sombra `2px 0 8px oklch(0 0 0 / .06)`.
- No mobile (≤768px) a alça é escondida junto com `.sb-collapse-handle` — no drawer ela não faz sentido (`styles.css:5266-5267`).
- Atalho: **⌘⇧\\** alterna `hidden`; **⌘\\** (já vivo) alterna `rail`.

## C · Âncora (o `main` responde *onde e com que dado*)
1. `shared.ts` — `SidebarMode` ganha `'hidden'`. Cuidado: `LS.SB_MODE` pode ter valores antigos; a leitura já filtra por lista, **acrescentar `hidden` à lista**, não trocar o parse.
2. `AppShellV2.tsx` — `<aside className="sb">` deixa de montar quando `hidden` (o `main` ocupa a largura toda), e `{sidebarMode === 'hidden' && <SidebarReopenHandle onOpen={() => chooseSidebarMode('expanded')} />}`. `hidden` **nunca** vem do auto-rail (UI-0030 decide só entre rail/expanded) — é escolha manual.
3. `Sidebar.tsx` — exportar `SidebarReopenHandle` ao lado de `CompanyPicker`/`SidebarMenu`/`SidebarFooter`. **Não** copiar do bundle do Financeiro: portar do protótipo já corrigido pela thread 01.
4. `cockpit.css` — `.sb-reopen-handle` no bloco `Sidebar — DARK FIXO`, com os tokens `--sb-*`. Sem cor crua.
5. O `useEffect` do ⌘\\ em `AppShellV2` ganha o ramo `e.shiftKey` → `hidden`, e **não** dispara dentro de `input`/`textarea`/`contenteditable` nem colide com ⌘K.
6. Mobile: em `isMobile` o modo renderizado continua `expanded` (drawer) — `hidden` não se aplica.

## D · Regras duras
- Sidebar **PRETA** nos dois modos (UI-0023). `.sb-item.is-open` não clareia.
- Um `<main>` por documento (AP9) · chain de overflow (AP10) — remover a `<aside>` não pode quebrar o grid do `.cockpit` (é grid de 3 colunas: Sidebar 260 + Main 1fr + LinkedApps 320: conferir o que acontece com a 1ª coluna vazia).
- Nada de `Menu::dropdown` nem grupo cross-módulo — esta thread não toca no menu.

## Execução
```
ARQUIVOS A EDITAR : shared.ts (tipo + lista de modos) · AppShellV2.tsx (render + atalho) · Sidebar.tsx (export do handle) · cockpit.css (.sb-reopen-handle)
REUSAR            : chooseSidebarMode · LS.SB_MODE · .sb-collapse-handle (mesma família visual) · tokens --sb-*
PASSO A PASSO     : 1) gh pr list --state open × estes 4 arquivos  2) tipo  3) render + atalho  4) CSS
                    5) grid do .cockpit sem a aside  6) mobile intocado  7) gates  8) _saida-04.md
PARAR SE          : (a) RESÍDUO-3 sem resposta  (b) remover a <aside> deixar coluna fantasma no grid → reportar antes de "consertar" o grid
                    (c) o bundle do Financeiro passar a divergir → NÃO editar o bundle nesta thread; anotar
```

## Prova (o que o PLACAR confere no `main`)
- `shared.ts` com `hidden` em `SidebarMode` · `AppShellV2.tsx` contendo `SidebarReopenHandle` · `cockpit.css` contendo `.sb-reopen-handle`.
- Gates: `php artisan test --filter=Sidebar` · `--filter=Cockpit` · `--filter=AppShellUsageGate` verdes; `SidebarMenuItemContractTest` **não** deve nem precisar mudar (esta thread não mexe no shape do item) — se mudar, é sinal de escopo estourado.
- `_saida-04.md` com os 5 itens + sha lida.
- Não verificável daqui: T7 `design-diff --compare --check` · screenshot prod dark 1280 ([W2]).
