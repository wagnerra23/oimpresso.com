---
owner: W
last_validated: "2026-08-28"
slug: ponto-runbook-banco-horas
title: "Ponto — Runbook do Banco de Horas (/ponto/banco-horas · BancoHoras/Index + BancoHoras/Show)"
type: runbook
module: Ponto
tela: Ponto/BancoHoras/Index
status: ativo
date: 2026-08-28
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
---

# RUNBOOK — Banco de Horas (`Ponto/BancoHoras/Index` + `Ponto/BancoHoras/Show`)

> **Este RUNBOOK é RETROATIVO, e isso importa para ler o resto.** As duas Pages já estão em
> Inertia/React desde antes deste documento — o F3 do MWART não está pendente, está *feito*.
> Ele nasce porque o hook `block-mwart-violation` ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) §F1)
> barra `Edit` em `Pages/Ponto/BancoHoras/*.tsx` enquanto não existir `RUNBOOK-banco-horas.md`
> aqui, e o bloqueio **não tem escape**.
>
> Conteúdo **derivado** do SDD, do controller e do `.tsx`. Onde algo não foi medido, está dito
> que não foi. Anti-padrão inventado em RUNBOOK é pior que ausente: parece canon.

---

## 1. O que é a tela

Saldo de horas por colaborador (`Index`) e o **extrato/ledger** individual (`Show`). O ledger é
**append-only por lei de domínio**: ajustar saldo é *acrescentar movimento*, nunca editar o anterior
— o extrato é prova (CLT Art. 59 §5º).

| Rota | Método | Controller |
|---|---|---|
| `GET /ponto/banco-horas` | `index` | `BancoHorasController@index` |
| `GET /ponto/banco-horas/{colaborador}` | `show` | idem |
| `POST /ponto/banco-horas/{colaborador}/ajuste` | `ajustarManual` | idem → `BancoHorasService` |

Títulos renderizados: **"Banco de Horas · Ledger append-only"** (`Index.tsx:69`) e
**"Banco de Horas · {nome}"** (`Show.tsx:100`). SubNav ativo: `banco-horas`.

`Index` difere `saldos` (paginado 30, `orderByDesc(saldo_minutos)`) e `totais` (4 agregações:
crédito/débito e contagem de colaboradores em cada lado). `Show` mantém o cabeçalho de saldo
**eager** — ele já foi materializado pelo `firstOrFail()` que valida o tenant — e difere os
`movimentos` (paginado 50).

---

## 2. Fontes canônicas (ordem de precedência)

Vale a regra-mestre de [proibicoes.md](../../proibicoes.md):
**teste verde citando o UC > `.casos.md` > `.charter.md` > `SPEC.md`**.

| Ordem | Fonte | Papel |
|---|---|---|
| 1 | [`SDD-espelho-e-jornada-v1.0.md`](SDD-espelho-e-jornada-v1.0.md) §5.3 **F6** e §6.3 **CU-PONTO-08/09** | fluxo e casos de uso |
| 2 | `BancoHoras/{Index,Show}.casos.md` | contrato executável (UC) |
| 3 | `BancoHoras/{Index,Show}.charter.md` — **`status: draft`** | intenção-lei, não ratificada |
| 4 | [`SPEC.md`](SPEC.md) US-PONTO-004 (saldo + créditos/débitos) · US-PONTO-008 (append-only) · US-PONTO-011 | escopo |
| 5 | Lei: **CLT Art. 59 §5º** (compensação) | contrato de domínio |

---

## 3. Estado MEDIDO em 2026-08-28

| Item | Estado |
|---|---|
| Ambas as Pages em Inertia/React sobre `AppShellV2` | ✅ |
| Charters | ✅ existem — ambos **`status: draft`** |
| `Index.casos.md` · `Show.casos.md` | ✅ existem |
| Scorecards | ✅ `ponto-bancohoras-index.yaml` · `ponto-bancohoras-show.yaml` |
| Cor crua no `.tsx` | 1 por arquivo: `Index:69` e `Show:100`, ambas `text-stone-400` |

⚠️ O `text-stone-400` é **padrão sistemático do `os-page-h`** (19 de 19 Pages do Ponto), não desvio
local desta tela.

Cobertura (E2E, a11y, VRT) **não é restateada aqui** — rode `npm run screen-coverage:report` e
`npm run casos:report` (§5 2026-07-17).

---

## 4. Os dois pontos de atenção deste fluxo

**(a) Append-only tem UMA camada, não duas.** O SDD §5.4 item 6 registra: o ledger de banco de horas
é protegido por *override Eloquent*, **sem trigger MySQL** — diferente de `ponto_marcacoes`, que tem
as duas. Consequência declarada: **SQL cru ainda edita**. Não é regressão desta tela; é dívida do
módulo, com US própria (US-PONTO-011 no SPEC). Não "resolva" isso pela UI.

**(b) Tenant pelo global scope, como no F5.** `show()` faz
`BancoHorasSaldo::where('colaborador_config_id', $id)->firstOrFail()` **sem** `business_id` explícito.
Verifiquei: `BancoHorasSaldo` e `BancoHorasMovimento` **têm** `HasBusinessScope` aplicado
(`:18` e `:29`). Funciona — e é defesa única, igual ao caso das Aprovações
([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).

---

## 5. Verificação

```bash
node .claude/hooks/block-mwart-violation.mjs   # path no stdin: rc=0 após este RUNBOOK
node scripts/memory-schemas/validate.mjs memory/requisitos/Ponto/RUNBOOK-banco-horas.md
npm run typecheck:baseline:check               # delta deve ser +0
npm run casos:check
```

Pest e PHPStan **não** rodam local nem no Hostinger — CT 100, sempre
([ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)).

⚠️ Esta tela mostra **minuto de jornada, que é valor** (marca `[V0]` do SDD §3.2). Mudança que possa
alterar saldo, sinal ou agregação cai na regra-mestre **CÁLCULO DE VALOR ou ESTOQUE** de
[proibicoes.md](../../proibicoes.md): dupla prova por caminhos independentes + tabela antes→depois
apresentada ao [W] **antes** de aplicar.

---

## 6. Não fazer

- ❌ **Não permitir editar ou apagar movimento do ledger.** Ajuste é **novo** movimento
  (`CU-PONTO-09`, `[V0]` `[T0]`). Movimento existente é prova.
- ❌ **Não tornar a `observacao` do ajuste opcional** — é `required|string|max:500` no controller e
  é o que dá rastreabilidade ao ajuste manual.
- ❌ **Não converter saldo em dinheiro nesta tela.** Folha de pagamento é Non-Goal declarado do
  módulo (SDD §6.6); o handoff é via eSocial.
- ❌ **Não promover os charters a `status: live`** — depende de [W] aprovar Non-Goals + Anti-hooks.
