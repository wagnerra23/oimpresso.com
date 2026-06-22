---
slug: 0297-anchor-lint-wired-testado-sa-a2-bis
number: 297
title: "Anchor-lint SA-A2-bis — wired-check (zumbi) + testado-check: existir ≠ estar vivo"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-22"
module: governance
quarter: 2026-Q2
tags: [sdd, spec-anchored, anchor, wired-check, zombie, testado, traceability, ratchet, governanca]
supersedes: []
superseded_by: []
related: ["0273-anchor-spec-codigo-formato-canonico-fluxo-novo", "0271-revisao-gates-ci-estado-real-required-e-subtracao-segura", "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes", "0256-knowledge-survival-meia-vida-catraca-sentinela"]
pii: false
---

# ADR 0297 — Anchor-lint SA-A2-bis: wired-check (zumbi) + testado-check

> **Estende** o [ADR 0273](0273-anchor-spec-codigo-formato-canonico-fluxo-novo.md) (não o supersede — a gramática §1 segue valendo). Origem: reconciliação do SPEC do Financeiro (2026-06-22), onde Wagner apontou *"suas regras estão frouxas e permissivas a erro — tem que garantir que isso funcione"*. Brick de fidelidade de anchor da [Onda 0 — rede de enforcement](proposals/onda-0-rede-seguranca-enforcement.md) (faltava: os 4 bricks daquele plano não cobrem fidelidade spec↔código).

## Status

Proposto — aguarda aceite Wagner (caminho "ADR canon" do CLAUDE.md). O código da detecção já foi implementado e **provado por contrafactual** antes desta ratificação (doutrina Onda 0: cada brick prova que armou). Falta o aceite pra (a) promover o gate e (b) re-armar o baseline do scorecard.

## Contexto

O `anchor-lint.mjs` (ADR 0273) classificava `anchored_ok` por **`existsSync` puro**: bastava o arquivo existir no disco. Isso deixou passar uma classe inteira de mentira:

- **Âncora ZUMBI** — US-FIN-013 apontava `resources/js/Pages/Financeiro/Dashboard/Index.tsx`, com carimbo `verificado@fd96258 (2026-06-13)`. O arquivo existe, mas a tela foi **deprecada em 2026-06-06** (`Route::redirect('/', '/financeiro/unificado', 301)`); o `DashboardController` nem é referenciado nas rotas. O lint dava 🟢. *Existir no disco ≠ estar vivo.* O `knowledge-drift.mjs` (`identity_drift`) tem o mesmo limite — só pega referência a coisa **inexistente**, não a coisa **desligada**.
- **`Testado em:` sem governança** — o lint só parseia `**Implementado em:**`. As linhas `**Testado em:**` do §3 (regras Gherkin) nunca foram checadas: o Financeiro citava ~13 testes-fantasma (`AutoCriacaoTituloVendaTest` etc.) que não existem.

Medição determinística pós-implementação (full-tree, 57 SPECs, `origin/main` @ee798b6, 2026-06-22): **8 âncoras zumbi** (ProjectMgmt 7 · KB 1) e **31 refs de teste-fantasma** (NfeBrasil 12 · RecurringBilling 11 · LaravelAI 8) — invisíveis sob a regra antiga. A nota de cobertura era *idêntica* antes e depois da reconciliação do Financeiro (~26,5%): a regra não distinguia verdade de mentira.

## Decisão

### 1. Estado novo `anchored_zombie` (wired-check)

Uma **Page-âncora** (`resources/js/Pages/<comp>.tsx`) é ZUMBI quando: existe no disco **E** é renderizada por algum controller (`Inertia::render('<comp>')`) **mas por nenhum controller VIVO**. "Controller vivo" = importado (`use …\XController;`) ou usado com `::class` nas rotas do módulo — comentário não conta. A verdade do *está vivo* vem do **roteador**, não do filesystem. Determinístico, fs-puro, sem PHP/DB.

