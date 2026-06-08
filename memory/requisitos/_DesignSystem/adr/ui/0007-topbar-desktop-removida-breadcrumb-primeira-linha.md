# ADR UI-0007 · Topbar desktop removida, breadcrumb vira a primeira linha

> ⚠️ **PARCIALMENTE DEPRECADA em 2026-04-27** — superseded by [UI-0008 Cockpit layout-mãe](0008-cockpit-layout-mae-do-erp.md).
>
> No **Cockpit** (layout-mãe do core ERP), a topbar **volta a existir** porque agora tem função real: breadcrumb dinâmico + ações contextuais (phone/info/more no chat, toggle Apps Vinculados). Não é mais a topbar redundante de antes.
>
> Esta ADR continua válida pro **AppShell legado** que sobrevive em telas administrativas isoladas (`/showcase/components`, `/modulos`, settings superadmin) — onde topbar continua sem função e a remoção +48px de altura útil ainda vale.

- **Status**: accepted (escopo: AppShell legado) · superseded for Cockpit by UI-0008
- **Data**: 2026-04-24
- **Decisores**: Wagner, Claude

## Contexto

O AppShell tinha uma `<header>` fixa de 48px (`h-12`) com apenas 3 elementos:
- `<MenuIcon>` hamburger (visível só em mobile via `md:hidden`)
- `<div className="flex-1" />` spacer vazio
- `<UserQuickMenu />` avatar dropdown no canto direito

No desktop, essa barra tinha só o avatar — que **também já aparece no rodapé da sidebar** (avatar + nome + email + theme toggle + logout). Completa redundância.

Wagner na revisão do showcase (2026-04-24):

> "acho que pode remover o topo? vai ser aproveitado para alguma coisa? deixe o breadcrumb, aprovei pode ser o padrão sim"

## Decisão

Condicionar o `<header>` da topbar a `md:hidden`. Consequências:

- **Desktop (>=md):** não existe topbar. Primeiro elemento abaixo da sidebar é o `ModuleTopNav` (se o módulo tiver) → depois breadcrumb → depois main. Ganho: +48px de altura útil em toda tela desktop.
- **Mobile (<md):** topbar mantém, porque é lá que mora o hamburger pra abrir o Sheet drawer da sidebar. Sem topbar no mobile, o user não tem como acessar o menu.

Breadcrumb fica como "cabeçalho leve" (12px text-xs) sempre presente.

## Consequências

**Positivas:**
- Densidade de informação maior no desktop — especialmente relevante em monitor pequeno (cliente ROTA LIVRE usa ~1280px).
- Menos elementos redundantes reduz ruído visual.
- Dark mode não precisa gerenciar mais uma barra.

**Negativas:**
- Se um dia precisar de "busca global" ou "notificações" no topo, volta a precisar de topbar — mas aí seria topbar útil, não vazia.
- Usuário que estava acostumado a clicar no avatar no topo desktop agora precisa ir pro rodapé da sidebar.

## Alternativas consideradas

- **Manter topbar com algo útil**: rejeitado sem necessidade real. "Algo útil" viria depois.
- **Remover totalmente (inclusive mobile)**: rejeitado — mobile precisa do hamburger.
- **Mover hamburger pro breadcrumb row no mobile**: considerado, rejeitado — misturaria responsabilidades (breadcrumb é navegação, hamburger é menu) e teria que reflow em desktop.

## Implementação

Diff mínimo em `resources/js/Layouts/AppShell.tsx`:

```diff
-          <header className="flex h-12 items-center gap-3 border-b border-border bg-background px-4">
+          {/* Mobile-only header: hamburger + avatar no canto.
+              Desktop: hidden — sidebar + breadcrumb já cobrem navegação. */}
+          <header className="md:hidden flex h-12 items-center gap-3 border-b border-border bg-background px-4">
             <Sheet open={mobileOpen} onOpenChange={setMobileOpen}>
               <SheetTrigger asChild>
-                <Button variant="ghost" size="icon" className="md:hidden" aria-label="Abrir menu">
+                <Button variant="ghost" size="icon" aria-label="Abrir menu">
                   <MenuIcon size={18} />
```

Commit: `8ff3d67a`.

## Validação

Testado em:
- Desktop 1440×900 dark mode — breadcrumb "Design System / Showcase" na primeira linha, conteúdo sobe 48px ✅
- Desktop 1440×900 light mode ✅
- Mobile 375×667 — hamburger + avatar aparecem, sidebar drawer abre ✅ (teste manual pendente em tela real, browser resize ok)

## Reversão

Se algum dia precisar voltar:
1. Remover `md:hidden` da `<header>` no AppShell
2. Re-adicionar `className="md:hidden"` no Button do hamburger (pra não duplicar no desktop)
3. Atualizar esta ADR com status `superseded by 00XX`

Zero regressão funcional.
