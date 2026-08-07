---
date: "2026-08-03"
hour: "07:34 BRT"
duration: "8h"
topic: "Triagem da quarentena da lane Financeiro (required), um arquivo por PR: 10 arquivos triados, 12 PRs mergeados, quarentena 33→25. Nenhum era regressão de produto. Um veredito meu não sobreviveu à lane e foi revertido por mim mesmo."
authors: [C]
outcomes:
  - "Quarentena da lane `PHP / Pest (Financeiro · MySQL)` (REQUIRED) caiu de 33 → 25 arquivos. 10 triados um a um, cada um com PR próprio, veredito medido e recibo antes→depois."
  - "ZERO era regressão de produto: 3 fixtures quebradas (FK/append-only/coluna fantasma), 4 testes podres (asserção impossível, auto-acusação, contagem exata, sintaxe JSX), 1 artefato do ambiente de medição, 1 flake por ordem, 1 só reclassificado."
  - "ACHADO 1 — RetencaoLoopE2ETest estava 100% SKIPADO dentro da lane required: os 3 UCs (UC-F01/02/03) alimentavam o manifesto G-7 com veredito `skip`. Causa: resolvia biz=1 enquanto o seed do CI cria location/contact só pro biz=98 (decisão [W] 2026-07-28). 3 skipped (0 assertions) → 3 passed (14)."
  - "ACHADO 2 — `transactions.total_remaining_amount` NÃO EXISTE (sonda: coluna NAO, accessor NAO, appends []), e `TituloAutoService:100` lê essa coluna com `?? final_total`: a leitura é código morto. `Model::create()` descarta a chave em SILÊNCIO (lê de volta NULL) enquanto `->update()` estoura Unknown column — o que explica 8 arquivos de teste montando cenário de pagamento parcial que NÃO montam. Decisão [W] pendente (toca VALOR)."
  - "ACHADO 3 — o `merge=union` do .gitattributes tem TRÊS modos de falha neste arquivo, dois deles silenciosos: duplica linha modificada · RESSUSCITA linha removida no main (pegou 2×, desfazendo PR já mergeado) · DESFAZ a remoção do próprio branch (PR vira no-op que mergeia 'com sucesso' sem tirar nada da quarentena)."
  - "AUTOCORREÇÃO — o veredito isolado do #5200 (Onda23OcrBoletoTest) NÃO sobreviveu à lane: 5 failed com 403 em vez de 422, flake por ORDEM. Devolvido à quarentena com o motivo real (#5217), cumprindo a própria ressalva que eu escrevia em cada PR."
  - "3 erros MEUS, nenhum apagado: migrei o banco COMPARTILHADO do staging por env var do container sobrepor o .env (12 tabelas removidas) · rodei testes contra o staging por esquecer `-e DB_DATABASE` (fixtures injetadas, auditadas e limpas) · abri PR duplicando trabalho de outra sessão em arquivo derivado, pego pelo `dup-detector` e fechado."
prs: [5192, 5195, 5196, 5197, 5198, 5200, 5206, 5209, 5212, 5214, 5215, 5216, 5217]
us:  []
related_adrs: ["0264-governanca-executavel-trio-dominio-e2e", "0093-multi-tenant-isolation-tier-0", "0101-tests-business-id-1-nunca-cliente", "0062-separacao-runtime-hostinger-ct100"]
---

# Session log 2026-08-03 — Triagem da quarentena da lane Financeiro

## TL;DR

A quarentena da lane **required** do Financeiro caiu de **33 → 25** arquivos, um PR por arquivo.
**Nenhum dos 10 triados era regressão de produto** — eram fixtures quebradas, testes podres e
um artefato do ambiente de medição. O maior valor não foi tirar arquivo da lista: foi descobrir
que um teste da lane estava **100% skipado** (3 UCs alimentando o manifesto com `skip`), que o
produto lê uma **coluna que nunca existiu**, e que o `merge=union` da lista já tinha produzido
um **no-op silencioso**. Um veredito meu não sobreviveu à lane e **eu mesmo o revertei**.

## Contexto

