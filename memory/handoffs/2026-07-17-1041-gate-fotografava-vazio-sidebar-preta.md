---
date: "2026-07-17"
time: "10:41 BRT"
slug: gate-fotografava-vazio-sidebar-preta
tldr: "A flakiness do gate visual da Oficina não era não-determinismo: em 5 das 6 telas o estado `default` fotografa tela VAZIA, então a única coisa que podia se mover era a flag process_seeded. Semear 6 OS destapou um bg-white escondido no card há meses. Em paralelo: sidebar é PRETA (UI-0023 supersede UI-0019, que carimbou o erro como definitivo)."
prs: [4378, 4388, 4384]
decided_by: [W]
related_adrs:
  - 0340-tema-colapso-oposto-auto-blade-dark-react-light
  - 0101-tests-business-id-1-nunca-cliente
  - 0265-oficina-reparo-erradica-locacao
next_steps:
  - "Congelar o relógio do BROWSER (Playwright clock API) — mata a dívida do 'há 1 mês' e conserta a matriz toda, Financeiro incluído"
  - "Flake dos fluxos do Financeiro: 3 dispatches do mesmo código, 3 conjuntos diferentes de baseline"
  - "As outras 4 telas com `default` vazio: sells-index, clientes, compras, caixa-unificada"
  - "[W] declarar related_us das 2 telas de Suporte + owner/status dos 3 RUNBOOKs (5 resíduos da UI-0023)"
---

# Handoff 2026-07-17 10:41 — o gate fotografava o vazio; a sidebar é preta

## Estado MCP no momento do fechamento

⚠️ **MCP INACESSÍVEL** — `curl https://mcp.oimpresso.com/` → `000` (checado 10:41 BRT). O hook
`brief-fetch` do SessionStart já tinha caído no fallback (`curl falhou (exit 28)`). **A sessão
inteira rodou sem brief e sem `cycles-active`/`my-work`** — o checklist MCP-first do ADR 0130 não
pôde ser cumprido. Estado abaixo colhido de git+gh (prova do que dava pra provar, não promessa).

- **Mergeados por mim:** #4378 (16/jul→main), **#4388** (`e530fccbab`, 17/jul 10:24Z)
- **Chip que [W] disparou → landou:** #4384 (baseline dos 28 módulos travado, `9855260a2d`)
- **Sessão paralela ativa** (`serene-varahamihira-82e004`, tem o `main` em checkout): #4382/#4383
  (ADR 0340) · #4385/#4387 (visreg). **#4387 mergeou 1 min depois do meu #4388** e regenerou
  baselines em massa — **conferido: não tocou Oficina**; a minha `default` segue em main
  (219608 bytes, último toque `e530fccbab`).
- Off-cycle. Worktree `blissful-cori-9e0703`.

## O que aconteceu

### 1. A hipótese de flakiness estava errada — e o defeito era maior

[W] trouxe: duas baselines do `oficina-os · default` com 10,1074% de diferença (bbox y 263..407)
**ambas passando**, com hipótese de `businessId=0` via `session()->forget` na rota do VRT.

**Refutei as duas pontas, com código:**
- **`businessId=0`:** a rota do Board (`Modules/OficinaAuto/Routes/web.php:33`) inclui
  `SetSessionData`, que reconstrói a sessão **exatamente** quando `user` some — o estado que o
  `forget` deixa. O forget é auto-curativo por design. Se o auth faltasse, o middleware estouraria
  em `$user->id` → 500, não render degradado silencioso.
- **"o gate não morde":** o `.snap` existe e o path bate (`Str::evaluable` preserva `\x80-\xff`,
  então o `·` sobrevive). `readBaseline` acha e compara. **Não** é o caminho `baselineBlob === null`.

**O que era, medido decodificando as `.snap` e OLHANDO:**

| Tela | Seeder de **dado** | `default` fotografa |
|---|---|---|
| `financeiro-unificado` | ✅ `VisregFinanceiroFlowSeeder` | **1 lançamento com valor** |
| `oficina-os` | 🟡 `VisregOficinaFsmSeeder` — só etapas, **sem OS** | 0 OS, KPIs zerados |
| `sells-index` · `clientes` · `compras` · `caixa-unificada` | ❌ | 0 vendas · 0 clientes · 0 de 0 · 0 conversas |

**5 de 6 telas: `default` fotografa tela vazia** — contra a promessa do docblock do
`IsolatedStatesBaselineTest` (*"o estado SEEDADO de cada tela"*). Num quadro vazio,
`default ≡ empty` a menos da flag `process_seeded`, e **a única coisa que podia se mover entre
dois renders era ela** (kanban vazio ⇄ "não configurado") = exatamente a faixa de 10%.
**Não era não-determinismo: não havia o que fotografar.**

### 2. O gate cego escondia um bug real (a prova da tese)

Semeei 6 OS (`VisregOficinaBoardSeeder`, 1 por coluna), regenerei o `dark`, **abri o png**: cards
**brancos com texto ilegível**. `ServiceOrderKanbanCard.tsx:190` = `bg-white` fixo sem par `dark:`.
**15º `bg-white`** — a varredura de 14 do #4367 não pegou porque o card está em outro arquivo, e
**nenhum gate podia ver porque o card nunca esteve na foto**. Controle que fecha: o *drag overlay*
(o MESMO card, `Board.tsx:462`) já usava `bg-card`.

Complementa a [ADR 0340](../decisions/0340-tema-colapso-oposto-auto-blade-dark-react-light.md)
(aceita [W] 16/jul, sessão paralela) — **não contradiz**: o resíduo dela são as **colunas**
(`bg-muted/40` translúcido revelando o cockpit light = o híbrido); o meu era o **card**. A própria
0340 mediu `bg-card` dark = (42,46,49) em 24% da tela = **o Tailwind dark está ligado** — é por isso
que o `bg-card` resolveu.

