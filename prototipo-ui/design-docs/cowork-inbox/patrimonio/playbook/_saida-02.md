---
sessao: "02"
titulo: "Trava de saldo na alocação — e o gêmeo Tier 0 que o teste dela achou"
dono: "[C]"
criado: 2026-09-08
base: origin/main fresco (mergeado antes do PR)
thread: 02-trava-de-saldo.md
prefixo_escrito: "Modules/AssetManagement/Services/AssetAllocationService.php · Modules/AssetManagement/Tests/Feature/Wave27AssetManagementPolishTest.php · +1 arquivo NOVO fora do prefixo declarado: Modules/AssetManagement/Exceptions/SaldoInsuficienteException.php (a thread pedia mensagem PT-BR no Service; exceção de domínio é a forma de tê-la sem tocar o controller, que é `nao_toca`)"
pr: "#7060 — aberto, NÃO mergeado"
veredito: "entregue — trava no caminho vivo, provada por bite-test (5 failed sem ela, 5 passed com ela) · 1 achado Tier 0 INÉDITO corrigido junto (o gêmeo do lado `allocate`) · `atualizar()` NÃO entrou, com motivo medido"
invalida: "NADA de thread irmã. CORRIGE por medição a premissa do §B da própria thread 02 — `quantidadeDisponivel()` NÃO retorna o disponível, retorna o ALOCADO; reusá-lo como saldo teria invertido a trava. COMPLETA a thread 01: ela pôs o predicado de tenant só na subconsulta de `revoke`, e o lado `allocate` do MESMO método ficou sem — o alocado somava transação de qualquer empresa. CONFIRMA 1:1 o `_saida-04.md §5` (o Request é órfão) — a trava não encostou nele."
---

# 02 · Saída — a trava, e o gêmeo que ela desenterrou

> **Gates de parada da thread, os três verificados antes de escrever:**
> **(a)** thread 01 mergeada ✅ (o `AR.business_id=assets.business_id` está no `main`);
> **(b)** bem com saldo legitimamente negativo em produção — **0 de 308** no CT 100, a trava
> não quebra fluxo existente; **(c)** ≤300 linhas — o `atualizar()` ficou de fora, §5.

---

## 1 · ⚠️ A premissa do §B da thread estava ERRADA, e seguir cegamente inverteria a trava

A thread manda: *"**Reusar** `quantidadeDisponivel()`. Não escrever segunda contagem"*. A
intenção está certa; **o nome do método mente**, e reusá-lo como "saldo disponível" teria
produzido uma trava invertida.

**Medido no CT 100** (bem de 10 unidades, 10 alocadas, 0 devolvidas):

| candidato | valor |
|---|---:|
| `quantidadeDisponivel()` devolve | **10** |
| "alocado líquido" (alocado − devolvido) | **10** |
| "o que sobra pra alocar" (`quantity` − alocado) | **0** |

Ou seja: ele devolve **o que está na mão das pessoas**, não o que sobra. Uma trava escrita como
`pedido > quantidadeDisponivel()` deixaria passar exatamente o caso que devia barrar.

**O que fiz, honrando a intenção sem herdar o erro:** extraí `alocadoLiquido(assetId,
businessId)` — a **mesma** expressão SQL, só parametrizada, porque `criar()` precisa do número
antes de existir transação — e `quantidadeDisponivel()` passou a **delegar** a ela. Há **uma**
contagem, com dois pontos de entrada. O saldo real nasce em `saldoLivre()`, que é
`asset.quantity − alocadoLiquido()`.

O nome enganoso **fica**: é consumido pelo `edit()` e pelo Blade legado, e renomear é outro
intent. Está documentado no docblock, com o número medido.

---

## 2 · ACHADO Tier 0 INÉDITO — a thread 01 corrigiu metade do cálculo

O teste Tier 0 que escrevi foi **o único vermelho dos 5**, e o motivo é real:

```php
// antes — o lado `allocate` do join, SEM predicado de tenant
->leftJoin('asset_transactions as AT', fn ($j) => $j->on('assets.id','=','AT.asset_id')
    ->where('transaction_type','allocate'))
...
// e logo abaixo, o lado `revoke` COM ele (thread 01):
(SELECT SUM(...) FROM asset_transactions AS AR
   WHERE AR.asset_id=assets.id AND AR.business_id=assets.business_id AND ...)
```

A thread 01 pôs o predicado na subconsulta de `revoke` e **o lado `allocate` do mesmo método
ficou sem**. Efeito: o alocado somava transação de **qualquer empresa**, derrubando o saldo do
dono — e a trava recusaria alocação legítima por causa de dado de outro tenant. É precisamente
o que a thread 02 avisa ao exigir a 01 primeiro (*"a trava usaria um número contaminado"*): a
01 fechou metade, esta fecha a outra.

**Não é cosmético e não é fora de escopo** — a trava *lê* esse número. Uma linha,
espelhando o que a 01 já fizera no irmão.

---

## 3 · REGRA MESTRE — antes→depois, medido antes de aplicar

A thread mexe em **quantidade**, então nada entrou sem os dois lados medidos.

```
bens varridos ................................. 308
bens cujo ALOCADO muda com o predicado ........   0
transações `allocate` cross-tenant (pré-cond) .   0
bens sobre-alocados (gate b) ..................   0
alocações existentes ..........................  38
que a trava TERIA recusado ....................   0
```

