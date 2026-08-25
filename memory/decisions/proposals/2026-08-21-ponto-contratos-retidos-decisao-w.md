---
proposal_id: ponto-contratos-retidos
status: open
created: 2026-08-21
proposed_by: claude-code
decided_by: wagner
title: "Ponto — os 2 contratos retidos (`ponto-fechamento`, `ponto-rep-p`): o que falta é decisão, não código"
module: Ponto
type: bloqueio-de-produto
related_prs: [6113, 6114, 6115]
---

# Os 2 contratos retidos do Ponto — o bloqueio é de PRODUTO, não de engenharia

> **Pedido que originou:** *"construa o contrato"* → os 2 retidos (2026-08-21).
> **Resposta curta:** os contratos eu escrevo hoje; as telas eu **não** construo sem as decisões
> abaixo, porque construí-las é inventar um fluxo com consequência legal.

## 1. Por que não basta descer os contratos

Medido no `contract.schema.json` e no `scripts/contrato-de-tela.mjs`: **não existe estado "em
espera"**. Os campos são `alvo` + `secoes` (obrigatórios) e o único mecanismo de skip é o nome
`EXEMPLO`. O job `Preflight + contratos ativos` varre `git ls-files '*.contract.json'` — todo
contrato não-EXEMPLO é **ativo**.

Consequência: contrato com `alvo` inexistente nasce **vermelho permanente**, e como o job dispara
em qualquer `.tsx` tocado, ele pinta **todo PR de UI do projeto**. Foi exatamente por isso que a
decisão [W] de 2026-08-21 (opção B) reteve estes dois. Descê-los agora reintroduz o ruído que os
PRs #6113/#6114/#6115 acabaram de eliminar.

## 2. O que EXISTE (e é bom)

| Peça | Estado |
|---|---|
| Fonte de design | ✅ `prototipo-ui/cowork/ponto-fechamento.jsx` (258 ln) e `ponto-mobile.jsx` |
| Âncoras declaradas | ✅ `fechamento-pre-checagem` · `fechamento-totais` · `repp-fila-validacao` · `repp-nota-regras` |
| Regras de negócio | ✅ o protótipo traz `REGRAS` e `PASSOS` explícitos |

Os contratos são **deriváveis hoje** — mesma técnica de `ponto-painel` e `ponto-espelho`.

## 3. O que NÃO existe — e por que isso muda a natureza do trabalho

| Peça | Estado | Consequência |
|---|---|---|
| CU no SDD | ❌ `CU-PONTO-01..14` não cobrem fechamento nem REP-P | UC derivado do código seria tautológico (§5 2026-06-05) |
| US no SPEC | ❌ nenhuma para estas telas | sem âncora de escopo |
| **Blade legado** | ❌ **não existe** | **é a diferença decisiva** |
| Rota / Controller | ❌ nenhuma | |
| Backend | ❌ `fechar_competencia` 0 arquivos · `validacao_mobile` 0 arquivos | |

⚠️ **O Espelho foi construível porque tinha PARIDADE**: o contrato dele declara *"campo a campo"* a
importação de `espelho/show.blade.php` + `reports/espelho-pdf.blade.php`. A copy legal (`Portaria MTP
671/2021 Art. 85`) veio de um Blade que já roda em produção — eu **transcrevi**, não redigi.

Estas quatro telas **não têm esse chão**. Construí-las é redigir do zero um fluxo que produz efeito
jurídico.

## 4. As telas MUTAM — e o que elas mutam é protegido por lei

Não é diagnóstico read-only. Medido no protótipo:

**`Fechamento`** — máquina de 4 passos: `Pré-checagem → Consolidar apuração → Fechar competência →
Gerar AFD/AEJ`. O `confirm` do próprio protótipo diz: *"Depois disso a marcação só muda por anulação
com trilha de auditoria."* Há ainda um botão **"Consolidar com exceções"**, descrito como *"registra
os bloqueios como exceção assinada"*, e um **"Reabrir"**.

**`ValidacaoMobile`** — `Validar` / `Recusar` sobre **marcações**. E `ponto_marcacoes` é
**append-only por força de lei** (Portaria 671/2021 · `Marcacao::anular()`): "recusar" não pode ser
`UPDATE`.

## 5. As decisões [W] — nomeadas, não vagas

O commit da opção B falava em *"decisões [W] 1-4"* sem enumerá-las, e elas não estão no repo (varri
`memory/**` e o SPEC). Derivadas do protótipo, são estas:

1. **Quem pode fechar uma competência, e o fechamento é reversível?** O protótipo oferece "Reabrir"
   depois de fechado. Reabrir competência fechada tem consequência em fiscalização — é decisão sua,
   não default de engenharia.
2. **O que é "exceção assinada"?** O protótipo consolida "com exceções" registrando bloqueios.
   *Assinada por quem, com que prova, guardada onde?* Sem isso, o botão promete um ato jurídico que
   o sistema não pratica.
3. **Como "recusar" uma marcação sem violar append-only?** Marcação nova com `ORIGEM_ANULACAO` (o
   padrão que o módulo já usa) ou entidade separada de validação? São modelos de dado diferentes.
4. **AFD/AEJ entram neste escopo ou são outro passo?** O 4º passo do protótipo gera arquivo fiscal.
   `AEJ` aparece em 4 arquivos PHP; `fechar_competencia` em nenhum.

## 6. O que eu faço assim que houver resposta

- Escrevo os 2 `.contract.json` (deriváveis hoje — âncoras e copy saem do protótipo).
- Construo as 4 telas + backend, na mesma disciplina do Espelho: copy **copiada** do contrato, nunca
  redigitada; `business_id` escopado; `Inertia::defer` em prop cara.
- Desço contrato e tela **no mesmo PR** — que é o que mantém o gate verde.

## 7. A alternativa, se a resposta demorar

Se as decisões não vierem cedo, o honesto **não** é descer os contratos vermelhos: é deixá-los onde
estão (Cowork) e manter esta proposta como o registro de que a lacuna é conhecida e tem dono. Contrato
vermelho permanente treina o time a ignorar gate — custo maior que a ausência do contrato.

Refs: decisão [W] 2026-08-21 (opção B) · PRs #6113 · #6114 · #6115 · Portaria MTP 671/2021
