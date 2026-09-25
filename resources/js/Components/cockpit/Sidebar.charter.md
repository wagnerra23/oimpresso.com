---
id: resources-js-components-cockpit-sidebar-charter
page: n/a — componente do Shell, aparece em TODAS as rotas (não tem rota própria)
component: resources/js/Components/cockpit/Sidebar.tsx
related_prototype: prototipo-ui/cowork/Wagner/sidebar.jsx
module: _Shell
status: draft
created: 2026-09-25
owner: wagner
related_adrs: [0094-constituicao-v2-7-camadas-8-principios, 0114-prototipo-ui-cowork-loop-formalizado, 0180-sidebar-v3-5-grupos-ghosts-header]
related_ui_adrs: [0013-constituicao-ui-v2-camadas, 0023-sidebar-dark-fixo-preto-definitivo-supersede-0019, 0029-prototipo-soberano-sobre-adr-ui, 0030-sidebar-auto-rail-responsivo]
prototypes: [prototipo-ui/cowork/Wagner/sidebar.jsx]
contract: governance/design/contracts/cockpit-sidebar.contract.json
---

# Charter — `Sidebar` (camada Shell da Constituição UI v2)

> ⚠️ **Este charter NÃO é resolvido pela máquina de âncora nem coberto por gate.** Medido em
> 2026-09-25: `scripts/design/ancora.mjs` e `casos-coverage-guard.mjs` varrem só as raízes de
> `Pages/` (`raizesDePages()`). `ancora.mjs cockpit/Sidebar` → "sem charter". O charter irmão
> `resources/js/Layouts/AppShellV2.charter.md` tem a mesma condição. Estender a máquina é
> outra thread (`_saida-16.md`). Quem defende cada UC hoje são os **testes** citados em
> [`Sidebar.casos.md`](Sidebar.casos.md), não um gate.

## Mission

Ser o menu único do ERP, na coluna esquerda do shell: achar qualquer tela em até 2 cliques,
dizer onde o usuário está e dar acesso à conta (empresa, presença, aparência, sair). A forma
vem do protótipo `prototipo-ui/cowork/Wagner/sidebar.jsx`, soberano no eixo FORMA
([UI-0029](../../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)).

## Regiões (as âncoras `data-contract` do contrato de tela)

| Região | Âncora | O que carrega |
|---|---|---|
| Modos | `sb-modos` (no `aside.sb`, AppShellV2) | `expanded` · `rail` · `hidden` |
| Topo | `sb-topo` | seletor de empresa + atalhos de topo (IA, Visão geral, Atendimento) |
| Corpo | `sb-corpo` | grupos → itens single-link → sub-telas (ghosts) do item ativo |
| Alças | `sb-alcas` | recolher/expandir + "Mostrar sidebar" no modo oculto |
| Rodapé | `sb-rodape` | menu da conta (perfil, presença, aparência, modo de trabalho, Buscar tela, atalhos, sair) |

## Regras de forma e dado

- **Preta (dark-fixo) nos dois temas** ([UI-0023](../../../../memory/requisitos/_DesignSystem/adr/ui/0023-sidebar-dark-fixo-preto-definitivo-supersede-0019.md)). Só tokens `--sb-*`, sem cor crua.
- **Item é single-link** ([ADR 0180](../../../../memory/decisions/0180-sidebar-v3-5-grupos-ghosts-header.md)): sem `Menu::dropdown` com sub-itens. As sub-telas aparecem como ghosts **só sob o item ativo**, com teto `GHOST_TETO = 5`.
- **Agrupamento é do frontend** (`SIDEBAR_GROUPS`). O backend publica `group` por item no contrato v2 (`app/Sidebar/*`); grupo cross-módulo no `AdminSidebarMenu.php` é proibido.
- **Contadores e presença vêm do servidor** (`shell.sidebar_counts`, `auth.user.ui_presence`). Nada de mock no vivo.
- **Estado de UI persistido só por escolha do usuário:** modo manual (`oimpresso.sb.mode`), grupos abertos. O que a tela deriva (grupo aberto por conter a rota atual) **não** é gravado.

## Non-Goals

_[W] preenche. Os itens abaixo são decisões já registradas em ficha/ADR, não inferência._
- ❌ Consumir a presença em Atendimento/Equipe nesta camada (thread 13: "só grava e mostra")
- ❌ Portar instrumentos do protótipo: `WipMark`, `podeVer(papel)`, `MOCK.SIDEBAR_*`
- ❌ Aba Chat/conversas na sidebar (removida de propósito, [UI-0011](../../../../memory/requisitos/_DesignSystem/adr/ui/0011-sidebar-single-pane-cascata-user-menu.md) single-pane)

## Automation Anti-hooks

_[W] preenche._

## Pest GUARD

Nenhum Pest GUARD derivado deste charter ainda. A defesa atual está em `Sidebar.casos.md`.
