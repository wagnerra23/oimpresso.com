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

**Passa se:** ≥2/3 famílias de defeito achadas com o critério certo (t02 C1 · t04 C2 · t05 C3 · t06 C6) · **zero discordo** no controle limpo (t07) · **incerto** no sem-âncora (t08) · nenhum falso-discordo nos bons (t01/t03). Repetibilidade (T1): ≥90% por-critério em 3 rodadas.

## Estender

Mais twins = mais 1 par bom/ruim em `twins/` + a entrada no `manifesto-SELADO.json`. Braço-incidente (defeitos REAIS já rotulados por teste de regressão): `IncidentValorInfladoNumUfTest`, `UpdateCrossTenantIdorTest`, `SafeSelectItem` — índice em `memory/LICOES_CODE.md`.
