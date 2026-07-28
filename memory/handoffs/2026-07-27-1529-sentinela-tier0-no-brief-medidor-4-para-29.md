---
date: "2026-07-27"
time: "15:29 BRT"
slug: sentinela-tier0-no-brief-medidor-4-para-29
tldr: "A sentinela de exposição Tier-0 já ranqueava o débito e publicava só numa issue semanal — agora publica no Daily Brief. Ao plugar, descobri que a perna casos_coverage dela estava morta: media 4 telas cobertas onde havia 29. PR #4843 mergeado, smoke real em prod verde. 6 chips abertos e em execução paralela."
decided_by: [W]
prs: [4843]
next_steps:
  - "6 sessões paralelas em voo (chips): Sells/Show · Tributação NfeBrasil · Fiscal/Nfe+Sped · migrar ucHeadRe · fixtures do casos-gate · 28 UC órfãos"
  - "4 delas regravam o MESMO scripts/casos-coverage-baseline.json — conflito textual esperado; a reintrodução de violação é barrada pelo required Casos-coverage · ratchet"
  - "Pergunta original (quais módulos justificam ANTI-REGRESSAO) segue ABERTA — redirecionada, não respondida"
related_adrs:
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
---

# Handoff — sentinela Tier-0 no brief + medidor 4→29

## De onde veio

[W] pediu levantamento (não implementação): **quais módulos justificam ganhar um
`ANTI-REGRESSAO-*.md`** (destilado do legado Delphi), porque a Camada 1 do `sdd-from-source`
mordeu no piloto Produto — ≥6 achados, incluindo bloqueador que zeraria estoque — mas só roda
onde o destilado existe. Medido: `git ls-files "memory/requisitos/*/ANTI-REGRESSAO*"` → **2
arquivos, 1 módulo** de ~40.

## Onde parou

A pergunta original **segue aberta**. O levantamento redirecionou a prioridade: o gargalo
imediato não era o destilado, era o **canal** de uma régua que já existia — e um **defeito**
nela.

## O que foi feito (mergeado e em prod)

[PR #4843](https://github.com/wagnerra23/oimpresso.com/pull/4843) — 94 checks verdes, merge
`2704fa59fe`, deploy verde, smoke real:

```
🟡 Exposição Tier-0: 89/118 quentes sem teste (Δ-30) · topo: Sells/Show.tsx
```

Provado end-to-end: `--stdout` da sentinela em prod → Service real via `tinker` → `inject()`
sobre o brief real de `mcp_briefs`, entrando como 1º bullet do `## FLAGS` sem quebrar o
`---END---`. `Process::run(['node','-v'])` no Hostinger dá exit 0 — a linha não nasce muda.

**Duas partes:**

1. **Plug** — `ExposicaoTier0BriefLineService` (padrão dos 10 irmãos) + modo `--stdout` na
   sentinela + kill-switch + registro em `ci-sqlite-pest.list` (12 testes Pest no CT 100).
2. **Fix do medidor** — `ucHeadRe()` é ancorado em `^UC-` e a sentinela o aplicava na **linha
   crua** (`## UC-CEDI-01 · …`), então `hasCasosCoverage()` era sempre `false`:
   `covered = e2e || casos` virava `covered = e2e`. Cobertura **4 → 29**, débito **120 → 89**,
   Cliente **0/7 → 7/7**. A divergência contra `npm run screen:files` desapareceu — prova de
   que o fix está certo. **O ganho é destravamento de medição, não cobertura nova.**
   Raiz da raiz: `ucsDeclaredInCasos()` agora é fonte única do **parser** (não só do regex),
   com 9 testes e controle-negativo. `casos-coverage-guard.mjs` (required) **não foi tocado**.

## Estado MCP no momento do fechamento

Consultado 2026-07-27 ~15:29 BRT:

- **`cycles-active`** → `Nenhum cycle ATIVO em COPI`.
- **`my-work`** (@wagner) → **8 tasks, todas em REVIEW**: US-TR-309, US-TR-310, US-PG-008,
  US-PROD-027, US-TR-305, US-TR-306, US-TR-311, US-PROD-025. **Nenhuma criada por esta
  sessão** — o trabalho daqui foi 1 PR + 6 chips (sessões locais), não tasks MCP.
- **`decisions-search "exposição Tier-0 sentinela cadência brief"`** → 4 ADRs, nenhuma nova
  no intervalo; as relevantes já eram conhecidas (0298 teto de governança anti-proliferação,
  0307 Onda 0 enforcement). Nada aceito hoje que mude o que foi feito.
- **Brief do dia** → gerado há ~2h, FLAGS sem a linha nova (esperado: o deploy saiu 15:03 e o
  cron roda `0 7,11,14,17,20,23` BRT — a linha aparece na próxima geração).

## Para a próxima sessão

**6 chips iniciados em sessões locais paralelas** (todos com contexto medido embutido):
`Sells/Show` · Tributação NfeBrasil (2 telas) · `Fiscal/Nfe`+`Sped` · migrar os 5 consumidores
do `ucHeadRe` · fixtures que provam a mordida do `casos-gate` · 28 UC órfãos.

**Colisão medida:** 4 deles regravam o **mesmo** `scripts/casos-coverage-baseline.json` (lista
itemizada de 220 violações num arquivo único). Conflito textual é esperado e resolvível. O
vetor perigoso — sessão B reintroduzir o que A removeu — está **contido por gate required**:
`Casos-coverage · ratchet (trio + rastreabilidade)` com `enforce_admins`, cujo step "Baseline
só-desce" roda `--check-baseline-shrink` contra `main` e exige trailer `BASELINE-GROW` para
crescer. (`baseline-tamper-guard` é advisory — não está no `required-checks-baseline.json`.)

**Débito medido hoje** (re-rodar as portas antes de citar — número datado apodrece): 235 telas
· 118 quentes Tier-0 · 29 cobertas · **89 sem teste de comportamento** · 192 sem `casos.md` ·
181 UC declarados com **32 (18%)** com prova executável · 28 órfãos · E2E 9/235 · a11y 3/235.

**Se retomar a pergunta original:** os ≥6 achados da Camada 1 se separam em 3 classes —
**(A)** mismatch de chave já tem máquina (`AutosaveContractRunner`, 11 fixtures, CI);
**(B)** campo omitido = zero, sem harness; **(C)** comportamento de UI legado, **não
mecanizável**, exige o print do Delphi. `ANTI-REGRESSAO` serve só para (C), e o gargalo lá é
**acesso ao Office Comercial rodando** ([W]/[F]), não redação — baseline 186 asserções/2 telas.

## Registro de erro

4 erros de medição meus nesta sessão, todos classe **LC-08**, cada um mudou o alvo do
trabalho. Ledger incrementado **13 → 14** em [`LICOES_CODE.md`](../LICOES_CODE.md); detalhe em
[session 2026-07-27](../sessions/2026-07-27-sentinela-tier0-no-brief-medidor-consertado.md) §7.
O mais informativo: `grep "casos-result"` não casa `casos-test-**results**.json` → concluí que
um gate **required** dormia sempre. Corolário: *quando o resultado de um filtro sustenta uma
conclusão forte, teste o filtro contra um caso que você SABE que existe antes de concluir a
ausência.*
