---
date: "2026-06-22"
topic: "Veredito adversarial do 'Grafo Spec-Ancorado' (Financeiro) — reduzir-escopo: não construir framework, 3 cortes cirúrgicos, Tier-0 proibido pra regra de negócio"
authors: [C]
type: veredito-adversarial-design
metodo: "workflow adversario-spec-anchored-graph (7 lentes céticas + juiz-defensor, Opus, verificação LIVE no código real do repo — não no plano)"
gatilho: "Wagner — 'quero um adversário aqui é a hora certa no máximo' (sobre o design SAG proposto na própria sessão)"
recomendacao: reduzir-escopo
run_id: wf_6ce01ec4-0a3
tokens_subagentes: 977763
adrs_citados: [0264, 0261, 0256, 0298, 0273, 0303, 0275, 0093, 0144, 0070, 0105]
tasks_linkadas: [US-GOV-031, US-GOV-029, US-GOV-044]
sessao_pai: "2026-06-21-sdd-avaliacao-adversarial.md"
---

# Veredito adversarial — Grafo Spec-Ancorado (SAG) no Financeiro

> **Recomendação: `reduzir-escopo`.** Não construir o SAG como framework. O motor já existe (ADR 0273) e está em **7%** de cobertura — o problema não é "falta construir", é "já construímos e não pegou tração com 1 dev + ancoragem manual".

## Contexto

Nesta sessão Wagner desenhou um **"Grafo Spec-Ancorado"** (FONTE atômica vs VISTA gerada · âncora = id-do-caso · `anchor-lint`/`casos-coverage-guard` · lock Tier-0 de comportamento · rebuild-on-change) pra resolver 5 dores do Financeiro: (a) plano louco, (b) testes saem do ideal, (c) função desativada, (d) funcionário coloca task indevida, (e) recebimento parcial sem teste. Pediu adversário "no máximo". Rodado painel 7-lentes + juiz-defensor (2 passadas; retry robusto após 3 falhas transitórias de conexão na 1ª).

## 3 achados verificados no código (não hipótese)

1. **G-2 prova cobertura por `String.includes`, não por execução.** `scripts/casos-coverage-guard.mjs` L154-161. A própria meta-suite prova o buraco: `tests/casosGuard.spec.ts` usa `<?php // UC-01` (comentário) como fixture de cobertura → comentário, `it.skip` e `expect(true)` são indistinguíveis de teste real. É o "teste-fantasma" mecanizado.
2. **A dor (e) — baixa parcial — NÃO está no grafo.** O teste existe (`UnificadoBaixaDialogGuardTest` "GUARD G3", asserta o split `valor_aberto=60`/filho=40) e é `required` — mas tem **zero UC-id**. O único UC do fluxo (UC-F02) testa "recebe COMPLETO". Confiar no SAG daria **falsa segurança bem na dor que dói** — pior que não ter SAG.
3. **O motor do SAG já existe e está em 7%.** `governance/sdd-scorecard.json`: 728/823 US sem o campo de âncora, 15 `anchored_dead`, `anchor-lint` advisory. Causa-raiz: ancoragem manual com 1 dev não escala.

## Veredito de timing

"É a hora certa" está **errada na escala de framework, certa na escala de 1 caso.** 4 das 5 dores são problemas **multi-autor** que mal existem com Wagner solo (o "funcionário que coloca task" é o próprio Wagner; os 3 planos divergem porque a mesma mão escreveu em momentos diferentes — 1 PR de delete resolve). Gatilho objetivo pra ligar o grafo: **quando o 2º humano (Felipe/Maiara) fizer o 1º commit.** Reforçado pelo CYCLE-08 (receita) e ADR 0105 (sinal do cliente puxa a feature, não o grafo).

## Correção registrada (o adversário pegou o Claude)

Antes do painel, Claude havia dito a Wagner que baixa parcial seria **"Tier-0 irremovível, igual ADR 0093"**. O painel refutou e está certo: **erro de categoria.** ADR 0093 trava invariante de **segurança** (vazar tenant = catástrofe sempre, independente de mercado); baixa parcial é **hipótese de produto** (se a ROTA LIVRE pedir rateio de juros/dia, o modelo muda — e o lock Tier-0 bloquearia o PR que corrige, no caminho do sinal mais qualificado).

→ **Tier-0 é PROIBIDO pra regra de negócio.** Reservar Tier-0 a segurança/correção (`business_id`, PII, idempotência de migration). Lock de comportamento = "Comportamento Protegido": gate required bypassável-com-label, teto K≤2/módulo, sem `enforce_admins`, promovido só com N dias em prod + 1 sinal de cliente. Coerente com ADR 0298 (teto de governança).

## Design que sobrevive (4 passos, NÃO 45 arquivos)

- **PASSO 0** — deletar/fundir os 3 PLANOs frozen (mata a dor 'a', zero grafo).
- **PASSO 1** — UC-id no teste de baixa parcial que já existe (reusar `UC-*`, **não** inventar `R-FIN-NNN` — colide com o vocabulário existente).
- **PASSO 2** — endurecer o que já roda: G-2 bloco-vivo+`verdict=pass`; manifesto CI-fresco (hoje hand-commitado/falsificável); tamper-guard no `casos-coverage-baseline.json`; path-filter inclui Core/Services (fecha o skip-as-pass).
- **PASSO 3** (só com 2º humano) — grafo completo + task-governance via **DB do MCP (RBAC, nunca grep no SPEC** — senão reabre o Bug #2 do ADR 0144: done→todo fantasma).

**NUNCA:** Tier-0 de comportamento de negócio; `tasks-create` validando markdown do SPEC; forkar `R-FIN-NNN`; commitar VISTA-status volátil no git.

## Decisão tomada (opção A — linkar, não criar)

Zero artefato de execução novo. A evidência do painel foi **linkada no cluster de Governança que já existe**, governado por ADR 0298:

- **US-GOV-031** ← G-2 falso-clean + casos-baseline/allowlist sem tamper-guard (precondição pra promover o casos-gate a required).
- **US-GOV-029** ← guard-rail de escopo: não promover anchor-gate a required sobre 7%/728-sem-campo; escopar a um punhado de Tier-0 reais. Pareia com **US-GOV-044**.

Off **CYCLE-08** (receita — 5 dias: pricing, FIN-004 cobrança ROTA LIVRE, 5 migrações, MRR R$2000). O keystone (G-2 bloco-vivo) está teed-up em US-GOV-031, espera o time / pós-cycle — branch limpa off `main`, não no worktree parcial `frosty-greider`.

---

_Pai: [2026-06-21-sdd-avaliacao-adversarial.md](2026-06-21-sdd-avaliacao-adversarial.md) (mesma lente: a infra mede honesto, a governança não morde). Runs: `wf_6ce01ec4-0a3` (painel SAG)._
