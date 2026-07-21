# Fixture de calibração NÃO-CIRCULAR do juiz `funcao-scorecard`

Fecha o gargalo da grade 2026-07-21 (dimensão *validação-não-circular do juiz* = **4/10**): o bite-test de 2026-07-21 foi **invalidado por circular** (chamou código de produção de "defeito plantado", pré-declarou o veredito, o juiz tinha a resposta no contexto). Esta fixture é o remédio que o **§5 do [FUNCAO-SCORECARD-METODO](../../../memory/requisitos/_Governanca/FUNCAO-SCORECARD-METODO.md)** já especificava.

## Por que é NÃO-circular (por construção)

- **Twins SINTÉTICOS** (`twins/*.php.txt`) — código **fabricado** (`Widget`/`Gadget`/`PriceRow`, nomes que não existem no repo). O juiz **não pode saber a resposta do contexto** do repo — não há contexto.
- **Rótulo = a MUTAÇÃO** que apliquei (`manifesto-SELADO.json`), **objetivo e determinístico** (`label_source: mutation`) — não é opinião de modelo, é qual defeito foi injetado. É o gabarito estilo **CodeJudgeBench / SWE-bench Verified** (o padrão-ouro que a grade apontou).
- **Juiz CEGO** — roda `--pack` (gerado SEM o manifesto, por construção), julga, e **nunca** abre o selado. Quem monta o pack **não exibe** os vereditos (regra `_quem_monta_nao_exibe` do ledger).
- **`.php.txt`** (não `.php`) — o auditor real não conta os twins como código vivo (padrão corruptor de `governance-fixtures/`).

## O que NÃO é (fronteira honesta)

Isto calibra o **INSTRUMENTO** (o juiz discrimina defeito mecânico?), **não** aprova os vereditos de uma função REAL específica — esses ainda dependem da **âncora de intenção externa** por-função (SPEC/charter/ADR/golden), que é o trabalho do **tópico** (`memory/requisitos/<Mod>/topicos/`), não desta fixture.

**Complementar — NÃO estende — o ledger `tipo:"juiz"`.** Aquele é **humano-só de propósito** (calibra juízos sem verdade objetiva: status de módulo, refutação de prosa). Esta fixture cobre o caso **mecanicamente definível** (defeito sintético), onde o rótulo objetivo é melhor que humano. Dois mecanismos, dois ground-truths — ver `_custo_real` do `_meta.schema_entry_juiz`.

## Como rodar

```bash
# 1) o runner morde? (juiz-perfeito PASSA, juiz-carimbo FALHA)
node scripts/governance/funcao-scorecard-calibracao.mjs --selftest

# 2) emitir o PACK CEGO (o que o juiz vê — sem rótulos)
node scripts/governance/funcao-scorecard-calibracao.mjs --pack

# 3) rodar N juízes FRESCOS (sessões isoladas), cada um julga o pack e grava vereditos.json;
#    NUNCA dê o manifesto ao juiz.

# 4) pontuar cada juiz vs o selado
node scripts/governance/funcao-scorecard-calibracao.mjs --score <vereditos.json>
```

**Passa se:** ≥80% das famílias de defeito achadas com o critério certo · **κ (chance-corrected) ≥ 0,6** vs o rótulo objetivo · **zero discordo** no controle limpo (t07) · **incerto** no sem-âncora (t08) · nenhum falso-discordo nos bons. Repetibilidade (T1): ≥90% por-critério em 3 rodadas.

**20 twins em 3 braços:**

**Braço sintético-mutação (t01–t11)** — código fabricado, rótulo = a mutação determinística.
- **Fáceis (t01–t08)** cobrem os defeitos óbvios (C1/C2/C3/C6 + controle + incerto).
- **DIFÍCEIS (t09–t11)** são armadilhas onde um juiz preguiçoso erra (100% em caso óbvio prova pouco):
  - `t09-partial-scope-nao-tenant` — escopa por `location_id`, parece escopado mas **não é** business_id (C1 discordo).
  - `t10-golden-vetor-errado` — cita um golden que cobre **outra** operação; "existe golden" não basta, tem que cobrir **o vetor** (C2 discordo, refinamento do 4617).
  - `t11-nullable-tipado-ok` — retorno `?Coupon` tipado é contrato explícito, **não** é o empty-string do t05 (C3 concordo — quem carimba todo null erra aqui).

**Braço-incidente (t12–t14)** — MODELAM defeitos REAIS já catalogados; o rótulo é ancorado no **teste de regressão real** (não na mutação, não em opinião), mas o CÓDIGO segue **sintético** (não colado do repo → não-circular por construção):
- `t12-incident-numuf-inflacao` — desconto % gera float de 5 casas que o parser pt-BR lê como milhar → infla ~×100k. Âncora: [`IncidentValorInfladoNumUfTest`](../../../tests/Unit/Utils/IncidentValorInfladoNumUfTest.php) (C2 discordo).
- `t13-incident-idor-cross-tenant` — `findOrFail(id-do-request)` em Model sem global scope + `update()` sem `business_id` → escrita cross-tenant em dinheiro. Âncora: [`UpdateCrossTenantIdorTest`](../../../tests/Feature/Purchase/UpdateCrossTenantIdorTest.php) (C1 discordo).
- `t14-incident-empty-value-list` — lista de valores do distinct inclui membro vazio silencioso que derruba o consumidor. Âncora: [`SafeSelectItem.tsx`](../../../resources/js/Components/ui/SafeSelectItem.tsx) + proibicoes §5 2026-06-29 (C3 discordo).

**Braço critérios-extra (t15–t20)** — pares bom/ruim pra os critérios que faltavam, cada bad com a armadilha "parece-ruim-mas-é-ok":
- `t15/t16` **C4 atomicidade** — 2 escritas fora de transaction (discordo) × 2 escritas que **declaram** caller-wraps (concordo — rubrica C4 "OU declara que o caller envolve").
- `t17/t18` **C5 N+1** — query dentro do `foreach` (discordo) × `foreach` sobre relação **eager-loaded** (concordo — parece N+1, não é).
- `t19/t20` **C7 tipos** — retorno polimórfico `false|string|array` com docblock mentindo (discordo) × `?int` tipado+documentado (concordo — quem carimba todo nullable erra).

## Estender

Mais twins = mais 1 par bom/ruim em `twins/` + a entrada no `manifesto-SELADO.json`. Braço-incidente já implementado (t12–t14); mais incidentes reais têm índice em `memory/LICOES_CODE.md`.
