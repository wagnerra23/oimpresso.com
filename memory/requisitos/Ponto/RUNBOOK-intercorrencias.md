---
owner: W
last_validated: "2026-08-28"
slug: ponto-runbook-intercorrencias
title: "Ponto — Runbook das Intercorrências (/ponto/intercorrencias · Intercorrencias/Index + Create + Show)"
type: runbook
module: Ponto
tela: Ponto/Intercorrencias/Index
status: ativo
date: 2026-08-28
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
---

# RUNBOOK — Intercorrências (`Ponto/Intercorrencias/{Index,Create,Show}`)

> **Este RUNBOOK é RETROATIVO, e isso importa para ler o resto.** As três Pages já estão em
> Inertia/React desde antes deste documento — o F3 do MWART não está pendente, está *feito*.
> Ele nasce porque o hook `block-mwart-violation` ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) §F1)
> barra `Edit` em `Pages/Ponto/Intercorrencias/*.tsx` enquanto não existir
> `RUNBOOK-intercorrencias.md` aqui, e o bloqueio **não tem escape**.
>
> ⚠️ **Mas atenção: neste grupo o F3 está feito PELA METADE** — ver §2. É a única família de telas
> do módulo onde isso vale, e nenhum outro RUNBOOK deste módulo diz isso.

---

## 1. O que é a tela

Justificativa de ausência/ajuste: o colaborador (ou o DP por ele) registra a ocorrência, e ela
segue para decisão em `Aprovacoes/Index`. Ciclo canônico de estados:

```
RASCUNHO → PENDENTE → APROVADA | REJEITADA → APLICADA
                          ↘ CANCELADA
```

| Rota | Método | Renderiza |
|---|---|---|
| `GET /ponto/intercorrencias` | `index` | **Inertia** `Intercorrencias/Index` |
| `GET /ponto/intercorrencias/create` | `create` | **Inertia** `Intercorrencias/Create` |
| `POST /ponto/intercorrencias` | `store` | redirect → `show` |
| `GET /ponto/intercorrencias/{id}` | `show` | **Inertia** `Intercorrencias/Show` |
| `GET /ponto/intercorrencias/{id}/edit` | `edit` | 🔴 **Blade** `pontowr2::intercorrencias.edit` |
| `PUT /ponto/intercorrencias/{id}` | `update` | redirect → `show` |
| `POST .../{id}/submeter` · `.../{id}/cancelar` | — | transições de estado |
| `POST /ponto/intercorrencias-ai/classify` | `aiClassify` | JSON (`throttle:10,1`) |

Os 8 tipos aceitos estão **duplicados literalmente** no `AprovacaoController@index` e no
`IntercorrenciaController@create` (medido: as duas listas são idênticas — `CONSULTA_MEDICA`,
`ATESTADO_MEDICO`, `REUNIAO_EXTERNA`, `VISITA_CLIENTE`, `HORA_EXTRA_AUTORIZADA`,
`ESQUECIMENTO_MARCACAO`, `PROBLEMA_EQUIPAMENTO`, `OUTRO`). **Mexeu numa, confira a outra.**

---

## 2. 🔴 A dívida que define este grupo: a EDIÇÃO ainda é Blade

`Route::resource` expõe `GET /ponto/intercorrencias/{id}/edit` → `IntercorrenciaController@edit`,
e esse método retorna **`view('pontowr2::intercorrencias.edit')`**, não `Inertia::render`.
Confirmei que o Blade existe: `Modules/Ponto/Resources/views/intercorrencias/edit.blade.php`.
**Não existe `Intercorrencias/Edit.tsx`** na árvore.

Consequência operacional: **o operador que clica "editar" num rascunho sai do shell React e cai no
AdminLTE.** O SDD §5.4 item 1 registra a varredura contada: **21 renders nos controllers = 20
Inertia + 1 Blade** — este é o 1.

Corolários para quem for mexer aqui:

- Este RUNBOOK **destrava as três Pages Inertia**. Ele **não** autoriza inventar a `Edit.tsx`:
  migrar essa tela é F1→F5 do MWART com decisão de escopo, não carona.
