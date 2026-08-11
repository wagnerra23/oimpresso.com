---
title: "Jana — pedido [CC] de ondas 5-12: o delta real, e os donos que já existem"
status: proposta
date: "2026-08-09"
owners: [W]
parent_module: Jana
related_adrs: [93, 104, 180, 264, 275, 344]
related_specs:
  - memory/requisitos/Jana/SPEC.md (US-COPI-010..014, 020, 021, 031, 040, 060, 061, 148)
related_charters:
  - resources/js/Pages/Jana/Index.charter.md
  - resources/js/Pages/Jana/Memoria.charter.md
  - resources/js/Pages/Jana/Pro.charter.md
---

# Jana — ondas 5-12: o delta real, e os donos que já existem

> ## ⚠️ As ondas 8, 10 e 11 do pedido **já têm dono**
>
> - **[`memory/requisitos/Jana/PLAN-MWART-metas.md`](../../requisitos/Jana/PLAN-MWART-metas.md)**
>   — 240 linhas, PLAN Fase 1 do [ADR 0104](../0104-processo-mwart-canonico-unico-caminho.md),
>   `status: draft-aguardando-aprovacao`, com reconciliação v2 de 2026-07-15. **É a onda 8**,
>   `superadmin/metas` incluído.
> - **11 US no [SPEC](../../requisitos/Jana/SPEC.md)** cobrem item a item (tabela §2).
>
> Este doc **não reescreve** as ondas 8/10/11 — aponta pros donos e guarda só o **delta**.
> Estender o dono, nunca abrir paralelo ([§5 2026-07-09](../../proibicoes.md)).

## 1. Origem e o que foi verificado

Pedido [CC] `JANA-ONDAS-PR-2026-08-09`, colado no chat por [W] em 2026-08-09. Continua o
`JANA-FUSAO-2026-08-06` (ondas 1-4, entregues — ver US-COPI-148) e diz substituir a tabela
de ondas do `JANA-FASE2-2026-08-07`.

**Medido em `origin/main` neste turno** (clone completo, `--is-shallow-repository=false`):

| Claim do pedido | Veredito |
|---|---|
| `S-1` `reapurar` não despacha (`// TODO`) | ✅ [`MetasController.php:81`](../../../Modules/Jana/Http/Controllers/MetasController.php) |
| `S-2` `updateConfig` não persiste | ✅ [`AlertasController.php:23`](../../../Modules/Jana/Http/Controllers/AlertasController.php) |
| `S-3` "STUB spec-ready" em 2 telas | ✅ docblocks + views |
| `S-4` `MetasController` documentado STUB | ✅ docblock da classe |
| `S-5` `destroy` sem gatilho em tela | ✅ rota viva, `index.blade.php` sem ação |
| 8 views Blade em `Resources/views/` | ✅ metas(4) · alertas(2) · fontes · superadmin |
| Fatias D e E **não** aplicadas | ✅ `_components/` só tem `AssistantUiChat`, `JanaCockpit`, `JanaDrillDrawer` |
| `casos.md` só em `Memoria` e `Pro` | ✅ |
| `B-7` superadmin "vaza número da plataforma" | 🟡 **meia-verdade** — ver §5 |

**Achado de bônus:** [`ChatController.php:623`](../../../Modules/Jana/Http/Controllers/ChatController.php)
já faz `ApurarMetaJob::dispatch($meta, now())`. O `S-1` tem implementação de referência
dois arquivos ao lado — não é capacidade nova.

## 2. Reconciliação — item do pedido → dono existente

| Pedido | Dono no SPEC | Âncora hoje |
|---|---|---|
| `B-1` metas/index → Inertia | `US-COPI-010` | ancorado (*"UI ainda Blade, não migrada pra Inertia"*) |
| `B-2` metas/show | `US-COPI-011` | `_parcial_` (*"série 12 janelas + projeção + farol dependem do render Blade"*) |
| `B-3` create + edit | `US-COPI-012` · `US-COPI-013` | `_parcial_` |
| `B-4` períodos ganham UI | `US-COPI-020` · `US-COPI-021` | ancorado / `_parcial_` |
| `B-5` alertas/index | `US-COPI-060` | `_parcial_` |
| `B-6` alertas/config | `US-COPI-061` | `_parcial_` |
| `B-8` fonte da meta | `US-COPI-040` | `_parcial_` |
| `S-1` reapurar no-op | `US-COPI-031` | `_parcial_` — diz literalmente *"o dispatch do ApurarMetaJob está comentado (// TODO)"* |
| `S-5` destroy sem gatilho | `US-COPI-014` | DoD já pede `AlertDialog "você tem certeza"` |
| `U-H` projeção e delta | `US-COPI-011` | **é o DoD dela**, não item novo |

