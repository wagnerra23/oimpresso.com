---
thread: "03"
modulo: shell-usermenu
dono: "[CL]"
prefixo: resources/js/Components/cockpit/Sidebar.tsx
base: remedir antes de escrever
---
# 03 · "+ Adicionar empresa" — `role="menuitem"` sem ação

## Problema
O rodapé do switcher de empresa traz **+ Adicionar empresa** com `role="menuitem"` e **sem handler**, nos **dois** dropdowns (sidebar expandida e rail). Item anunciado ao leitor de tela como acionável e que não aciona é defeito de a11y, não só de UI.

"Empresa" aqui é **business** (o multi-tenant do UltimatePOS — o `biz=164` do Martinho), então o destino natural é a administração de negócios (**Superadmin › Negócios**), não uma tela nova.

## O que fazer
1. Navegar pro destino de business que **já existe** no app (confirme o nome da rota no `main` — não deduza do protótipo).
2. Se o usuário logado **não** puder administrar business: o item vira `disabled` com motivo (`aria-disabled` + título curto), em vez de navegar pra um 403.
3. Corrigir nos **dois** dropdowns. Eles são código separado no arquivo — é fácil consertar um e achar que acabou.

## Prova
- `npm run lint && npx tsc --noEmit` → exit 0.
- Runtime: clicar navega (ou está desabilitado com motivo) — **conferido nos dois modos da sidebar**.
- **Varredura:** nenhum outro `role="menuitem"` sem ação sobra nos dropdowns do arquivo. Conserte a classe do defeito, não a instância.

## Parar se
- A rota de business exigir permissão que o menu não conhece → pare na opção 2 (disabled com motivo) e reporte.
