---
date: "2026-09-03"
topic: "Fiscal Onda 9 (SPED): a régua de 4 checagens, e o golden que provou que o gerador nunca tinha rodado até o fim"
authors: [C]
related_adrs:
  - 0062-separacao-runtime-hostinger-ct100
  - 0093-multi-tenant-isolation-tier-0
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0358-doutrina-de-teste-tenant-98-supersede-0101
us: [US-FISCAL-016, US-FISCAL-017, US-FISCAL-020]
---

# Fiscal Onda 9 — SPED: régua de geração + golden file

## TL;DR

- **`UC-FSF1-03` e `UC-FSF1-05`** (descidos do Cowork vermelhos de propósito) fecham verdes.
  [PR #6708](https://github.com/wagnerra23/oimpresso.com/pull/6708), mergeado em `3f935647ed`.
- **O gerador EFD nunca tinha rodado até o fim.** Ao produzir o golden, ele estourou `TypeError`:
  o PHP coage `'9001'`/`'9900'`/`'9990'`/`'9999'` para `int` como chave de array, e isso chegava
  em `registro9900(string $reg)`. Corrigido com um cast — zero efeito no conteúdo.
- **O emitente sai sem CNPJ, sem IE e com UF fixa `SP`** — a tabela `business` não tem as colunas
  que o registro `0000` lê. **Não consertado**: é motor fiscal, decisão [W].
- **A prévia do TXT ficou PARADA**, pelo gatilho que o próprio pedido definiu.
- Um recibo canon **caducou** e foi corrigido: `nfe_emissoes` existe no CT 100 desde 2026-07-28.

## O que a onda entregou

**Régua (`UC-FSF1-03`).** Quatro checagens — `ano-minimo`, `nao-futura`, `fechada`, `trava` —
avaliadas em `SpedController::checagens` e enviadas prontas à tela como `{id, ok, rotulo, motivo}`.
A tela renderiza; não decide. Isso virou anti-hook no charter: regra duplicada no `.tsx` divergiria
da `validar()` do Service no primeiro ajuste, e aí a tela libera o que o servidor recusa sem
ninguém perceber.

**Guarda de entrada.** `competenciaFechada()` entrou em `validar()`, que `gerarInterno()` chama na
primeira linha — antes do `DB::table('business')`. Até aqui **só a tela bloqueava**; o Service
aceitava gerar EFD de mês em aberto. O registro `0000` declara `DT_INI`/`DT_FIN` do período
(CONFAZ Guia Prático v3.1.1, perfil A, `COD_VER 018`), então um mês não encerrado produziria
movimento parcial se apresentando como a apuração fechada daquele período.

**Gate estendido, não duplicado.** O `disabled` + `title="Sem notas autorizadas no período"` que já
existia virou um `motivoBloqueio()` único, somando as 4 checagens à contagem de notas.

**Golden (`UC-FSF1-05`).** Saída real do gerador, capturada em base64 e materializada por script —
nunca transcrita. Tenant fictício **98** (ADR 0358), competência 2026-01, 1794 bytes, 47 linhas,
sha256 `e4eeccd4…`. O teste confere pipe-delimit, `0000`/`9999`, os 5 pares de bloco e **cada
contador `9900` contra as linhas reais**.

## Os dois achados do motor

### 1. O gerador quebrava antes de terminar — e o motivo é uma regra de PHP

`gerarInterno()` copia `$this->contadores` **depois** de chamar `registro9001()`, então a chave
`'9001'` já está no array. E o PHP coage chave de array numérica **canônica** para `int`: `'9001'`,
`'9900'`, `'9990'` e `'9999'` viram `integer`; `'0000'` e `'C100'` **não** (zero à esquerda e
letra). Medido direto no PHP 8.4 do CT 100:

```
'0000' => string   'C100' => string
9001   => integer  9900 => integer  9990 => integer  9999 => integer
```

Resultado: `registro9900(string $reg)` recebia `int` e lançava `TypeError`. Corrigido com
`(string) $reg` — o valor emitido é idêntico ao que a intenção do código já era.

**Por que ninguém tinha visto:** os testes de bloco existentes são **source-grep** (asserem que uma
string existe no fonte), o caso que geraria o TXT **skipava** por falta de `nfe_emissoes`, e a
trava `sped_simples_only_lock` (fail-secure, default `true`) impede o download em produção. O
golden é o **primeiro run ponta-a-ponta que já existiu**.

### 2. O emitente do registro 0000 está vazio — e isso muda o CFOP de toda operação

Medido no CT 100: a tabela `business` **não tem** `state`, `city`, `zip_code`, `landmark`,
`tax_number`, `inscricao_estadual`, `mobile` nem `email` — no UltimatePOS todas moram em
`business_locations`. O Service lê `$business->state`, recebe `null` e cai no fallback `'SP'`
(daí também `COD_MUN = 350000`). Como o CFOP interno×interestadual é decidido pela UF do emitente,
**toda** operação é comparada contra SP: no golden, a nota SC→SC saiu com CFOP `6102` em vez de
`5102`.

**Não consertado** — é motor fiscal e decisão [W]. A trava já cobre o risco, e o charter já
anti-hooka *"NÃO declarar o gerador validado sem smoke no PVA-EFD"*. Registrado em
`sped-icms-ipi-golden.meta.md`.

## A parada declarada: prévia do TXT

> ⚠️ **SUPERADO no fim da sessão.** Uma sessão irmã apontou que o pedido do Cowork existe no projeto
> vivo; baixei por ID e a fonte responde: o charter traz o Non-Goal *"❌ Gerar o arquivo de verdade
> no F1 — a ação é encenada"*, e o protótipo renderiza `SPED_TXT`, linhas de amostra. **A prévia
> nunca exigiu rodar o gerador** — minha premissa estava errada. Além disso, o charter tem **5
> Goals** e esta onda entregou **1**. Detalhe completo, com os números, na §ERRATA do
> [handoff](../handoffs/2026-09-03-2040-fiscal-onda9-sped-regua-golden.md) — não repetido aqui de
> propósito (§5 2026-07-17: não restatear o que outro doc sabe melhor).

### O que se sabia no momento do merge


O pedido mandava **parar e perguntar** se a prévia exigisse gerar o arquivo inteiro em request
síncrono. Exige: o único método público do Service monta o arquivo completo em memória, não há modo
parcial, e reimplementar a contagem no Controller seria escrever motor fiscal no lugar errado.
Pior — uma prévia server-side **contornaria a trava fail-secure**.

`previaTxt` é `null` e a tela **declara a ausência** em texto, listando só o que o layout já fixa.
Nenhuma amostra fabricada. As saídas (job com artefato, ou modo parcial no gerador) ficaram como
Non-Goal com a pergunta aberta no charter.

## Um recibo canon que caducou

`Sped.casos.md` §recibo afirmava, desde 2026-07-27, que `nfe_emissoes` **não existe** no staging do
CT 100 e que por isso os casos que a tocam *"não executam em nenhuma lane disponível hoje"*.
**Medido em 2026-09-03: `Schema::hasTable('nfe_emissoes')` = SIM** — o provisionamento de
2026-07-28 fechou a lacuna. O recibo antigo foi **preservado como fato datado** e a errata escrita
ao lado. É a §5 2026-09-03 na prática: *lápide que declara um gap tem prazo de validade implícito*.

O que **não** caducou: a lane de CI segue SQLite in-memory, e nenhum caso desta tela depende de
banco — os 14 rodam sem tocar o DB.

## Provas

| Medição | Resultado |
|---|---|
| Arquivo novo, CT 100 | **14 passed (182 assertions)** |
| `Sped\|SimplesOnly` na árvore **original** | 2 failed, **32 passed** |
| `Sped\|SimplesOnly` **com o PR** | 2 failed, **46 passed** |

O contador subiu **+14**, batendo com os casos adicionados — a prova é o contador, não o nome no
log (§5 2026-08-02). As 2 falhas são **pré-existentes**, medidas na árvore original *antes* de
qualquer mudança: `SimplesOnlyGateTest` colide em `users_username_unique` porque o banco do CT 100
**persiste** entre runs, e `CuradorEngineTest` é de outro módulo.

**Bite-test:** sem o golden → 4 vermelhos (só do `UC-FSF1-05`); sem `competenciaFechada` → 4
vermelhos (só do `UC-FSF1-03`). O gate morde dos dois lados.

**CI:** 45/45 required verdes. Dois advisory vermelhos, ambos com autoria medida como alheia:

- **watchdog G6** — `jana-ragas-canary` falhou às 10:25Z, onze horas antes do PR existir, e um
  baseline parado há 64d. Causa raiz já diagnosticada em
  [2026-09-03-watchdog-g6-tres-achados-ragas.md](2026-09-03-watchdog-g6-tres-achados-ragas.md)
  (conta OpenAI sem crédito desde 08-31, US-COPI-145).
- **visual-regression** — a tela que falhou é **`Home`**, e o PR não toca nenhum arquivo de Home.
  A baseline dela é o **mesmo blob** (`f47fb1f4d`) na última run verde do main e na base do PR;
  o que mudou foi o render, por [#6698](https://github.com/wagnerra23/oimpresso.com/pull/6698) e
  [#6690](https://github.com/wagnerra23/oimpresso.com/pull/6690), sem regravar a baseline. O main
  está verde só porque o workflow não voltou a rodar lá depois desses merges. Regravar a baseline
  aqui seria adotar dívida de outro PR e carimbar um render que ninguém desta onda olhou.

## Smoke pós-merge (R1) — o que foi provado, e o que ficou faltando

Mergeado `3f935647ed` (23:22Z). O deploy do próprio commit foi **cancelado por concorrência** —
outro merge (`f6058d59`, Onda 7) entrou logo atrás e o workflow tem `cancel-in-progress`. O deploy
que rodou foi o do `f6058d59`, e `3f935647ed` **é ancestral dele** (conferido), então carrega a
mudança. Concluiu `success`.

**Provado em produção, medido no servidor e por HTTP:**

| O quê | Prova |
|---|---|
| HEAD deployado | `f6058d59e` |
| `Sped.tsx` no servidor | 8 ocorrências de `motivoBloqueio` |
| `SpedController.php` no servidor | `private function checagens` presente |
| `SpedIcmsIpiGeneratorService.php` no servidor | 2× `competenciaFechada` |
| Golden no servidor | 1794 bytes, `Sep 3 23:23` |
| **Asset compilado que o browser executa** | `build-inertia/assets/Sped-D55BGGG7.js`, 11998 bytes, mtime **23:31:20** (posterior ao merge) — contém "Régua de geração", "Competência liberada para geração", "Geração bloqueada" e "Prévia do arquivo" |
| Asset servido | `curl -sv` → **`HTTP/1.1 200 OK`**, e a copy da régua chega pelo HTTP |

**O que NÃO foi provado, e é honesto dizer:** o **render autenticado da régua dentro do drawer**.
O deploy encerrou as sessões — tanto o browser interno quanto o Chrome real caíram em `/login` — e
digitar senha é proibido. O screenshot que existe é o **baseline pré-merge** (drawer sem régua),
capturado antes do deploy em dark/1280.

O que **foi** medido no drawer real, injetando a sonda do `<Alert>` do DS antes do merge:
`bg oklch(0.3 0.008 240)` / texto `oklch(0.965 0.004 240)` → **contraste 12.40:1 (WCAG AAA)**.
A régua é legível ali dentro.

**Pendência declarada:** abrir `/fiscal/sped` autenticado, clicar na lupa de uma competência e
conferir a régua renderizada. Como todos os 5 períodos de biz=1 têm **0 notas autorizadas**, o
motivo esperado no `title` é "Sem notas autorizadas no período" — para exercitar as 4 checagens é
preciso um período com notas, ou olhar o payload (`periodos[].checagens`) no `data-page`.

## Duas coisas que o CI pegou, e eram minhas

1. **`layout-primitives · ratchet`** — introduzi 3 `flex` soltos ao trocar a classe `fx-*` pela
   lista da régua (`0 → 3`). Composto com `<Stack asChild>` / `<Inline asChild>`. **Baseline não
   regravado**: o conserto é o código, não a foto.
2. **`SUPERFICIE.md == árvore`** — os 3 arquivos novos não estavam mapeados. Regenerado pelo dono
   (`module-surface Fiscal --write`): 85 → 88.

## Decisões de método que valem além desta onda

- **O git barrou a fixture antes de eu corromper.** `* text=auto eol=lf` normalizaria o golden para
  LF — e o layout SPED **exige CRLF**, com o teste separando por `\r\n`. Sem o `-text` no
  `.gitattributes`, a fixture passaria local e quebraria no CI. Conferido no blob do índice: 1794
  bytes, 47 CRLF, 0 LF solto, SHA idêntico ao gerado.
- **Não alimentei o manifesto compartilhado.** Cheguei a rodar `casos:results` com o JUnit real e
  vi que ele **perde a proveniência dos outros 18 relatórios** quando gerado de uma rodada local.
  Revertido; os UCs ficam `Status: 🧪` (como os outros 10 desta tela) com o recibo do verde ao
  lado. Há 5 sessões Fiscal paralelas — o arquivo é delas também.
- **`transaction_id` fixo no golden.** O código do item do `0200` é `PDV-{transaction_id}`; sem
  fixá-lo o arquivo mudava a cada execução (dois SHA distintos antes de perceber). Determinismo
  provado com dois runs de SHA idêntico.

## Tamanho

10 arquivos, +675/−16 — acima do teto, declarado no PR e não escondido: 190 linhas de teste, ~200
de documentação, ~210 de código de produção, 47 da fixture gerada, 8 do `.gitattributes`.
