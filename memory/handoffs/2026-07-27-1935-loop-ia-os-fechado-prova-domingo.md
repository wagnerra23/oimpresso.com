---
date: "2026-07-27"
time: "19:35 BRT"
slug: loop-ia-os-fechado-prova-domingo
tldr: "Loop dos 4 gaps P0 fecha no manifesto (3 entregues + 1 descartado por [W]), mas 3 das 4 entregas nunca rodaram em produção — a primeira prova real é a corrida semanal de domingo 2026-08-02 06:00. 8 PRs mergeados, todos consertando mecanismos que afirmavam o que não faziam."
decided_by: ["W"]
cycle: null
prs: [4833, 4844, 4849, 4853, 4856, 4857, 4859, 4860]
us: ["US-COPI-140", "US-COPI-143", "US-COPI-115"]
next_steps:
  - "[W] marcar jana.access nos papéis (/roles/{id}/edit) — gate JÁ está em main, não-admin sem o checkbox toma 403"
  - "Depois de domingo 02/08: conferir se o eval trouxe TODAS as failures e se o alerta chegou em mcp_alertas_eventos"
  - "Investigar causa do context_recall 0.3461 < piso 0.36 usando o detalhe por pergunta que o #4860 destravou"
  - "ContextSnapshotService scopa só por business_id — vazamento entre usuários da mesma empresa (aberto)"
related_adrs: ["0216-governance-drift-checkers-alertas", "0318-ragas-eval-real-mata-tautologia-ct100-staging", "0093-multi-tenant-isolation-tier-0"]
---

# Handoff — Loop IA-OS fechado no manifesto; a prova é domingo

## Estado em uma frase

O loop dos 4 gaps P0 (auditoria 2026-05-29) fecha — `NADA PENDENTE: 3 entregue(s),
1 descartado(s)`. **Mas três das quatro entregas nunca foram exercitadas em produção**:
a primeira prova real é a corrida semanal de **domingo 2026-08-02 06:00 BRT**.

## O que rodar primeiro na próxima sessão

```bash
node .claude/hooks/loop-fechar-check.mjs          # o banner (agora com 3 estados)
node scripts/governance/permission-drift.mjs      # 42 órfãs · 66 teatro
```

E, **depois de domingo**, o que decide se o trabalho de hoje valeu:

```bash
# 1. o eval rodou e o corte silencioso sumiu? (esperado: failures com TODAS, não 10)
tailscale ssh root@ct100-mcp 'tail -60 /opt/oimpresso-ragas/evals.log'

# 2. o elo do alerta disparou? (só se gate_status=fail)
tailscale ssh root@ct100-mcp \
  'docker exec oimpresso-mcp php artisan tinker --execute="
     echo DB::table(\"mcp_alertas_eventos\")->where(\"tipo\",\"drift_ragas_eval_quality\")->count();"'
```

## Mergeado hoje (8 PRs)

| PR | SHA | O que era |
|---|---|---|
| #4833 | `36d7e37dae` | banner dizia "LOOP FECHADO" com item aberto — veto do `done:false` |
| #4844 | `96cb18230d` | README alegava que o `drift-sentinel` mede a Jana |
| #4849 | `88219cd922` | medidor de drift permissão declarada × aplicada |
| #4853 | `632c5182e2` | código alinhado ao `jana.*` que o banco usa desde maio |
| #4856 | `a4e6cd3729` | 3º estado do manifesto: descartado ≠ feito ≠ pendente |
| #4857 | `6232171c49` | elo do alerta — vermelho do eval chega no HITL |
| #4859 | `4a603ac91e` | `can:jana.access` ligado no `/ia` + proposta de desenho |
| #4860 | `4e93790dc1` | fim do corte silencioso (`n_failed: 20` entregava 10) |

## ⚠️ Ação pendente de [W] — tem janela de risco

O **#4859 está em `main`**: o gate `can:jana.access` está ligado. `jana.access` nasce
`default => false`. **Funcionária não-admin sem o checkbox marcado toma 403** no
próximo deploy. Larissa **não** é afetada (é admin → `Gate::before` passa).

Conserto: marcar a permissão em `/roles/{id}/edit`. **Sem deploy.**

## Aberto e nomeado (não é dívida oculta)

1. **`context_recall 0.3461 < piso 0.36`** desde 26/07. Correlação medida: o corpus
   Meilisearch foi de ~1153 → 1898 docs, indexando continuamente; a semana 19→26/07
   teve 1.186 dos 3.240 docs de `memory/` alterados (backfill de `id:` no frontmatter),
   e o indexador re-indexa por mudança de frontmatter mesmo com body idêntico.
   **Correlação com mecanismo plausível, NÃO causa provada.** O #4860 faz a corrida de
   domingo dizer **quais** das 51 perguntas caíram.
2. **`ContextSnapshotService` scopa só por `business_id`** — 6 queries, zero
   `created_by`/`view_own_*`. Vazamento **entre usuários da mesma empresa** (não entre
   empresas — Tier 0 segura). Registrado na proposta de permissões.
3. **Manifesto do loop ainda mede presença** nos 3 `[OK]` restantes (`detect: file_any`).
   Consertei o caso que mentia, não o critério.
4. **US-COPI-143** — o `drift-sentinel` segue tautológico. Decisão de aposentar vs
   manter como canário-do-juiz: mantive (é o único sinal sobre o **juiz**), rótulo
   corrigido no README.

## Decisão [W] registrada — não reabrir

**Item #6 (LGPD purge) = won't-do.** *"Em um sistema ERP não pode apagar o PII"* +
*"isso deve ser feito por permissões de acesso do usuário"*. Lápide em `proibicoes.md`
§5 2026-07-27. Fica vivo o `LgpdEsquecerTitularTool` (Art. 18 §VI, sob demanda).
Se uma auditoria futura vir `JANA_RETENTION_ENABLED=false` e concluir "gap", a resposta
é a lápide.

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work` (@wagner) → **8 tasks em REVIEW**: US-TR-309/310/311, US-TR-305/306,
  US-PG-008, US-PROD-027, US-PROD-025 — **nenhuma tocada nesta sessão**
- Sessions de hoje (paralelas, outros temas): `arte-permissoes-ia-erp`,
  `auditoria-camada1-sdd-mordida`, `orfaos-ligados-elo-hitl`,
  `produto-3-achados-tier0-fechados`, `sdd-produto-fluxos-sem-tela`
- Handoffs anteriores de hoje: `1135-produto-3-achados-tier0-fechados`,
  `1445-orfaos-ligados-elo-hitl`, `0905-sdd-produto-fechado-cadeia-requisitos`

## Pegadinha de ambiente (custou tempo hoje)

O checkout **ficou raso no meio da sessão** (5.729 → 100 commits) após fetch de branch
órfã + pull. O hook `block-instrumento-sem-porta-viva` barrou meu `git log --since` —
corretamente. Conserto: `git fetch --unshallow origin main`. **Confira
`git rev-parse --is-shallow-repository` antes de qualquer conclusão baseada em data.**
