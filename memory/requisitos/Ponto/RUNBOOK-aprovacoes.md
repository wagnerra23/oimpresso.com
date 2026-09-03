---
owner: W
last_validated: "2026-08-28"
slug: ponto-runbook-aprovacoes
title: "Ponto — Runbook das Aprovações (/ponto/aprovacoes · Aprovacoes/Index)"
type: runbook
module: Ponto
tela: Ponto/Aprovacoes/Index
status: ativo
date: 2026-08-28
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
---

# RUNBOOK — Aprovações de intercorrência (`Ponto/Aprovacoes/Index`)

> **Este RUNBOOK é RETROATIVO, e isso importa para ler o resto.** A tela já está em
> Inertia/React desde antes deste documento — o F3 do MWART não está pendente, está *feito*.
> Ele nasce porque o hook `block-mwart-violation` ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) §F1)
> barra `Edit` em `Pages/Ponto/Aprovacoes/*.tsx` enquanto não existir `RUNBOOK-aprovacoes.md`
> aqui, e o bloqueio **não tem escape** — a mensagem do próprio hook diz isso.
>
> Conteúdo **derivado** do SDD, do controller e do `.tsx`. Onde algo não foi medido, está dito
> que não foi. Anti-padrão inventado em RUNBOOK é pior que ausente: parece canon.

---

## 1. O que é a tela

Fila de decisão hierárquica das intercorrências: o gestor vê o que está pendente, prioriza e
**aprova, rejeita ou decide em lote**. É o outro lado do `Intercorrencias/Create` — quem pede é
o colaborador, quem decide é esta tela.

| Rota | Método | Controller |
|---|---|---|
| `GET /ponto/aprovacoes` | `index` | `AprovacaoController@index` |
| `POST /ponto/aprovacoes/{id}/aprovar` | `aprovar` | idem |
| `POST /ponto/aprovacoes/{id}/rejeitar` | `rejeitar` | idem |
| `POST /ponto/aprovacoes/lote` | `aprovarEmLote` | idem |

Título renderizado (`Index.tsx:253`): **"Aprovações · Intercorrências"**. SubNav ativo: `aprovacoes`.

Duas props são `Inertia::defer` (`aprovacoes` paginado 20 + `contagens` de 6 buckets); `filtros`
e `tipos` ficam eager por serem estado de UI — o padrão do
[RUNBOOK-inertia-defer-pattern](../_DesignSystem/RUNBOOK-inertia-defer-pattern.md).

Ordenação declarada no controller: `FIELD(prioridade,'URGENTE','NORMAL')`, depois `created_at` desc.

---

## 2. Fontes canônicas (ordem de precedência)

Vale a regra-mestre de [proibicoes.md](../../proibicoes.md):
**teste verde citando o UC > `.casos.md` > `.charter.md` > `SPEC.md`**.

| Ordem | Fonte | Papel |
|---|---|---|
| 1 | [`SDD-espelho-e-jornada-v1.0.md`](SDD-espelho-e-jornada-v1.0.md) §5.3 **F5** e §6.2 **CU-PONTO-06/07** | fluxo e casos de uso |
| 2 | `Aprovacoes/Index.casos.md` | contrato executável (UC) |
| 3 | `Aprovacoes/Index.charter.md` — **`status: draft`** | intenção-lei, ainda não ratificada |
| 4 | [`SPEC.md`](SPEC.md) US-PONTO-003 (estados canon) · US-PONTO-007 (isolamento) | escopo |

O charter aponta `related_prototype: prototipo-ui/cowork/ponto-telas.jsx` (existe na árvore).

---

## 3. Estado MEDIDO em 2026-08-28

| Item | Estado |
|---|---|
| Tela em Inertia/React sobre `AppShellV2` | ✅ |
| `Index.charter.md` | ✅ existe — **`status: draft`** |
| `Index.casos.md` | ✅ existe |
| Scorecard | ✅ `ponto-aprovacoes-index.yaml` |
| Cor crua no `.tsx` | 1 ocorrência: `:253 text-stone-400` (subtítulo do `os-page-h`) |

⚠️ **O `text-stone-400` NÃO é defeito desta tela** — medido: ele aparece **em 19 de 19** Pages do
Ponto, sempre no `<span>` de subtítulo do header. É padrão sistemático do `os-page-h`, não desvio
local; tratá-lo tela-a-tela seria consertar o sintoma no lugar errado.

Números de cobertura (E2E, a11y, VRT, casos) **não são restateados aqui** — rode as portas vivas
`npm run screen-coverage:report` e `npm run casos:report` (§5 2026-07-17: doc canônico não repete
número que outro sistema sabe melhor).

---

## 4. O ponto de atenção que herda deste fluxo

O SDD §5.3 F5 registra, e eu confirmei lendo o controller: `aprovar`, `rejeitar` e `aprovarEmLote`
usam `Intercorrencia::findOrFail($id)` **sem** `where('business_id')`. A defesa é o global scope do
trait `HasBusinessScope` na entity — **verifiquei que ele está aplicado** (`Intercorrencia.php:23`).

Funciona, e **não é bug hoje**. Mas é **defesa única**: se o trait sair da entity, os três handlers
passam a decidir intercorrência de outro tenant. É por isso que o SDD marca `CU-PONTO-07` como
`[T0]`. Quem for mexer nestes handlers mexe num caminho Tier 0
([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).

---

## 5. Verificação

```bash
node .claude/hooks/block-mwart-violation.mjs   # path no stdin: rc=0 após este RUNBOOK
node scripts/memory-schemas/validate.mjs memory/requisitos/Ponto/RUNBOOK-aprovacoes.md
npm run typecheck:baseline:check               # delta deve ser +0
npm run casos:check
```

Pest e PHPStan **não** rodam local nem no Hostinger — CT 100, sempre
([ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)).

---

## 6. Não fazer

- ❌ **Não remover o `motivo` obrigatório da rejeição.** É `required|string|max:500` no controller e
  âncora de `CU-PONTO-06` ("rejeitar exige motivo"). Rejeição sem justificativa quebra a trilha.
- ❌ **Não trocar `findOrFail` por busca sem escopo nem adicionar `withoutGlobalScopes()`** nestes
  handlers — ver §4. Se precisar, é decisão [W] com teste `[T0]` junto.
- ❌ **Não deixar a IA decidir.** O `IntercorrenciaAIClassifier` **sugere** tipo/prioridade no
  `Create`; o estado só muda por ação humana (SDD §5.3 F4).
- ❌ **Não promover o charter a `status: live`** — depende de [W] aprovar Non-Goals + Anti-hooks.
