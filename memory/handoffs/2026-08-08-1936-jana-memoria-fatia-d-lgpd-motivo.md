---
date: "2026-08-08"
time: "19:36 BRT"
slug: jana-memoria-fatia-d-lgpd-motivo
tldr: "Fatia D da lista protótipo × produção da Jana entregue e MERGEADA em 3 PRs empilhados que o [W] colapsou. O item 1 não era divergência de design: o charter mandava registrar motivo no activitylog desde 2026-05-16, o código validava só `fato` e nenhum teste mordia — lei que valia zero em produção. Junto, o trio da tela nasceu (ela estava live desde 2026-04 sem F1 PLAN)."
prs: [5404, 5408, 5414]
decided_by: [W]
related_adrs:
  - 0253-primitivos-layout
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
  - 0364-trio-de-tela-mora-em-memory-emenda-0264
next_steps:
  - "R1: smoke real em /ia/memoria pós-deploy com screenshot 1280px (passo 6 do RUNBOOK-memoria.md) — NÃO feito"
  - "Rodar o Benchmark do §11 do PROCESSO_MEMORIA_CC — IT5 está STALE e avermelha QUALQUER PR de design-memory"
  - "Decidir edição parcial (categoria/relevância) e o naming Copiloto→Jana — abertos no Memoria.casos.md"
---

# Fatia D da Jana — a lei do charter que valia zero, e o trio que faltava

## Estado MCP no momento do fechamento

- `cycles-active`: **nenhum cycle ATIVO em COPI**
- `my-work` (@wagner): 8 tasks, **todas em REVIEW**, nenhuma da fatia D — este trabalho não tinha task
- `decisions-search`: nenhuma ADR nova nesta sessão
- Handoffs irmãos do dia: [07-1840 lanes required](2026-08-07-1840-lanes-required-vermelhas-e-quarentena.md) · [07-1524 fusão da Jana](2026-08-07-1524-jana-fusao-fechada-e-5-chips.md) (é dele que a lista de diferenças veio)

## O que aconteceu

**#5404 MERGED** (`eec278dde1c`, 14:01Z) carregando as três fatias — o [W] colapsou a pilha mergeando #5414 → #5408 → #5404.

O **item 1 não era divergência de design, era regressão silenciosa de compliance.** O `Memoria.charter.md` manda, desde **2026-05-16**, *"editar fato inline com `activitylog` registrando autor/quando/**motivo**"* e proíbe *"update direto sem activitylog"*. Medido: o `useForm` mandava **só `fato`** (0 hits de motivo), o Controller validava **só `fato`**, e **nenhum teste mordia**. É o caso do charter que não é lei porque ninguém o executa — e o protótipo dizia o mesmo na cara do usuário.

Agora o servidor **rejeita** edição sem motivo, a trilha sai em `activity_log` (`jana_memoria_fato_editado`/`_esquecido`) com autor + motivo **redigido por `PiiRedactor`**, e o texto do fato **continua fora do log** — respeitando a decisão que a entity já tinha tomado (`logOnly([...])`, *"NÃO logga fato/metadata (PII livre)"*). Incluí a trilha do **esquecer** por decisão minha: auditoria com edição e sem exclusão é auditoria quebrada — dava pra apagar um fato sem rastro.

**Decisões [W] no meio:** relevância **fica `/10`** (a produção é a fonte; o protótipo 1–5 é que se adapta — mudar seria migração de `metadata.relevancia`, sem razão de domínio) e **renderizar `origem`**, que o charter exigia no Goal 4 e a prod não mostrava. A 1ª ficou registrada como **"não re-propor"**: divergência protótipo×prod não é, por si, motivo pra mexer em dado persistido.

## Achados que não eram o pedido

| achado | prova |
|---|---|
| **`tests/Feature/Modules/Copiloto/MemoriaControllerTest.php` não roda em lane nenhuma** (LC-13) | ausente do `ci-sqlite-pest.list` (149 alvos); por isso ficou verde **anos** afirmando rotas `copiloto.memoria.*` que **nunca existiram** (as reais são `jana.memoria.*`). Corrigi só as 2 mentiras que meu PR criaria; religar é intent separado — e virá **vermelho**: o 1º caso grava em `businessId: 1` e lê em `listar(4, …)` esperando **achar**, ou seja documenta que o `NullMemoriaDriver` ignora `business_id` |
| **`US-COPI-MEM-005/008/012` são ids fantasma** | 0 hits no SPEC da Jana — mesmo padrão do `US-JANA-PAINEL-001` que a onda 1 da US-COPI-148 pegou. Removidos; **nada** inventado no lugar |
| **lane `kb-pest` é FLAKY** | mesma branch, mesmo PR: 08-07 falhou `KbIndexV2ContractTest > V2c /sops`; 08-08 falhou **outro** teste, `KbNodeBodyReaderTest > L2`, mesma forma (1 failed · 14 skipped · 105 passed). E o diff entre o último verde e o vermelho **não tocava nada** no caminho do `/sops` |
| **`IT5 — benchmark STALE` é do REPO, não do PR** | extraí `prototipo-ui` do `origin/main` **puro** e o IT5 falha idêntico (última medição 2026-07-09, 31d > 30d). Vai avermelhar **qualquer** PR de design-memory até alguém rodar o Benchmark do §11 |

