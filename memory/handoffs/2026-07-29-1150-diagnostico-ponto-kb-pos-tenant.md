---
date: "2026-07-29"
time: "11:50 UTC"
slug: diagnostico-ponto-kb-pos-tenant
tldr: "Check-in agendado fechou a dívida deixada pelo merge do tenant 98: KB não era o tenant (verde no main desde 0ec1a92e) e Ponto falha por FK em business_id=99 que nada cria nos cenários *-ALHEIO-*. Zero required vermelho no main. Achado colateral: o Secrets audit detecta drift e morre antes de abrir o PR."
prs: []
decided_by: [W]
next_steps:
  - "Ponto: aplicar seededSupportClientTenant() nos testes que usam o business alheio 99 — diagnosticado, NÃO autorizado"
  - "Secrets audit --auto-pr: conserta o caminho que morre em 'nothing to commit' — detecta e não entrega"
  - "[W]: rotacionar a Meilisearch master key (🔴 comprometida desde 2026-05-28, em git history)"
related_adrs:
  - 0101-tests-business-id-1-nunca-cliente
  - 0093-multi-tenant-isolation-tier-0
  - 0216-governance-drift-checkers-alertas
---

# Diagnóstico de `Ponto` e `KB` — a hipótese "fallout do tenant" caiu nas duas

Continuação do [handoff das 22:38 BRT](2026-07-28-2238-revert-blade-producao-e-tenant-98.md), que fechou a sessão com `KB` e `Ponto` **vermelhas e não diagnosticadas**. O check-in agendado existia exatamente para isso.

## Estado MCP no momento do fechamento

⚠️ **MCP do oimpresso indisponível** — segue em fallback desde o início desta sessão (`brief-fetch` sem token). **Não há** snapshot de `cycles-active` / `my-work` / `sessions-recent` / `decisions-search`. Não estou herdando número de handoff antigo como se fosse medição de agora.

Fonte alternativa usada, e ela **não é equivalente**: `actions_list` (workflow runs de `main`) + `get_job_logs` + `required-checks-baseline.json`.

⚠️ **Clone raso.** Nenhuma data de git é citada como recibo.

⚠️ **`send_later` desapareceu no meio da sessão** (servidor MCP desconectado). O próximo check-in foi armado com `CronCreate`, que é **session-only** — morre com a sessão, ao contrário do anterior.

## Zero required vermelho no main

Método: 30 runs completos em `main`, filtrados por `conclusion=failure`, com o nome do **job** cruzado contra os 34 contexts de [`governance/required-checks-baseline.json`](../../governance/required-checks-baseline.json).

**Uma** falha, e **não-required**: `event: schedule`. O único job 0216 entre os required é o **PR scan (`--diff-only`)**; o que falhou foi o **daily health-check (`--all`)**.

## `KB` — não era o tenant

| sha | conclusão |
|---|---|
| `6fe585b9` · `80dd82bf` · `b46597d0` | failure |
| **`0ec1a92e`** (merge do tenant 98) | **success** |

A falha vista no #4974 vinha da **base velha da branch**, não da mudança. Não reabrir.

## `Ponto` — a causa é FK em `business_id = 99`

`11 failed / 18 passed` (run `30414793692`). Três falhas com a mesma assinatura, literal:

```
FK ponto_importacoes_business_id_foreign        → values (99, AFD, SDD-BH-IMP-CONTRATO-alheio-show...)
FK ponto_colaborador_config_business_id_foreign → values (99, 23, SDD-ESPELHO-CONTRATO-ALHEIO-...)
FK ponto_colaborador_config_business_id_foreign → values (99, 25, SDD-ESPELHO-CONTRATO-ALHEIO-...)
```

Os testes inserem no **business alheio 99** e **nada o cria** nessa lane.

Encaixe com o que esta sessão mexeu: o #4974 **original** fazia o seed criar o 99, e teria feito as 3 passarem **por efeito colateral**. Movido o tenant principal para 98 (para não colidir com `SUPPORT_CLIENT_TENANT_ID`), o 99 voltou a não existir ali.

**Conserto canônico, não aplicado:** `seededSupportClientTenant()` — o helper já cria o 99 na ordem correta (`user → business → backfill`).

O restante da lane é o **bug de compliance CLT declarado** (`EspelhoContratoTest`, `SDD §9 D-1` — apuração em `DIVERGENCIA` não contada; Blade contava por `estado`, React lê `tem_divergencia`, que não existe), failing-first por desenho do `fab0d5ec`.

⚠️ **NÃO verificado:** se essas 3 FKs são as mesmas de antes da troca de tenant. `Ponto` já estava vermelha em 27/07 e 28/07 (histórico de runs), mas **os logs daquelas datas não foram lidos**. **Provável, não provado.**

## Achado colateral — o audit de secrets detecta e não entrega

```
[secrets:audit] 2 drift(s) detectado(s)
Switched to a new branch 'chore/secrets-drift-2026-07-29-124048'
nothing to commit, working tree clean
exit code 1
```

Abre a branch, **não tem o que commitar, morre** — o drift nunca vira PR. Família "mede e não reporta". Não corrigido: não é required e não foi autorizado.

Item **pré-existente de [W]** dentro do relatório: *Meilisearch master key* 🔴 **COMPROMETIDA desde 2026-05-28** (em git history, append-only, não removível) — aguarda **rotação**.

## Aberto

- `Ponto` — conserto diagnosticado, **não autorizado**.
- `Secrets audit --auto-pr` — defeito diagnosticado, **não autorizado**.
- Rotação da Meilisearch key — **[W]**.
- Smoke em biz=1 se [W] quiser re-landar o fix do Produto (#4943 revertido; re-land exige aprovação do diff do Blade + smoke **antes** do merge).

## Pointers

- Session log (seção "Pós-merge"): [2026-07-28-revert-blade-producao-e-tenant-98.md](../sessions/2026-07-28-revert-blade-producao-e-tenant-98.md)
- Handoff anterior: [2026-07-28-2238-revert-blade-producao-e-tenant-98.md](2026-07-28-2238-revert-blade-producao-e-tenant-98.md)
