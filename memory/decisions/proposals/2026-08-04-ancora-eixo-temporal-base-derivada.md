---
proposal_id: ancora-eixo-temporal-base-derivada
status: proposto
created: 2026-08-04
proposed_by: claude-code
decided_by: _pendente — merge [W] = ratificação_
parent_adr: 0273 (gramática da âncora) + 0303 (anchor-lint wired/testado)
related_adrs: [0256, 0264, 0271, 0273, 0275, 0302, 0303, 0314, 0336]
type: governanca-executavel
supersedes: []
---

# Âncora — o eixo temporal fecha por BASE DERIVADA, não por re-carimbo

> **Ato que a [US-GOV-058](../../requisitos/Governance/SPEC.md) esperava.** Ela está `blocked`
> aguardando *"a decisão sobre a RECEITA de carimbo"*. A medição abaixo mostra que **o problema
> não é a receita** — a receita já é lei desde o ADR 0273 §1 — e que o conserto barato é do
> **medidor**, não do dado. Nada aqui escreve em documento nenhum.

## 1. O que foi medido (reprodução, 2026-08-04, `origin/main` @ `69039c8cd45`)

```bash
node scripts/governance/anchor-lint.mjs --stale --json
```

| campo | valor |
|---|---|
| `anchor_stale_total` | **42** |
| `anchor_stale_fresco_total` | **99** |
| `anchor_stale_unknown_total` | **296** (67,7% de 437) |
| motivos do unknown | `sha_fora_da_ancestralidade` = **270** · `sem_sha_verificado` = **26** |

Clone completo (`git rev-parse --is-shallow-repository` → `false`, 5.7k commits) — sem isso
as datas e a ancestralidade não sustentariam conclusão (§5 2026-07-24).

**Concentração:** os 270 cegos carimbam **16 SHAs distintos**; 4 deles respondem por 239
(`dd3ed7c`=135 · `176f9bc`=67 · `3b425d8`=21 · `98cae0a`=16). Todos os 16 **existem** como
commit no clone — são commits de branch, não shas inventados.

**Causa confirmada:** `git log -50 --format=%p origin/main | awk '{print NF}'` → **50 de 50 com
1 parent**. O squash-merge descarta o commit da branch; o carimbo aponta para um estado que
nunca esteve na história do `main`.

## 2. Duas premissas do enunciado que a medição corrige

**(a) "Ligar o `--stamp` do `ancora-codigo-sync`" não alcança este corpus.** Medido:

```bash
node scripts/governance/ancora-codigo-sync.mjs --measure
# → 307 doc(s) do corpus · 70 ref(s) doc→código   [.casos.md/.charter.md]
# → ocorrências em SPEC.md: 0
```

São dois carimbos homônimos e disjuntos: aquele script cura `Arquivo.php:443 (verificado@sha)`
em casos/charter; a âncora vive em `**Implementado em:** … · verificado@<sha7> (data)` no SPEC.
O formato que o `--stamp` escreve **não casa** a `GRAMMAR_RE` do `anchor-lint` (verificado).
Ligá-lo moveria 0 das 437. Além disso ele só carimba ref **sem** sha — carimbar `HEAD` numa ref
que ninguém olhou fabrica proveniência, que é o oposto do que a âncora existe para provar.

**(b) A receita já está decidida — e é ignorada.** ADR 0273 §1, `status: aceito`, literal:

> `verificado@<sha7> (<data>)` = **proveniência**: sha curto do commit de **`origin/main`** em
> que a existência do path foi verificada + dia da verificação.

Conformidade real, por mês da verificação declarada (411 âncoras carimbadas):

| mês | total | conforme (sha ancestral) | cego |
|---|---|---|---|
| 2026-06 | 46 | **0** | 46 |
| 2026-07 | 365 | 141 | 224 |

