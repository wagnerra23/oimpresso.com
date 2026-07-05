---
slug: plano-aprofundamento-avaliacoes
title: "Plano de aprofundamento das avaliações — 5 ondas"
date: "2026-07-05"
status: proposto
authors: [F, C]
related_adrs: ["0093", "0101", "0105", "0106", "0155", "0230", "0264", "0275", "0294", "0314"]
esforco_estimado: "5 ondas · ~4-6 sessões IA-pair (ADR 0106)"
topic: "programa de aprofundamento das lentes de avaliação do projeto — telas stale, módulos piores, ops/DR, LGPD/performance + revisão defensiva apartada"
---

# Plano de aprofundamento das avaliações

As lentes vivas não cobrem o projeto inteiro: SDD 70/100 · Jana RAG ~46% · module-grade média 76.7 (36 módulos) · 17 CAPTERRA-FICHA · RAGAS canary. Faltam lentes profundas em **telas stale (Check B: 217), módulos Tier-0 fracos, ops/DR, LGPD e performance**. Este doc é o contrato de execução — fonte única, sem roadmap paralelo (T6).

## Status vivo

- status: proposto
- reviewed_at: 2026-07-05
- proximo_passo: Wagner aprova a ordem; executor começa pela Onda 0 (baseline), depois Onda 1.
- adversário deste plano: [`sessions/2026-07-05-adversario-plano-aprofundamento.md`](../../sessions/2026-07-05-adversario-plano-aprofundamento.md).

## Regras da sessão executora

1. `brief-fetch` primeiro; branch nova a partir de `origin/main` fresco.
2. Merge = Wagner (R10). `tasks-create` em lote = Wagner confirma 1×. Nenhuma onda auto-cria task.
3. Tier 0: `business_id` global scope; Pest/PHPStan só no CT100; smoke biz=1 (nunca biz=4); sem PII/valores BRL; evidência literal antes de "pronto".
4. 1 PR = 1 intent, ≤300 linhas, conventional commit PT-BR.
5. Antes de criar doc em `memory/`: `Glob`/`Grep` o tema e **estender** o canon — nunca abrir paralelo (T6).
6. Toda onda entrega **catraca**, não só relatório (sentinela conta, catraca morde — ADR 0264/0275).
7. Executa no tier da própria sessão — não force troca de modelo. Se spawnar `capterra-senior`/`audit-senior-expert` (Opus-pinned), passe `model: fable` no override pra manter o tier.
8. **Ordem:** 0 → 1 → 2 → 4 → 5. A **Onda 3 (revisão defensiva) roda numa sessão dedicada** e separada — não abrir junto das outras.

## Máquina de cobertura (Check X)

O `Check X` do `memory-health` flaga módulo Tier-0 **ou** nota module-grade < 70 sem nenhum `AUDIT*.md` no dir de requisitos. Hoje aponta **1** (PaymentGateway). É o **DoD vivo da Onda 2**: a onda fecha ⟺ Check X zera. Advisory, determinístico, com teste físico (`tests/memoryHealth.spec.ts`).

---

## Onda 0 — Baseline consolidado (½ sessão, pré-req)

Junta as notas atuais num só doc pra não re-medir o que já está medido.

1. Colher notas: `module:grade --all --json` (CT100) · `sdd-scorecard.json` · `jana-ragas-baseline.json` · `screen-grade-report.mjs` · `memory-health.mjs`.
2. Escrever `memory/governance/BASELINE-QUALIDADE-2026-07.md` — tabela: lente · nota · fonte · frescor · dono. Sem valores BRL.

**DoD:** doc com as 6 lentes (module-grade, SDD, Jana RAG, RAGAS, screen-grade, memory-health), cada uma com nota + data + fonte.

---

## Onda 1 — Re-grade das telas stale (Check B: 217)

Os scorecards de tela estão velhos (a tela mudou depois do `graded_at`). A lente existe, só precisa refrescar.

**Pré-reqs:** nenhum. Ferramentas prontas (skill `screen-grade`, `screen-grade-report.mjs`, `screen-grades-ratchet.mjs`).

