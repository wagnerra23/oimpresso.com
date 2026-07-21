---
date: "2026-07-21"
hour: "14:30 BRT"
topic: "Medição do piloto de tópicos (ADR 0345) para decidir flip grace→required"
authors: [C]
outcomes:
  - "Limiar objetivo de promoção grace→required pré-registrado ANTES de medir (anti-circularidade)."
  - "Medição do git: 3 tópicos, todos nascidos no commit fundador da própria ADR 0345, zero PRs posteriores, zero dias de janela advisory."
  - "Recomendação: ESPERAR. 4 de 5 critérios substantivos falham; não há janela de medição ainda."
prs: []
us: []
related_adrs:
  - 0345-topicos-vivos-aprendizado-por-critica-revisada
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0327-anchor-content-required-emenda-0314
---

# Medição do piloto de tópicos (ADR 0345) — flip grace→required?

## TL;DR

**Recomendação: ESPERAR.** O ADR 0345 foi aceito **hoje (2026-07-21)** e o piloto de tópicos é exatamente o próprio commit fundador: os **3** tópicos que existem nasceram todos na PR #4617 (a mesma PR que criou o ADR + schema + template + wiring do gate), e **nenhuma PR posterior tocou tópico**. Janela advisory = **0 dias**. Promover agora seria o anti-padrão `foundation-ratchet` (armar um gate que nunca teve chance de morder) **e** promover no calado (nenhuma janela [W] cumprida). Este log registra o **limiar objetivo** (pré-fixado antes de medir) para a rodada futura resumir sem re-derivar.

## Limiar objetivo — PRÉ-REGISTRADO antes de medir (anti-circularidade)

Fixado antes de rodar qualquer `git`/`gh`. Fonte das regras: ADR 0345 (linha 76 — *"falso-positivo zero no piloto e nova decisão humana"*), ADR 0275 §5 (calendário de promoções — critérios A10/C2/G3 usam *"14 dias advisory + FP <5%"*), ADR 0314 (*required = só Tier-0*), ADR 0327 (exceção via emenda).

Recomendar **FLIP AGORA** sse **T1 ∧ T2 ∧ T3 ∧ T4 ∧ T5** satisfeitos com evidência de git/runs; a execução do flip ainda depende de **T6** ([W]). Qualquer um falhando = **ESPERAR**.

| # | Critério | Barra objetiva | Origem |
|---|---|---|---|
| **T1** | Volume | ≥ **5 tópicos** distintos nascidos/tocados em **PRs mergeadas** desde 2026-07-21, cada um atravessando o gate ≥1 vez | 0345 "algumas PRs"; 0275 arma métrica com ≥3 medições — escolho 5 pra ter >1 módulo/autor, não só o seed |
| **T2** | Janela advisory | ≥ **14 dias corridos** de operação advisory | padrão pré-escrito A10/C2/G3 do 0275 |
| **T3** | Falso-positivo | **FP = 0** provado em tópicos **de autoria independente** (não só o seed) | exigência textual 0345 linha 76 |
| **T4** | Mordida / cadência | ≥ **1 verdadeiro-positivo** (gate pegou tópico malformado que devia pegar) **OU** cadência de nascimento provada | sem isso = `foundation-ratchet` (verde que não pode ficar vermelho) |
| **T5** | Caminho de categoria | **(a)** flip como **advisory-que-morde** (fora do required, sem emenda) **OU** **(b)** required de verdade com **emenda ADR estilo 0327** (reincidência dura + custo determinístico ~0) | 0314/0327 (required = só Tier-0 + exceções via emenda) |
| **T6** | Autorização | clique de branch protection = **[W] explícito** | 0275 regra 3 + R10 |

Definições de medição: **FP** = gate reprova/reprovaria (em strict) um tópico **bem-formado**. **VP** = gate reprova/reprovaria um tópico **malformado**.

## Medição — números do git/gh (2026-07-21)

Fonte: `git ls-files` + `git log --diff-filter=A` no repo (oráculo certo p/ contar arquivos e nascimento) + `gh pr checks`/`gh pr list` (oráculo certo p/ outcome de CI).

- **Tópicos rastreados: 3**
  - `memory/requisitos/Produto/topicos/calculo-total-fatura.md`
  - `memory/requisitos/_Geral/topicos/componentes-compartilhados.md`
  - `memory/requisitos/_Geral/topicos/templates-herdados.md`
