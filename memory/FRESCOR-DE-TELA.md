# FRESCOR-DE-TELA — Ratchet de frescor de documentação + delta-driven re-análise (camada 2)

> **Status:** proposta de [CC] · soberania [W] (numera/versiona/linka no PROTOCOL = [W] via [CL]).
> **O que é:** o par da `CONTEXTO-DE-TELA.md`. A Ficha garante que [CC] **lê o certo**; esta regra garante que o que [CC] lê **está fresco** e que [CC] **sabe o que mudou**.
> **De onde vem:** estende o padrão JÁ CANON do **`review-freshness.mjs` (#2078)** — que marca `<Tela>.review.md` como `stale` quando o `.tsx` muda — para **charters** e **dossiês de contexto**.
> **Por que existe:** doc desatualizada é pior que doc ausente — [CC] confia nela e erra com confiança. E reler `origin/main` inteiro a cada sessão não escala pra 16 telas.

---

## Princípio

> **Documentação só vale se for provadamente fresca. Re-análise só onde houve mudança.**
> Frescor é mecânico (ratchet contra o hash do código-fonte), não confiança. Mudança é um **diff determinístico** que o sistema entrega a [CC], não memória nem releitura cega.

---

## Mecanismo 1 — Charter-freshness ratchet (espelha `review-freshness.mjs`)

Cada `<Tela>.charter.md` ganha, no front-matter, o vínculo com o código que descreve:

```yaml
---
page: /sells/create
component: resources/js/Pages/Sells/Create.tsx
last_validated: 2026-06-01          # quando o charter foi confirmado vs o componente
validated_against: <git sha curto>  # commit do componente naquele momento
---
```

- **Check CI `charter_stale`** (novo, irmão de `design_review_stale` do #2078): se o `git log` do `component` tem commit **mais novo** que `validated_against`, o charter está **`stale`** → flag vermelha no `ui:lint`/PROTOCOL §6.
- **Ratchet:** o baseline de charters frescos só cresce; mergear `.tsx` sem revalidar o charter **trava** (igual o freshness ratchet de review).
- **Revalidar** = [CC]/[CL] relê o `.tsx`, confirma/atualiza o charter, carimba `last_validated` + `validated_against` no HEAD.

## Mecanismo 2 — Dossiê com `last_analyzed_commit` (delta-driven)

Todo dossiê de contexto (`memory/sessions/AAAA-MM-DD-contexto-<tela>.md`, o Lado-C da Ficha) carimba:

```
last_analyzed_commit: <sha do origin/main em que o dossiê foi construído>
fontes_analisadas: [Create.tsx@<sha>, Create.charter.md@<sha>, ContactAddress.php@<sha>, …]
```

- **Passo 0 redefinido:** em vez de "reancorar e reler tudo", [CC] roda `git diff <last_analyzed_commit>..origin/main` **restrito às `fontes_analisadas`** → re-analisa **só o que mudou**; o resto herda a análise anterior.
- **Sem `last_analyzed_commit` no dossiê = dossiê incompleto** (não satisfaz o Lado-C da Ficha).

## Mecanismo 3 — Delta-log por tela (o "o que foi acrescentado")

Quando uma tela muda entre sessões, [CC] registra no dossiê uma linha de delta:
`Δ AAAA-MM-DD: <campo/seção/regra> +/~/− · commit <sha> · impacto no design: <1 frase>`
→ vira o histórico navegável de "o que entrou que eu tive que analisar". Append-only.

---

## GATE — travas que [CC] não ultrapassa

- ❌ **Charter `stale`, não desenho** → revalido o charter contra o `.tsx` atual primeiro.
- ❌ **Dossiê sem `last_analyzed_commit`** → incompleto; carimbo antes de prosseguir.
- ❌ **Não confio em doc não-carimbada** → trato como ausente e re-derivo do código.
- ✅ **Re-análise é delta, não full** → só o diff desde o último commit analisado.

---

## Como fecha as perguntas de [W] (2026-06-01)

- *"cada tela bem documentada?"* → documentada **e provadamente fresca** (ratchet), não só existente.
- *"vai saber o que foi acrescentado?"* → sim, por **diff determinístico** (`last_analyzed_commit` + delta-log), não releitura/memória.
- *"como garantir que não erre?"* → esta é a **camada 2 (detectar deriva)** das 5 (Intake → **Frescor** → Crítica/Auditoria → Humano → Lição). Garantia é assintótica: cada camada pega o que a anterior deixou; a 5ª impede repetir.

## Par com a Ficha
`CONTEXTO-DE-TELA.md` (camada 1, prevenir) + `FRESCOR-DE-TELA.md` (camada 2, detectar deriva) = o intake completo. Crítica/auditoria (#2078 + F1.5), humano (F2/canary) e `LICOES_CC.md` já cobrem 3–5.
