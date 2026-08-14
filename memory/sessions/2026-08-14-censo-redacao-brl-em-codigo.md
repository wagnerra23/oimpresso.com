---
date: "2026-08-14"
hour: "14:00 BRT"
duration: "1h"
topic: "Censo do dano colateral do git filter-repo de 2026-06-08 fora de markdown — 472 ocorrências em 167 arquivos, classificadas por balde"
authors: [C]
outcomes:
  - "Medido: 167 arquivos não-markdown carregam o sentinela de redação (472 ocorrências)"
  - "14 ASSERTs abertos em 9 arquivos (fora os 2 já tratados no PR #5792) — 12 deles em spec SEM lane de CI"
  - "Achado fora de Tests/: 9 de 51 linhas do gabarito da Jana tiveram a chave-oráculo redigida"
  - "Achado em produção: 4 strings visíveis ao usuário renderizam o sentinela (3 fallback de NaN + 1 placeholder na tela de venda)"
prs: []
us:  []
related_adrs: []
---

# Censo — redação BRL que caiu em código (dano colateral do `filter-repo` de 2026-06-08)

## TL;DR

Em 2026-06-08 o repo passou por `git filter-repo --replace-text` sobre ~5.033 commits pra apagar
valores BRL do histórico. **A varredura não tinha filtro de path** — e a regra que a motivou nunca
proibiu valor em código. O dono mecânico da regra hoje
([`block-brl-values-in-memory.mjs`](../../.claude/hooks/block-brl-values-in-memory.mjs)) exclui
`.php`/`.jsx`/`.ts`/`.html` **de propósito**, com a razão escrita no próprio arquivo: *não são
conhecimento, e um valor ali é dado de fixture, não vazamento de negócio*.

O sentinela está em **690 arquivos**, dos quais **167 fora de markdown** (472 ocorrências). Deles:

