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

# 2) emitir o PACK CEGO (o que o juiz vê — comentários // REMOVIDOS pelo stripTells).
#    --blind (rodada 6): rótulos OPACOS L01.. em vez do id (o id `t15-atomicidade-bad` nomeava o veredito!)
node scripts/governance/funcao-scorecard-calibracao.mjs --pack --blind

# 3) rodar N juízes FRESCOS (sessões isoladas), cada um julga o pack e grava vereditos.json;
#    NUNCA dê o manifesto ao juiz.

# 4) pontuar cada juiz vs o selado (--score auto-traduz L→id real)
node scripts/governance/funcao-scorecard-calibracao.mjs --score <vereditos.json>

# 5) κ INTER-FAMÍLIA (rodada 6, gap #2): juiz-A vs juiz-B de famílias de modelo DIFERENTES
node scripts/governance/funcao-scorecard-calibracao.mjs --kappa-inter <a.json> <b.json>

# 6) SET-FRONTEIRA (rodada 6, gap #3): twins deliberadamente difíceis que procuram o erro do juiz
node scripts/governance/funcao-scorecard-calibracao.mjs --pack --blind --set frontier
node scripts/governance/funcao-scorecard-calibracao.mjs --score <vereditos.json> --set frontier
```

**Passa se:** ≥80% das famílias de defeito achadas com o critério certo · **κ (chance-corrected) ≥ 0,6** vs o rótulo objetivo · **zero discordo** no controle limpo (t07) · **incerto** no sem-âncora (t08) · nenhum falso-discordo nos bons. Repetibilidade (T1): ≥90% por-critério em 3 rodadas.

**25 twins em 4 braços:**

**Braço sintético-mutação (t01–t11)** — código fabricado, rótulo = a mutação determinística.
- **Fáceis (t01–t08)** cobrem os defeitos óbvios (C1/C2/C3/C6 + controle + incerto).
- **DIFÍCEIS (t09–t11)** são armadilhas onde um juiz preguiçoso erra (100% em caso óbvio prova pouco):
  - `t09-partial-scope-nao-tenant` — escopa por `location_id`, parece escopado mas **não é** business_id (C1 discordo).
  - `t10-golden-vetor-errado` — cita um golden que cobre **outra** operação; "existe golden" não basta, tem que cobrir **o vetor** (C2 discordo, refinamento do 4617).
  - `t11-nullable-tipado-ok` — retorno `?Coupon` tipado é contrato explícito (**C7c concordo** — rubric v1.1; era C3, migrou pro dono preciso da nullabilidade). Quem carimba todo `?T` como violação erra aqui.

**Braço-incidente (t12–t14)** — MODELAM defeitos REAIS já catalogados; o rótulo é ancorado no **teste de regressão real** (não na mutação, não em opinião), mas o CÓDIGO segue **sintético** (não colado do repo → não-circular por construção):
- `t12-incident-numuf-inflacao` — desconto % gera float de 5 casas que o parser pt-BR lê como milhar → infla ~×100k. Âncora: [`IncidentValorInfladoNumUfTest`](../../../tests/Unit/Utils/IncidentValorInfladoNumUfTest.php) (C2 discordo).
- `t13-incident-idor-cross-tenant` — `findOrFail(id-do-request)` em Model sem global scope + `update()` sem `business_id` → escrita cross-tenant em dinheiro. Âncora: [`UpdateCrossTenantIdorTest`](../../../tests/Feature/Purchase/UpdateCrossTenantIdorTest.php) (C1 discordo).
- `t14-incident-empty-value-list` — lista de valores do distinct inclui membro vazio silencioso que derruba o consumidor. Âncora: [`SafeSelectItem.tsx`](../../../resources/js/Components/ui/SafeSelectItem.tsx) + proibicoes §5 2026-06-29 (C3 discordo).

**Braço critérios-extra (t15–t20)** — pares bom/ruim, cada bad com a armadilha "parece-ruim-mas-é-ok":
- `t15/t16` **C4 atomicidade** — 2 escritas fora de transaction (discordo) × 2 escritas que **declaram** caller-wraps (concordo — rubrica C4 "OU declara que o caller envolve").
- `t17/t18` **C5 N+1** — query dentro do `foreach` (discordo) × `foreach` sobre relação **eager-loaded** (concordo — parece N+1, não é).
- `t19/t20` **C7 (re-rotulados na v1.1)** — `t19-mixed-return-bad` retorno polimórfico `false|string|array` NÃO-declarado → **C7b discordo** (era "C7"); `t20-nullable-int-documentado-ok` `?int` tipado+documentado → **C7c concordo** (era "C7").

**Braço desdobramento-C7 v1.1 (t21–t25)** — calibram o C7 desdobrado em C7a/C7b/C7c/C7d ([FUNCAO-SCORECARD-METODO §1](../../../memory/requisitos/_Governanca/FUNCAO-SCORECARD-METODO.md)). O C7 monolítico flipou (`getProductDiscount`: 2×1) por misturar 3 perguntas; cada twin isola UM sub-critério:
- `t21-docblock-mente` — `@return string` sob assinatura/retorno `int`, **sem null, sem DB, sem polimorfismo** (C7a puro discordo). Isola o C7a que o t19 mistura com C7b.
- `t22-null-silencioso` — `: Gadget` (não-nullable) mas `first()` devolve null → null silencioso (C7c discordo). Contraste direto do t11/t20 (`?T` tipado = concordo).
- `t23-erro-engolido` — `catch (\Throwable)` **vazio**, sem Log nem rethrow (C7d discordo — supressão mecânica).
- `t24-c3-colecao-tipada` — ausência → `Collection` vazia tipada (C3 concordo). Exemplar positivo de C3 NÃO-nullable (após t11 migrar pra C7c); C7c `n/a` (partição).
- `t25-union-declarado-ok` — retorno `int|string` **DECLARADO** na assinatura (C7b concordo). Armadilha simétrica ao t11/t20: quem carimba todo multi-tipo como C7b erra — só polimorfismo NÃO-declarado é discordo.

> **Blind pack v1.2 — correção adversarial 2026-07-21.** O pack agora usa IDs opacos (`T001…T025`) em vez dos nomes dos arquivos (`*-bad`, `*-ok`, `docblock-mente`, `incident-*`) e `stripTells()` remove prosa de `//`, `/* */` **e** `/** */`; de docblock só sobrevivem tags estruturadas mínimas (`@return T`, `@param T`, `@transactional Caller`). O teste falha se qualquer nome/veredito conhecido reaparecer. **Consequência honesta:** as rodadas 2–5 ficam como fósseis parcialmente contaminados (2–4 por comentários; 5 ainda por ID + docblocks narrativos). O runner continua aceitando os IDs antigos para auditar os fósseis, mas uma nova afirmação de κ exige três juízes frescos sobre o pack v1.2.