**Conservador por design (anti-falso-positivo, kill-criteria #3 da Onda 0):**
- Sub-componentes (`_components/`, `components/`) nunca são marcados — não são páginas roteáveis.
- Render por variável (string não-literal → não cai no `allRendered`) nunca é marcado.
- Módulo sem `Http/Controllers/` → indeterminável → não marca.

`anchored_zombie` **não conta como cobertura** (é mentira, igual `anchored_dead`). Conta no `--check` (exit 1).

### 2. Testado-check (`dead_tests`)

Cada linha `**Testado em:**` é parseada; ref de teste citada que não existe vira `dead_tests`: path `.php` inexistente, OU `ClassName…Test` (backtick) sem arquivo correspondente sob `Modules/*/Tests/`. Mención em itálico sem crase (`_lacuna — Foo não existe_`) é ignorada — **crase = só ref real**. Conta no `--check`.

### 3. Cobertura e promoção

- `anchor_coverage = (anchored_ok + pendente + parcial) / us_total` — **inalterada na fórmula**, mas `anchored_ok` agora exige path existente **E vivo**. Zumbi sai da conta → cobertura cai honestamente onde havia mentira. O baseline do `sdd-scorecard` (meta-catraca, ADR 0275) **precisa ser re-armado** no aceite (queda esperada, não regressão).
- CI (`anchor-drift.yml`): o passo **diff-aware** dos PRs passa a rodar `--check` (exit 1) **só nos SPECs tocados** — proíbe mentira NOVA sem travar a dívida existente (grandfathering por não-toque). Cron full-tree segue REPORT (a dívida dos 39 não avermelha o cron até reconciliada). Promoção a `required` (branch protection) segue o calendário do ADR 0275 §5 — flip do Wagner, fora deste arquivo.
- `gate-selftest.mjs` (GT-G6, ADR 0256) ganha fixture good/bad do anchor-lint: o detector de mentira passa a ser ele próprio testado ("quem vigia os vigias").

## Consequências

- ✅ A classe "tela deprecada mas anchor verde" deixa de ser invisível. Fecha o ponto-cego que o caso Financeiro expôs.
- ✅ `Testado em:` deixa de ser superfície sem governança (31 fantasmas já visíveis).
- ✅ Detecção provada por contrafactual (isca com zumbi+dead+fantasma → `--check` exit 1) antes da ratificação.
- ⚠️ Cobertura medida cai onde havia mentira → re-armar baseline do scorecard no aceite.
- ⚠️ 8 zumbis + 31 fantasmas reais a reconciliar nos outros módulos (mesmo tratamento do Financeiro) — dívida agora rastreável, não silenciosa.
- ⚠️ Heurística "controller vivo = referenciado nas rotas" pode ser over-permissiva (controller importado mas usado só em redirect conta como vivo) — escolha consciente: erra pro lado de NÃO acusar (zero falso-positivo > pegar todo zumbi).

## Alternativas consideradas

1. **`php artisan route:list --json`** como fonte de "vivo" — mais preciso, mas quebra o invariante do lint "Node puro, sem PHP/DB". Rejeitado por ora; fica como evolução (`anchor_format: v2`).
2. **Marcador `@deprecated` na .tsx** como sinal — depende de alguém lembrar de marcar (permissivo). Mantido como belt-and-suspenders futuro, não como mecanismo primário.
3. **Promover a required já** — violaria "gate novo/ampliado nasce advisory" (ADR 0271) e quebraria os 39 legados no dia 1. Por isso diff-aware --check (no-new-lies) + cron report.

## Referências

- [ADR 0273](0273-anchor-spec-codigo-formato-canonico-fluxo-novo.md) — gramática anchor (estendida aqui) · [ADR 0271](0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md) — advisory→required · [ADR 0275](0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md) — calendário/baseline · [ADR 0256](0256-knowledge-survival-meia-vida-catraca-sentinela.md) — gate-selftest GT-G6
- [Onda 0 — rede de enforcement](proposals/onda-0-rede-seguranca-enforcement.md) — brick de fidelidade que faltava
- `scripts/governance/anchor-lint.mjs` — implementação (estados `anchored_zombie` + `dead_tests`)
- Reconciliação do SPEC Financeiro 2026-06-22 — caso de origem (8 zumbis + 31 fantasmas medidos)