| O que | Quantos |
|---|---|
| **ASSERT** (sentinela em posição que o teste compara) | **16** ocorrências / 10 arquivos — 2 já tratados no [#5792](https://github.com/wagnerra23/oimpresso.com/pull/5792) ⇒ **14 abertos em 9 arquivos** |
| **ASSERT fora de `Tests/`** — chave-oráculo do gabarito da Jana | **9** de 51 linhas (achado que a varredura por diretório de teste NÃO pega) |
| **Produção — string visível ao usuário** | **4** confirmadas (3 fallback de `NaN` + 1 placeholder na tela de venda) |
| DADO NÃO-ASSERTADO (em teste) | 21 |
| PROSA (em teste) | 55 |
| INDETERMINADO (declarado, não chutado) | 2 |

**A boa notícia, e é a que mais importa:** os anti-regressão do incidente de valor inflado da ROTA
LIVRE — [`tests/Unit/Utils/IncidentValorInfladoNumUfTest.php`](../../tests/Unit/Utils/IncidentValorInfladoNumUfTest.php),
[`tests/Unit/Utils/NumUfHeuristicPtBRTest.php`](../../tests/Unit/Utils/NumUfHeuristicPtBRTest.php),
[`tests/Feature/Sells/SellsCreatePageTest.php`](../../tests/Feature/Sells/SellsCreatePageTest.php)
e [`app/Utils/Util.php`](../../app/Utils/Util.php) — sofreram **só em docblock e comentário**.
Nenhum valor comparado por eles foi tocado. O caso do `numberPtBR.test.ts` (PR #5792) permanece o
único ASSERT de cálculo de valor **quebrado** que este censo encontrou.

> ⚠️ **Este documento é censo. Nada foi consertado.** Zero edição de fixture; `tests/numberPtBR.test.ts`
> não foi tocado. Citações são `arquivo:linha` — **nenhum valor é reproduzido** (§"NUNCA commitar
> valores BRL" de [`proibicoes.md`](../proibicoes.md)).

---

## 1. Método — os comandos exatos

Tudo rodado no worktree, em `origin/main` @ `4e28f15c0`, repo **não-raso**
(`git rev-parse --is-shallow-repository` → `false`, conforme §5 2026-07-24).

### 1.1 Controle positivo antes de qualquer contagem

O enunciado avisou que `grep -P` falha com `rc=2` em algumas locales deste ambiente. Toda sonda
deste censo abre com um padrão que **eu sei que casa**, e aborta se ele voltar vazio — a lição de
§5 2026-08-01 (*"o instrumento que falha nem sempre devolve VAZIO; às vezes devolve um número
plausível"*):

```bash
git grep -c -F "[redacted Tier 0]" -- memory/proibicoes.md   # → memory/proibicoes.md:2 · rc=0
```

Os scripts `.mjs` do censo têm o mesmo controle embutido, com `process.exit(3)` se ele falhar.

### 1.2 Universo

`git grep` (não `rg`) porque o enunciado pede e porque `rg` pula dotfile por padrão — e
`.claude/hooks/` e `.github/workflows/` entram na conta (§5 2026-07-30). `-F` porque o sentinela
tem `[` e `]`; **não** tem `\E`, então a armadilha de §5 2026-07-31 não se aplica aqui.

```bash
git grep -l -F "[redacted Tier 0]" | wc -l                    # 690
git grep -l -F "[redacted Tier 0]" | grep -v '\.md$' | wc -l  # 167
```

### 1.3 Coleta e classificação

Coleta linha-a-linha + heurística de balde (script de censo em scratchpad). **A heurística é
sugestão, não veredito** — todo caso ambíguo foi aberto e lido (§8 lista os que ficaram
`INDETERMINADO` por eu não conseguir decidir sem executar).

### 1.4 Resolução de lane de CI

Cruzamento da árvore de specs contra o texto de **todos** os workflows + os `scripts.test:*` do
`package.json` que algum workflow invoca. Controle positivo:
`Modules/KB/Tests/Feature/KbRagServiceMultiTenantTest.php` **tem** que aparecer nomeado no
`kb-pest.yml` — apareceu.

---

## 2. O N de N

**472 ocorrências em 167 arquivos não-markdown** (de 690 arquivos no total; os outros 523 são `.md`
e estão fora do escopo desta tarefa).

| ext | arquivos | | ext | arquivos |
|---|---|---|---|---|
| `.php` | 110 | | `.mjs` | 3 |
| `.tsx` | 20 | | `.js` | 3 |
| `.html` | 7 | | `.py` | 2 |
| `.jsx` | 7 | | `.sql` | 2 |
| `.json` | 4 | | `.txt` · `.pdf` · `.yaml` · `.css` · `.worktree-preview` | 1 cada |
| `.ts` | 4 | | | |

Distribuição das 472 ocorrências: **94** em 41 arquivos de teste · **378** em 126 arquivos que não
são teste.

> Nota de alcance: o `filter-repo` chegou a **vendored de terceiro** —
> `resources/plugins/AdminLTE/plugins/DataTables/datatables.js:60909`,
> `.../pdfmake-0.1.32/pdfmake.js:51733` — e a um **binário**,
> `lib-custom/laravel-boleto/manuais/SICREDI/manual-cnab-400.pdf:5543`. Não classifiquei esses
> três (não é código nosso), mas ficam registrados: uma substituição de texto dentro de um PDF é
> alteração de arquivo binário.

---

## 3. Tabela por balde — as 94 ocorrências em arquivos de teste

| Balde | Occ | Definição usada |
|---|---:|---|
| **ASSERT** | **16** | o sentinela está em posição que o teste compara — dentro de `expect(...)`/`assert*`, ou numa fixture que alimenta diretamente o `expect` |
| **DADO NÃO-ASSERTADO** | 21 | seed/input/mock que o teste grava mas nunca compara (o oráculo é outra coisa: contagem, id, chave) |
| **PROSA** | 55 | docblock, comentário, mensagem de assert, nome de `it`/`describe` |
| **INDETERMINADO** | 2 | não consegui decidir sem executar — declarado, não chutado |

**PROSA é o balde grande, e por um motivo com forma:** o `filter-repo` acertou sobretudo onde o
autor *narrava* o valor (comentário explicando o cálculo, docblock do incidente, nome do cenário).
Nos casos de cálculo, o **número comparado sobreviveu** e a **narração morreu** — o teste continua
mordendo, o comentário ao lado é que virou ruído. Exemplos verificados abrindo o corpo:

- [`Modules/ComunicacaoVisual/Tests/Feature/OrcamentoCalculatorTest.php`](../../Modules/ComunicacaoVisual/Tests/Feature/OrcamentoCalculatorTest.php) — 8 ocorrências, **todas** em comentário ou nome de `it`. Os `expect(...)->toBe(...)` de área, subtotal e total estão intactos.
- Os 7 testes CNAB do `Modules/PaymentGateway/Tests/Feature/` — o sentinela está no comentário de fim de linha; o inteiro em centavos passado ao driver sobreviveu.
- [`Modules/Financeiro/Tests/Feature/TituloRepositoryWave18Test.php:116`](../../Modules/Financeiro/Tests/Feature/TituloRepositoryWave18Test.php) e [`Modules/Fiscal/Tests/Feature/SpedMotorTributarioIntegrationTest.php:167`](../../Modules/Fiscal/Tests/Feature/SpedMotorTributarioIntegrationTest.php) — sentinela na **mensagem** do assert; o valor comparado (1º argumento) sobreviveu nos dois.

---

## 4. Os ASSERTs, com lane

Sub-classifiquei por **simetria**, porque é o que separa "quebrou" de "ficou feio":

- **simétrico** — o sentinela caiu **nos dois lados** (input e esperado). O assert continua provando
  o que provava (round-trip, propagação de string); só o literal ficou estranho.
- **assimétrico** — caiu **num lado só**. É o formato do caso do `numberPtBR`: a entrada perdeu o
  número e o esperado continuou numérico ⇒ o parser recebeu string sem dígito e o assert quebrou.

| # | Arquivo:linha | O que o teste protege | Simetria | Lane no CI |
|---|---|---|---|---|
| 1 | `tests/numberPtBR.test.ts:49,60` | parser pt-BR da tela de venda — anti-regressão do bug de inflação da Larissa | **assimétrico (QUEBRADO)** | ❌ **SEM LANE** — *já tratado no #5792, não tocar* |
| 2 | `Modules/Jana/Tests/Feature/Ai/Clarify/ClarifyCascadeServiceTest.php:127,157` | heurística "mensagem acionável não vira clarificação" (curto-circuita o LLM) | assimétrico, mas o oráculo é heurístico | ❌ **SEM LANE** |
| 3 | `Modules/Jana/Tests/Feature/BriefDiarioChatTriggerTest.php:102` | falso-positivo do gatilho de brief diário | assimétrico, oráculo heurístico | ❌ **SEM LANE** |
| 4 | `Modules/Jana/Tests/Feature/Ai/fixtures/jana-gold-set.json:63` | `ground_truth` do gold set RAGAS (meta financeira) | ver §4.1 | ❌ **SEM LANE** |
| 5 | `tests/Feature/Modules/Copiloto/MemoriaContratoTest.php:62,69,96` | round-trip do contrato de memória da Jana | simétrico | ❌ **SEM LANE** |
| 6 | `tests/Feature/Modules/Copiloto/MemoriaControllerTest.php:42,68` | listagem/atualização de fato via controller | simétrico | ❌ **SEM LANE** |
| 7 | `tests/Feature/Modules/Copiloto/BridgeMemoriaChatTest.php:23` | fato de memória chega às `instructions` do agente | simétrico | ❌ **SEM LANE** |
| 8 | `tests/Feature/Modules/Copiloto/ChatCopilotoAgentContextoNegocioTest.php:139` | contexto de negócio chega ao prompt | simétrico | ❌ **SEM LANE** |
| 9 | `tests/Feature/Modules/Copiloto/SemanticCacheServiceTest.php:82` | round-trip do cache semântico | simétrico | ❌ **SEM LANE** |
| 10 | `.claude/hooks/block-askq-execution-menu.test.mjs:78,79` | hook deixa passar pergunta de produto/preço (decisão que só o [W] toma) | fixture degradada | ✅ **`governance-script-tests.yml`** |

**Placar: 14 ASSERTs abertos em 9 arquivos. 12 deles (86%) em spec SEM lane de CI.**

O único com lane (#10) eu **rodei** — é selftest node hermético, não Pest, então não viola a regra
do CT 100:

```
node .claude/hooks/block-askq-execution-menu.test.mjs   →   12 ok, 0 fail   (EXIT=0)
```

Passa, inclusive o caso `produto preço`. O dano ali é de **fixture degradada**, não de assert
quebrado: as duas opções eram dois preços distintos e hoje são **a mesma string duas vezes** — o
oráculo do hook é o texto da pergunta, que sobreviveu.

### 4.1 O `jana-gold-set.json` — provável falso alarme, mas declarado

A linha 63 é um `ground_truth` sobre a meta financeira (ADR 0022), consumido por
`JanaRagasCiCommand`, `JanaRagasRealEvalCommand`, `JanaDriftSentinelCommand` e três testes. **Mas o
corpus que a Jana recupera também está redigido** — `memory/why-oimpresso.md` diz a meta com o
mesmo sentinela. Ou seja: o esperado e a fonte concordam, e a comparação (semântica, não exata)
provavelmente não degrada. Classifico como **ASSERT-position com dano provavelmente nulo** — e
declaro que **não rodei** o RAGAS pra provar (exige CT 100 + custo de LLM).

### 4.2 Por que "SEM LANE" é o veredito honesto, e não preguiça de procurar

Não é inferência: **o próprio repo já mediu isso e escreveu no workflow.**
[`forja-shortcuts-gate.yml:7`](../../.github/workflows/forja-shortcuts-gate.yml) — *"medido em
2026-08-03 — NENHUMA lane roda `vitest run`"*; e
[`jana-conversas-gate.yml:8-10`](../../.github/workflows/jana-conversas-gate.yml) — *"os 10
workflows que citam vitest listam o spec um a um"*.

Cruzei mecanicamente mesmo assim: **das 31 specs vitest da árvore, 20 têm lane e 11 não.**
`tests/numberPtBR.test.ts` e `tests/layout-primitives.test.tsx` estão entre as 11.

Do lado PHP a arquitetura é a mesma — **allowlist explícita por arquivo**, com uma exceção:

| Lane | Forma | Consequência |
|---|---|---|
| `jana-pest.yml` | lista nomeada (catraca "ratchet up") | `ClarifyCascadeServiceTest` e `BriefDiarioChatTriggerTest` **não** estão nela |
| `sells-pest.yml` | 5 arquivos nomeados | `SellsCreatePageTest.php` **não** está |
| `verticais-pest.yml` | lista nomeada | `OrcamentoCalculatorTest.php` **não** está |
| `nfebrasil-pest.yml` | lista nomeada | `SpedMotorTributarioIntegrationTest.php` **não** está |
| **`financeiro-pest.yml`** | **`find` no diretório menos quarentena** | `TituloRepositoryWave18Test.php` **roda** (conferido: não está em `.github/financeiro-pest-quarantine.list`) |
| `kb-pest.yml` | lista nomeada | `KbRagServiceMultiTenantTest.php` **está** |

`tests/Feature/Modules/Copiloto/**` não aparece em lane nenhuma — os 6 arquivos daquele diretório
são invisíveis ao CI. Como todos os 9 ASSERTs de lá são **simétricos**, isso hoje não esconde
defeito; esconde a *capacidade de detectar* um.

---

## 5. O achado que a varredura por `Tests/` não pega — o gabarito da Jana

[`Modules/Jana/Database/Seeders/MemoriaGabaritoSeeder.php`](../../Modules/Jana/Database/Seeders/MemoriaGabaritoSeeder.php)
é um **seeder**, não um teste — então nenhuma heurística de "está sob `Tests/`" o encontra. Mas o
campo `memoria_esperada_keys` é exatamente um **oráculo**: a lista de chaves/snippets que a resposta
da Jana precisa conter pra a pergunta contar como acertada (tabela `copiloto_memoria_gabarito`,
migration `2026_04_29_200001`).

**9 das 51 linhas de `memoria_esperada_keys` tiveram uma chave redigida** (linhas 121, 127, 169,
185, 281, 288, 294, 316, 321). Essas 9 linhas do gabarito hoje exigem que a Jana produza a string
literal do sentinela — coisa que ela nunca vai produzir. **São 9 perguntas do gabarito que passaram
a errar por construção.**

O contraste dentro do mesmo arquivo prova que o dano é do `filter-repo` e não do autor: a linha 80
tem a mesma intenção escrita **por extenso** e sobreviveu intacta, porque não casava o padrão
`R$<número>`.

Esse é o caso mais próximo, em natureza, do `numberPtBR`: **oráculo neutralizado, silenciosamente.**

---

## 6. Fora de teste — o que o `filter-repo` alcançou em produção

Das 378 ocorrências fora de arquivos de teste, **195 estão em código de produção** (excluí
`prototipo-ui/`, bundles/mockups de design, vendored, `scripts/`, governança). Dessas: **104 em
comentário** e **91 em posição de código**.

As 91 não são asserts — produção não asserta. O risco ali é outro: **string que o usuário vê**.
Confirmei abrindo:

| Achado | Arquivo:linha | Gravidade |
|---|---|---|
| Fallback de `NaN` em `formatBRL()` renderiza o sentinela pro usuário | `resources/js/Pages/TransactionPayment/Index.tsx:72` · `Edit.tsx:55` · `Show.tsx:60` | **UI viva** — quando o valor não parseia, a tela mostra o sentinela em vez do zero formatado |
| `placeholder` do input de despesa adicional | `resources/js/Pages/Sells/Create.tsx:1513` | **UI viva na tela de venda** (Tier 0) — cosmético, mas é *a* tela |
| Texto de tarifa por banco (15 ocorrências) | `resources/js/Pages/Financeiro/Cobranca/_lib/cobranca-shared.ts` | copy de apoio à decisão de gateway — perdeu o número |
| `recomendado_para` dos templates fiscais (7 ocorrências) | `Modules/NfeBrasil/Resources/templates/*.php` | orienta escolha de regime (limites MEI/Simples) — perdeu o limiar |
| Few-shot do prompt do distiller | `Modules/Jana/Services/Memoria/ProfileDistiller.php:195-197` | exemplo que ensina a Jana a formatar — degradado |
| Label de saldo a receber | `Modules/Crm/Http/Controllers/ClienteIaController.php:289` · `resources/js/Pages/Cliente/_show/RiscoClienteCard.tsx:72` | copy de UI |

**Não** é bug vivo, conferido um a um: `app/Utils/Util.php` (35, 36, 49, 86),
`resources/js/Lib/numberPtBR.ts` (7, 33), `resources/js/Components/ui/numeric-input-ptbr.tsx`
(7, 10) e `Modules/Jana/Config/config.php:244` — **todos comentário/docblock**; no `config.php` o
inteiro do `valor_alvo` está intacto ao lado do comentário redigido. O caminho de cálculo de valor
não foi tocado em código.

---

## 7. Priorização

Ordem por *"um anti-regressão de valor neutralizado é o pior caso"* (§REGRA MESTRE de
[`proibicoes.md`](../proibicoes.md)):

| P | Item | Por quê |
|---|---|---|
| **P0** | `tests/numberPtBR.test.ts` | ✅ **já tratado** no #5792. É o único ASSERT de cálculo de valor comprovadamente quebrado. |
| **P1** | `MemoriaGabaritoSeeder.php` — 9 chaves-oráculo | oráculo neutralizado em silêncio, mesma família do P0. Não toca dinheiro, mas mede a Jana; e o gabarito **não** tem lane. |
| **P2** | Os 3 `formatBRL()` de `TransactionPayment` | única regressão que chega ao **olho do usuário** hoje. Conserto trivial; o risco de deixar é o cliente ver o sentinela numa tela de pagamento. |
| **P3** | `Sells/Create.tsx:1513` (placeholder) | cosmético, mas é a tela Tier 0 — cai junto com o P2 num PR de 4 linhas. |
| **P4** | Os 11 ASSERTs simétricos (Copiloto + Jana + hook) | **não estão quebrados.** Consertar é higiene de legibilidade, não correção. Ver a ressalva abaixo. |
| **P5** | Copy de produção (tarifas, templates fiscais, prompt do distiller) | perda de informação útil ao usuário; volume alto (~30 linhas), valor por linha baixo. Só com [W] decidindo quais números podem voltar. |
| — | PROSA em teste (55) e comentário em produção (104) | **não mexer em massa.** Ver §9. |

**Nenhum dos ASSERTs abertos toca cálculo de valor ou estoque.** Os 14 se dividem em: 11 de
memória/cache/prompt da Jana (simétricos), 2 de heurística de clarificação, 1 de fixture de hook.
O eixo valor/estoque foi atingido **só** no `numberPtBR` (P0, já tratado) e em prosa.

---

## 8. INDETERMINADO — declarado, não chutado

| Arquivo:linha | Por que não decidi |
|---|---|
| `Modules/Jana/Tests/Feature/Ai/Advisor/ProximaPerguntaServiceTest.php:94` | payload de `fakeAgent`. Li a linha, não o consumidor — não sei se algum `expect` compara `resposta_curta`. |
| `Modules/Jana/Tests/Feature/Ai/Clarify/ClarificadorAgentTest.php:74` | `content` de mensagem numa conversa de fixture. Mesma razão. |

Além desses dois, três incertezas **de segunda ordem** que registro por honestidade:

1. **A simetria dos 11 não foi provada por execução.** Foi provada por leitura do par
   entrada↔esperado no mesmo arquivo. Os testes rodam no CT 100, não aqui — não os rodei.
2. **O `jana-gold-set.json` (§4.1)** — argumento de "dano nulo" é plausível e não medido.
3. **As 378 ocorrências fora de teste não foram classificadas uma a uma.** Foram agrupadas por
   família de path e amostradas; os casos de produção do §6 foram abertos e confirmados, o resto
   (design, mockup, vendored, bundle) não.

---

## 9. O que este censo NÃO fez, e uma armadilha pra quem for consertar

Nada foi consertado. `tests/numberPtBR.test.ts` não foi tocado. Nenhum valor BRL foi reintroduzido
em `.md`, corpo de PR ou mensagem de commit.

**A armadilha, e ela é a lápide §5 2026-07-12 em cima:** o reflexo natural aqui é um codemod que
varre os 167 arquivos e "restaura" tudo. Isso é backfill em massa de legado — e nestes paths ele
acorda gate diff-aware (`casos-gate` G-6 mede data-git; `screen-coverage`; `pt-conformance`). Pior:
**os valores originais não estão em lugar nenhum.** O `filter-repo` reescreveu a história; não há
`git log` de onde recuperá-los. Cada restauração é um **valor novo, inventado hoje** — e inventar
número que parece histórico é a forma mais duradoura de mentira (§5 2026-08-13).

Daí a forma do consertável, e é o motivo da priorização do §7 ser tão curta:

- **P1/P2/P3 são consertáveis sem inventar nada** — a chave do gabarito pode voltar por extenso
  (a linha 80 do mesmo arquivo já é assim e sobreviveu); o fallback de `NaN` e o placeholder são
  zero formatado, que não é valor de negócio.
- **P4/P5 exigem o número, e o número não existe mais.** São decisão [W], caso a caso — e
  provavelmente vários deles a resposta certa é *deixar redigido e reescrever a frase pra não
  depender do número*.

Um `--measure` antes de qualquer máquina, se alguém propuser gate pra isso: o §5 tem quatro
lápides de guard sintático que reprovava o legítimo, e "sentinela em arquivo de código" tem
**472** ocorrências das quais **~130 são PROSA inofensiva** — FP alto por construção.