> **⚠️ Rodada 6 (2026-07-21) — os 2 leaks residuais + cross-família + fronteira.** A rodada 6 achou **dois** leaks de circularidade que TODAS as rodadas 2-5 deixaram passar (é a própria dimensão que se mede):
> 1. **O ID do twin no cabeçalho do pack NOMEAVA o veredito** (`## t15-atomicidade-bad`, `## t02-unscoped-find`, `## t16-...-ok`) — o juiz sabia a resposta antes de ler o código. Fix: `--blind` emite rótulos OPACOS `L01..` em ordem de **hash sha256(id)** (some o tell do id + a adjacência dos pares bom/ruim); o runner recomputa a ordem determinística pra pontuar (`translateBlind`).
> 2. **Docblock `/** */` narrando o defeito** (t12/t13/t14/t16/t20) — a "residual honesta" da rodada 5, agora fechada: de-narrados, mantido só o contrato genuíno (`@return` de tipo, `@covered-by`, `@transactional`, schema nullable).
>
> **κ honesto pós-leaks:** 0,83 sobre os 25 (a queda foi INTEIRAMENTE t08/t14) → **1,0 sobre os 23 válidos**, em **4 famílias de modelo cegas** (Opus 4.8 · Sonnet 5 · Fable 5 · Haiku 4.5). **κ INTER-FAMÍLIA = 1,0 nos 6 pares (22/22)** no mecânico — refuta "concordou porque é o mesmo modelo". **Fronteira** (`frontier/`, 10 twins difíceis): achou 1 modo de erro real (fr05 golden-lull, Opus+Haiku miss), 0/20 falso-positivo, e o achado estrutural — `incerto`-de-INTENÇÃO (t08/fr10) não é encodável não-circularmente (→ braço gold humano #4626); `incerto`-ESTRUTURAL (fr08 eager-load desconhecido) é (4/4). Detalhe: FUNCAO-SCORECARD-METODO §5 rodada 6 + `memory/sessions/2026-07-21-funcao-scorecard-rodada6-crossfamilia-fronteira.md`. Vereditos: `calibracao-2026-07-21/judge-r6-*-{main,frontier}.json`.

## Estender

Mais twins = mais 1 par bom/ruim em `twins/` + a entrada no `manifesto-SELADO.json`. Braços já implementados: sintético-mutação (t01–t11), incidente (t12–t14), critérios-extra C4/C5/C7 (t15–t20), desdobramento-C7 v1.1 (t21–t25), **fronteira (`frontier/` fr01–fr10, rodada 6)**. Mais incidentes reais têm índice em `memory/LICOES_CODE.md`.

**Aposentados** (`retired:true` no manifesto — na ORDEM cega mas fora das métricas): **t08** (incerto-de-intenção → braço humano) e **t14** (rótulo C3 errado, é C7a). Rodar sempre `--blind`; o `--pack` sem `--blind` fica só como fóssil comparativo das rodadas 2-5.
