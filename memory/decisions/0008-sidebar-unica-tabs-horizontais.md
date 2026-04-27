# ADR 0008 — Sidebar com 1 item + menu horizontal em abas dentro do módulo

> ⚠️ **DEPRECADO em 2026-04-27** — substituída por [ADR raiz 0039](./0039-ui-chat-cockpit-padrao.md) e [ADR UI-0008](../requisitos/_DesignSystem/adr/ui/0008-cockpit-layout-mae-do-erp.md).
>
> Esta ADR foi escrita pro AppShell legado (sidebar única vertical AdminLTE-like). O ERP completo agora vive dentro do **Cockpit** — sidebar é dual Chat↔Menu, módulos vêm do `shell.menu` real, módulos administrativos (Backup/CMS/Connector/Office Impresso) ficam no rodapé separados, e tabs horizontais foram absorvidos pela coluna principal contextual.
>
> Não usar este padrão pra telas novas. Mantida como referência histórica de quando o Ponto WR2 vivia isolado dentro do UltimatePOS.

**Status:** ⚠️ Superseded by ADR 0039 + UI-0008 (2026-04-27)
**Status anterior:** ✅ Aceita
**Data:** 2026-04-18

## Contexto

O UltimatePOS já tem muitos módulos instalados no cliente típico da WR2: Vendas, Estoque, Compras, Essentials & HRM, Conector, Superadmin, etc. A sidebar já é longa. Se o Ponto WR2 adicionar suas 10 sub-seções (Dashboard, Espelho, Aprovações, Banco de Horas, Escalas, Importações, Relatórios, Colaboradores, REPs, Configurações), cria 10 itens novos na sidebar.

A Eliana foi explícita: *"deve conter apenas 1 item no meu [menu], e ter um meu [menu] no topo com os itens do ponto, meu ultimatepos je tem muitos mudulos"*.

## Decisão

**Na sidebar global do UltimatePOS, o módulo ocupa exatamente 1 item:** "Ponto WR2" com badge de aprovações pendentes.

**Ao clicar no item, abre uma página com menu horizontal em abas logo abaixo do topbar** com as 10 sub-seções. A aba ativa tem sublinhado azul e texto em `text-blue-600` (estética shadcn).

## Consequências

### Positivas

- Sidebar enxuta, contexto preservado
- Escalável: se o módulo crescer, adicionamos abas horizontais em vez de lotar sidebar
- Padrão comum em SaaS modernos (Stripe, Linear) — familiar a usuário corporativo
- Respeita preferência explícita da cliente

### Negativas

- Exige componente de menu próprio (não padrão AdminLTE puro)
- Em mobile, menu horizontal pode precisar scroll horizontal ou virar dropdown

### Implementação

Layout Blade `Resources/views/layouts/module.blade.php` renderiza o menu horizontal, detecta aba ativa via `request()->route()->getName()`. Em produção com AdminLTE, pode virar um componente reutilizável.