**0% e 39%.** A lei existe em prosa, em 5 sites de receita, e **só um** (`skills/alinhar-tela`)
dá o comando (`git rev-parse --short=7 origin/main`). É o caso-escola do ADR 0256:
*escrito+lembrado apodrece*. Logo "emendar a ADR para definir a receita" resolveria um problema
que não existe — a definição já está escrita; o que falha é a execução.

## 3. A saída: base DERIVADA do git (não escreve nada)

Quando o sha declarado não é ancestral **ou não existe neste clone**, a base passa a ser o **commit
ancestral que INTRODUZIU aquele carimbo no `main`** — recuperado por pickaxe restrito ao SPEC:

```
git log --reverse --format=%H -S"verificado@<sha7>" -- <spec>   → 1º que for ancestral do HEAD
```

O squash come o commit da branch, mas **não come o commit em que a linha entrou** — ele é
ancestral por construção. E a base sai do **git**, não do documento: a data declarada continua
sem ser usada como medida (a metade auto-declarável que o `verificado_em` já reprovou, §5).

**Os dois motivos entram, e o segundo só apareceu na fonte certa.** Localmente o corpus dá
`sha_fora_da_ancestralidade=270 · sha_ausente=0`; o run real do `main` em 2026-08-03 dá
**`266 · 4`** — em CI a branch já foi deletada, então o objeto nem existe. Cobrir só o primeiro
motivo nasceria parcialmente cego no único lugar onde o job roda. O motivo original é
**preservado** em `anchor_stale_via_base_motivos`: medir pela base não pode apagar o rastro de
que aquele carimbo não era ancestral (`sha_ausente` também cobre typo, não só branch deletada).

### Resultado, medido no próprio `anchor-lint` instrumentado (denominador idêntico: 437)

| | oficial hoje | com base derivada |
|---|---|---|
| stale | 42 | **156** |
| fresco | 99 | **255** |
| não-medível | **296 (67,7%)** | **26 (5,9%)** |
| motivos | `fora_da_ancestralidade`=270 · `sem_sha`=26 | `sem_sha`=26 |

**270 de 270 recuperadas · 0 falhas.** Os 26 que sobram são âncoras legadas **sem carimbo
nenhum** — nada a derivar, e continuam "não sei".

### Controle de fidelidade (o teste que decide)

Nas **141** âncoras que já são medíveis hoje, computei os dois vereditos e comparei:

