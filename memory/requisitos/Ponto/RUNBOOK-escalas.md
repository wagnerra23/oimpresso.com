---
owner: W
last_validated: "2026-08-28"
slug: ponto-runbook-escalas
title: "Ponto — Runbook das Escalas (/ponto/escalas · Escalas/Index + Escalas/Form)"
type: runbook
module: Ponto
tela: Ponto/Escalas/Index
status: ativo
date: 2026-08-28
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
---

# RUNBOOK — Escalas de jornada (`Ponto/Escalas/Index` + `Ponto/Escalas/Form`)

> **Este RUNBOOK é RETROATIVO, e isso importa para ler o resto.** As duas Pages já estão em
> Inertia/React desde antes deste documento — o F3 do MWART não está pendente, está *feito*.
> Ele nasce porque o hook `block-mwart-violation` ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) §F1)
> barra `Edit` em `Pages/Ponto/Escalas/*.tsx` enquanto não existir `RUNBOOK-escalas.md` aqui,
> e o bloqueio **não tem escape**.
>
> Conteúdo **derivado** do SDD, do controller e do `.tsx`. Onde algo não foi medido, está dito
> que não foi. Anti-padrão inventado em RUNBOOK é pior que ausente: parece canon.

---

## 1. O que é a tela

Padrões de jornada do empregador. A escala é **o denominador da apuração**: é ela que diz quantos
minutos o dia "deveria" ter, e sem isso não existe atraso, hora extra nem saldo de banco.
`Form.tsx` serve **criação e edição** (medido: `isEdit` alterna o título entre "Nova escala" e
"Editar escala", `Form.tsx:78`).

| Rota | Método | Controller |
|---|---|---|
| `GET /ponto/escalas` | `index` | `EscalaController@index` |
| `GET /ponto/escalas/create` | `create` | idem → `Form` com `escala: null` |
| `POST /ponto/escalas` | `store` | idem |
| `GET /ponto/escalas/{id}/edit` | `edit` | idem → `Form` com turnos |
| `PUT /ponto/escalas/{id}` | `update` | idem (`StoreEscalaRequest`) |
| `DELETE /ponto/escalas/{id}` | `destroy` | idem |

Tipos aceitos (medidos no `validate()` do `store`): `FIXA`, `FLEXIVEL`, `ESCALA_12X36`,
`ESCALA_6X1`, `ESCALA_5X2`.

---

## 2. Duas inconsistências MEDIDAS — declaradas, não corrigidas

**(a) `Route::resource` declara `ponto.escalas.show`; o controller NÃO tem `show()`.** Varredura
contada dos métodos públicos de `EscalaController`: `index`, `create`, `store`, `edit`, `update`,
`destroy` — **seis, sem `show`**. A rota está registrada pelo `Route::resource` (`routes.php:73`).
⚠️ **Não exercitei a rota** — não afirmo qual o código de resposta; afirmo que o par
rota-declarada/método-ausente existe.

**(b) `store()` e `update()` validam DIFERENTE.** O `update` usa `StoreEscalaRequest`; o `store`
mantém validação inline mais frouxa (`carga_semanal_minutos` aceita `0`, enquanto o FormRequest
exige mínimo por CLT Art. 7º XIII). Isso **já está declarado no docblock do próprio controller**,
como follow-up consciente da US-PONTO-013 — não é achado novo meu, é dívida registrada.

Nenhuma das duas é regressão desta tela. **Não as conserte de carona** num PR de outra intenção.

---

## 3. Fontes canônicas (ordem de precedência)

Vale a regra-mestre de [proibicoes.md](../../proibicoes.md):
**teste verde citando o UC > `.casos.md` > `.charter.md` > `SPEC.md`**.

| Ordem | Fonte | Papel |
|---|---|---|
| 1 | [`SDD-espelho-e-jornada-v1.0.md`](SDD-espelho-e-jornada-v1.0.md) §1.1 (grupo Cadastro) · §9 D-1/D-8 (histórico dos atributos fantasma) | escopo e dívida |
| 2 | `Escalas/Form.casos.md` | contrato executável (UC) |
| 3 | `Escalas/{Index,Form}.charter.md` — **`status: draft`** | intenção-lei, não ratificada |
| 4 | [`SPEC.md`](SPEC.md) US-PONTO-012 · US-PONTO-013 | as duas US que consertaram esta tela |
| 5 | Lei: **CLT Art. 7º XIII** (jornada) · **Art. 59** (HE) | contrato de domínio |

⚠️ Como no Cadastro de colaboradores, **o SDD não tem fluxo F nem CU dedicado a Escalas** — e eu
**não inventei um**.

---

## 4. Estado MEDIDO em 2026-08-28

| Item | Estado |
|---|---|
| Ambas as Pages em Inertia/React sobre `AppShellV2` | ✅ |
| Charters | ✅ existem — ambos **`status: draft`** |
| `Form.casos.md` | ✅ existe |
| `Index.casos.md` | ❌ **não existe** (fora do escopo deste PR — ver nota abaixo) |
| Scorecards | ✅ `ponto-escalas-index.yaml` · `ponto-escalas-form.yaml` |
| Cor crua no `.tsx` | 1 por arquivo: `Index:50` e `Form:78`, ambas `text-stone-400` (padrão do `os-page-h`) |
| `HasBusinessScope` em `Escala` | ✅ aplicado (`Escala.php:22`) |
| `HasBusinessScope` em `EscalaTurno` | ❌ **ausente** — única das 10 entities (SDD §5.4 item 5) |

O `Index.casos.md` faltante **não é deste PR**: há decisão registrada de atacar primeiro os UC
órfãos. Mais contrato declarado com a mesma prova zero seria dobrar a aposta do presence-gate.

Cobertura **não é restateada aqui** — rode `npm run screen-coverage:report` e `npm run casos:report`.

---

## 5. Verificação

```bash
node .claude/hooks/block-mwart-violation.mjs   # path no stdin: rc=0 após este RUNBOOK
node scripts/memory-schemas/validate.mjs memory/requisitos/Ponto/RUNBOOK-escalas.md
npm run typecheck:baseline:check               # delta deve ser +0
npm run casos:check
```

Pest e PHPStan **não** rodam local nem no Hostinger — CT 100, sempre
([ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)).

⚠️ A escala é **o denominador do minuto de jornada**, que é valor (`[V0]`). Mudar carga diária,
carga semanal ou horário de turno **muda apuração retroativa**: cai na regra-mestre
**CÁLCULO DE VALOR ou ESTOQUE** de [proibicoes.md](../../proibicoes.md) — dupla prova + tabela
antes→depois ao [W] antes de aplicar.

---

## 6. Não fazer

- ❌ **Não "unificar" `store` e `update`** de carona noutro PR — ver §2(b). O `store` funciona e
  nenhum teste o cobre; mexer nele é mudar caminho vivo sem rede.
- ❌ **Não adicionar `show()` só para calar a rota** sem decidir o que a tela de detalhe mostra —
  ver §2(a). Rota órfã é dívida declarada, não convite a inventar tela.
- ❌ **Não ler `entrada`/`saida`/`almoco_inicio`/`almoco_fim` como coluna** de `EscalaTurno`. As
  colunas reais são `hora_*`; as chaves do payload é que são curtas. Isso já quebrou uma vez
  (US-PONTO-012: toda escala exibia horários vazios).
- ❌ **Não apagar escala em uso** sem checar impacto na apuração já calculada — não medi se
  `destroy()` tem guarda; **não afirmo que tem**.
- ❌ **Não promover os charters a `status: live`** — depende de [W] aprovar Non-Goals + Anti-hooks.
