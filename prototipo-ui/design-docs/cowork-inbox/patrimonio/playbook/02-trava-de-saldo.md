---
sessao: "02"
titulo: Trava de saldo na alocação — criar() aloca mais do que existe
dono: "[CL]"
base: cb475c0ca2f4
constituicao: CONSTITUICAO-COWORK.md (C1–C12)
prefixo: AssetAllocationService.php · StoreAssetAllocationRequest.php · Wave27AssetManagementPolishTest.php
nao_toca: AssetMaintenanceService · os controllers · resources/js/**
depende: **thread 01** (mesmo arquivo — vaga 2, nunca em paralelo: Lei 1)
antes:  criar() grava sem consultar quantidadeDisponivel()
depois: alocação acima do saldo é recusada, com mensagem PT-BR
---
# 02 · Trava de saldo na alocação

## ÂNCORA
```
arquivo  Modules/AssetManagement/Services/AssetAllocationService.php   5.054 B  sha bc036e9ed701
símbolo  criar(Request, int $businessId, int $userId): AssetTransaction      :31–:58
         quantidadeDisponivel(AssetTransaction): int                          :101–:116
         ↑ existe, e criar() NÃO chama
arquivo  Modules/AssetManagement/Http/Requests/StoreAssetAllocationRequest.php  1.964 B  sha c1d3ad6d5709
NÃO ler  AssetAllocationController.php (13 KB) — o fluxo está nas 2 faixas acima
```

## A · O defeito
`criar()` (`:31–:58`) monta o input com `asset_id` e `quantity`, carimba `business_id` (`:39`) e grava. **Em nenhum ponto consulta o saldo.** O método que faria isso — `quantidadeDisponivel()` — está no mesmo arquivo (`:101`) e não é chamado por `criar()`.

Resultado: dá pra alocar 10 unidades de um bem que tem 3. Não há erro; o rastro de responsabilidade nasce falso — que é exatamente o que o módulo existe pra entregar.

**A ordem importa:** a thread 01 conserta o **cálculo** do saldo. Aplicar esta antes faria a trava usar um número contaminado por outro tenant. Por isso 02 é vaga 2, e o primeiro passo dela é confirmar que a 01 está mergeada.

## B · Não inventar
- **Reusar** `quantidadeDisponivel()`. Não escrever segunda contagem: duas fontes pro mesmo número é como o bug renasce.
- ⚠️ **ERRATA [CL] 2026-09-08 — leia antes de escrever uma linha.** A instrução original desta linha era: *"Mensagem de validação em PT-BR (C2), no `StoreAssetAllocationRequest` — é onde as outras regras do módulo já moram"*. **A justificativa é falsa e a instrução produz trabalho inerte.** Medido no `main`: `AssetAllocationController@store` (`:186`) recebe `Illuminate\Http\Request` **cru**, o controller tem **0** `validate()`, e `StoreAssetAllocationRequest` não é referenciado por consumidor nenhum — o `rules()` dele **nunca executa**. As regras não "moram" lá: elas não rodam. Uma trava escrita nesse Request passa no CI e **deixa o saldo sem trava em produção**.
  **O caminho vivo é `AssetAllocationService::criar()` (`:34–:53`)**, que faz `$request->only(… 'asset_id' …)` e `AssetTransaction::create()` sem verificar dono do asset. Ou a trava vai no Service, ou o Request precisa primeiro ser ligado ao controller — e ligá-lo é mudança de assinatura, portanto outro PR.
  Recibo: `_saida-04.md` §5 (thread 04, medição) — [PR #7009](https://github.com/wagnerra23/oimpresso.com/pull/7009). Confirmado independentemente pela thread 01.
- `Wave27AssetManagementPolishTest.php` (4.594 B) já hospeda casos de polimento do módulo: estender, não criar arquivo de teste novo.

## Execução
```
PASSO   0) LER `_saida-04.md` §5 (thread 04) — ele invalida o passo 4 original
           e o §B desta thread. Sem isso você escreve num Request que não roda.
        1) confirmar a 01 mergeada (git log no arquivo) — senão PARE
        2) caso de teste: bem com saldo 3 → alocar 4 recusa; alocar 3 passa
        3) trava em criar(), reusando quantidadeDisponivel()
        4) [ERRATA] mensagem PT-BR NO SERVICE, não no Request órfão (ver §B).
           Se optar por ligar o Request ao controller, isso é PR separado —
           muda a assinatura de store().
        4b) PROVE que a trava roda: teste que exercita o caminho REAL
           (Service::criar), não o rules() do FormRequest. Verde no CI sobre
           um Request órfão não é evidência de nada.
        5) atualizar() (:65–:83) tem o MESMO buraco — se o total couber em
           ≤300 linhas, entra junto; se não, vira thread 02b. Não empurrar
           com a barriga.
        6) _saida-02.md
PARAR SE (a) a 01 não estiver mergeada
         (b) existir bem com saldo legitimamente negativo em produção (dado
             sujo): a trava quebraria fluxo real → RESÍDUO pra [W] ANTES de aplicar
         (c) passar de 300 linhas com atualizar() junto → divide (C6)
```

## Checklist de saída
1. `criar()` chama `quantidadeDisponivel()` · 2. caso de recusa e caso de sucesso · 3. mensagem PT-BR · 4. decisão sobre `atualizar()` registrada (entrou ou virou 02b) · 5. 9 Pest verdes · 6. placar no PR
