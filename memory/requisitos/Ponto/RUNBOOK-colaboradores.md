---
owner: W
last_validated: "2026-08-28"
slug: ponto-runbook-colaboradores
title: "Ponto — Runbook dos Colaboradores (/ponto/colaboradores · Colaboradores/Index + Colaboradores/Edit)"
type: runbook
module: Ponto
tela: Ponto/Colaboradores/Index
status: ativo
date: 2026-08-28
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
---

# RUNBOOK — Colaboradores do Ponto (`Ponto/Colaboradores/Index` + `Ponto/Colaboradores/Edit`)

> **Este RUNBOOK é RETROATIVO, e isso importa para ler o resto.** As duas Pages já estão em
> Inertia/React desde antes deste documento — o F3 do MWART não está pendente, está *feito*.
> Ele nasce porque o hook `block-mwart-violation` ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) §F1)
> barra `Edit` em `Pages/Ponto/Colaboradores/*.tsx` enquanto não existir
> `RUNBOOK-colaboradores.md` aqui, e o bloqueio **não tem escape**.
>
> Conteúdo **derivado** do SDD, do controller e do `.tsx`. Onde algo não foi medido, está dito
> que não foi. Anti-padrão inventado em RUNBOOK é pior que ausente: parece canon.

---

## 1. O que é a tela

Cadastro de **quem controla ponto e sob qual escala** — no vocabulário do SDD §1.1, o grupo
"Cadastro". Não cria pessoa: a pessoa já é `user` do UltimatePOS; aqui se configura a **faceta de
ponto** dela (`ponto_colaborador_config`).

| Rota | Método | Controller |
|---|---|---|
| `GET /ponto/colaboradores` | `index` | `ColaboradorController@index` |
| `GET /ponto/colaboradores/{id}/editar` | `edit` | idem |
| `PUT /ponto/colaboradores/{id}` | `update` | idem |

Títulos renderizados: **"Colaboradores · Configuração de ponto"** (`Index.tsx:77`) e
**"Configuração de Ponto · {nome}"** (`Edit.tsx:74`). SubNav ativo no Index: `colaboradores`.

Campos que o `update` aceita (medidos no `validate()`): `matricula`, `pis`, `cpf`,
`escala_atual_id`, `controla_ponto`, `usa_banco_horas`, `admissao` (obrigatória),
`desligamento` (`after:admissao`).

---

## 2. ⚠️ PII em tela — o que mais importa neste arquivo

O payload do `Index` e do `Edit` carrega **CPF e PIS** de pessoa real (`ColaboradorController`,
transform do `index` e array do `edit`). Isso muda o padrão de cuidado:

- **Nunca em log, nunca em PR, nunca em commit message.** Use `[REDACTED]` / `PiiRedactor`
  ([proibicoes.md](../../proibicoes.md) §Multi-tenant).
- **Nunca em fixture ou screenshot** anexado a PR.
- Teste roda no tenant fictício **98** ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)) —
  **biz=4 é proibido sem exceção** em teste, fixture, smoke ou exemplo.

Não medi se existe redaction ativa no caminho de log desta tela — **não afirmo que existe**.

---

## 3. Fontes canônicas (ordem de precedência)

Vale a regra-mestre de [proibicoes.md](../../proibicoes.md):
**teste verde citando o UC > `.casos.md` > `.charter.md` > `SPEC.md`**.

| Ordem | Fonte | Papel |
|---|---|---|
| 1 | [`SDD-espelho-e-jornada-v1.0.md`](SDD-espelho-e-jornada-v1.0.md) §1.1 (grupo Cadastro) e §6.5 **CU-PONTO-12** | escopo e invariante `[T0]` |
| 2 | `Colaboradores/{Index,Edit}.charter.md` — **`status: draft`** | intenção-lei, não ratificada |
| 3 | [`SPEC.md`](SPEC.md) US-PONTO-007 (isolamento) | escopo |
| 4 | Lei: **CLT Art. 74 §2º** (obrigatoriedade de registro) | contrato de domínio |

⚠️ **O SDD NÃO tem um fluxo F dedicado a esta tela** (F1–F8 cobrem espelho, intercorrência,
aprovação, banco de horas, importação e relatórios). Este grupo aparece no §1.1 e é alcançado pelo
invariante transversal `CU-PONTO-12`, não por um CU próprio. **Não inventei um.**

---

## 4. Estado MEDIDO em 2026-08-28

| Item | Estado |
|---|---|
| Ambas as Pages em Inertia/React sobre `AppShellV2` | ✅ |
| Charters | ✅ existem — ambos **`status: draft`** |
| `Index.casos.md` · `Edit.casos.md` | ❌ **nenhum dos dois existe** |
| Scorecards | ✅ `ponto-colaboradores-index.yaml` · `ponto-colaboradores-edit.yaml` |
| Cor crua no `.tsx` | 1 por arquivo: `Index:77` e `Edit:74`, ambas `text-stone-400` (padrão do `os-page-h`) |
| `HasBusinessScope` em `Colaborador` | ✅ aplicado (`Colaborador.php:30`) |

**Os `casos.md` faltantes NÃO são deste PR, e isso é decisão registrada:** há decisão de atacar
primeiro os UC órfãos existentes. Declarar mais contrato com a mesma prova zero seria dobrar a
aposta do presence-gate.

Cobertura **não é restateada aqui** — rode `npm run screen-coverage:report` e `npm run casos:report`.

---

## 5. Verificação

```bash
node .claude/hooks/block-mwart-violation.mjs   # path no stdin: rc=0 após este RUNBOOK
node scripts/memory-schemas/validate.mjs memory/requisitos/Ponto/RUNBOOK-colaboradores.md
npm run typecheck:baseline:check               # delta deve ser +0
```

Pest e PHPStan **não** rodam local nem no Hostinger — CT 100, sempre
([ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)).

---

## 6. Não fazer

- ❌ **Não logar nem colar CPF/PIS** em lugar nenhum — ver §2. Vale para output de debug também.
- ❌ **Não criar/apagar pessoa por aqui.** A tela configura a faceta de ponto de um `user`
  existente; `ColaboradorController` não tem `store` nem `destroy` (medido).
- ❌ **Não relaxar `desligamento` `after:admissao`** — data de saída anterior à admissão corrompe
  toda apuração de período do colaborador.
- ❌ **Não usar `biz=4` (ROTA LIVRE)** em teste, fixture ou exemplo desta tela
  ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)).
- ❌ **Não promover os charters a `status: live`** — depende de [W] aprovar Non-Goals + Anti-hooks.
