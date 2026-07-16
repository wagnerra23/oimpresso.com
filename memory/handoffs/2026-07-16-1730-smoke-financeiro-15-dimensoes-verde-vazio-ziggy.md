---
date: "2026-07-16"
slug: "smoke-financeiro-15-dimensoes-verde-vazio-ziggy"
tldr: "Pedido inicial era validar a Visão Unificada do Financeiro; ela já estava no ar e a produção JÁ tinha passado à frente do protótipo (não aplicar por cima). O caminho destapou 4 defeitos reais, todos mergeados: teste Onda10 lendo FinSubNav.tsx deletado escondido por skip-as-pass (#4332), 6 seeders com senha pública sem guard de produção em repo ABERTO (#4333, nasceu de pergunta do Wagner), smoke dando verde tendo testado 1 de 12 rotas (#4340, canário — pego ao vivo 3x), e Ziggy inline de 169KB/1418 rotas na /login pública que usa ZERO delas (#4361, 171KB->24KB medido em prod). Mais o teste permanente das 15 dimensões com controle-negativo (#4331). Smoke de máquina NÃO fechou: login do biz=99 recusado, causa não isolada."
time: "17:30 BRT"
prs: [4327, 4329, 4331, 4332, 4333, 4340, 4361]
decided_by: [W]
next_steps:
  - "Wagner: entrar em https://oimpresso.com/login com smoke_bot_99/12345678 em aba anônima — decide se o secret diverge ou se a conta está travada (lockout)"
  - "Sem esse dado, NÃO re-disparar o smoke: 5 retries com login falhando causaram ban dos IPs do GitHub (health gate 000x6, expirou sozinho ~2h)"
  - "PRE-MERGE-UI.md ainda manda smoke com biz=4 (cliente real) — doc stale perigosa apontada pela auditoria do protocolo"
---

# Handoff — 2026-07-16 17:30 · Smoke Financeiro, 15 dimensões, verde-vazio e Ziggy

## Estado MCP no momento

