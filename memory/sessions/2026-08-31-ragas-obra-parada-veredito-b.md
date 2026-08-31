---
date: "2026-08-31"
topic: "Obra parada do watchdog G6 nos 2 baselines RAGAS — veredito (b), e o colapso de 5 semanas que a investigação encontrou"
authors: [C]
related_adrs:
  - 0318-ragas-eval-real-mata-tautologia-ct100-staging
  - 0317-maquina-revisao-adr-quando-rever-gatilhos
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
us: [US-COPI-140, US-COPI-136]
---

# Obra parada nos 2 baselines RAGAS — veredito (b), e o P0 que apareceu no caminho

## TL;DR

- O check advisory G6 acusa 2 baselines RAGAS parados há 61d. **Não é o caso (a)** ("cron vivo,
  entrega morta"): varredura por **sítio de escrita** (não por basename) mostra **zero escritores
  automáticos** nos dois. Veredito **(b)** — referências curadas, como o precedente #4822.
- O canary **entrega todo dia** (20/20 runs `success`; run 33307668083 de 30/08: modo real,
  gate `pass`, delta −0,69%). A entrega dele é o veredito diário, não regravar a referência.
- **O achado que ninguém pediu:** o eval *não-tautológico* (`jana:ragas-real-eval`, ADR 0318) está
  **5 semanas sem `pass`** — `context_recall` de 0,40 → 0,03 (**−92%**), duas semanas com
  `no_context=51`. A semana 30/08 não saiu e o **CT 100 está offline há ~3 dias**.
- **Nada de baseline foi regravado.** Rebaixar os pisos para acomodar a queda seria editar o
  baseline para ficar verde. O vermelho **é** o achado — e a decisão é [W].

O check advisory `crons de governança vivos? (watchdog G6 · ADR 0317)` falha desde ~2026-08-30
acusando **2 artefatos de estado parados há 61d**. Medido nesta sessão em `main` fresco
(`71e559e349`), não citado de segunda mão:

```
📦 entrega — 16 artefato(s) de estado com data interna (de 266) · limite 60d · 2 🔴 parado(s)
🔴 governance/jana-ragas-baseline.json      — parado há 61d (última data interna: 2026-07-01)
🔴 governance/jana-ragas-real-baseline.json — parado há 61d (última data interna: 2026-07-01)
EXIT=1
```

O watchdog manda separar 3 casos — (a) cron que rodava e parou de entregar · (b) artefato curado
à mão cuja revisão envelheceu · (c) mecanismo inteiro parado — e diz **como** separar:
*"varra os escritores do path (quem faz write nele) — sem escritor, não é (a)"*.

## Veredito: (b) para os dois. A hipótese (a) está refutada.

A investigação partiu da hipótese de que era **(a)**, apoiada numa varredura que casava o
**basename** dos arquivos. Basename devolve **menção**, não escrita — e é exatamente contra isso
que a errata de 2026-07-26 no próprio `cron-watchdog.mjs` avisa (*"idade não revela autoria"*).
Refeita a varredura por **sítio de escrita**:

| Arquivo | Alegado escritor | O que ele realmente faz |
|---|---|---|
| `jana-ragas-baseline.json` | `jana-ragas-canary.yml` | escreve **só** em `workflow_dispatch && update_baseline=='true'` |
| | `scripts/jana-ragas-runner.py` | único `write_text` (L164) dentro de `update_baseline_file()`, chamado só por `if args.update_baseline:` (L203) |
| | `EvalReconciler.php` | **LEITOR** (L280 — *"Leitor default do artefato canary"*) |
| `jana-ragas-real-baseline.json` | `JanaRagasRealEvalCommand` | **LÊ** (`BASELINE_PATH` → `resolveThresholds`, L152); escreve em `storage/app/governance/ragas-real-eval-latest.json` (L415) |
| | `RecordRagasEvalAlertCommand` | só `@see` em comentário |
| | `ct100-jana-evals.sh` | só comentário |
| | `app/Console/Kernel.php` | agendamento |

**Escritores automáticos: zero, nos dois.** O cron do canary nunca passa `--update-baseline`; o
comando do eval real é *consumidor* dos pisos, não produtor do arquivo. São **referências
curadas por decisão**, iguais aos 5 scorecards do precedente 2026-07-26 (#4822).

E o mecanismo **entrega todo dia**. Artifact da run agendada `33307668083` (2026-08-30):

```json
{"gate_status":"pass","mode":"real","cost_usd":0.0408,"n_questions":51,
 "metrics_diff":[{"metric":"faithfulness","current":1.0,"baseline":1.0,"delta_pct":0.0},
                 {"metric":"answer_relevancy","current":0.8451,"baseline":0.851,"delta_pct":-0.69}]}
```

20/20 runs agendadas com `success`. A entrega do canary é o **veredito diário**, não a regravação
da referência — e regravá-la a cada run destruiria a capacidade de detectar drift.

## O que a investigação encontrou, e ninguém tinha pedido

O sinal **honesto** da Jana não é o canary. `JanaRagasCiCommand` L121-123 faz `answer =
ground_truth` e `context = ground_truth` — é o gate tautológico que a [ADR 0318](../decisions/0318-ragas-eval-real-mata-tautologia-ct100-staging.md)
nomeou, e por isso seu `faithfulness` crava 1.0000 todo dia, por construção. O medidor não-tautológico
é o `jana:ragas-real-eval`, cujo trend vive na **branch órfã** `governance/ragas-real-trend`
(o arquivo está no `.gitignore` de `main` de propósito). Lido de lá (último commit
`2026-08-23T08:30:04-03:00`):

| semana | gate | n_eval | no_context | faithfulness | context_recall |
|---|---|---|---|---|---|
| 2026-06-28 | pass | 51 | 0 | 0.7145 | 0.3951 |
| 2026-07-19 | pass | 51 | 0 | 0.7127 | 0.4016 |
| 2026-07-26 | **fail** | 51 | 0 | 0.6865 | 0.3461 |
| 2026-08-02 | **skipped** | 0 | 51 | — | — |
| 2026-08-09 | **fail** | 50 | 1 | 0.3030 | 0.0430 |
| 2026-08-16 | **fail** | 50 | 1 | 0.2748 | 0.0314 |
| 2026-08-23 | **skipped** | 0 | 51 | — | — |

**5 semanas consecutivas sem `pass`.** O `context_recall` caiu de 0.40 para 0.03 (−92%) e duas
semanas não recuperaram contexto algum (`no_context=51`). A semana **2026-08-30 está ausente**, e
o `tailscale status` reporta `ct100-mcp … offline, last seen 3d ago` — duas fontes independentes
convergindo para transporte/host fora do ar desde ~2026-08-28.

Isto é o oposto de um artefato envelhecido: é o piso fazendo o trabalho dele. O baseline "parado
há 61d" é justamente a **referência contra a qual a queda foi detectada**.

**Causa NÃO medida.** Há mudanças na janela do colapso (entre elas #5169/#5193, que mexeram no
redactor do **indexador**), mas nomear causa a partir de leitura de log de commits é o
anti-padrão do §5 2026-07-15. O teste que decide exige o CT 100 de volta: rodar
`jana:ragas-real-eval --json` no container de staging e ver se `no_context` persiste.

## O que fiz — e o que deliberadamente não fiz

Feito (é meu):
- **Errata datada** em `gaps_conhecidos` do `jana-ragas-real-baseline.json`. A prosa afirmava em
  presente *"o schedule NUNCA disparou sozinho"* e *"a órfã tem 1 semana só"* — caducou em
  2026-07-17, quando o invocador caminho A (#4426) foi instalado; hoje são 7 semanas e 6 delas
  posteriores ao invocador. É LC-10 vivo em canon. O fato datado ficou preservado; só acrescentei.
- **Alerta P0** registrado no mesmo arquivo, ao lado dos pisos que ele ameaça.

Não feito, e o motivo:
- **Não regravei nenhum baseline.** No `real-baseline` seria rebaixar `thresholds_regressao` para
  acomodar uma queda de 92% — editar o baseline para ficar verde (§5 2026-08-26). No canary
  baseline, regravar só carimbaria data nova num medidor tautológico: apagaria o vermelho sem
  fechar entrega nenhuma.
- **Não mexi no watchdog.** Ele está certo: mediu idade, acusou, e mandou investigar. O eixo 2 não
  tem allowlist por design (o registro de silêncio é do eixo 3).
- **Verifiquei que a errata NÃO apaga o alarme**: rodei o watchdog depois da edição e ele segue
  `EXIT=1` com os mesmos 2 arquivos. Nenhuma chave de `CHAVES_DATA` foi introduzida — o que seria
  apagar o alarme por acidente.

## Aberto para decisão [W]

1. **O colapso do eval real** (P0). O gate está vermelho há 5 semanas e o `⛔` é o achado, não um
   conserto silencioso a fazer.
2. **CT 100 offline desde ~28/08** — bloqueia o diagnóstico da causa e derrubou a semana 08-30.
3. **O canary tautológico gasta ~US$ 1,22/mês** rodando em modo real uma métrica que é 1.0 por
   construção. A ADR 0318 já tinha cortado esse gasto no gate de PR; o cron diário ficou.
4. **Re-cura dos dois baselines** (o desfecho do precedente #4822) fica pendente porque, no
   `real-baseline`, re-curar *é* decidir o que fazer com os pisos diante do colapso.
