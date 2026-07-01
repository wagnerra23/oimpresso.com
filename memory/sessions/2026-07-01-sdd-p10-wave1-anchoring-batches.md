---
date: "2026-07-01"
topic: "P10 wave 1 — campanha de anchoring em lotes (Financeiro/Whatsapp/Jana/OficinaAuto): 226 US ancoradas, refutador Fable tier superior pegou 6 erros, coverage 16,1%→~42% projetado"
authors: [C]
type: execucao-campanha-sdd
metodo: "4 geradores Opus 4.8 paralelos (áreas isoladas, zero git ops) → 4 refutadores Fable 5 em sessão fresca (amostra 100%, protocolo G5) → consolidação parent em PRs pareados por módulo + ledger append-only"
gatilho: "Wagner — executar P10 do roadmap SDD (trilho de CONTEÚDO; floor/nightly é outra sessão)"
related_adrs:
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0265-oficina-reparo-erradica-locacao
  - 0093-multi-tenant-isolation-tier-0
---

# P10 wave 1 — anchoring em lotes com refutador tier superior (2026-07-01)

## Resultado

| Lote | US | anchored/parcial/pendente | Refutação | PRs |
|---|---:|---|---|---|
| Financeiro | 38 | 10/6/22 → (pós-correção) 9/7/22* | r1 REPROVADO 7,5% (3 erros) → r2 APROVADO 0% | #3539 (SPEC+ledger) + #3540 (charter) |
| Whatsapp | 72 | 47/14/11 | APROVADO 0% (1 incerto melhorado) | #3546 (SPEC+ledger) — charter #3547 fechado (gate live-signal) |
| Jana | 68 | 30/15/23 | APROVADO 1,35% (1 erro charter corrigido) | #3543 (SPEC+ledger) + #3544 (charter) |
| OficinaAuto | 48 | 17/3/28 → (pós-correção) 16/4/28 | r1 REPROVADO 3,7% (2 erros) → r2 APROVADO 0% | #3541 (SPEC+ledger) + #3542 (charter) |

_*números por US do lote; o lint do módulo soma os campos pré-existentes._

- **226 US `sem_campo` → 0** nos 4 módulos (717 → 491 no full-tree local; coverage global 16,1% → **42,6%** projetado pós-merge).
- **Taxa de ambiguidade: 0%** nos 4 lotes (gatilho §103 do P10 passa folgado; com Sells batch1 ~6% = universo ≥5 módulos → fila A6 de ambíguas vazia POR PROVA).
- **dead=0, zombie=0, grammar 100%** em todos (auto-check + refutação independente).
- Tooling: #3530 — `ledger-check` reconhece `fable`/`mythos` + regra nova **refutador tier SUPERIOR** (achado da avaliação adversarial: modelo idêntico correlaciona erros) encodada no protocolo G5 e no check (selftest morde: good 0 / bad 1). CI 100% verde.
- Fila A6 materializada: #3549 (`memory/requisitos/_ANCHOR-REVIEW-QUEUE.md`) — §103 publicado, 6 telas órfãs, pendências Sells batch1, triagem 3-baldes da dívida entry-gate (zero teste tautológico), §3-bis com 13 `related_us` deferidos.

## O refutador tier superior FUNCIONOU (evidência do valor da regra nova)

6 erros reais que gerador Opus + anchor-lint deixaram passar, todos pegos pelo Fable em sessão fresca:

1. **US-FIN-030** — âncora em `FinAgeing.tsx`, sub-componente ZOMBIE (UI removida 2026-06-29 por decisão Wagner; backend "inócuo, ignorado pela UI"). Ponto cego DECLARADO do lint (conservador com sub-componentes).
2. **US-FIN-035** — `ClienteCombobox.tsx` órfão (zero imports).
3. **US-FIN-036** — PWA "pronta" sem `<link rel=manifest>` em nenhum blade (não instalável).
4. **US-OFICINA-006** — "pronta" mas `ServiceOrder` NÃO adota `GuardsFsmTransitions` (DoD central ADR 0143) — refutação SEMÂNTICA, invisível ao lint sintático.
5. **Charter SO/Edit** — `US-OFICINA-005` errada ("005-bis" do PR #1631 = UI de itens ≠ US-005 = cleanup legacy).
6. **Charter Jana/Chat** — `US-COPI-106` joinada na tela errada (DoD aponta Painel).

Padrão: 2 lotes de 4 REPROVADOS na rodada 1 (§2.6 aplicado à risca — lote inteiro re-verificado após correção). Ledger registra reprovados também (§6): 6 entries novas, primeira dupla opus→fable do ledger.

## Lições da sessão

- **Sub-componente órfão é o ponto cego sistemático** do zombie-check (o lint declara isso). O refutador tier superior é hoje a única defesa; candidata a evolução do lint (varrer imports de `_components/*.tsx` usados como âncora).
- **Gate charter-live-signal (required) mordeu o lote de charters**: 13 de 16 charters tocados são `status: live` sem sinal de prod → split honesto (3 draft aterrissaram; 13 joins verificados preservados na fila §3-bis aguardando smoke datado). O gate funcionou como desenhado — não foi contornado.
- **Efeito colateral honesto do anchoring**: req_sem_aceite 27→121 e req_sem_covering_test 45→187 no full-tree — ancorar não criou a dívida, tornou-a visível (métricas advisory; triagem 3-baldes na fila).
- Claim "Sells 47→0" do batch 1 (#3483) não bate com o lint vivo (17 `sem_campo` restantes; trabalho parcial em stashes) — pendência registrada na fila §4, não silenciada.

## Próximos lotes (ordem valor×buraco, briefing verificado)

L-next: Sells-completion (17) + NfeBrasil (18) → RecurringBilling (36) + Compras (8) + PaymentGateway (6) → Crm (22) + Pcp (21) + Fiscal (19) → Governance (35) + Mwart (13) → Infra (45) → verticais wish (~quase tudo `_pendente_`: ComVis 18 + Autopecas 15 + Comissao 15 + NFSe 15) → Marketplaces (26) + Vestuario (12) + Cms (10) → cauda. GATED (não ancorar): TaskRegistry/Inventory/EvolutionAgent/LaravelAI/MemoriaAutonoma (55 US, trilha E).

Armar `anchor_coverage` no scorecard: trilho floor/nightly (3 medições consecutivas do cron → PR no baseline).
