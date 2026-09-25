---
sessao: "16"
titulo: ÂNCORA · charter + casos do Sidebar
autor: "[CL]"
data: 2026-09-25
base: wagnerra23/oimpresso.com@main 0eba535ff6d0
---

# _saida-16 · Charter do Sidebar

## Resultado: arquivos entregues; prova da máquina NÃO fecha (parada prevista pela ficha)

A ficha diz: *"Se `ancora.mjs` só resolve charter em `Pages/`, não mover o arquivo pra lá:
registrar no `_saida` e parar — a mudança é na máquina."* Foi isso que aconteceu.

## Feito

| arquivo | conteúdo |
|---|---|
| `resources/js/Components/cockpit/Sidebar.charter.md` | Mission · as 5 regiões `data-contract` do contrato · regras de forma e dado (preta, single-link + teto de ghosts, agrupamento no front, dado do servidor, o que persiste) · Non-Goals **só** com decisões já registradas (thread 13, instrumentos do protótipo, UI-0011) · Anti-hooks vazio para o [W] |
| `resources/js/Components/cockpit/Sidebar.casos.md` | **13 UCs** (`UC-SB-01..13`): corpo, rail/modos, topo, rodapé |

Os vereditos dos casos são **medidos, não afirmados**. Rodei as 9 specs vitest da sidebar:

- **9 UCs ✅**: cada um nomeia o arquivo de teste que passou.
- **1 🧪 por ponteiro** (UC-SB-08): largura e auto-rail já estão em `AppShellV2.casos.md`. Não dupliquei.
- **2 ⬜**: UC-SB-02 (Pest não rodado aqui) e UC-SB-09 (modo oculto: não há teste de comportamento).
- **1 ⏳**: UC-SB-06, ícone da sub-tela (thread 14).

Nenhum teste cita os ids novos. O arquivo declara isso no topo, para que a existência dele não seja lida como cobertura de gate.

## Prova — medida, e falha

| prova da ficha | resultado |
|---|---|
| `node scripts/design/ancora.mjs cockpit/Sidebar` | ✗ `sem charter pra essa tela` (também com o path completo) |
| `pedido.mjs --tela cockpit/_sidebar --secao sb-corpo` | `NÃO MEDI: sem charter` |

**Causa (lida no código):** o `ancora.mjs` monta a lista de charters por
`raizesDePages(repoRoot)` (`scripts/qa/page-path.mjs:77`), que devolve só
`resources/js/Pages` e `Modules/*/Resources/js/Pages`. `Components/` e `Layouts/` ficam de fora. O
charter irmão `resources/js/Layouts/AppShellV2.charter.md` tem a mesma condição: `ancora.mjs
AppShellV2` também devolve "sem charter".

**O que falta, e é de outra thread (máquina, não `.md`):** dar ao `ancora.mjs` uma raiz extra para charters de Shell (`Components/cockpit/`, `Layouts/`), sem mexer no `raizesDePages`. Ele é consumido por `casos-coverage-guard` e por outros, e ampliar a função mudaria o denominador de gates required. Proponho uma thread nova no índice, com o Cowork decidindo.

## Descobertas

1. **Regressão da thread 13 pega aqui:** ao medir os casos, 6 de 49 specs da sidebar estavam
   vermelhas no `main`. O `SidebarUserMenu` do #7960 chamava `usePage()` direto e quebrava fora
   do Inertia. Consertei no **#7962** (49/49).
2. **As specs vitest da sidebar não rodam em nenhuma lane de CI**, e foi por isso que o #7960
   mergeou verde. Abri a tarefa à parte.
3. **O `AppShellV2.charter.md` ficou desatualizado.** O Non-Goal *"❌ Um terceiro modo `hidden`"*
   ficou falso depois da thread 04 (#7209), que portou o `hidden` e a `SidebarReopenHandle`. Está
   fora do meu prefixo, então não mexi, e fica registrado.

## Prefixo tocado

`Sidebar.charter.md` · `Sidebar.casos.md` · este `_saida-16.md`. Nada do `nao_toca` foi alterado. O `Sidebar.tsx` foi mexido só no #7962, que é outro PR.