**139 de 141 concordam (98,6%).** As 2 divergências são o MESMO par (`3acabd2` → `13559be`),
e a base derivada está **mais certa**, não menos: o "movimento" que o método atual chama de
stale é o **próprio PR** ([#4207](https://github.com/wagnerra23/oimpresso.com/pull/4207)) que
fez a verificação e carimbou. Quem carimba o `origin/main` de ontem e mexe no código hoje
**nasce stale** — efeito colateral da receita conforme, que a base derivada cancela.

## 4. As outras saídas, e por que não

| saída | veredito |
|---|---|
| **A1 forward-only** (carimbar `origin/main`) | **Não é decisão nova** — é a lei do 0273 §1 já aceita. Vale como higiene (dispensa o pickaxe), mas sozinha não move **1** das 270 legadas, e o número seguiria dominado por ruído por meses. E nasce stale quando o PR toca os paths ancorados. |
| **A2 lote retroativo** (`--reverify`) | Já classificado pela própria US-GOV-058 como *gaming automatizado*: re-carimbar apaga as stale sem ninguém olhar o código. Mantido descartado. |
| **`--stamp` pós-merge** | Corpus disjunto (§2a) — move 0 das 437. E escreveria carimbo sem verificação. |
| **sha do merge no carimbo** | Impossível por construção: o commit de squash **não existe** quando a linha é escrita. Só funcionaria escrevendo no `main` depois — churn + o mesmo risco de re-carimbo cego. |
| **tag por PR** | Infra nova, referência mutável e global, para responder o que o commit-de-introdução já responde de graça. |
| **conteúdo-hash por path** | Mudaria a gramática canônica do 0273 — lida por **10 arquivos em 2 linguagens** (incl. `SpecAnchorClassifier.php`, que alimenta o `deveFecharPorAncora`) — e exigiria backfill das 411 (big-bang de legado, §5 2026-07-12). Responde "mudou?" mas perde "quais paths andaram". Custo alto, ganho ≤ base derivada. |

## 5. Limites honestos (o que esta proposta NÃO resolve)

- **Viés de janela.** A base derivada mede *"desde que a verificação entrou no `main`"*, não
  *"desde que alguém olhou"*. Movimento dentro da janela branch→merge fica invisível. Medido:
  p50 = **0 dia**, **98% ≤ 1 dia**, máx 42 dias, 0 negativos. O viés é real e pequeno.
- **Não detecta desonestidade.** Continua valendo o resíduo já escrito no C8: quem re-carimbar
  sem re-verificar zera o sinal. Isto mede divergência, não intenção.
- **Rename de SPEC.** O pickaxe é path-scoped; 1 rename real na janela
  (`ProjectMgmt/SPEC.md` → `Forja/SPEC.md`) e ainda assim 411/411 acharam base. Se não achar,
  **cai no `unknown` de hoje** — "não sei" nunca vira "fresco" (fail-safe preservado).
- **O número vai SUBIR.** 42 → 156 stale. Não é regressão: é dívida que estava escondida atrás
  de "não sei". Metade tem p50 de **34 dias** desde a verificação declarada.
- **Custo em CI:** 7,7s → 28,2s no job `anchor-stale` (report-only, já roda com `fetch-depth: 0`).

## 6. O que muda no código

Um ponto, dentro do dono do tema (`anchor-lint.mjs` — não nasce motor novo, §5 2026-07-09):
`tocadosDesde()` ganha o fallback pela base derivada quando o motivo for
`sha_fora_da_ancestralidade`. **Zero mudança de exit code** — o eixo segue report-only, fora
de `--check*` e de `anchor_coverage`, e a invariante fs-pura dos jobs required (ADR 0303)
continua intacta porque o fallback só existe sob `--stale`.

O `unknown` passa a reportar o motivo novo `via_base_derivada` na saída, para que ninguém leia
"medível" como "carimbado certo" — a dívida de conformidade do §2b continua visível.

**Bite-test** (a régua tem que morder e soltar), no `anchor-stale.test.mjs` que já roda contra
repo git real — **11/11 verde**: (a) sha de branch + código andou depois do merge → **stale**;
(b) sha de branch + path parado → **fresco**; (c) carimbo fora do histórico (PR ainda não
mergeado) → **unknown**, nunca fresco; (d) a stale registra `via: base_derivada`; (e) o motivo
original sobrevive em `via_base_motivos`; (f) as 2 invariantes antigas (sem `--stale` o eixo
fica desligado; `anchor_coverage` não muda) seguem passando.

Uma nota de granularidade que a implementação obrigou a declarar: a chave do pickaxe é
**(SPEC, sha)**, não (US, sha) — um SPEC inteiro costuma ser carimbado com o mesmo sha (411
âncoras em 25 shas). US carimbada depois com sha já usado no arquivo herda a base da 1ª
introdução, mais antiga → mede mais movimento → tende a `stale`. Erra reportando, nunca calando.

## 7. Aceite

- `anchor-lint --stale` reporta `sha_fora_da_ancestralidade` = **0** e não-medível ≤ 6%.
- O selftest e o `anchor-stale.test.mjs` cobrem os 3 casos do §6.
- Nenhum arquivo de `memory/` é reescrito por máquina neste PR.

## 8. Reversão

Remover o fallback devolve o comportamento atual byte a byte (o caminho oficial não é tocado).
Se o viés de janela do §5 se mostrar maior que o medido, o gatilho é: divergência > 5% no
controle de 141 pares → recuar e reabrir.

---

**Não vira gate.** O eixo é higiene de frescor, não Tier 0 (ADR 0314) — promoção a required
só por emenda + flip [W] com mordida provada (ADR 0336), e não é o que se pede aqui.