**Nenhum registro existente muda de valor.** A correção do predicado é inócua nesta base — ela
fecha a porta para o caso que o teste fabrica, sem reescrever nada do que já está gravado. E a
trava não teria barrado nenhuma das 38 alocações existentes.

⚠️ **Ressalva de honestidade:** essa base é o **staging do CT 100**, não produção. A medição
prova que a mudança é neutra *aqui*; ela **não afirma nada sobre produção**, que não foi medida.
Antes do merge, [W] pode querer a mesma sonda contra o banco de prod — os dois scripts são
leitura pura e estão reproduzidos no §7.

---

## 4 · Bite-test — o alarme toca, e não toca à toa

Rodei o teste **antes** da implementação, com a exceção já no lugar mas o Service intacto:

```
CONTROLE NEGATIVO (Service do main, sem trava) .... 5 failed
COM a trava ....................................... 5 passed
Wave27 inteiro (9 originais + 5 novos) ............ 14 passed · 28 assertions
regressão do módulo ............................... 70 passed · 341 assertions · 0 falhas
```

Os 5 cenários, e por que cada um existe:

| cenário | o que ele impede |
|---|---|
| bem com 3, pedido de **4** → recusa | o defeito original |
| bem com 3, pedido de **3** → passa | uma trava que recusasse TUDO passaria no primeiro sozinha |
| 3 no bem, 2 já alocados, novo pedido de 2 → recusa; 1 → passa | prova que lê o **estado**, não só o cadastro |
| 3 alocados, 3 devolvidos, novo pedido de 3 → passa | impede o desenho que conta só `allocate` e trava o bem pra sempre |
| alocação de **outro business** não conta | o §2 — foi este que ficou vermelho |

O primeiro cenário também conta as linhas gravadas (`toBe(0)`): se a transação nascesse e só
depois fosse rejeitada, o saldo já teria mentido.

**Duas falhas úteis do caminho**, ambas viraram nota no teste:
1. `Carbon\InvalidFormatException` — `Util::uf_date` monta o formato a partir de
   `session('business.date_format')`; **sem** sessão e com `$time=true` ele concatena
   `null.' H:i'` = `' H:i'`, que **não é vazio**, escapa do guard e estoura. A data tem de vir
   no mesmo formato que a sessão declara.
2. `Util.php:450` — sem `ref_no` explícito, `criar()` chama `setAndGetReferenceCount`, que grava
   contador na sessão e estoura fora de uma request HTTP. Sintoma revelador: o cenário de
   **recusa passava** (a trava barra antes do contador) e os de **sucesso** quebravam.

---

## 5 · `atualizar()` NÃO entrou — e o motivo é medido, não preguiça

O passo 5 da thread manda incluir `atualizar()` "se o total couber em ≤300 linhas". **Não cabe**,
e não é só tamanho: a regra é **diferente**. Em `criar()`, o pedido inteiro é novo; em
`atualizar()`, a quantidade **antiga da própria alocação** precisa voltar ao saldo antes da
comparação, senão editar 3→3 se recusaria a si mesma. Isso é outro caso de teste, outro cenário
de devolução parcial, e outra rodada de antes→depois.

**Vira thread 02b**, como a própria thread prevê ("não empurrar com a barriga"). O buraco segue
aberto e declarado.

---

## 6 · Limite conhecido — a mensagem PT-BR chega ao LOG, não à tela

`AssetAllocationController::store()` captura `\Exception` genérica e responde
`messages.something_went_wrong`. A mensagem da `SaldoInsuficienteException` (com pedido e
disponível formatados em pt-BR) vai para o log, não para o usuário.

Mudar isso exige tocar o controller, que é **`nao_toca`** nesta thread, e é 1 PR = 1 intent.
**A trava em si funciona**: a gravação é recusada, que é o que impede o rastro de nascer falso.
Declarado no docblock da exceção em vez de escondido.

Nota: trocar por `ValidationException` **não** resolveria — ela também estende `Exception` e cai
no mesmo `catch`.

---

## 7 · Como reproduzir

```bash
# bite-test (CT 100 — nunca local)
tailscale ssh root@ct100-mcp "docker exec -e DB_CONNECTION=mysql oimpresso-staging \
  php artisan test Modules/AssetManagement/Tests/Feature/Wave27AssetManagementPolishTest.php --filter=T02"

# a semântica de quantidadeDisponivel() e o gate (b)   -> sonda-saldo.php
# o antes->depois do predicado no lado `allocate`      -> sonda-allocate-tenant.php
# (as duas são LEITURA PURA; o corpo está no PR #7060)
```

---

## 8 · Checklist de saída

| # | item da thread | estado |
|---|---|---|
| 1 | `criar()` consulta o saldo reusando a contagem existente | ✅ via `alocadoLiquido` → `saldoLivre` |
| 2 | caso de recusa **e** caso de sucesso | ✅ 5 cenários |
| 3 | mensagem PT-BR **no Service**, não no Request órfão | ✅ com o limite do §6 declarado |
| 4 | decisão sobre `atualizar()` registrada | ✅ vira **02b**, com o motivo medido (§5) |
| 5 | Pest verdes | ✅ 14 no arquivo · 70 na regressão |
| 6 | placar no PR | ✅ [#7060](https://github.com/wagnerra23/oimpresso.com/pull/7060) |
| 7 | gate (b) verificado ANTES de aplicar | ✅ 0 de 308 sobre-alocados |
| 8 | ambiente compartilhado restaurado | ✅ 2 fingerprints idênticos ao inicial |
| 9 | PR aberto, **não mergeado** | ✅ |