- **Commits que criaram tópicos: 1** — `b0bc928f4a` (2026-07-21 11:48 BRT, squash da PR **#4617**, *"feat(governança): tópicos vivos e aprendizado por crítica revisada"*).
- **Co-localização no commit fundador:** o mesmo `b0bc928f4a` introduziu — ADR 0345, `topico.schema.json`, `TOPICO-TEMPLATE.md`, o wiring grace no `memory-schema-gate.yml`, os 3 tópicos, o session log e o handoff. **O piloto é o próprio seed do ADR.**
- **Commits que tocaram tópico DEPOIS do nascimento: 0.**
- **Janela advisory decorrida: 0 dias** (ADR aceito 2026-07-21 = hoje).
- **Exposições do gate a conteúdo de tópico: 1** — na PR #4617 a perna `Tópico (memory/requisitos/*/topicos/*.md) [grace]` **passou** (46s, 0 erros; grace/warn-only, `run 29840270171`).
- **Falso-positivos observados: 0** — mas a única exposição foi o **seed autoral** (escrito para caber no schema), então FP=0 aqui é **vacuamente zero**, não *provado* sobre autoria independente.
- **Verdadeiros-positivos observados: 0** — nenhum tópico malformado jamais chegou ao gate.
- PRs desde 2026-07-21 com `topico in:path`: #4617 (fundadora) e #4618 (doc de sessão da grade — **não** tocou `topicos/*.md`, confirmado pelo `git log` de conteúdo). As demais do search (#4195 07-13, #1792/#1041 mai) **predam** o ADR e só casaram por keyword no path.

## Veredito por critério

| # | Critério | Resultado | Status |
|---|---|---|---|
| T1 | ≥5 tópicos em ≥2 PRs | 3 tópicos, **1 PR (a fundadora)**, 0 PRs posteriores | ❌ FALHA |
| T2 | ≥14 dias advisory | **0 dias** | ❌ FALHA |
| T3 | FP=0 provado (autoria independente) | 0 FP, mas só sobre o seed → **insuficiente** (vacuamente zero) | ⚠️ INSUFICIENTE |
| T4 | ≥1 mordida OU cadência | 0 mordidas, 0 nascimentos pós-fundação | ❌ FALHA |
| T5 | caminho de categoria | não resolvido (moot enquanto T1–T4 falham) — pré-análise abaixo | ⏸️ pendente |
| T6 | autorização [W] | N/A nesta rodada | ⏸️ pendente |

**Conclusão: ESPERAR.** Não há janela de medição — o piloto ainda é o próprio nascimento. Recomendar flip agora reproduziria dois anti-padrões já lapidados: `foundation-ratchet` (gate que nunca mordeu, promovido no zero-day) e promoção-no-calado (§5 proibicoes).

## Pré-análise de T5 para a rodada futura (não decide nada agora)

Quando T1–T4 fecharem, o caminho de categoria precisa ser resolvido — deixo mastigado pra não re-derivar:

- O schema de tópico valida **estrutura/âncoras de um doc de memória** — é **higiene de conhecimento**, não Tier-0 por si (não toca dinheiro/PII/multi-tenant/fiscal no ato de validar frontmatter). Mesma categoria que `briefing`/`reference`, que o README e o ADR 0314 tratam como **grace → required só após backfill FP=0, nunca required de nascença**.
- Logo, sob a política vigente (0314: *required = só Tier-0*; 0327: exceção **só via emenda ADR** com reincidência dura + custo determinístico ~0), o flip natural do tópico é **(a) advisory-que-morde** — deixa a perna ficar **vermelha visível** em strict (sai do `grace: true`), **fora** do required do branch protection. Isso não precisa de emenda 0314 e já dá o "morde sem bloquear" que o próprio chip cita.
- **(b) required de verdade** exigiria uma emenda estilo 0327 provando reincidência dura (um tópico podre que passou e causou dano real) + custo ~0 determinístico. Hoje **não há reincidência** (0 tópicos pós-seed). Sem esse histórico, (b) é injustificável.
- Mecânica do flip, seja (a) ou (b): remover `grace: true` da entrada `Tópico` na matriz do [`memory-schema-gate.yml`](../../.github/workflows/memory-schema-gate.yml) (isso já liga STRICT=true → morde). Para (b), **adicionalmente** somar o context `Tópico (...)` ao [`governance/required-checks-baseline.json`](../../governance/required-checks-baseline.json) + flip do branch protection no mesmo PR (regra de sincronia de registro da 0314) — clique [W].

## O que re-medir na próxima rodada (re-rodar, não editar os números acima)

Estes números são um **fóssil datado de 2026-07-21** — re-rodar, nunca editar:

```bash
git ls-files 'memory/requisitos/*/topicos/*.md' | wc -l                 # nº de tópicos
git log --diff-filter=A --format='%h %ci %s' -- 'memory/requisitos/*/topicos/*.md'   # nascimentos + PRs
git log --format='%h %ci %s' -- 'memory/requisitos/*/topicos/*.md'      # todas as edições (cadência)
gh pr list --repo wagnerra23/oimpresso.com --state merged --search 'topicos/ in:path' --json number,mergedAt
```

Gatilhos de reabertura do chip: (T1) ≥5 tópicos em ≥2 PRs distintas; **e** (T2) 14 dias corridos desde 2026-07-21 (≥ 2026-08-04); **e** (T3) evidência de FP=0 sobre tópico de autoria independente; **e** (T4) ≥1 VP real ou cadência. Só então montar o pacote (a) ou (b) do T5 e levar a [W].

## Referências

- ADR 0345: `memory/decisions/0345-topicos-vivos-aprendizado-por-critica-revisada.md` (linha 76 — critério de promoção)
- ADR 0275 §5: calendário único de promoções (critérios pré-escritos A10/C2/G3)
- ADR 0314 / 0327: política *required = só Tier-0 + exceções via emenda*
- Log fundador: `memory/sessions/2026-07-21-topicos-vivos-aprendizado-critica-revisada.md` (§Próximos passos, item "Medir o piloto por algumas PRs")
- PR fundadora: [#4617](https://github.com/wagnerra23/oimpresso.com/pull/4617)
