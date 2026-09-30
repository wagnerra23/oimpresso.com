---
slug: 0420-ponto-aej-le-apuracao-e-falha-fechada
number: 420
title: "Ponto — o AEJ lê a apuração para horário contratual e ausências; dado legal ausente é recusa, nunca preenchimento"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-09-30"
module: pontowr2
tags: [ponto, portaria-671, aej, afd, relatorios-legais, playbook]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0413-ponto-fechamento-competencia-conformidade-relatorios-legais
  - 0093-multi-tenant-isolation-tier-0
pii: false
---

# ADR 0420 — Ponto: o AEJ lê a apuração; dado legal ausente é recusa

## Contexto

A ADR 0413 (W7) fixou a ordem **AFD → AEJ** e listou como violação *"geração de AFD/AEJ lendo
`ponto_apuracao_dia` em vez de `ponto_marcacoes`"*. Ao ler o leiaute oficial do AEJ (MTE, versão `002`,
gov.br "Leiaute do Arquivo Eletrônico de Jornada - AEJ") ficou medido que essa regra não cabe no AEJ
inteiro: o AEJ é o arquivo do **Programa de Tratamento de Registro de Ponto**, e dois registros dele
são, por definição, dado de tratamento — o **04** (horário contratual) e o **07** (DSR, falta não
justificada, banco de horas). Nenhum dos dois existe em `ponto_marcacoes`.

O leiaute também exige campos que o sistema não guarda: `motivo` na marcação incluída manualmente
(`fonteMarc "I"`) e na desconsiderada (`tpMarc "D"`), e a identidade do PTRP no registro 08.

## Decisão ([W] 2026-09-30, no chat da thread 12)

1. **Emenda a 0413 só para o AEJ:** os registros **04** e **07** do AEJ são gerados da apuração
   (`ponto_apuracao_dia`, `ponto_escala_turnos`, `ponto_banco_horas_movimentos`). O registro **05**
   (marcações) continua lido de `ponto_marcacoes`. Para o **AFD** a regra da 0413 segue intacta.
2. **Identidade do PTRP em configuração sem default**, como a do REP-P no AFD: vazia, o AEJ recusa e o
   catálogo o marca indisponível.
3. **Dado legal ausente é recusa (falha fechada), nunca preenchimento.** Isto vale para o `motivo`
   exigido pelo leiaute — [W] não decidiu o que escrever nele, e texto inventado num arquivo de
   fiscalização é pior que arquivo nenhum — e para CPF ou nome do colaborador ausentes.

## Consequências

- Uma empresa com marcação manual (sem motivo) ou anulação anterior a 2026-09-29 (o texto do motivo só
  passou a ser gravado nessa data) no período **não consegue gerar o AEJ daquele período** até que a
  lacuna seja resolvida. A recusa diz quantas marcações e de que tipo.
- O que o sistema não modela fica **fora do arquivo, declarado**: DSR e folga compensatória de feriado
  (registro 07, tipos 1 e 4), movimentos de banco de horas que não são inclusão nem compensação
  (`PAGAMENTO`, `EXPIRACAO`, `AJUSTE`), a redução da hora noturna em `durJornada` e o registro 06
  (matrícula eSocial para mais de um vínculo — o modelo tem um vínculo por colaborador).

## Como se reconhece violação

- Qualquer texto de `motivo` gerado pelo sistema sem vir de um dado gravado.
- AEJ que sai com configuração do PTRP vazia.
- Registro 05 lido da apuração.
