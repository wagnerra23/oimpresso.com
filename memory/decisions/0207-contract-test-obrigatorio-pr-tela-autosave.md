---
slug: 0207-contract-test-obrigatorio-pr-tela-autosave
number: 207
title: "Contract test obrigatório em PR que toque tela autosave — CI gate hard"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-27"
module: Infra
quarter: 2026-Q2
tags: [testes, ci, autosave, contract-test, ci-gate, anti-regressao, amends-0205]
supersedes: []
amends: [0205]
related:
  - 0205-contract-tests-autosave-padrao-canonico
  - 0179-cliente-drawer-760px-substitui-show-fullpage
  - 0093-multi-tenant-isolation-tier-0
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0104-processo-mwart-canonico-unico-caminho
  - 0155-module-grade-v3-sub-dimensoes-gate-ci
pii: false
review_triggers:
  - "Falsos-positivos do detector (PR bloqueado por engano, label exempt usado por convite) — refinar heurística"
  - "Falsos-negativos (PR de tela autosave passa sem fixture e regrede em prod) — ajustar grep ou exigir label exempt explícita sempre"
  - "Tier 2 browser smoke aceito como cumprimento alternativo desta regra — amend este ADR"
  - "Custo manutenção fixture > 30min/PR em média (medido em retrospectiva trimestral) — repensar trade-off"
---

# ADR 0207 — Contract test obrigatório em PR de tela autosave (amends 0205)

## Contexto

**Em 2026-05-27** o projeto oimpresso atingiu **11 fixtures contract** cobertas (cliente_drawer, service_order_edit, service_order_items, sells_create, produto_edit, vehicles_edit, compras_create, nfe_config, dvi_inspection, sells_edit_shipping, ncm_rules) + 1 decisão doc-only justificada (Stock adjustment). O 3º review_trigger do [ADR 0205](0205-contract-tests-autosave-padrao-canonico.md) declara expressamente:

> "Padrão extendido pra >5 telas — promover regra de criar fixture obrigatório em PR de tela nova"

**Gatilho atingido.** Padrão maduro, runner reusável, 6 padrões reusáveis catalogados (P1-P6 — ver [session log 2026-05-27](../sessions/2026-05-27-contract-tests-rollout-4-waves-paralelas.md)).

### O risco que continua existindo

Mesmo com framework estável, **PR de tela autosave nova OU mudança em validator/Controller existente sem fixture associado continua sendo vetor de bug silencioso** (mesmo padrão Daniela bug Heinig — badge "Salvo" verde mascarando `Eloquent::update([])` no-op). Toda hora que o time MCP (Felipe/Maiara/Eliana/Luiz) ou Claude criar tela nova de drawer/autosave/CRUD com autosave per-field sem fixture, abre porta pra regressão silenciosa em prod.

ADR 0205 § matriz já dizia "✅ obrigatório" pra cenários:
- Tela nova com 2+ endpoints PATCH autosave
- Tela existente sem fixture mas modificada (Controller PATCH novo)
- Modificação em validator existente (campo novo, alias novo)

Mas **regra sem gate automatizado não é regra — é convite**. Wagner e team confiavam em revisão manual, que falha sob pressão / sessão produtiva.

## Decisão

**Promover ADR 0205 § matriz pra CI gate hard.** PR que toque qualquer um dos triggers abaixo **DEVE** incluir fixture contract atualizada OU label `contract-test-exempt` justificando exceção.

### Triggers do gate (heurísticas no diff)

PR é flaggado se diff contém:

1. **Backend autosave detectado:**
   - `Modules/*/Http/Controllers/*Autosave*Controller.php` ou `app/Http/Controllers/*Autosave*Controller.php` modificado/criado
   - Método `update(.*Request` ou `store(.*Request` adicionado/modificado em controllers com `@route patch|put|post` quando route correspondente usa endpoint per-field (heurística: rota com path `/{id}/<tab-or-field>`)
   - `FormRequest` modificado em rules() — campo novo / alias novo / mutex novo

