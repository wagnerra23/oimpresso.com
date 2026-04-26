# ADR TECH-0003 (EvolutionAgent) · 3 tiers de autonomia (read → comment → PR-draft)

- **Status**: accepted
- **Data**: 2026-04-26
- **Decisores**: Wagner
- **Categoria**: tech

## Contexto

Wagner: "comece pequeno e teste a evolução."

Pulo direto pra agente que abre PR autônomo = risco alto. Pulo direto pra agente que só lê = baixo ROI ao longo do tempo.

Solução: tiers progressivos com gate de qualidade entre eles.

## Decisão

**3 tiers de autonomia**, ativados sequencialmente após gate de fase anterior.

### Tier 1 — Read-only (Fase 1)

- Agente só **responde perguntas** sobre o repo (memory + código + git log).
- Toda saída vai pro Wagner via Claude Code.
- Wagner age manualmente.
- Toggle: sempre ON.
- **Gate pra avançar**: top-1 do agente aceito por Wagner em ≥3/5 testes manuais.

### Tier 2 — Comentário em PR (Fase 3a)

- Agente **comenta em PRs do GH** apontando dívida técnica detectada.
- Não modifica código. Não aprova/reprova PR.
- Trigger: `pull_request: opened`.
- Filtro: comenta só se score de relevância >0.7 (evita spam).
- Toggle: `EVOLUTION_PR_COMMENT_ENABLED=false` por padrão; Wagner liga manualmente.
- **Gate pra avançar**: comentários úteis em ≥70% dos PRs em 1 semana (Wagner avalia thumbs).

### Tier 3 — PR-draft autônomo (Fase 3b)

- Agente **abre PR-draft** pra mudanças triviais e seguras.
- Allowlist (hardcoded):
  - Rename de variável/função (com refactor pleno via Rector se disponível)
  - Remove `// TODO: removed` comments
  - Fix link quebrado em arquivos `.md`
  - Adiciona `@deprecated` em código marcado pra remoção há >30 dias
- Sempre **draft**, nunca direto em main.
- Diff <50 linhas; senão, vira issue.
- Pest verde antes de abrir PR (rodado pelo agente).
- Cron diário às 6am.
- Toggle: `EVOLUTION_AUTO_PR_ENABLED=false` por padrão.
- **Gate pra continuar ligado**: ≥60% dos PRs do agente mergeados em 1 semana. Senão, volta pra Tier 2.

### Fora do escopo (não vira tier)

- ❌ Mutação direta em main
- ❌ Mudança de schema DB
- ❌ Mudança em ADRs/SPECs (são fonte da verdade humana)
- ❌ Deletar arquivo
- ❌ Mexer em config de produção
- ❌ Operações em servidor Hostinger

## Consequências

**Positivas:**
- Risco escala com confiança comprovada.
- Tier 3 = ~30min/semana economizados em chores.
- Cada tier com toggle + gate = reversível.
- Allowlist hardcoded previne expansão por LLM-hallucination.

**Negativas:**
- Tier 3 PR-draft pode ficar "abandonado" se Wagner não revisar. Mitigação: cron fecha PR-draft com >7 dias sem atividade.
- Allowlist conservadora pode parecer pouco. Aceitável; expandir só após confiança provada.
- Falso positivo em rename pode quebrar build. Mitigação: Pest verde obrigatório antes de abrir PR.

**ROI estimado** (após Tier 3 estável):
- ~25h/ano em chores autônomos (30min/semana × 50 semanas).
- A R$200/h equivalente = ~R$5k/ano.
- Custo: 2 dias setup + ~$5/mês em LLM = ~R$60/mês.
- **Payback**: ~1 mês.

## Alternativas consideradas

| Alt | Motivo de rejeição |
|---|---|
| Tudo Tier 1 forever | Sem ROI crescente; agente vira manual de consulta. |
| Pulo direto pra Tier 3 | Risco alto sem confiança comprovada. |
| Tier 4 = mutação direta em main | Wagner não pediu; risco alto, ROI marginal. |
| Allowlist por LLM (semântica) | Hallucination = cirurgia errada. Hardcoded é mais seguro. |

## Métrica de continuidade

A cada mês após Tier 3 ligado:
- Tempo poupado/semana ≥1h? Continua.
- ≥60% PRs mergeados? Continua.
- Senão, volta pra Tier 2 e revisa.

## Links

- [SPEC §7 US-EVOL-006 e US-EVOL-007](../../SPEC.md#us-evol-006--tier-2-autonomia-comentar-pr)
- [SPEC §10 Cronograma](../../SPEC.md#10-cronograma-faseado)
