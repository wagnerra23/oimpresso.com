---
title: "RUNBOOK — Shell Mobile /m (app das lojas): contrato para as sessões de tela"
module: Mobile
tela: null
owner: W
status: ativo
last_validated: "2026-10-01"
related_adrs: ["0093-multi-tenant-isolation-tier-0", "0104-processo-mwart-canonico-unico-caminho"]
---

# RUNBOOK — Shell Mobile `/m`

> **Decisão [W] 2026-10-01:** o app das lojas (Capacitor, `com.oimpresso.app`) **não** abre o
> site do ERP emulado. Abre o **protótipo Mobile** — *"não gostei, foi pego o site e emulado. eu
> quero o Mobile mesmo"*. Escopo v1: Início, Tarefas, Pedidos (lista + detalhe com etapas),
> Produção, Pessoas, Ponto e Mais. v2: Produtos, Venda rápida, Finanças.
>
> Este documento é o **contrato da base** (o shell). As telas de negócio são de outras sessões
> e devem seguir o que está aqui.

## 1. Arquitetura (o COMO)

- As telas mobile vivem **dentro do ERP**, como páginas Inertia/React, sob o prefixo **`/m`**.
  O app Capacitor abre `https://oimpresso.com/m`.
- Por quê: reaproveita **sessão** (login do ERP), **permissões Spatie**, **`business_id` global
  scope** e os **Services** existentes; nenhuma API JSON nova exposta; trocar uma tela não exige
  reenvio às lojas.
- **Não** usar o app Expo de `mobile/` (servidor tRPC próprio — fora das lojas por decisão [W]).

## 2. Fonte de design (fiel, hi-fi)

| O quê | Onde |
|---|---|
| Spec (telas, temas, densidade, tab bar, push/pop, hit target 44px) | `mobile/ref/design-v3/oimpresso-mobile/project/design_handoff_oimpresso_mobile/README.md` |
| Tokens + classes `.oi-*` (fonte de verdade visual) | `.../design/oimpresso-tokens.css` → copiado **inteiro** em `resources/css/cowork-mobile-bundle.css` |
| Telas | `.../design/screens-*.jsx`, `task-viewers.jsx`, `mobile-app.jsx`, `icons.jsx` |
| Ponto | `prototipo-ui/cowork/Wagner/ponto-mobile.jsx` — **sem câmera/biometria** (ADR 0383) |
| Descartar | `ios-frame.jsx`, `android-frame.jsx`, `design-canvas`, `tweaks-panel.jsx` (andaimes) |

## 3. O que a base entrega

| Peça | Arquivo |
|---|---|
| Layout | `resources/js/Layouts/MobileShell.tsx` — tab bar de 5 abas, área de tela, tema do sistema |
| Primitivos | `resources/js/Pages/Mobile/_components/MobileHeader.tsx` — `ScreenHeader`, `DetailHeader`, `MobileScroll`, `ActionBar`, `Iniciais` |
| Tokens/estilos | `resources/css/cowork-mobile-bundle.css` (**não editar**) + `resources/css/mobile-shell.css` (customização) |
| Rotas | `routes/web.php`, grupo `Route::prefix('m')->name('mobile.')` dentro da pilha autenticada do ERP |
| Controller | `app/Http/Controllers/Mobile/MobileShellController.php` (Início e Mais mínimos + marcador "Em construção") |
| Testes | `tests/Feature/Mobile/MobileShellContratoTest.php` · lane `mobile-pest.yml` (MySQL) |

## 4. Login

O app abre `/m`. Sem sessão, o middleware `auth` redireciona ao **`/login` existente** guardando
`url.intended = /m`; depois do login o `LoginController::sendLoginResponse` devolve a `/m`.
**Escolha deliberada** de não fazer tela de login própria: o login existente já carrega as
travas de negócio (empresa inativa, usuário inativo, `allow_login`, rate limit) — uma segunda
porta de autenticação seria uma segunda superfície a manter e auditar. Uma tela de login no
visual do protótipo (gradiente da marca) é melhoria futura **sobre o mesmo controller**.

Com o cookie de sessão já presente no WebView, o app cai direto em `/m`.

## 5. Como criar uma tela `/m` nova (receita para as sessões de tela)

1. **Rota** — dentro do grupo `prefix('m')` em `routes/web.php`, controller **FQCN**. Se a aba
   era servida pelo marcador `emConstrucao`, **tire o slug** da lista `whereIn('aba', …)` e da
   constante `EM_CONSTRUCAO` no mesmo PR.
2. **Controller próprio** (não engorde o `MobileShellController`). Use os Services existentes do
   módulo; dados sempre do usuário autenticado (Tier 0 — nenhum parâmetro escolhe tenant).
   Props caras → `Inertia::defer()`.
3. **Página** em `resources/js/Pages/Mobile/<Tela>.tsx` (detalhe: `Pages/Mobile/<Area>/Show.tsx`),
   com `.charter.md` (com `runbook: memory/requisitos/Mobile/RUNBOOK-shell-mobile.md` ou um
   RUNBOOK próprio) e `.casos.md` **antes** do `.tsx` (o hook MWART bloqueia sem RUNBOOK).
4. **Estrutura da página:**

   ```tsx
   import MobileShell from '@/Layouts/MobileShell';
   import { ScreenHeader, MobileScroll } from '@/Pages/Mobile/_components/MobileHeader';

   export default function Pedidos(props: Props) {
     return (
       <MobileShell tab="pedidos" title="Pedidos">
         <ScreenHeader title="Pedidos" eyebrow="…" />
         <MobileScroll>…</MobileScroll>
       </MobileShell>
     );
   }
   ```

   Tela empilhada (detalhe): `tab` = aba de origem, `DetailHeader voltarPara="/m/pedidos"`
   (**obrigatório** — regra firme do handoff: nunca deixar o usuário preso), e `ActionBar` para a
   barra contextual (Aprovar arte · Liberar produção …).
5. **Estilo:** só classes `.oi-*` do bundle e tokens `var(--…)` dele. Nada de Tailwind/shadcn do
   ERP dentro de `/m` — são outro sistema visual. Faltou classe? Ela vai em `mobile-shell.css`
   com comentário, nunca no bundle.
6. **Ícones:** `lucide-react` (mapeamento por nome do `icons.jsx`), `strokeWidth={1.6}`.
7. **Navegação** entre telas: `<Link>` do Inertia (URL real por tela, deep link funciona).
8. **Teste Pest** na pasta `tests/Feature/Mobile/` (a lane `mobile-pest.yml` roda a pasta
   inteira): acesso autenticado + isolamento `business_id` (tenant 98, ADR 0358) + nome do UC.
9. **Smoke real** a 375px em produção, com screenshot, depois do deploy.

## 6. Comportamentos fixados pela base

- **Tema** segue `prefers-color-scheme` do aparelho (claro/escuro), inclusive troca ao vivo.
- **Safe area** por `env(safe-area-inset-*)`; o `inertia.blade.php` liga `viewport-fit=cover`
  **só** em `/m`.
- **Altura** = `100dvh`; o corpo rola dentro de `MobileScroll`, cabeçalho e tab bar fixos.
- **Densidade** fixa em `normal` (o seletor do protótipo é tweak de apresentação).
- **Badge** de aba: `badges={{ tarefas: n }}` no `MobileShell`.

## 7. Pendências conhecidas

- O app Capacitor ainda abre outra URL — a troca para `/m` é da sessão do app.
- `@import` do Google Fonts no topo do bundle: as fontes IBM Plex já são self-hosted pelo
  `app.tsx`; o `@import` fica no bundle por ser cópia integral.
