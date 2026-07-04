---
tipo: proposta-errata-adr
alvo: "0273-anchor-spec-codigo-formato-canonico-fluxo-novo"
status: proposta
kind: errata
proposto_por: [C]
proposto_em: "2026-07-04"
decide: [W]
related_adrs: [0273-anchor-spec-codigo-formato-canonico-fluxo-novo, 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes, 0314-poda-gates-onda-2-lei-fusoes]
pii: false
---

# Errata (proposta) — ADR 0273: §Status stale no aceite + §4 F1-advisory superado pelos fatos

> Origem: [avaliação adversarial SDD 2026-07-03](../../sessions/2026-07-03-sdd-avaliacao-adversarial.md) — pendência de "higiene bookkeeping (…, §Status ADR 0273)" na tabela de ondas. A [ADR 0273](../0273-anchor-spec-codigo-formato-canonico-fluxo-novo.md) é canon append-only (CI `Append-only canon (ADRs, handoffs, Constituição)` — [`.github/workflows/governance-gate.yml:37`](../../../.github/workflows/governance-gate.yml)), então a reconciliação exige documento NOVO — este. Todos os fatos abaixo foram verificados em `origin/main@53b2500` (2026-07-04).

## E-1 — O aceite do frontmatter é o vigente; o §Status do corpo ficou stale

**Drift interno registrado:** o frontmatter da ADR 0273 diz `status: aceito` + `decided_by: [W]` + `decided_at: "2026-06-12"` (linhas 6/10/11), mas o corpo `## Status` (linha 27) ainda diz *"Proposto — aguarda aprovação Wagner"*.

**Reconciliação:** o **frontmatter é a fonte máquina-lida** (schema [`scripts/memory-schemas/adr.schema.json`](../../../scripts/memory-schemas/adr.schema.json), validado pelo `memory-schema-gate`) e é o **vigente**: a ADR 0273 está **ACEITA** e ativa desde 2026-06-12. O `## Status` do corpo é texto do momento da redação (quando a ADR ainda era proposta) que não foi atualizado no aceite — e, uma vez mergeada como canon, o append-only impede editá-lo retroativamente. Leia o §Status do corpo como **registro histórico**, não como estado atual.

## E-2 — §4 "F1 ADVISORY … reporta sem bloquear" foi superado pelos fatos (o lint é required)

O §4 da 0273 descreve o ratchet F1 ADVISORY → F2 CATRACA → F3 REQUIRED full-tree. A realidade andou por outro caminho, todo registrado no ledger vivo [`governance/required-checks-baseline.json`](../../../governance/required-checks-baseline.json) (GT-G4):

| Data | Fato | Prova (em `origin/main@53b2500`) |
|---|---|---|
| 2026-06-24 | `anchor entry/covers gate (advisory)` armado (PR #3320, grandfather + diff-aware) | `required-checks-baseline.json` linha 9 (entry `promocoes`) |
| 2026-06-30 | `anchor-lint ADR 0273 (advisory)` promovido a **required** junto com 2 irmãos (require-safe via PR #3444; paths-filter removido). **Override consciente** da cadência 1/semana da ADR 0275 §5, a pedido explícito do Wagner (*"promova agora"*) | `required-checks-baseline.json` linha 11; comentário em [`.github/workflows/anchor-drift.yml:11`](../../../.github/workflows/anchor-drift.yml) confirma `anchor entry/covers gate` required em 2026-06-30 |
| 2026-06-30 | Poda ADR 0314 D-1 (required 29→22) **manteve** `anchor entry/covers` como LEI (nenhum gate multi-tenant/dinheiro/PII/fiscal saiu) | `required-checks-baseline.json` linha 18 (entry `demovidos`) |
| 2026-07-01 | **P14 (d)** — rename dos 6 required tirando "(advisory)" do nome (*"o nome mentia: eram required desde 06-24/06-30"*): `anchor-lint ADR 0273 (advisory)` → **`anchor-lint ADR 0273`** e `anchor entry/covers gate (advisory)` → **`anchor entry/covers gate`**. Dança zero-window: PR-1 #3535 shims → swap atômico na protection → PR-2 renomeia jobs + baseline (#3550 baseline + #3552 jobs/watchdog, per [`_ROADMAP.md`](../../requisitos/_Governanca/roadmap/_ROADMAP.md) linha 87, P14 ✅ executado 2026-07-01). Zero mudança de enforcement — só o nome | `required-checks-baseline.json` linha 15 |
| hoje | Estado vivo: contexts required incluem `"anchor entry/covers gate"` e `"anchor-lint ADR 0273"`; jobs em [`.github/workflows/anchor-drift.yml`](../../../.github/workflows/anchor-drift.yml) linhas 51 e 80 | `required-checks-baseline.json` linhas 48-49 |

**Registro honesto (sem retcon):** a promoção **não** seguiu o critério F3 do §4 (coverage 100% full-tree — a avaliação 2026-07-03 mede 85.6%≠100%, flip via override); foi decisão explícita do Wagner registrada no ledger. O ratchet F1→F2→F3 do §4 fica como **plano histórico do momento da redação**; a régua viva de promoções required é a ADR 0275 §5 + o registro `promocoes` do `required-checks-baseline.json`.

## O que esta errata NÃO faz (escopo zero-regra-nova)

- **Não** muda a gramática canônica (§1), a semântica de cobertura (§2) nem a regra do fluxo novo (§3) da 0273 — tudo segue vigente e enforçado pelo lint.
- **Não** promove, demove nem renomeia gate algum — só reconcilia o registro com fatos já consumados e já ledger-ados.
- **Não** edita a ADR 0273 (append-only).

## Rito

Aguarda decisão do Wagner. No aceite: ganha número via alocação atômica, frontmatter completo `adr.schema.json` (`kind: errata`, `supersedes_partially: [0273-anchor-spec-codigo-formato-canonico-fluxo-novo]` — a base permanece `lifecycle: ativo`), nos moldes da [ADR 0265](../0265-oficina-reparo-erradica-locacao.md) (errata que fechou resíduo da 0194).
