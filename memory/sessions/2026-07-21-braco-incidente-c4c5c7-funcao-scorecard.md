---
date: "2026-07-21"
hour: "14:30 BRT"
topic: "Braço-incidente + C4/C5/C7 na fixture não-circular do juiz funcao-scorecard: N 11→20, re-grade 7,5→8,5"
authors: ["C"]
tags: [funcao-scorecard, fixture, calibracao, braco-incidente, kappa, grade, nao-circular]
outcomes:
  - "Braço-incidente (t12/t13/t14): twins que MODELAM defeitos REAIS com rótulo ancorado no TESTE DE REGRESSÃO real (num_uf, IDOR, Radix), código segue sintético."
  - "C4/C5/C7 ganharam pares bom/ruim com armadilha (t15..t20); discriminante subiu de C1/C2/C3/C6 → C1..C7."
  - "3 juízes cegos frescos: 12/12 famílias, κ=1, T1 100%, 0 falso-discordo nas 6 armadilhas. Re-grade validação-não-circular 7,5→8,5."
---

# Braço-incidente + C4/C5/C7 — fixture funcao-scorecard

## TL;DR

Estendi a fixture não-circular do juiz `funcao-scorecard` de **11 → 20 twins**, fechando 3 dos gaps que a rodada 3 nomeou. **Braço-incidente**: 3 twins (`t12` num_uf, `t13` IDOR, `t14` Radix) que **modelam defeitos REAIS já catalogados**, com o rótulo **ancorado no teste de regressão real** — não na minha mutação, não em opinião. **Critérios-extra**: pares bom/ruim pra **C4/C5/C7**, cada bad com armadilha "parece-ruim-mas-é-ok". **3 juízes cegos frescos passaram: 12/12 famílias, κ=1, T1 100%, 0 falso-discordo.** Re-grade: validação-não-circular **7,5 → 8,5**.

## Contexto

Pedido [W]: fechar a validação-não-circular de 7,5 → ~8,5. A rodada 3 ([session arrume](2026-07-21-arrume-fixture-twins-dificeis-kappa.md)) tinha deixado nomeados os gaps pra 8-9: braço-incidente com teste real, N maior, C4/C5/C7. Este é o fechamento deles.

## O que fiz (integrando, não reinventando)

### Braço-incidente (t12–t14) — o rótulo vem do teste de regressão, não de mim
A crítica mais forte da fixture original era "o rótulo é a MINHA mutação". O braço-incidente responde: 3 famílias agora têm ground-truth num **teste que roda no CI**, independente da sessão-juiz:
- `t12-incident-numuf-inflacao` → C2 discordo · âncora [`IncidentValorInfladoNumUfTest`](../../tests/Unit/Utils/IncidentValorInfladoNumUfTest.php) (incidente 2026-06-05, desconto % → float 5 casas lido como milhar → infla ~×100k).
- `t13-incident-idor-cross-tenant` → C1 discordo · âncora [`UpdateCrossTenantIdorTest`](../../tests/Feature/Purchase/UpdateCrossTenantIdorTest.php) (`findOrFail` sem global scope + update sem `business_id`).
- `t14-incident-empty-value-list` → C3 discordo · âncora [`SafeSelectItem.tsx`](../../resources/js/Components/ui/SafeSelectItem.tsx) + proibicoes §5 2026-06-29 (distinct com membro vazio silencioso).

**Código segue SINTÉTICO de propósito** — colar o repo reintroduziria circularidade (o juiz saberia a resposta do contexto). O que muda é a FONTE do rótulo: de "mutação minha" → "teste de regressão real".

### Critérios-extra (t15–t20) — C4/C5/C7 com armadilha
- **C4** atomicidade: `t15` 2 escritas fora de transaction (discordo) × `t16` 2 escritas que DECLARAM caller-wraps (concordo — rubrica C4 "OU declara que o caller envolve").
- **C5** N+1: `t17` query dentro do `foreach` (discordo) × `t18` `foreach` sobre relação eager-loaded (concordo — parece N+1, não é).
- **C7** tipos: `t19` retorno `false|string|array` com docblock mentindo (discordo) × `t20` `?int` tipado+documentado (concordo).

O discriminante agora cobre **C1..C7** (era C1/C2/C3/C6). C8 é contagem — `n/a` em código isolado.

## Resultado (reprodutível do git)

`node scripts/governance/funcao-scorecard-calibracao.mjs --score tests/governance-fixtures/funcao-scorecard/calibracao-2026-07-21/judge-ext-b{1,2,3}.json`

| Rodada | famílias | κ | over-flag | incerto | falso-discordo | Veredito |
|---|---|---|---|---|---|---|
| b1/b2/b3 | 12/12 | 1,0 | 0 | ✓ | 0 | ✅ CALIBRADO |

**T1 = 100%** (0 flips/20 saliente; 32/32 células emitidas pelos 3). **Sinal de cegueira forte:** cada juiz usou **1 tool call** (só o `--pack`, nunca abriu o selado) e os 3 INDEPENDENTEMENTE adicionaram `C2:discordo` ao t13 (mutação em dinheiro) que o SELADO **não lista** (salient C1 só) — se tivessem lido o manifesto, teriam espelhado só o C1. Divergência = julgamento independente.

Self-test do runner segue mordendo com 20 twins (juiz-perfeito PASSA, juiz-carimbo FALHA).

## Re-grade (honesta)

Validação-não-circular do juiz: **7,5 → 8,5.** Fechou: **N 11→20**, **C4/C5/C7 cobertos**, **braço-incidente ancorado em teste de regressão REAL**.

**Falta pra 9-10 (registrado, não inflado):** κ vs gold HUMANO (hoje κ vs rótulo objetivo — defensável pra defeito mecânico, mas a barra nomeou humano); diversidade de modelo (os 3 juízes são a mesma família Opus); e o κ=1 de novo pode significar que os twins ainda estão dentro do alcance de um juiz competente — um teste mais forte procuraria a FRONTEIRA de erro do juiz, não só confirmaria acerto.

## Fronteira honesta (inalterada)

Calibra o **INSTRUMENTO** (o juiz discrimina defeito mecânico não-circularmente). **NÃO** re-valida os vereditos da função REAL (`ProductUtil`) — `validation_status: invalidado` do scorecard dele não muda por esta rodada. O braço-incidente aproxima da função real (rótulo agora é teste de regressão), mas o código é sintético por construção.

## Arquivos

- `tests/governance-fixtures/funcao-scorecard/twins/t12..t20.php.txt` (9 novos)
- `tests/governance-fixtures/funcao-scorecard/manifesto-SELADO.json` (+9 entradas selado + `_meta.bracos`)
- `tests/governance-fixtures/funcao-scorecard/calibracao-2026-07-21/judge-ext-b{1,2,3}.json` (vereditos)
- `tests/governance-fixtures/funcao-scorecard/README.md` (3 braços documentados)
- `memory/requisitos/_Governanca/FUNCAO-SCORECARD-METODO.md` §5 (rodada 4, append)
