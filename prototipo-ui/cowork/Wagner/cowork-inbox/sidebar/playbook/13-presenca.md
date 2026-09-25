---
sessao: "13"
titulo: RODAPÉ · presença clicável e persistida (4 estados)
dono: "[CL]"
criado: 2026-09-25
base: wagnerra23/oimpresso.com@main (árvore 034e476895cb · lido 2026-09-25 13:48 UTC)
onda: 2 — protótipo → vivo · decisão [W] 2026-09-25 ("pode fazer esses")
---

# 13 · Presença

## Abertura
Sessão limpa · `/onda sidebar --thread NN` · ler só esta ficha + o objeto dela no §7 + as âncoras abaixo, **relidas no main**. Escreve `Sidebar.tsx` → serial com 08–12 (Lei 1).

## Decisão
[W] 2026-09-25: a presença vira real. Resolve RESIDUO-6.

## Alvo — protótipo
`sidebar.jsx` → `PRESENCAS` + `UserMenu`: 4 estados, **clicáveis**, o gatilho mostra ponto + rótulo do estado atual, o subpainel marca o atual com ✓ e `aria-pressed`.
Literais: `disponivel · Disponível · oklch(0.72 0.18 145)` · `ocupado · Ocupado · oklch(0.62 0.20 25)` · `ausente · Ausente · oklch(0.75 0.15 75)` · `invisivel · Invisível · oklch(0.55 0.01 280)`.

## Âncora — vivo (padrão a COPIAR, não criar outro)
O tema já faz exatamente isto: coluna `users.ui_theme` → `auth.user.ui_theme` (prop) → `resources/js/Hooks/useTheme.ts:70` `fetch('/user/preferences/theme')` → rota `routes/web.php:1162`. Presença = **mesma forma**: coluna `users.ui_presence` (nullable, default `disponivel`), rota `POST /user/preferences/presence` ao lado da do tema, mesmo controller/validação (enum dos 4 ids).
Front: `Sidebar.tsx` → `SidebarUserMenu`: gatilho hoje fixo em `Disponível` (`:1281-1293`) e subpainel com 3 `<div className="um-item">` sem ação (`:1419-1426`, e um deles é `Não perturbe`, que o protótipo não tem).

## Faz
1. Migration + fillable/cast + validação + rota (≤ 4 arquivos de backend).
2. `auth.user.ui_presence` na prop compartilhada, junto do `ui_theme`.
3. Gatilho e subpainel lidos do valor; clique persiste; `<button aria-pressed>` em vez de `<div>`.

## Não faz
Ninguém **consome** a presença ainda (Atendimento/Equipe). Só grava e mostra. Consumidor é outra thread.

## Prova
- pré-condição: `Sidebar.tsx` contém `Invisível` e não contém `Não perturbe`.
- fecha: `execucao` — Feature test da rota (4 válidos passam, 1 inválido 422, grava na coluna) + teste de render do menu.
- contrato: copy `Ocupado` e `Invisível` em `sb-rodape`.

## Fechar
`_saida-13.md` e PARE.