2. **Frontend autosave detectado:**
   - `resources/js/Pages/<Mod>/<Tela>.tsx` adicionado/modificado contendo um dos: `useAutosave`, `onAutosave`, `patchJson`, `axios.patch`, `router.patch`, debounced save pattern
   - Componente `*Tab.tsx` ou `*Drawer.tsx` novo

3. **Wave de amends em fixture existente:**
   - Mudança em `tests/Contract/Fixtures/<tela>.php` sem mudança correspondente em código → suspeita ajuste pra esconder regressão (warn, não bloqueia)

### Comportamento do gate

- **Bloqueio hard:** check `contract-test-required` falha → PR não pode merge
- **Override:** label `contract-test-exempt` adicionada pelo Wagner OU adicionada via comando `/exempt <razão>` em comentário (auditável)
- **Justificativa obrigatória:** label exempt exige session log em `memory/sessions/YYYY-MM-DD-contract-test-exempt-<slug>.md` documentando razão (padrão similar [Stock adjustment doc 2026-05-27](../sessions/2026-05-27-contract-tests-stock-adjustment-decisao.md))

### Casos de exception legítimos (matriz expandida)

| Cenário | Decisão default | Como justificar |
|---|---|---|
| Tela CRUD tradicional Blade full-form Save+Redirect | ⚪ exempt | Session log doc-only ([padrão Stock adjustment](../sessions/2026-05-27-contract-tests-stock-adjustment-decisao.md)) |
| Refactor puro (renomear var, mover método) sem mudança de comportamento | ⚪ exempt | Justificar em PR description |
| Tela read-only (apenas GET/show) sem mutação | ⚪ exempt | Auto-detectado pelo gate (sem PATCH/PUT/POST autosave no diff) |
| Migration apenas (sem Controller/Request mudanças) | ⚪ exempt | Auto-detectado |
| Endpoint exposto pra integração externa (webhook receiver, queue worker) | ⚪ exempt | Documentar em session log — contract diferente (HMAC/payload upstream-defined) |
| Tela nova drawer/autosave/PATCH per-field | ✅ obrigatório | Sem exception aceitável |
| Modificação validator existente (novo campo, novo alias PT-BR/EN) | ✅ obrigatório | Sem exception aceitável |
| Adição de novo endpoint PATCH/PUT em Controller já coberto | ✅ obrigatório | Atualizar fixture existente |

### Implementação (próxima onda — não bloqueia este ADR)

GH Action canon em `.github/workflows/contract-test-gate.yml`:

```yaml
name: Contract test gate (ADR 0207)
on: [pull_request]
jobs:
  detect:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with: { fetch-depth: 0 }
      - name: Detect autosave touch
        run: scripts/contract-test-detect.sh "${{ github.event.pull_request.number }}"
      - name: Check label exempt
        if: failure()
        run: |
          if gh pr view ${{ github.event.pull_request.number }} --json labels --jq '.labels[].name' | grep -q contract-test-exempt; then
            echo "✓ Exempt label aplicada — gate skipped"
            exit 0
          fi
          exit 1
```

Script `scripts/contract-test-detect.sh` faz:
1. `git diff origin/main --name-only` pra lista de arquivos modificados
2. Filtra por patterns autosave (regexes acima)
3. Se match → verifica se `tests/Contract/Fixtures/` OU `tests/Feature/Contract/` também foi tocado
4. Se backend touch SEM fixture touch → falha + comenta no PR linkando ADR 0207 + sugerindo nome do fixture

**Task tracking via MCP:** `tasks-create module:Infra title:"Implementar contract-test-gate GH Action ADR 0207"` em sessão futura (não bloqueia este ADR — ADR descreve a decisão, GH Action é entrega).

## Princípios derivados

### P1 — Gate hard > regra escrita
Regras escritas em ADR sem enforcement são convites. Time MCP entra logo (Felipe/Maiara/Eliana/Luiz) — sem gate, drift acumula em meses.

### P2 — Exemption opt-in, documentada, auditável
Label exempt deve gerar session log. Não vira "dispense everywhere por reflexo". Wagner ou senior owner aprova.

### P3 — Heurística falsa-positiva é melhor que falsa-negativa
Em caso ambíguo, gate BLOQUEIA — Wagner aprova exempt em 30s OU dev escreve fixture em 5min. Pior cenário é PR passar sem fixture e regredir em prod (custo ordens de magnitude maior).

