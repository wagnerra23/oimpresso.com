---
date: "2026-08-12"
topic: "Fronteira entre módulos: da medição aos 4 passos do shared kernel — e o CPF que vazou pra telemetria no caminho"
authors: [C]
module: null
tags: [arquitetura, modular-monolith, shared-kernel, multi-tenant, lgpd, codemod, refutacao-adversarial]
pii: false
---

# Fronteira entre módulos — 4 passos, 5 PRs, e o vazamento que o CI pegou

> **Pedido do [W]:** *"o quanto pode melhorar"* na arquitetura de fronteira entre módulos.
> **Entregue:** medição derivada (2 eixos) → parecer de especialista → 4 refactors → 2 catracas.
> **Custo escondido:** um codemod que assumia UMA forma de referência quando eram **4**, e um
> vazamento de PII que só não chegou em prod porque o Pest pegou.

## O que aconteceu, em ordem

**1. Recusei criar agente.** O primeiro instinto foi propor um "agente de fronteira". Rodei o
inventário antes: [`catalog-graph.mjs`](../../scripts/governance/catalog-graph.mjs) já era o dono,
required desde a [ADR 0370](../decisions/0370-module-surface-catalog-graph-required-emenda-0314.md),
com `--check` verde e 0 arestas penduradas. Criar agente teria sido LC-19 pela quarta vez.

**2. Mas o grafo media a fronteira DECLARADA.** Ele deriva do frontmatter escrito à mão. Medindo a
**real** (import no código): **57 pares em produção, 16 declarados — 28,1%**. Fato escrito à mão
apodrece; foi o diagnóstico que sustentou tudo depois.

**3. Um eixo que nenhum medidor via.** `app/` importava `Modules/` em **60 imports / 31 arquivos** —
seta invertida. Caso emblemático: `app/Concerns/HasBusinessScope.php`, o trait multi-tenant **Tier 0
do núcleo**, importando de dentro de um módulo.

**4. Especialista, não palpite.** Despachei o `estado-da-arte` (parecer em
[`2026-08-12-arte-shared-kernel-laravel.md`](2026-08-12-arte-shared-kernel-laravel.md)). Ele corrigiu
um **bug meu**: `Subscription` são duas classes homônimas, e meu detector agrupava por nome curto —
somavam 5 e cruzavam o limiar de cross-cutting sem nenhuma qualificar sozinha.

**5. Os 4 passos.** #1 catraca de direção (forward-only, 31 congelados) · #2 os 2 scopes → `App\Scopes`
· #3 `PiiRedactor` → `App\Support\Privacy` · #4 catraca de acoplamento módulo→módulo.

## Resultado medido (em `main`, não estimado)

| | pares | cobertura | alojamento | seta invertida |
|---|---|---|---|---|
| início | 57 | 28,1% | 22 pares | 31 arquivos |
| fim | **37** | **37,8%** | **2 pares** | **19 arquivos** |

`HasArquivos` **fica** — o especialista mostrou que não é leaf (arrasta `Arquivo` + o singleton
`ArquivosService`); movê-lo **criaria** seta núcleo→módulo.

## O vazamento — e por que ele é a parte que importa

O codemod do passo #3 procurou `Modules\Jana\Services\Privacy\PiiRedactor` com **uma** barra. Em
string literal PHP os bytes têm **duas** (`'\\Modules\\...'`), e as formas **não se cruzam como
substring**. 16 sites nunca entraram na lista. Num deles:

```php
$class = '\\Modules\\Jana\\Services\\Privacy\\PiiRedactor';
if (! class_exists($class)) { return null; }   // fail-open
```

Classe inexistente ⇒ `class_exists` falso ⇒ `maybeRedact()` devolve o valor **cru**. Um CPF foi
parar nos eventos Langfuse. O `ChatStreamObservabilityTest` reprovou. **Não chegou em produção.**

Foram **4 formas** de referência, e **nenhuma eu achei**: string escapada (Pest) · nome curto em
mesmo-namespace (PHPStan) · link markdown meio-atualizado (deadlink-gate) · registro datado tratado
como doc vivo (refutador GT-G5).

## O erro que mais dói

O refutador acusou o codemod de falsificar **registro datado**. Eu "refutei" 2 dos achados dizendo
que os arquivos tinham **0 linhas no diff** — e escrevi isso num commit **como fato**. A medição veio
de `git diff | grep -v "^[+-][+-]"`: em arquivo de bullets, a linha alterada vira `+- texto`, casa o
filtro e some. Em `licoes-rejeitadas.md`, que é só bullet, **zero por construção**. `--numstat`
provou 1/1. Uma das reescritas atingiu uma **lápide append-only Tier 0** — falsificando a história do
incidente que ela própria cataloga.

Foram **3 rodadas** de refutação (12,5% → 8,7% → 0%). Evidência em
[`2026-08-12-refutacao-lote-pr5675.md`](2026-08-12-refutacao-lote-pr5675.md).

## Duas tensões que ficaram resolvidas

**História × link.** Quando o alvo se move, registro datado não pode citar o path como link (morre)
nem trocá-lo (falsifica). Saída: **texto com nota de data** — a claim fica idêntica ao que era verdade
e o leitor de hoje sabe pra onde foi.

**Declaração × derivação.** Não preenchi `depends_on` nos 32 SCOPEs: seria escrever à mão o fato que
a máquina calcula — a doença diagnosticada. Declaração serve pra **norma** (o que *pode*); o fato é
derivado. Daí a catraca em vez do campo.

## Lições catalogadas

- §5 `2026-08-12` — codemod de rename com 4 formas + medição quebrada ([`licoes-rejeitadas.md`](../licoes-rejeitadas.md))
- **LC-16** → 4 ocorrências (reescrita textual sem âncora)
- **LC-08** → 88 (medir da fonte errada)

Nenhuma virou gate novo: "doc vivo ou registro datado?" é predicado semântico, e a família de guard
sintático já tem 5 lápides medidas. O que segurou foi **defesa em profundidade real** — 4 donos
distintos, cada um pegando uma forma.

## O que fica pro [W]

**19 fronteiras de negócio**, não mecânicas. As mais quentes tocam dinheiro: `RecurringBilling →
Financeiro` (9 imports em `ContaBancaria`/`ExtratoLancamento`), `Officeimpresso → Financeiro`
(`fin_titulos` por query crua). Regra Tier 0 de cálculo de valor se aplica. A catraca impede que
**nova** dívida entre calada enquanto a decisão não vem.
