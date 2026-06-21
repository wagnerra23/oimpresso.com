---
date: "2026-06-13"
hour: "20:07 BRT"
topic: "Ficha do cliente — lupa da busca não encavala (cascade-layer Tailwind v4) + Auditoria vira sub-aba de Operações (remove cabeçalho/Exportar log); shipado pro main+prod via PR limpa #2685"
duration: "~2h"
authors: [W, C]
---

# Cliente — fix lupa da busca + Auditoria entra em Operações (shipado)

## Estado MCP no momento
- **Cycle CYCLE-08** (Receita Onda A) — trabalho desta sessão é **off-cycle** (polish de UI da ficha, disparado por feedback visual direto do Wagner, não task individual).
- Worktree `frosty-greider-83ab2f` tem **sessão paralela** ("Parallel turbo stages") dona do working tree (cluster Whatsapp não-commitado + floor/harness). Commitei só os 2 arquivos deste handoff, lista explícita.

## O que aconteceu
Arco curto disparado por screenshots do Wagner na ficha do cliente:
1. **Lupa encavalada na busca** (print): `.cw-input` (cowork-fields.css) é **unlayered** e vencia as utilitárias `pl-9`/`pr-9` do **Tailwind v4** (que ficam em `@layer utilities`) — pela cascata de layers, unlayered SEMPRE ganha de layered → `padding: 0 8px` anulava o `pl-9`, texto colava em 8px e a lupa sobrepunha o placeholder. Tentativas anteriores (mexer em classe tailwind) não tinham como vencer. **Fix:** 2 classes unlayered `cw-input-icon-left/right` declaradas após `.cw-input`. Aplicado em `Cliente/Index` (busca), `Cliente/Map` e Vendas da ficha.
2. **"chips e abas são a mesma coisa, integrar"** → Wagner escolheu (AskUserQuestion) **Auditoria entra em Operações**: saiu do chip flutuante do header e virou item do rail de `OssTab`. Chips do header agora = Placas + IA (+ anexos).
3. **"não quero isso"** (cabeçalho da Auditoria) → removido o cabeçalho INTEIRO da `AuditoriaTab` (título "Historico de alteracao" + texto LGPD Art.18 + botão "Exportar log") — fica só a timeline. Rota `/auditoria/export` **mantida no backend**.
4. **"merge e build, quero ver"** → PR original #2682 (DS Rollout) estava **109 commits atrás do main + 4 conflitos + acabou CLOSED**. Wagner escolheu **PR limpa só dos fixes de cliente** (cherry-pick num worktree isolado de `origin/main`). PR **#2685** → 14 checks verdes (depois do bump do baseline CSS) → **squash-merge no main** (`0e30fed57`) → **auto-deploy Hostinger** (ADR 0269) disparado.

## Artefatos gerados (canon = main via #2685)
- `resources/css/cowork-fields.css` — classes `.cw-input-icon-left/right` (+ comentário cascade-layer)
- `resources/js/Pages/Cliente/Index.tsx` — busca usa cw-input-icon-*; remove chip Auditoria + render + imports/types mortos; `TAB_ORDER` corrige `oss`→`operacoes`
- `resources/js/Pages/Cliente/_drawer/OssTab.tsx` — sub-aba `auditoria` no rail + render AuditoriaTab
- `resources/js/Pages/Cliente/_drawer/AuditoriaTab.tsx` — cabeçalho removido, só timeline
- `resources/js/Pages/Cliente/{Map.tsx,_show/SalesTab.tsx}` — busca cw-input-icon-left
- `tests/Feature/Cliente/{ClienteDrawerHeaderButtonsReorgTest,ClienteIndexDrawer760CharterTest}.php` — catraca atualizada + GUARD 12/13
- `resources/js/Pages/Cliente/Index.charter.md` — v9→v10
- `config/css-size-baseline.json` — +17 consciente
- PR [#2685](https://github.com/wagnerra23/oimpresso.com/pull/2685) · merge `0e30fed57`

## Persistência
- **git/main**: ✅ via #2685 (squash). DS Rollout (#2682) **NÃO** foi pro main — fica CLOSED (decisão Wagner).
- **prod**: deploy Hostinger automático (run 27477575298) **estava in_progress** no fechamento — F5 em oimpresso.com confirma.
- Os 2 commits originais (170ccbf87, a159e3a96) seguem na branch `feat/governance-ds-rollout-ledger` (origem do cherry-pick).

## Próximos passos pra retomar
- Confirmar deploy `27477575298` `success` (`gh run view`) → smoke F5 na ficha em prod.
- Nada pendente do escopo de cliente. Se Wagner quiser o DS Rollout no main → reabrir/re-encaminhar #2682 (109-behind, exige resolver 4 conflitos).

## Lições catalogadas
- **Tailwind v4 cascade-layer**: utilitárias (`pl-*`/`pr-*`) vivem em `@layer utilities` e PERDEM pra qualquer CSS **unlayered** (ex `.cw-input` de cowork-fields.css), independente de especificidade/ordem. Pra afastar ícone overlay num `<Input variant="cowork">`, classe tailwind NÃO resolve — usar classe unlayered (`cw-input-icon-*`) ou inline style. (Idem: `h-8`/`bg-background` em `.cw-input` são mortas pelo mesmo motivo.)
- **Catraca source-grep**: quando o design muda conscientemente, os guards `file_get_contents`/`toContain` têm que ser atualizados no MESMO PR — e CUIDADO: comentário no .tsx contendo a string proibida derruba o `not->toContain` (reescrevi o comentário pra ser guard-safe).
- **Inertia SPA**: rebuild troca o bundle, mas o usuário só vê com **F5 (reload da página inteira)** — navegar dentro do drawer mantém o JS antigo em memória (foi por isso que o "Exportar log" ainda apareceu pro Wagner pós-build).
- **Branch longa**: `feat/governance-ds-rollout-ledger` 109 atrás do main → PR grande vira swamp de conflito. Fatiar o que precisa shipar em PR limpa de `origin/main` (cherry-pick em worktree isolado) é mais rápido e seguro que resolver a divergência inteira.

## Pointers detalhados
- ADR 0269 (deploy automático on push main) · ADR 0179 (drawer 760 substitui Show) · `Index.charter.md` v10 (estrutura de abas + chips atual).
<!-- schema-allowlist: salvo de feat/governance-ds-rollout-ledger (branch shallow-orfanada 2026-06-20); output de subagente/legacy, schema estrito de secao nao se aplica -->
