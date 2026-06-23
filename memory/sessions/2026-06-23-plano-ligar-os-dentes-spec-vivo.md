---
date: "2026-06-23"
topic: "Plano executável — ligar os dentes do spec-vivo: promover 3 gates advisory→required (diff-aware) + executar ADR 0302 (aposentar status: à mão)"
authors: [C]
type: session
module: governance
pii: false
related_adrs:
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0302-fonte-unica-doneness-anchor-aposenta-status-spec
  - 0303-anchor-lint-wired-testado-sa-a2-bis
---

# Plano — "Ligar os dentes" do spec-vivo

## TL;DR

O spec-vivo **já está ~70% construído e DESLIGADO**: `reconcile-triplet`, `outcome-metrics`, `anchor-drift` e `dtcg-equivalence` existem e rodam — todos **advisory** (não mordem). A descoberta veio de um adversário (Wagner pediu) que pegou que os 3 audits de 2026-06-22 propunham construir o que já tinha mergeado horas depois (PRs #3214/#3220/#3232) — a síntese inicial era stale porque lia uma branch 163 commits atrás. Medição ao vivo (2026-06-23): **nenhum gate morde limpo hoje** — todos têm dívida legada que, no modo estrito, travaria merges. Logo "ligar os dentes" é um **programa de burn-down + grandfathering diff-aware**, executado **1 promoção/semana** com o **clique do Wagner** (ADR 0275 §5), não um flip. Decisão Wagner: **bifurcação (A) diff-aware** + executar **os dois tracks** (gates + ADR 0302).

## 1. Realidade medida ao vivo (2026-06-23, contra origin/main — NÃO audit stale)

| Gate | Check name (context) | Dívida legada medida | Morde hoje? |
|---|---|---|---|
| `reconcile-triplet` | `paridade por setor (advisory)` | **46/142 telas com DIVERGÊNCIA MUDA** + 9 ponteiros órfãos | ❌ `--strict` travaria 46 PRs |
| `anchor-drift` | `anchor-lint ADR 0273 (advisory)` | **51/59 SPECs com `status:` à mão**; 128 âncoras (cobertura por-US rala) | ⚠️ já diff-aware |
| `dtcg-equivalence` | `equivalencia DTCG <-> CSS canonico (advisory)` | invariante limpo, sem dívida por-tela; 1 vermelho histórico (feature branch) | ⚠️ só precisa streak verde |
| `outcome-metrics` | `medidor de aceitacao (advisory)` | — | ❌ é **medidor**, nunca morde — **fora da fila** |

Branch protection main hoje: **20 required contexts**, `enforce_admins: true`. Registro canônico das promoções: `governance/required-checks-baseline.json` (`classic_protection.contexts[]`).

## 2. Bifurcação escolhida: (A) diff-aware grandfathering

O dente morde só o que o PR **toca**; dívida legada fica "congelada por não-toque" + catraca que **só desce**. É o padrão do repo (ADR 0271) e como o `anchor-drift` já opera. Rejeitada (B) debt-to-zero (limpo mas lento — zerar 46 + preencher tudo antes de morder).

## 3. Regras duras que governam o flip (ADR 0275 §5)

1. **Só o Wagner flipa branch protection.** Agente prepara evidência (runs, não narração); humano clica.
2. **Máx 1 promoção a required por semana civil** (vaga não acumula).
3. Flip exige: critério objetivo pré-escrito **atingido com evidência linkada** + PR atualizando `required-checks-baseline.json` citando ADR 0275 **no mesmo PR do flip**.
4. **Demoção exige PR + ADR** (sem demoção invisível de 1 clique).
5. **Armamento:** métrica/gate só arma após **3 medições válidas consecutivas** (fonte rodou sem erro, não-mock, valor não-nulo).

## 4. Dossiê por gate (Track A)

### Gate 1 — `dtcg-equivalence` (o dente mais barato — Semana 1)
- **Por quê primeiro:** invariante determinístico (DTCG ↔ CSS canônico), **zero dívida por-tela**. Não depende de burn-down.
- **Estado:** advisory, `continue-on-error: true`. Verde no main; 1 vermelho histórico em feature branch (`feat/pr1-dark-bg`, divergência real pega).
- **Critério pré-escrito p/ flip:** equivalência verde em **5 PRs consecutivos** que tocam tokens/CSS + 0 divergência nova não-justificada.
- **Passos:** (1) remover `continue-on-error` do passo do check (vira exit≠0 em divergência); (2) confirmar 5 runs verdes; (3) PR adicionando `equivalencia DTCG <-> CSS canonico` ao `required-checks-baseline.json` citando 0275; (4) **Wagner flipa**.

### Gate 2 — `reconcile-triplet` (o mais valioso — Semana 2)
- **Dívida:** 46 telas com divergência muda + 9 ponteiros órfãos.
- **Caminho (A):** ligar **modo diff-aware** (morde só telas TOCADAS no PR) + **catraca dos 46** num baseline que só desce + os 9 ponteiros viram tarefa de correção.
- **Critério pré-escrito:** 0 divergência muda NOVA em tela tocada por **7 runs**; baseline-46 nunca sobe.
- **Esforço:** fiação diff-aware (pequena, espelha `anchor-drift --check` só-tocado) + baseline-ratchet dos 46.

### Gate 3 — `anchor-drift` (acoplado ao Track B — Semana 3)
- Já é diff-aware. Morder hoje já pega SPEC novo malformado. O ganho real vem do **Track B** (elevar a cobertura real das âncoras).
- **Critério:** anchor-lint verde em SPEC tocado por 7 runs + FP <5%.

### Fora da fila — `outcome-metrics`
É **medidor de aceitação**, não gate. Permanece advisory/reporting por design (`continue-on-error`). Alimenta o Daily Brief, não derruba PR.

## 5. Track B — executar ADR 0302 (o build real do spec-vivo)

Não é flip, é trabalho de fundo. **51/59 SPECs ainda carregam `status:` à mão** — doneness declarado, não derivado. Sub-passos (PRs pequenos, 1 intent):

1. **`doneness-lint`** (advisory de nascença) — computa o status de cada SPEC a partir das âncoras `**Implementado em:**` (existe + testado, ADR 0303) e compara com o `status:` escrito. Reporta drift.
2. **Backfill de âncoras** — preencher `Implementado em:` faltantes (com `_pendente_` como estado de 1ª classe válido, ADR 0275 métrica 1).
3. **Remover `status:`** em lotes de PR conforme a doneness derivada cobre — começar pelos SPECs já 100% ancorados.
4. **Atualizar consumidores** do `status:` (qualquer índice/brief que leia o campo) pra ler a doneness derivada.

Track B eleva `anchor_coverage` real → é o pré-requisito pro dente do `anchor-drift` ser *significativo* (não só pegar SPEC novo malformado).

## 6. Sequência

| Quando | Item | Eu preparo | Wagner faz |
|---|---|---|---|
| Sem 1 | dtcg-equivalence | tira `continue-on-error` + evidência 5 runs + PR baseline | clica branch protection |
| Sem 2 | reconcile-triplet diff-aware | fiação diff-aware + catraca-46 + critério + PR | clica |
| Sem 3 | anchor-drift diff-aware | critério + PR | clica |
| Paralelo | Track B (0302) | lotes: doneness-lint → backfill → remove status: → consumidores | revisa/merge |

## 7. Papéis fixos

- **Agente (C):** mede ao vivo (nunca número stale), prepara fiação dos gates, evidência de runs, critérios pré-escritos, PRs no `required-checks-baseline.json`, e o Track B em lotes pequenos.
- **Wagner (W):** único que flipa branch protection (o clique), revisa/merge dos lotes do Track B.

## 8. Invariante anti-regressão deste plano

Toda afirmação de "está pronto/verde/existe" é verificada **ao vivo contra origin/main** antes de virar ação — a lição do adversário 2026-06-23 (audits stale propuseram reconstruir o que já existia). `git cat-file -e` sofre da pegadinha de cwd-scope nesta worktree; usar checkout limpo (`_salvage-ap`) ou `git grep <rev>` da raiz pra verdade de arquivo.
