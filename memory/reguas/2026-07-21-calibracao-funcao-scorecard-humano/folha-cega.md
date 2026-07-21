# Calibração do `funcao-scorecard` — gold HUMANO · FOLHA CEGA (rotular sem abrir o gabarito)

> **Para [W].** Este é o **braço que faltava** do `funcao-scorecard` (método §5, rodada 3 —
> [FUNCAO-SCORECARD-METODO.md](../../requisitos/_Governanca/FUNCAO-SCORECARD-METODO.md)). A fixture
> de mutação já provou que o juiz acha o **defeito mecânico** não-circularmente (κ = 1,0 vs rótulo
> **objetivo**). O que ela **não** prova é se o juiz acerta quando a **intenção é ambígua** — onde o
> veredito certo é `incerto`, ou depende de intenção que só o **dono** tem. Aqui o gold é **você**,
> não uma mutação plantada. É **complementar**, dois ground-truths (o número desta rodada NÃO
> substitui a fixture — mede outra coisa).
>
> **Esta folha é limpa** (`_quem_monta_nao_exibe` do ledger): um juiz **Fable** isolado julgou as 9
> abaixo em sessão fresca, gravou os vereditos em `gabarito-SELADO.md`, e devolveu ao agente **só a
> contagem** — o agente ficou cego. Nada do gabarito passou pelo seu canal.

## ⛔ Antes de rotular

**NÃO abra `gabarito-SELADO.md`** desta pasta (tem as respostas do juiz) nem o YAML de scorecard destes
arquivos, se existir. Abrir = rotular ancorado, não às cegas → a entry vira `cego: false` (o validador
rejeita, e está certo). Se abriu por engano, diga — eu monto outra rodada com funções que não passaram
pelo seu canal.

**Você PODE** (e deve) abrir o **código-fonte** de cada função (o `arquivo:linha` está na tabela) e
usar o que você souber de **intenção / ADR / SPEC / charter**. Julgar o código com a intenção na cabeça
**é** o gold — o proibido é só ver o veredito do juiz.

## O vocabulário (o mesmo do scorecard — 1 veredito por linha)

Para o **critério indicado** de cada função, qual o seu veredito?

- **`concordo`** — a função **atende** o critério / **não há** problema nesse eixo.
- **`discordo`** — a função **viola** o critério / é um **problema real** nesse eixo.
- **`incerto`** — **não dá pra decidir** sem intenção que **nenhum canon escrito** (SPEC/charter/ADR/
  golden/runtime) resolve. Se você resolve **de cabeça** mas não está escrito em lugar nenhum, isso
  **também** é `incerto` — e é um **gap de documentação** (anote qual seria a resposta se documentada).
- **`n/a`** — o critério **não se aplica** a esta função.

> Quando você **resolver** (`concordo`/`discordo`), diga **de onde** vem a resolução: **(canon)** se
> há um ADR/SPEC/golden/charter escrito que sustenta, ou **(cabeça)** se é intenção sua ainda não
> escrita. Essa marca é o dado mais valioso da rodada — separa "o juiz deixou de achar um canon que
> existe" de "o juiz deferiu certo porque a intenção não está escrita".

## Os 9 itens

> `#3` e `#4` são a **mesma função** em **critérios diferentes** — de propósito (o scorecard é
> PLUGAR-NÃO-FUNDIR: cada eixo tem seu veredito). Responda os dois separados.

| # | Função | Arquivo:linha | Critério | Fato do código (neutro) | Seu veredito |
|---|--------|---------------|----------|-------------------------|--------------|
| 1 | `Util::num_uf` | [Util.php:31](../../../app/Utils/Util.php) | **C2** valor/estoque | Parser pt-BR: heurística "1 ponto + ≤2 dígitos = decimal en-US" (`80.00`→80), "1 ponto + exatamente 3 = milhar" (`25.000`→25000). Endurecida pós-incidentes. | |
| 2 | `Util::format_date` | [Util.php:341](../../../app/Utils/Util.php) | **C7** verdade de tipos/falha | Produz deslocamento de **+3h** no horário exibido; a assinatura/docblock de `format_date` não o sinaliza. Existe `format_date_no_shift` como alternativa sem o shift. | |
| 3 | `ProductUtil::getVariationGroupPrice` | [ProductUtil.php:1050](../../../app/Utils/ProductUtil.php) | **C3** dado-ausente | Sem linha em `variation_group_prices`, retorna `['price_inc_tax' => '', 'price_exc_tax' => '']` (string vazia). | |
| 4 | `ProductUtil::getVariationGroupPrice` | [ProductUtil.php:1052](../../../app/Utils/ProductUtil.php) | **C1** multi-tenant | `VariationGroupPrice::where('variation_id', $variation_id)` e `Variation::find($variation_id)` — sem `->where('business_id', ...)` explícito na função. | |
| 5 | `ProductUtil::calculateInvoiceTotal` | [ProductUtil.php:640](../../../app/Utils/ProductUtil.php) | **C3** dado-ausente | Com `$products` vazio, `return false` (não um total zerado tipado). | |
| 6 | `ProductUtil::getProductDiscount` | [ProductUtil.php:1559](../../../app/Utils/ProductUtil.php) | **C6** SQL cru | `whereRaw('(brand_id="'.$product->brand_id.'" AND category_id IS NULL)')` — interpola `$product->brand_id`/`$product->category_id` no SQL (sem binding). | |
| 7 | `KbAutoClassifierService::runClassification` | [KbAutoClassifierService.php:70](../../../Modules/KB/Services/KbAutoClassifierService.php) | **C1** multi-tenant | Usa `->withoutGlobalScopes()` **e** `->where('business_id', $businessId)` explícito (business_id chega por parâmetro; rodado em CLI/job com session vazia), com comentário `// SUPERADMIN:`. | |
| 8 | `FsmAuthorizationFlag::consume` | [FsmAuthorizationFlag.php:41](../../../app/Domain/Fsm/Support/FsmAuthorizationFlag.php) | **C7** verdade de tipos/falha | Estado `static` mutável; `consume` retorna `false` (sem log/exceção) se a chave não foi marcada. Docblock afirma "Per-request scope (static reset entre requests PHP-FPM/Octane)". | |
| 9 | `ProductUtil::generateProductSku` | [ProductUtil.php:699](../../../app/Utils/ProductUtil.php) | **C1** multi-tenant | Resolve o tenant por `request()->session()->get('user.business_id')` (não por parâmetro). | |