Continuação direta do [handoff de 2026-08-02 21:00](../handoffs/2026-08-02-2100-b7-cobertura-travas-de-prova.md),
que deixou explícito o precedente: *"'N failed' na lista NÃO significa 'produto quebrado'"* —
o `ConciliacaoAuditReabrirTest` parecia defeito de produto e era **fixture** (`titulo_id = 12345`
hardcoded violando FK, morrendo no setup sem exercer `reabrir()`).

O pedido desta sessão foi aplicar o mesmo rigor aos **29 arquivos restantes** do bucket C e
vizinhos, com a regra dura: **um por PR, nada de lote**.

## Cronologia

| Quando | Evento |
|---|---|
| 23:30 | Setup do ambiente de medição no CT 100 — worktree próprio em `origin/main` + DB `oimpresso_qa` pela receita do CI |
| 23:40 | **Erro meu**: `migrate` caiu no banco COMPARTILHADO do staging (env var do container sobrepõe o `.env`) — 12 tabelas removidas |
| 00:10 | Controle validado: `ConciliacaoMatchScoreTest` reproduz o veredito documentado (3 passed, 15 assertions) |
| 00:20 | Medição fresca dos 33 arquivos, 1 por processo |
| 00:30–03:00 | Triagem 1–6 (PRs #5192, #5195, #5196, #5197, #5198, #5200) |
| 02:15 | Achado do skip: `RetencaoLoopE2ETest` 100% skipado na lane required (#5212) |
| 03:30 | Achado do `total_remaining_amount` — leitura morta em `TituloAutoService:100` (#5209) |
| 04:35 | `dup-detector` me pega: #5215 duplicava trabalho de outra sessão → fechado |
| 05:20 | Primeiro modo de falha do `merge=union`: linha duplicada |
| 05:40 | Segundo modo: **ressuscitou** arquivo que um PR já mergeado tinha libertado |
| 06:10 | Terceiro modo: **desfez a remoção do próprio PR** (no-op silencioso) |
| 06:10 | Lane vermelha no #5216 — mas o arquivo que falhou era o do **#5200, já mergeado** |
| 06:45 | **Autocorreção**: #5217 devolve `Onda23OcrBoletoTest` à quarentena com o motivo real |
| 07:20 | Último PR mergeado. Quarentena em `main`: **25** |

## Entregas

| PR | Arquivo | Veredito | Antes → depois |
|---|---|---|---|
| [#5192](https://github.com/wagnerra23/oimpresso.com/pull/5192) | `TituloCriadoEventTest` | fixture: `forceDelete()` bate no append-only de `fin_titulos` | 1F/1P (3) → **2P (3)** |
| [#5195](https://github.com/wagnerra23/oimpresso.com/pull/5195) | `ExtratoControllerTest` | fixture: business sintético viola FK — o caso **[T0] cross-tenant** morria antes de asserir | 1F/2P (2) → **3P (3)** |
| [#5196](https://github.com/wagnerra23/oimpresso.com/pull/5196) | `ProvaVivaControllerTest` | teste podre: header `X-Inertia` e `assertInertia` são **mutuamente exclusivos** | 1F/1P (3) → **2P (9)** |
| [#5197](https://github.com/wagnerra23/oimpresso.com/pull/5197) | `ProvaVivaContractTest` | teste podre: o guard acusava o **comentário que documenta a própria regra** | 1F/1P (10) → **2P (10)** |
| [#5198](https://github.com/wagnerra23/oimpresso.com/pull/5198) | `Wave23SaturationTest` | teste podre: `toBe(8)` checks, o comando cresceu pra 10 | 1F/6P (17) → **7P (17)** |
| [#5200](https://github.com/wagnerra23/oimpresso.com/pull/5200) | `Onda23OcrBoletoTest` | artefato do ambiente (chave OpenAI real no container) | 1F/11P (24) → **12P (25)** |
| [#5206](https://github.com/wagnerra23/oimpresso.com/pull/5206) | `FluxoControllerTest` | 3 drifts de teste (header · JSON int × float · Collection) | 3F/3P (39) → **6P (46)** |
| [#5209](https://github.com/wagnerra23/oimpresso.com/pull/5209) | — | **só reescreve o motivo**; arquivo FICA (decisão [W]) | — |
| [#5212](https://github.com/wagnerra23/oimpresso.com/pull/5212) | `RetencaoLoopE2ETest` | **100% skipado** na lane required | 3 skipped (0) → **3P (14)** |
| [#5214](https://github.com/wagnerra23/oimpresso.com/pull/5214) | `Onda9ResumirMesTest` | bucket A: sintaxe JSX → cadeia de wiring | 1F/13P (42) → **14P (46)** |
| [#5216](https://github.com/wagnerra23/oimpresso.com/pull/5216) | `Onda7bTroubleshootPresentTest` | bucket A, mesmo molde | 1F/11P (44) → **12P (48)** |
| [#5217](https://github.com/wagnerra23/oimpresso.com/pull/5217) | `Onda23OcrBoletoTest` | **VOLTA** — flake por ordem (403) | autocorreção do #5200 |
| [#5215](https://github.com/wagnerra23/oimpresso.com/pull/5215) | — | **CLOSED** — duplicata pega pelo `dup-detector` | — |

**Bite-test em 5 consertos** (controle negativo ANTES de tirar da quarentena): injetei `<div className="flex">`
real → guard falhou nomeando a linha 627 · removi `checkOrphanBaixas` → falhou nomeando o check ·
mudei `MARGEM_MINIMA_PADRAO` pra 6000 → falhou · desliguei `open={resumoOpen}`/`open={presentOpen}` →
falhou. Restaurados, todos verdes. Nenhum virou presence-gate.

## Aprendizados / pegadinhas

- **`| head` sobre `ssh + docker exec` mata o pipe por SIGPIPE ANTES do flush.** O comando aparece
  como "sem output" mas **executou e mutou estado**. Perdi várias medições assim e cheguei a
  reinterpretar estado sujo como resultado. Use `tail`, nunca `head`, nesses pipelines.
- **`merge=union` não é seguro para resolução automática.** Três modos de falha, dois silenciosos.
  O gate correto é bidirecional, e **contagem sozinha não denuncia** (30 vs 29 parecia plausível):
  ```
  comm -13 main br  → ressuscitados  (deve ser vazio)
  comm -23 main br  → removidos      (deve ser EXATAMENTE o alvo do PR)
  ```
- **Env var de container sobrepõe o `.env`.** Sonde o banco efetivo dentro do próprio processo
  antes de qualquer `migrate`/`test` — foi o que faltou e custou 12 tabelas do staging.
- **Credencial real no ambiente muda o veredito do teste.** O container do CT 100 carrega ~130
  env vars (OpenAI/Stripe/PayPal/…) que o runner do CI não tem; teste cujo comportamento depende
  de credencial AUSENTE mede outra coisa lá. Só 1 dos 33 arquivos foi afetado, mas a medição
  fiel (`env -i` + o bloco `env:` do step do CI) é barata.
- **Antes de abrir PR em arquivo derivado/hot-path, pergunte ao `dup-detector`.** Foi ele que me
  pegou duplicando trabalho de duas outras sessões, não eu.
- **A ressalva "quem prova é o run da lane" cobra de verdade.** 1 dos 10 vereditos isolados não
  sobreviveu ao processo compartilhado. O erro não passou porque a lane é required e a ressalva
  estava escrita — mas eu tirei da quarentena um arquivo que não estava pronto.

## Próximos passos (não-bloqueante)

- [ ] **Decisão [W]** — `TituloAutoService:100` (leitura morta de `total_remaining_amount`): (a) só o teste, ou (b) remover a leitura. (b) toca VALOR → REGRA MESTRE.
- [ ] **Decisão [W]** — re-clonar o staging (12 tabelas removidas pela minha migração acidental).
- [ ] **Decisão [W]** — bucket A segue no molde do #5214/#5216 (cadeia de wiring) ou vai direto pra Playwright.
- [ ] Achar o vazador de permissão que causa o 403 do `Onda23OcrBoletoTest` (bissecção com seed fixo).
- [ ] 8 arquivos do bucket A + os demais do bucket C ainda não triados.

## Referências

- Handoff: [2026-08-03-0734-triagem-quarentena-financeiro.md](../handoffs/2026-08-03-0734-triagem-quarentena-financeiro.md)
- Sessão anterior: [2026-08-02-b7-cobertura-conciliacao-quarentena-ledger.md](2026-08-02-b7-cobertura-conciliacao-quarentena-ledger.md)
- Lista: [`.github/financeiro-pest-quarantine.list`](../../.github/financeiro-pest-quarantine.list)