1. `screen-grade-report.mjs` → lista das telas stale (work-list).
2. Priorizar por peso: Sells/POS → Financeiro → Fiscal/NfeBrasil → cauda.
3. Re-gradear em lotes de ~10-15 (fan-out por módulo). Cada agent roda o Pré-Flight, re-pontua as 16 dims, atualiza o YAML.
4. Nota que caiu: registrar como achado (batch de tasks, humano-gated) — não maquiar.
5. `screen-grades-ratchet.mjs` verde antes de cada PR (docs/YAML, ≤300 linhas).

**DoD:** Check B < 50 (medição) + ratchet verde + lista de notas-que-caíram pro Wagner.

---

## Onda 2 — Módulos Tier-0 mais fracos: Compras (58) e PaymentGateway (60)

Os dois piores do baseline tocam dinheiro/estoque (REGRA MESTRE). Sair de nota-fria pra diagnóstico + backlog.

**Pré-reqs:** ler `Compras/SPEC.md` + `PaymentGateway` (ADR 0170 status "later" = docs only) + §REGRA MESTRE valor/estoque antes de Edit.

1. `module:grade Compras --detail --evolve` (CT100) → dimensões D1-D9 + top gaps.
2. Spawn `capterra-senior` pra Compras (`model: fable`) → `Compras/CAPTERRA-FICHA.md`.
3. `/comparativo Compras` → inventário + batch tasks → Wagner aprova antes de criar.
4. Repetir 1-3 pra PaymentGateway respeitando ADR 0170 (se "later", só diagnóstico). Zera o Check X.
5. Fix que toque cálculo/estoque: REGRA MESTRE — dupla confirmação + antes→depois + OK Wagner.

**DoD:** ficha + inventário + batch pros 2 módulos; Check X zera; re-grade pós-fix mostra Compras ≥70 sem regredir outros.

---

## Onda 3 — Revisão de segurança defensiva (sessão dedicada)

Escopo, checklist, baseline e DoD ficam apartados → [`AUDITORIA-SEGURANCA-ESCOPO.md`](AUDITORIA-SEGURANCA-ESCOPO.md). Abrir só numa sessão dedicada, com OK do Wagner. **Não misturar com as outras ondas.**

---

## Onda 4 — Infra / Ops / DR (CT100 + Hostinger)

Zero grade, e já houve incidente silencioso (cópia `/opt` do CT100 desatualizada; SSH Hostinger flaky). Ninguém mediu backup/restore.

**Pré-reqs:** sessão com Tailscale/CT100. Drill em staging, nunca prod.

1. Inventário read-only: o que roda onde (ADR 0062), crons, daemons, SPOFs.
2. Drill de restore em staging: existe backup? Restaura? RTO/RPO? Documentar gap.
3. Sync canon↔servidor: `/opt` bate com `origin/main`? Propor catraca "deployado == HEAD canon".

**DoD:** `Infra/AUDITORIA-OPS-DR-2026-07.md` — tabela SPOF + gap backup/DR + 1 catraca de drift; drill tentado (sucesso ou gap honesto).

---

## Onda 5 — LGPD + Performance prod

Duas lentes finais, hoje só dimensão do module-grade.

- **5a LGPD (gated Eliana):** inventário de PII por tabela, retenção/consent, `PiiRedactor` cobre os fluxos? Entregar mapa + gaps, não decisão jurídica.
- **5b Performance:** baseline p95/p99 por rota (OTel/Jaeger CT100) → top-10 lentas; N+1 (`paginate(` sem `->with(`) e `Inertia::defer` nas props caras.

**DoD:** `AUDITORIA-PERFORMANCE-2026-07.md` com baseline p95 + 5 piores N+1 com fix; mapa LGPD pra Eliana. Sem valores BRL.

---

## Ordem e esforço (IA-pair, ADR 0106)

| Onda | Tema | Prioridade | Esforço | Gate |
|---|---|---|---|---|
| 0 | Baseline consolidado | pré-req | ½ sessão | — |
| 1 | Re-grade telas stale (217) | alta (barato) | 1-2 sessões | ratchet |
| 2 | Compras + PaymentGateway | alta (Tier-0 fraco) | 1-2 sessões | Wagner |
| 4 | Ops/DR CT100+Hostinger | média (incidente real) | 1-2 sessões | CT100 |
| 5 | LGPD + Performance | baixa | 1 sessão | Eliana |
| 3 | Revisão defensiva | sessão dedicada | 2-3 sessões | Wagner |

**Quick-win:** Onda 1 + Onda 2. O Check X vigia a Onda 2 a cada PR.