### P4 — Fixture é doc viva — atualizar = parte do PR, não follow-up
Dev mexendo validator/Controller atualiza fixture no MESMO PR. Nada de "fixture follow-up" — perde a coerência do contract test.

## Consequências

### Positivas
- **Regressão silenciosa impossível em telas cobertas:** validator filter dropping unknown key → fixture quebra no CI antes do merge
- **Onboarding rápido:** novo dev (Felipe/Maiara/Eliana/Luiz) vê fixture → entende contrato frontend↔backend de uma tela em 30s
- **Skill com-integrar mais precisa:** pre-flight encontra fixture → mostra padrão da tela imediatamente
- **Cobertura cresce naturalmente:** PR de tela autosave nova = automaticamente cria fixture (gate força)
- **Doc-only entregável ratificado:** decisão de não-cobrir tem caminho legítimo (label exempt + session log)

### Negativas
- **Custo PR marginal:** atualizar fixture quando mexer em validator/Controller. Mitigado: 1 linha por campo, copy-paste de campo similar, ~5min médio
- **Falsos-positivos iniciais:** heurística vai pegar PRs que não deveria. Mitigado: label exempt + iteração da heurística via review_triggers acima
- **Pressão em sessão produtiva:** pode tentar bypassar com exempt por reflexo. Mitigado: P2 (session log obrigatório justificando)
- **Sem cobertura de bugs visuais:** Tier 1 contract test não pega cache stale frontend, layout quebrado, microcopy. Tier 2 browser smoke continua sendo follow-up canônico

### Neutras
- **Custo CI adicional:** ~5s pra GH Action detect script + 0s se fixture já existe (não roda fixture nova, só verifica presença). Aceitável
- **Coexiste com tests específicos:** runner contract test = breadth (chave bate?), tests Cliente/Sells específicos = depth (negócio/FSM/race). Complementam, não competem

## Roadmap de adoção

1. **Sprint atual (2026-05-27):** este ADR aceito ✅
2. **+1 semana:** implementar `scripts/contract-test-detect.sh` + `.github/workflows/contract-test-gate.yml` (PR separado, owner: Wagner ou primeiro disponível Maiara/Eliana — task MCP `module:Infra`)
3. **+2 semanas:** monitorar primeiros PRs com gate ativo, ajustar heurística falsa-positiva
4. **+1 mês:** retrospectiva — quantos PRs bloqueados corretamente, quantos exempt aplicados, custo médio de atualizar fixture
5. **Q3 2026:** considerar Tier 2 browser smoke como satisfação alternativa da regra (após estabilizar Tier 1 gate)

## Riscos Tier 0 mitigados

- **❌ Multi-tenant ADR 0093:** preservado — runner `setupContext` força business_id session, todo fixture passa por isso
- **❌ PII LGPD:** preservado — todos valores fixture são sintéticos (`'CT-{stamp}'`, `'11.222.333/0001-44'` fake CNPJ)
- **❌ FSM Pipeline ADR 0143:** preservado — contract test NÃO mexe em transação FSM `current_stage_id`. Tests específicos seguem cobrindo
- **❌ Privacy auto-mem ADR 0061:** preservado — fixture vive em git canônico, MCP server propaga via webhook GitHub, time MCP enxerga

## Refs

- [ADR 0205](0205-contract-tests-autosave-padrao-canonico.md) — Contract tests autosave padrão canônico (amendado por este)
- [ADR 0179](0179-cliente-drawer-760px-substitui-show-fullpage.md) — Drawer Cliente 760 (origem dos bugs Daniela)
- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 (preservado)
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (princípio "Loop fechado por métrica" aplicado aqui via CI gate)
- [Session 2026-05-27 rollout 4 waves](../sessions/2026-05-27-contract-tests-rollout-4-waves-paralelas.md) — 6 padrões reusáveis P1-P6
- [Session 2026-05-27 Stock adjustment doc-only](../sessions/2026-05-27-contract-tests-stock-adjustment-decisao.md) — padrão exempt
- `tests/Contract/README.md` — receita prática + tabela cobertura