Ou seja: **10 dos 14 itens das ondas 8/10/11 são US existentes**, várias com `_parcial_`
descrevendo exatamente o gap que o pedido apresenta como achado. Abrir onda nova por cima
seria a classe **LC-19** ([§5 2026-08-07](../../proibicoes.md) — *"o plano nasce paralelo
à US que já é dona"*), 3ª ocorrência catalogada dois dias antes deste pedido.

## 3. Três decisões da fila §5 do pedido **já estão respondidas** no canon

| # | Pergunta do pedido | Resposta que já existe |
|---|---|---|
| 2 | Meta: drawer × tela FOCO | `US-COPI-148`: *"**Fora da fusão** (ficam FOCO, sem abas): `/ia/pro` e `/ia/metas*`"* — [W] já ratificou **FOCO** em 2026-08-06 |
| 5 | Fonte: editor com preview × leitura auditada | DoD da `US-COPI-040`: *"preview do resultado antes de salvar; SQL roda em contexto `business_id` injetado"* — **editor com preview** |
| — | (não estava na fila) projeção/delta | DoD da `US-COPI-011`: *"série temporal últimas 12 janelas; projeção linear; farol"* |

Restam abertas as decisões **1** (HITL), **3** (alertas), **4** (superadmin), **6** (gating), **7** (permissões).

## 4. Bloqueador de processo que o pedido não vê — MWART

Ondas 8 e 10 são Blade→Inertia, logo **MWART** ([ADR 0104](../0104-processo-mwart-canonico-unico-caminho.md),
5 fases, caminho único). Medido: existem **15 RUNBOOKs** em `memory/requisitos/Jana/`, e
**nenhum** é `RUNBOOK-metas.md` ou `RUNBOOK-alertas.md`.

O hook `block-mwart-violation` barra o primeiro `Edit` em `resources/js/Pages/Jana/Metas/*.tsx`.
⚠️ E o `/mwart-override` que a mensagem dele anuncia **não existe no código** — 194 linhas,
zero `process.env`, zero bypass ([§5 2026-08-08](../../proibicoes.md), classe LC-15). Ou seja:
**onda 8 está travada até nascer o RUNBOOK**, e não há escape.

## 5. Correção ao `B-7`

O pedido diz que o empty state *"vaza número da plataforma na tela"*. O número **já está
redigido no git** — [`superadmin/metas.blade.php:13`](../../../Modules/Jana/Resources/views/superadmin/metas.blade.php)
lê `R$ [redacted Tier 0]mi/ano`, resquício do `git filter-repo` de 2026-06-08.

Não há vazamento. Há uma **string quebrada** renderizada pro superadmin. A remediação que o
pedido propõe (tirar da tela) continua certa — por outro motivo, e sem urgência Tier 0.

## 6. O delta real — o que este pedido acrescenta e não tem dono

1. **`S-3`** — "STUB spec-ready" renderizado pro cliente. Nenhuma US cobre. É higiene, e é
   a única coisa da onda 5 que não é US existente.
2. **`N-1`** — namespace de view `copiloto::` num módulo chamado `Jana`.
3. **`N-2`** — `jana.mcp.*` / `jana.cc.*` apontam pra telas que hoje são TeamMcp/Forja/Governance.
   ADR + migration próprios; **não pega carona em PR de UI**.
4. **`G-1`..`G-4`** — trio por tela nova, contrato de tela, `jana:health-check` acusando Blade
   viva em `/ia/*`, `prototipo-readiness`.
5. **A ordenação em ondas** — verdade-nos-botões antes de migração de tela é sequenciamento
   bom e é o principal valor do pedido.
6. **Fatias `U-D`..`U-L`** — vêm do `JANA-FASE2-2026-08-07.md`, que **não está no repo**
   (`git ls-files` só acha os 4 artefatos da FUSAO). Sem o doc, não dá pra reconciliar
   contra US: fica declarado como não-verificado.

## 7. Recomendação

- **Onda 5 executa agora** — é a única sem cláusula de entrada, e o `S-1`/`S-2` fecham gap
  de US existente (031 e 061), então é *pagar dívida ancorada*, não abrir escopo.
- **Ondas 8/10/11 não abrem como ondas.** Viram: (a) aprovar ou aposentar o
  `PLAN-MWART-metas.md`, que está `draft-aguardando-aprovacao` desde 2026-05-09; (b) criar
  `RUNBOOK-metas.md` (destrava o hook); (c) trabalhar as US pelos ids que já existem.
- **Ondas 6/7/12** dependem do `JANA-FASE2` entrar no repo ou ser reescrito.

## 8. Limite honesto

Não conferi arquivo a arquivo as fatias `F`–`L` do FASE2 (o doc não está no repo). O
`tree sha` citado no pedido (`f1a9606cca64`) **é** o commit `HEAD` de `origin/main` no
momento desta leitura, não um tree sha — mas confere com o que o pedido descreve.