### 3. Sidebar: a ADR estava errada, não o código (pivô do [W] no meio)

[W]: *"sidebar é como esta black então. apague os conflitos em definitivo"*. A **UI-0019** (07/jul)
declarou *"light DEFINITIVO"* alegando ter **medido** prod como clara — mas `cockpit.css:171` tem
`/* Sidebar — DARK FIXO (Wagner 2026-05-05) */` + `--sb-bg: oklch(0.18 …)`, sem qualificador de
tema. **O código é preto desde 05-05 (1 dia depois da UI-0009) e a lei ficou errada ~2,5 meses.**
A proposta correta (D-2, 08/jul, "DARK-FIXED") existia e **nunca foi numerada**.

**UI-0023** criada (supersede UI-0019; não editei nenhuma ADR — append-only). 9 sites de lei viva
corrigidos. O pior: `constituicao-ui-aware:97` dizia *"Sidebar permanece light · NÃO mudar pra
dark"* — **instrução ativa pra regressão numa skill que dispara em toda edição de UI**.

## Artefatos gerados

| Artefato | Onde | PR |
|---|---|---|
| ADR **UI-0023** (sidebar preta, supersede UI-0019) | `memory/requisitos/_DesignSystem/adr/ui/0023-*.md` | #4378 |
| `VisregOficinaBoardSeeder` (6 OS, 1/coluna) | `database/seeders/` | #4388 |
| `bg-white` → `bg-card` no card do Quadro | `.../board/ServiceOrderKanbanCard.tsx:190` | #4388 |
| 3 baselines (default/dark/PixelBaseline Oficina) | `tests/.pest/snapshots/` | #4388 |
| Chip → baseline dos 28 módulos travado | `governance/module-grades-baseline.json` | #4384 |

## Persistência

- **git:** ✅ #4378 + #4388 em main; deploy `success` (`e530fccbab`)
- **MCP:** ❌ servidor fora (000) — sem `tasks-update`. **Retomar quando voltar.**
- **BRIEFING:** não tocado (mudança é de gate/DS, não de capacidade de módulo)

## Próximos passos pra retomar

```
gh pr view 4388 --json state && curl -s -o /dev/null -w "%{http_code}" https://mcp.oimpresso.com/
```

Ordem sugerida: congelar relógio do browser → flake do Financeiro → as 4 telas com `default` vazio.

## Lições catalogadas

1. **Sonda no momento errado não flagra nada.** A `oficina_stages_biz1=` mede o **seed**; o defeito
   estava no **render**. Ela imprimia `9` e a tela saía degradada. **O DADO é a sonda**: com 6 cards,
   perder a pré-condição vira diff ≫ τ_alto e o gate grita, em vez de trocar um vazio por outro.
2. **Rodar o gate no modo errado não prova nada.** Rodei `charter-us-lint` **sem `--check`** (report,
   exit 0) e li como verde; o CI roda `--check` diff-aware e ficou vermelho. Validar **na mesma flag
   do CI**.
3. **Fixture de gate NÃO inventa dado que outra tela lista.** Meu `ensureContact` vazou pra baseline
   de **Clientes** ("Todos" 1→3) e colidiu de nome com o seeder do Financeiro. Peguei **comparando
   artifacts**, não em review — `.snap` binário de outra tela não aparece no diff do PR.
4. **A lápide 2026-07-12 mordeu 3× no mesmo PR** (charters de Suporte, RUNBOOKs). Pra calar os gates
   eu teria que fabricar `related_us`/`owner`/`last_validated` (este último é o campo que a lápide
   2026-07-09 chama de teatro). **Preferi 5 linhas erradas e visíveis a 5 âncoras inventadas.**
5. **Aceite de artefato não estende pro artefato seguinte.** [W] aprovou o `default`; eu mexi em
   produto (`bg-card`) depois → devolvi o screenshot em vez de mergear por conta.
6. **Ruído absorvendo podridão ≠ resolvido.** Os cards mostram "há 1 mês"/"vencida há 41d"
   (relativos, JS × relógio real; `setTestNow` não alcança o browser). Derivam ~0,003%/~0,02% =
   **abaixo do τ_baixo** → auto-aprovam calados. Nomeado no PR, não escondido.
7. **Tropecei na regra de valores BRL.** O hook `block-brl-values-in-memory.mjs` me barrou ao
   escrever este handoff — e **corretamente**. Só que eu já tinha posto o valor do fixture do
   Financeiro no **corpo do #4388 e em mensagens de commit** (que a mesma regra proíbe) sem o hook
   pegar: ele cobre `memory/**`, não PR body nem commit message. É valor de fixture (público no
   fonte do seeder), não dado de cliente — mas a regra é mecânica e eu passei. **Contagem basta;
   valor não.** Gap do mecanismo anotado: o hook não vê PR/commit.

## Pointers detalhados

- [ADR UI-0023](../requisitos/_DesignSystem/adr/ui/0023-sidebar-dark-fixo-preto-definitivo-supersede-0019.md) — linha do tempo do conflito + os 5 resíduos tabelados
- [PR #4388](https://github.com/wagnerra23/oimpresso.com/pull/4388) — evidência completa do gate vazio
- [ADR 0340](../decisions/0340-tema-colapso-oposto-auto-blade-dark-react-light.md) — o híbrido (sessão paralela)
- `scripts/tests/visreg-flake-retry.sh` — registra os 11 patches do Financeiro "por relógio cross-processo"