Responda na ordem, no chat (ex.: `1 concordo (canon), 2 incerto, 3 discordo, ...`). **Onde hesitar,
comente** — a hesitação marca onde a régua do juiz é frouxa, e vale tanto quanto o rótulo.

---

## Procedência (auditável)

- **Juiz sob calibração:** o próprio `funcao-scorecard` — subagente **Fable** (mesmo tier do refutador
  do ledger), sessão fresca, aplicou a rubrica dos 8 critérios lendo o código real em `origin/main` +
  varredura contada + busca por âncora de intenção no canon. Devolveu ao agente **APENAS** `9 itens
  julgados + path` — zero vereditos no canal, então você está limpo.
- **Amostra:** as 9 são **funções REAIS de risco** (valor, multi-tenant, SQL cru, dado-ausente),
  escolhidas onde a **intenção é genuinamente ambígua** — não o defeito mecânico da fixture sintética.
  Cobrem C1/C2/C3/C6/C7 (os critérios onde "depende de intenção" morde). C4/C5/C8 ficam de fora deste
  braço de propósito (são mecânicos — a fixture os cobre melhor).
- **Contaminação conhecida (declarada):** `proibicoes.md` é contexto always-on, então o juiz pode
  "saber" que este ecossistema teve incidentes de valor/tenant. Diferente da fixture sintética (onde
  isso seria cola), aqui **é correto** o juiz usar o canon — achar a ADR 0066/0093 **faz parte** de
  julgar código real. O limite honesto: por isso o número desta rodada mede "o juiz aplica a rubrica +
  acha o canon como um humano faria", não "o juiz acerta do zero sem canon".

## Como o resultado será lido (declarado ANTES da resposta — pra não escolher a régua depois)

- **Concordância** = seu rótulo == o veredito do juiz. Publicado como `K/9`, nunca arredondado pra
  adjetivo. Uma rodada não decide nada sozinha; o `--juiz-report` agrega as rodadas.
- **As discordâncias não são todas iguais** — e é isso que a marca `(canon)`/`(cabeça)` desambigua:
  - **juiz `incerto` × você resolveu `(cabeça)`** → o juiz **deferiu certo** (a intenção não está
    escrita). Conta como discordância, mas é **acerto de humildade** + revela **gap de doc**.
  - **juiz `incerto` × você resolveu `(canon)`** → o juiz **deixou de achar** um canon que existe →
    miss leve (não fez o lookup).
  - **juiz resolveu × você `incerto`** → o juiz **super-afirmou** (alucinou certeza onde o dono vê
    ambiguidade). É o modo de falha **perigoso** que este braço existe pra pegar.
  - **os dois resolveram e discordam** (`concordo`×`discordo`) → **miss de direção** — o juiz errou o
    lado.
- **N = 9, sem classe dominante forçada** (não há "responder X em tudo" que acerte de graça), então o
  baseline trivial é baixo → mais sinal por item.
- É **MEDIÇÃO, não portão** — nenhum merge trava com esse número
  (`node scripts/governance/ledger-check.mjs --juiz-report` sai 0 sempre).

## Depois que você rotular

1. O agente lê `gabarito-SELADO.md` (só **agora**, com seus rótulos já cravados no chat).
2. Mostra a tabela item-a-item, **incluindo onde o juiz divergiu** (você é o gabarito, não ele), e
   separa os 4 modos de discordância acima.
3. Registra 1 entry `tipo:"juiz"` em `governance/sdd-verification-ledger.json` (schema em
   `_meta.schema_entry_juiz`): `rotulador:"[W]"`, `cego:true`, `concordancia_pct` fechando com `K/9`.
4. `node scripts/governance/ledger-check.mjs --juiz-report` agrega esta rodada com as de status
   (`JUIZ-CAL-2026-07-r2` etc) — é o **denominador humano** do juiz.