- **Cycle:** sem foco declarado no brief do dia.
- **PRs mergeados na sessão:** 7 (#4327, #4329, #4331, #4332, #4333, #4340, #4361).
- **Handoffs irmãos:** último era 2026-07-02 (SDD 75/100). Codex trabalhou em paralelo hoje (#4335 `ui-impact.mjs`, #4336 `visreg-screens.json`, #4339/#4341/#4343).
- **Auditoria do protocolo (Codex, commit 4113b92):** nota **5,4/10**. Cobertura visual 3,0 (pixel cobre 6 de 242 Pages). Fail-safe 4,0 ("checks advisory ou fail-open"). P0 nº1 = "Classificador único de impacto UI + canário anti-verde-vazio".

## O que aconteceu

**O pedido virou outra coisa — e foi bom.** Wagner pediu "aplicar o protótipo do Financeiro Unificado em produção". Investigação mostrou que a tela **já estava no ar** (landing de `/financeiro` via 301, controller + 18 testes Pest) e que a **produção passou MUITO à frente do protótipo** (protótipo parou em ~23/jun; prod evoluiu até #4304 — removeu FinSubNav, migrou combobox pro canon, pill Conta Indefinida). Aplicar o protótipo por cima teria **revertido ~20 PRs**. Redirecionado para validação.

**Validação manual (R1):** as 8 telas do Financeiro renderizam vivas em prod — Unificado, Cobrança, Caixa, Fluxo, Conciliação, DRE, Plano de contas, Impostos. Zero erro de console. (Screenshot em branco no início foi timing, não crash — confirmado por `read_page`.)

**Os 4 defeitos que o caminho destapou:**

1. **#4332 — teste órfão escondido por skip-as-pass.** `Onda10Canon100PercentTest` lia `_components/FinSubNav.tsx` (deletado no #4279) via `file_get_contents` → ErrorException. O MESMO arquivo já tinha um bloco `REVISADO` assertando a **ausência** do FinSubNav: o teste estava **contraditório**. Só apareceu porque o PR #4329 tocou `Modules/Financeiro/` — o check "PHP / Pest (Financeiro)" é **skip-as-pass** e não roda a suite se você não tocar o módulo. 5 falhas escondidas → 0.

2. **#4333 — senha pública em repo ABERTO.** Wagner perguntou *"o repositório é público, criar senha assim seria correto?"*. Não era — e a pergunta destapou 6 seeders (`DummyBusinessSeeder` `123456`, `FullSuiteMinimalTenantSeeder` `ci`, 3 Visreg `visreg-secret-not-for-prod`) com senha em claro e **zero guarda de ambiente**. Sem buraco ativo (DatabaseSeeder não os chama), mas o `deploy.yml` tem input manual de artisan → uma dispatch errada criaria contas `123456` em produção. Guard `isProduction()` nos 6; CI (`testing`) e CT100 (`staging`) intactos.

3. **#4340 — o canário anti-verde-vazio.** Rota auth pulada é `{...route, skipped:true}` — **sem `verdict`** — e não entrava no `some()` que calcula `worst`. Resultado: 11 telas puladas → `geral=OK` → exit 0 → **✅ verde**. Reproduzido **3x ao vivo** (runs 29496887149, 29504072970, 29506291169: `success` tendo aberto só `/login`). Depois do fix: `failure` + as 11 rotas nomeadas. Fecha o P0 nº1 da auditoria pelo lado da **execução** (o `ui-impact.mjs` do Codex fecha pelo lado da **classificação**).

4. **#4361 — Ziggy: 99,3% da /login era desperdício.** A tela que todo cliente vê primeiro pesava **171.938 B**, sendo **169 KB de `<script>` inline** com **1.418 rotas** serializadas — dentro de um HTML `Cache-Control: no-cache`, ou seja, o maior payload da página era **o único que nunca entrava em cache**. As páginas públicas (`Pages/Site/*`) usam **ZERO** `route()` (auditado nas 4 camadas: Pages/Site, Components/Site, SiteLayout, app.tsx; o login faz `post('/login')` com URL literal). Corte via `@auth → @routes` / `@else → @routes('public')`. **Medido em prod: 171.938 B → 24.666 B (−86%), Ziggy 147.815 B/1.418 rotas → 531 B/7 rotas, total 1,91s → 1,22s.** App autenticado **intocado**: tem 21 chamadas `route(nomeVindoDoServidor)` (prop Inertia) que nenhum grep enumera — corte global lá quebraria em runtime **sem o gate visual pegar** (erro de rota é em clique, não em screenshot).

**#4331 — teste permanente das 15 dimensões.** Espelha o controle-negativo do `ConformanceProbesTest` (L-31 "todo ✅ tem que ter sido visto falhar"): 8 probes de pixel (dims 1/2/3/4/9/10/11/12) + 1 comportamental (dim 6, atalho `/` foca busca) + matriz **honesta** marcando o que máquina NÃO testa (13/14/15 são meta/decisão → humano/PR-Judge). Precedido de experimento live (#4330, fechado): mudei a cor dos KPIs e a máquina acusou **exatamente** `financeiro-unificado estado=dark`, com zero falso-positivo nas outras 5 telas.

**O que NÃO fechou — smoke de máquina.** Conta fake **SMOKE TESTE (#225)** + pacote **SMOKE CI** (privado, com Financeiro) criados; secrets setados. Mas o login do `smoke_bot_99` é **recusado pelo site** (`login FALHOU (ainda em /login)` — preencheu, clicou, recusou), enquanto Wagner entra na mão. Causa não isolada: secret divergente ou lockout de tentativas. **Parei de investigar por retry** — ver lição abaixo.

## Artefatos gerados

| PR | Arquivo | Efeito |
|---|---|---|
| #4327 | `scripts/screen-smoke/routes.json` | +8 telas Financeiro (12 rotas curadas) |
| #4329 | `Modules/Financeiro/Console/Commands/ProvisionSmokeTenantCommand.php` | provisiona conta fake (dry-run default, senha aleatória em runtime) |
| #4331 | `tests/Browser/CoreScreens/PixelDimensionProbesTest.php` + `VisregThreshold` (+2 helpers) + `visual-regression.yml` | prova permanente das 15 dims |
| #4332 | `Modules/Financeiro/Tests/Feature/Onda10Canon100PercentTest.php` | −5 falhas (192→167 linhas) |
| #4333 | 6 seeders em `database/seeders/` | guard `isProduction()` |
| #4340 | `scripts/screen-smoke/smoke.mjs` | canário: rota não exercida → exit 1 |
| #4361 | `config/ziggy.php` (novo) + `layouts/inertia.blade.php` | /login −86% |

## Persistência

- **Git canon:** 7 PRs mergeados na `main`.
- **MCP:** propaga via webhook GitHub→MCP (~2min após push deste handoff).
- **Task spawned:** bug de view `edit_account_transaction.blade.php:39` ("Trying to access array offset on null") — chip pendente, fora de escopo.

## Próximos passos pra retomar

```
Wagner: aba anônima → https://oimpresso.com/login → smoke_bot_99 / 12345678
  entrou    → o secret diverge; trocar senha e resetar os DOIS secrets
  não entrou → conta travada (lockout) ou senha ≠ do que pensamos
```
Só depois desse dado disparar o smoke — **uma vez**.

## Lições catalogadas

**L — Retry cego causa ban.** Disparei o smoke 5x "tentando mais uma" com o login falhando. Do lado da Hostinger isso é indistinguível de brute-force: o health gate passou a receber **000 × 6** (runner sem conectar) enquanto a máquina do Wagner recebia 200. Expirou sozinho em ~2h. **Devia ter parado no 2º e investigado.** O canário (#4340) nasceu de *olhar* o log em vez de repetir — o método certo estava disponível desde o começo.

**L — `gh api` faz PUT mesmo com `base64` vazio.** Um `base64` falhou silenciosamente (arquivo inexistente) e o `gh api -X PUT` gravou **`inertia.blade.php` com 0 bytes** — o layout que renderiza o app inteiro. `main` não foi tocada (só a branch) e restaurei da main, mas o que salvou foi **conferir depois de escrever**, não a intenção. **Sempre validar `[ -z "$B64" ] && exit 1` antes do PUT.**

**L — Skip-as-pass esconde defeito, não previne.** O "PHP / Pest (Financeiro)" só roda se o PR tocar `Modules/Financeiro/`. 5 testes quebrados viveram escondidos até um PR de Console tocar o módulo. Mesma família do verde-vazio do smoke: **o gate existe, roda, e mente**.

**L — Medir em cima do deploy dá falso vermelho.** Um smoke rodou durante o deploy: assets em troca → erro de JS → React não montou → `locator.fill: Timeout` → OpenAI viu "tela quebrada". A /login estava sã. **Aguardar o deploy assentar antes de medir.**

**L — `printf` com `\n` invalida JSON do `gh api`.** Converte em quebra real dentro da string JSON → HTTP 400. Usar heredoc (preserva `\n` literal).

## Pointers detalhados

- Auditoria do protocolo (5,4/10, eixos + benchmark Nx/ESLint/Playwright/Storybook/W3C-ACT): colada pelo Wagner nesta sessão, commit de referência `4113b92`.
- Trabalho paralelo do Codex: #4335 `ui-impact.mjs` (fail-closed na classificação, dormente até #4336 trazer `visreg-screens.json`), #4339/#4341/#4343.
- Worktree `exciting-mccarthy-f4219c` estava com **índice git vazio** (`git ls-files` = 0) — todo o trabalho foi via API do GitHub. Vale recriar antes de reusar.