## Gates que me pegaram — e os 3 estavam certos

- **`Layout primitives · ratchet` (ADR 0253)** — `Memoria.tsx` foi de **5 → 7** flex soltos com a minha fatia. **Não usei `--write-baseline`**: não havia refator consciente a absorver, era dívida nascida no PR com o primitivo já existindo. Converti pro `<Inline>` ⇒ arquivo **7 → 4** (abaixo do baseline) e repo **2039 → 2036**.
- **`Casos-coverage · ratchet` (required)** — `stale:Memoria.casos.md`. É a **emenda §5 2026-07-27** em pessoa: o G-6 mede **data-git** do `.tsx`, então o fix do `<Inline>` (semanticamente inerte) acordou o gate. Bumpei o `last_run` — convenção do repo. **Não** "consertei" o G-6 pra olhar conteúdo: medir data é desenho conservador consciente.
- **`SUPERFICIE.md == árvore` (required)** — meu teste novo entrou na superfície do módulo. Regenerado.

## Erros meus — três, e são a MESMA família

**LC-08.** Nas três eu perguntei ao instrumento errado e o número falso era *plausível*:

1. `node ... | tail -8; echo exit=$?` → medi o exit do **`tail`**, e reportei `exit=0` enquanto a saída dizia `❌ 1 falha(s)`.
2. `... && grep -c "X" && node module-surface; echo exit=$?` → o `grep -c` deu 0 ⇒ exit 1 ⇒ o **`&&` abortou a cadeia**, o `module-surface` **nunca rodou**, e o "exit=1" que reportei era do grep.
3. O próprio `grep -c "MemoriaEdicaoMotivoTest" SUPERFICIE.md` → o arquivo **não lista nome de teste**, ele agrega por pasta. Perguntei ao arquivo algo que ele não responde e li o "0" como se significasse algo.

**Uma hipótese minha que a medição derrubou:** atribuí o vermelho do KB aos commits `fix(permissoes)` que entraram no main. **Falso** — são de ~14:00 e minha branch rodou verde às 19:39, cinco horas depois, já com eles dentro.

**E uma correção de raciocínio, não de código:** declarei o `charter related_us` vermelho como "esperado, linkar seria teatro". Estava **errado** — confundi *"que US implementa esta mudança"* com o que o campo mede, *"User Stories que esta tela **atende**"*. A `US-COPI-148` atende de fato (é dona da aba Memória e o DoD dela pede o `casos.md` por aba). Errata no version log do charter, não apagada.

## O pedágio da fila (vale pra próxima sessão)

O `Preflight + contratos ativos` exige `origin/main` **ancestral** do HEAD. Medido: o main anda **16 commits em 3h** (~1 a cada 11 min) e a fila do CI chegou a **335 runs**. Resultado: reconciliei **5×** e o check reprovou de novo entre uma e outra — corrida que se perde por construção enquanto o PR espera. **Todos os conflitos foram nos 2 arquivos gerados** (`casos-coverage-baseline.json`, `SUPERFICIE.md`) e resolvi **regenerando da árvore**, nunca escolhendo lado (§5 2026-07-28). O conflito com `jana-drill-down` no `SUPERFICIE.md` foi previsto no turno anterior e caiu exatamente assim.

## Artefatos

| arquivo | o quê |
|---|---|
| [`memory/requisitos/Jana/RUNBOOK-memoria.md`](../requisitos/Jana/RUNBOOK-memoria.md) | **novo** · 102 ln · a tela estava live desde 2026-04 **sem** F1 PLAN. Declara validação **estática**; fluxo vivo **NÃO** exercitado |
| [`Memoria.casos.md`](../../resources/js/Pages/Jana/Memoria.casos.md) | **novo** · `UC-MEM-01..05` derivados do charter + protótipo, **nunca** do `.tsx` |
| `Modules/Jana/Tests/Feature/Memoria/MemoriaEdicaoMotivoTest.php` | **novo** · lane `jana-pest.yml` (MySQL real), ligado nos **3** pontos (filtro do PR + `push: paths` + lista) — o Controller mora em `Modules/KB`, fora do `Modules/Jana/**`, então sem o path seria **gate mudo** |
| `MemoriaController.php` · `Memoria.tsx` · `Memoria.charter.md` (v2) | o código + a lei reconciliada |

⚠️ **Quando a Jana flipar pra [ADR 0364](../decisions/0364-trio-de-tela-mora-em-memory-emenda-0264.md)** (`memory/requisitos/<Mod>/_telas/`), charter + casos da Memória vão junto. Hoje a Jana **não tem** `_telas/` e o flip por módulo exige smoke + adversário + [W] — por isso o trio nasceu ao lado do `.tsx`, e o `casos-gate` verde confirma.

## Próximos passos pra retomar

```
brief-fetch && gh pr view 5404 --json mergedAt && cat memory/requisitos/Jana/RUNBOOK-memoria.md
```

Depois: **smoke real em `/ia/memoria`** (browser MCP, 1280px, console sem `EXCEPTION`) — passo 6 do RUNBOOK. **Não foi feito**; sem ele a fatia D não está provada em produção, só em CI.
