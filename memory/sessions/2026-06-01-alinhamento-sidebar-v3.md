# Sessão 2026-06-01 — Alinhamento do Sidebar Cowork → modelo v3 do repo · [CC]

**Pedido [W] (chat):** comparar sidebar real vs meu → "sim eu quero o alinhamento, proponha o plano, quero como está hoje ativo, não mude o repo real, páginas soltas no MAIS, mantenha fiel ao git." → "faça".

## Diagnóstico (comparação real vs meu)
Meu `data.jsx` era foto pré-ADR-0180: 10 grupos ad-hoc (OFFICEIMPRESSO/VERTICAIS/OUTROS/INTEGRAÇÕES...), chat embebido, tudo hardcoded. Real (`Sidebar.tsx` v3): 8 grupos canon ordenados + MAIS, shortcuts topo (IA/Equipe/Atendimento), ghosts no PageHeader, config no rodapé, backend-driven, visibilidade por módulo.

## O que foi feito (F1, Cowork — fiel ao git, repo intocado)
- **`data.jsx`** — MENU reescrito: 3 shortcuts topo + 8 grupos canon em ordem fixa (CADASTRO→COMERCIAL→FINANÇAS→FISCAL→PRODUÇÃO→ESTOQUE→RH→SISTEMA) + **MAIS** (19 páginas soltas, fechado por default). Ghosts via attr `ghosts:[]` nos hubs (Vendas: Orçamentos/WooCommerce/Portal OS · Financeiro: fluxo/concil/dre/pcontas · Cobrança: gateways). `USER_MENU` (Preferências/Usuários/Admin/Superadmin) → rodapé. `flattenMenu()`/`MENU_FLAT` p/ roteamento. `GROUP_META` realinhado (9 grupos, hue/ícone).
- **`sidebar.jsx`** — default-open canon (MAIS fechado); ghosts renderizam **indentados sob o hub, só quando o hub está ativo** (contextual, subordinado) no menu expandido + flyout rail; UserMenu do rodapé renderiza `USER_MENU` roteando via `window.__selectRoute`.
- **`app.jsx`** — lookups → `MENU_FLAT` (ModuleStub, Header, handleSelectRoute); Header ganhou render de ghosts (inerte: Header é dead-code neste shell, pages têm header próprio — por isso ghosts vivem no sidebar); `window.__selectRoute` exposto.

## Decisões / desvios honestos
- **Ghosts no sidebar, não no PageHeader:** o `Header` do shell **não é montado** (cada página tem chrome próprio) → pôr ghost lá deixaria órfão. Desvio do repo (que usa PageHeader) **a favor de alcance**; representação fiel ao espírito (subordinado ao hub, contextual). Documentado.
- **Tarefas** saiu do topo (repo removeu shortcut) → MAIS. **VERTICAIS** deixou de existir (Oficina→COMERCIAL, Repair→PRODUÇÃO, Vestuário→MAIS). **Repo real intocado** (sou read-only).

## Verificação
- 58 itens em `MENU_FLAT`, **missingFromFlat=[]** (zero órfão). Ordem canon ok. `window.__selectRoute`=function. Ghosts resolvem com `ghostOf`+group. Console limpo (só warnings Tailwind/Babel). Screenshots: sidebar-vendas, sidebar-ghosts, sidebar-mais-footer.

## Residual
- Fidelidade total (ghosts no PageHeader) exigiria hub pages adotarem um PageHeader compartilhado — fora de escopo; anotado.
- Charter de Sidebar inexistente — candidato a 1º uso do par CONTEXTO+FRESCOR (pegaria a deriva no futuro).

## Refs
- `data.jsx` · `sidebar.jsx` · `app.jsx` · `Sidebar.tsx`@main (ADR 0180/UI-0011) · screenshots/sidebar-*.png
