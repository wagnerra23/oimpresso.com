---
slug: 0276-decisao-pelo-fluxo-classes-pares-adversariais
number: 276
title: "Decisão pelo fluxo — 3 classes de decisão; pares adversariais substituem aprovação humana em decisões ritualizáveis (Wagner sai do caminho crítico)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: meta
decided_by: [W]
decided_at: "2026-06-12"
module: governance
tags: [governanca, decisao, adversarial, refutador, hitl, autonomia, sdd]
supersedes: []
superseded_by: []
related:
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
---

# ADR 0276 — Decisão pelo fluxo: classes de decisão + pares adversariais

## Contexto

Na reestruturação SDD (2026-06-12), 3 decisões ficaram "gated no Wagner" (tabela de
identidade de pastas, skim do golden set de recall, secret do RAGAS). Wagner, textual:

> "acho que não sou mais necessário nisso. Pois sempre é as mesmas decisões e o erp tem
> seus ritos [...] Eu acho que ter um adversário pensando no módulo e testando e outro
> adversário fazendo o ajuste do projeto seriam melhor do que eu no processo.
> **NÃO QUERO SER A TRAVA.** deve ter método mais eficiente para isso."

Evidência do mesmo dia a favor do método: o auditor adversarial da Fase 1 pegou o codemod
corrompendo 11 ADRs históricos (humano não pegou); o gate-selftest pegou catraca sem dente
no primeiro run. Converge com a meta-lição 2026-06-11 ("decisão por âncora, não por
Wagner"), com o HITL por nível do ADS (PolicyEngine) e com ADR 0105 (cliente pagante é o
sinal de priorização — não a opinião do fundador).

## Decisão

Toda decisão do processo de desenvolvimento pertence a UMA de 3 classes:

| Classe | O que é | Quem decide | Registro |
|---|---|---|---|
| **D0 — mecânica** | Verificável por máquina (formato, schema, path existe, catraca, baseline) | Gate determinístico em CI | o próprio gate |
| **D1 — rito** | Julgamento padronizável (triagem de pastas, golden sets, mapeamentos, promoções a required com critérios objetivos cumpridos, aceite de lote de backfill) | **Par adversarial**: proposer (propõe com evidência) + refutador (sessão fresca, modelo ≥ proposer, tenta DERRUBAR). Concordância = decidido. Desacordo → 3º juiz (voto 2-de-3). Ainda dividido → fila Wagner (exceção) | entry no `governance/sdd-verification-ledger.json` |
| **D2 — humana** | Lista CURTA e FECHADA: (1) secrets/credenciais; (2) dinheiro/pagamentos/pricing; (3) mudança de escopo Tier 0 multi-tenant; (4) deleção irreversível de dados de produção; (5) identidade pública (marca, comunicação a cliente); (6) mudanças NESTA lista | Wagner | ADR ou aprovação explícita |

Regras complementares:

1. **Cliente pagante continua o sinal** de o-quê-priorizar (ADR 0105, intacto).
   Preferência estética de UI segue o rito vigente (gate visual MWART) até ADR própria.
2. **Promoções advisory→required** (calendário ADR 0275): a checagem "critérios objetivos
   cumpridos" vira D1; o flip de branch protection permanece operacional (e o
   protection-drift garante que demoção silenciosa é impossível).
3. Par adversarial **sem evidência não decide**: refutador que não verifica contra o repo
   real conta como ausente; decisão fica pendente.
4. Default de classificação: na dúvida entre D1 e D2, é D2 (1 pergunta a Wagner) — mas a
   resposta deve virar regra escrita pra próxima vez ser D1.

## Aplicação imediata (mesmo dia)

- Tabela `_TRIAGEM-IDENTIDADE-2026-06.md` → par adversarial (refutador valida cada linha
  contra o repo; concordâncias aplicam; desacordos → 3º juiz).
- Golden set `tests/eval/recall-golden.yaml` → par adversarial (idem).
- Secret RAGAS **eliminado**: canário real roda no CT 100 via cron com a
  `OPENAI_API_KEY` que já existe em `/opt/oimpresso-mcp/code/.env` — nenhum secret novo
  no GitHub (que seria D2).

## Consequências

- Wagner sai do caminho crítico das decisões de rito; throughput deixa de depender da
  agenda dele (era o risco nº1 apontado pelos críticos do plano SDD).
- Custo: ~2× tokens por decisão D1 (proposer+refutador). Aceito — erro de decisão em
  memória canônica custa mais (caso real: corrupção dos ADRs históricos).
- Risco de degenerar em carimbo (refutador complacente): mitigado pela regra 3 (evidência
  obrigatória), pelo gate-selftest (fixtures com refutação esperada) e pela amostragem do
  `backfill_error_rate` (<2%, ADR 0275).
- Esta ADR é ela mesma D2 (mudança na lista/na governança de decisão): aceita por Wagner
  no chat em 2026-06-12, citação acima.