- `edit()` tem `abort_unless($estado === RASCUNHO, 403)` — **só rascunho é editável**, e isso é
  âncora de `CU-PONTO-05`. Vale para qualquer sucessora React.
- Uma segunda inconsistência medida: `Route::resource` declara `ponto.intercorrencias.destroy`, mas
  `IntercorrenciaController` **não tem `destroy()`** (varredura dos métodos públicos: `index`,
  `create`, `store`, `show`, `edit`, `update`, `submeter`, `cancelar`, `aiClassify`). ⚠️ **Não
  exercitei a rota** — afirmo o par rota-declarada/método-ausente, não o código de resposta.
  Provável intenção: cancelamento é por `cancelar`, não por `DELETE` — mas isso é leitura minha,
  **não está escrito em canon nenhum**.

---

## 3. Fontes canônicas (ordem de precedência)

Vale a regra-mestre de [proibicoes.md](../../proibicoes.md):
**teste verde citando o UC > `.casos.md` > `.charter.md` > `SPEC.md`**.

| Ordem | Fonte | Papel |
|---|---|---|
| 1 | [`SDD-espelho-e-jornada-v1.0.md`](SDD-espelho-e-jornada-v1.0.md) §5.3 **F4**, §5.4 item 1, §6.2 **CU-PONTO-05** | fluxo, dívida e caso de uso |
| 2 | `Intercorrencias/{Index,Create,Show}.casos.md` | contrato executável (UC) |
| 3 | `Intercorrencias/{Index,Create,Show}.charter.md` — **`status: draft`** | intenção-lei, não ratificada |
| 4 | [`SPEC.md`](SPEC.md) US-PONTO-003 (estados canon) · US-PONTO-013 | escopo |
| 5 | `Modules/Ponto/Resources/views/intercorrencias/*.blade.php` | **contrato de paridade** do legado |

---

## 4. Estado MEDIDO em 2026-08-28

| Item | Estado |
|---|---|
| `Index` · `Create` · `Show` em Inertia/React | ✅ |
| `Edit` | 🔴 **Blade** — ver §2 |
| Charters das três Inertia | ✅ existem — os três **`status: draft`** |
| `casos.md` das três | ✅ existem |
| Scorecards | ✅ `ponto-intercorrencias-{index,create,show}.yaml` |
| Cor crua no `.tsx` | 1 por arquivo (`Index:104`, `Create:160`, `Show:77`), todas `text-stone-400` (padrão do `os-page-h`) |
| `HasBusinessScope` em `Intercorrencia` | ✅ aplicado (`Intercorrencia.php:23`) |

Cobertura **não é restateada aqui** — rode `npm run screen-coverage:report` e `npm run casos:report`.

---

## 5. Verificação

```bash
node .claude/hooks/block-mwart-violation.mjs   # path no stdin: rc=0 após este RUNBOOK
node scripts/memory-schemas/validate.mjs memory/requisitos/Ponto/RUNBOOK-intercorrencias.md
npm run typecheck:baseline:check               # delta deve ser +0
npm run casos:check
```

Pest e PHPStan **não** rodam local nem no Hostinger — CT 100, sempre
([ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)).

---

## 6. Não fazer

- ❌ **Não migrar a `Edit` Blade de carona** neste ou noutro PR de outra intenção — ver §2. É F1→F5
  do MWART com RUNBOOK e decisão de escopo próprios.
- ❌ **Não deixar a IA mudar estado.** `aiClassify` **sugere** tipo/prioridade a partir de texto
  livre; o estado só muda por ação humana (SDD §5.3 F4). O `throttle:10,1` é parte do contrato.
- ❌ **Não permitir editar intercorrência fora de `RASCUNHO`** — o `abort_unless` é âncora de
  `CU-PONTO-05`, não zelo opcional.
- ❌ **Não aceitar `business_id` vindo do request body.** Ele é injetado pelo controller via
  `session`/`auth` — padrão anti-tampering cross-tenant do módulo
  ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)). Isso já quebrou uma vez: a
  coluna é NOT NULL e registrar intercorrência pela tela simplesmente não gravava (US-PONTO-013).
- ❌ **Não promover os charters a `status: live`** — depende de [W] aprovar Non-Goals + Anti-hooks.
