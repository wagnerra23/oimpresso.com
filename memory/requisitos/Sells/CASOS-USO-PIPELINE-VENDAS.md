# Casos de uso — Pipeline de Vendas (Orçamento → Produção → Venda → Faturamento)

> **Origem:** sessão 2026-05-12 — Wagner pediu auditoria do pipeline canônico após dores recorrentes:
> *"cancelam nota perdem número pula sequencial, orçamento foi para estágio voltou sem ninguém ter autorizado, produção iniciada sem pessoas ter autorizado"*.
>
> **Pré-requisitos JÁ EXISTENTES (não duplicar):**
> - [ADR 0129](../../decisions/0129-state-machine-canonica-fsm-rbac.md) — FSM canônica (5 tabelas + Service + RBAC + side-effects)
> - US-SELL-010..014 — **done** (PRs #501/507/508/510)
> - `app/Domain/Fsm/` — 16 classes (Models, Service, Policy, SideEffects, Exceptions)
> - `tests/Feature/Domain/Fsm/` — 5 suites, todos verdes
> - `nfe_inutilizacoes` table (criada, **sem service implementado**)
>
> **Este documento NÃO cria fundação nova.** Ele identifica 7 GAPs concretos entre o que existe e os 3 pain points de Wagner, propõe casos de uso Given/When/Then, e amarra com testes Pest failing-first (especificação executável).

---

## Sumário dos 7 GAPs

| GAP | Pain point Wagner | Severidade | US proposta | Esforço |
|---|---|---|---|---|
| **G1** | NFe cancelada → `forceDelete()` → próxima emissão pula sequencial | **P0 fiscal** | US-SELL-029 | 3h fix + 5h tests |
| **G2** | Sem `NfeInutilizacaoService` — falta service que use a tabela `nfe_inutilizacoes` existente | **P0 fiscal** | US-SELL-030 | 6h + 4h tests |
| **G3** | Actions FSM críticas sem role obrigatória → bypass autorização | **P1 governança** | US-SELL-031 | 2h seed + 1h tests |
| **G4** | UPDATE direto em `current_stage_id` (Eloquent/tinker) bypass do ExecuteStageActionService | **P1 governança** | US-SELL-032 | 4h Observer + 3h tests |
| **G5** | Sem processo seed "Venda Com Produção" — produção iniciada sem stage canônico | **P0 negócio** | US-SELL-033 | 6h + 4h tests |
| **G6** | Sem side-effect `CancelarVendaCascade` (orquestra cancelar NFe + estornar boleto + liberar reserva + notificar) | **P1 negócio** | US-SELL-034 | 4h + 3h tests |
| **G7** | Sem UI timeline `/sells/{id}/historico` mostrando transições (dados estão em `sale_stage_history` mas não exibidos) | **P2 UX/auditoria** | US-SELL-035 | 8h frontend |

**Total estimado:** ~60h (cobre 100% dos 3 pain points + LGPD audit trail). Fator 10x IA-pair recalibrado ([ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)): ~20-25h codáveis + canary 7d.

---

## CU-01 (G1+G2) · Cancelar NFe não pula sequencial

### Cenário real (Wagner 2026-05-12)
*"Cliente cancelam nota perdem número pula sequencial"*

### Contexto fiscal
SEFAZ distingue 3 estados de número NFe:

| Estado | Status oimpresso | Tratamento legal |
|---|---|---|
| **Autorizada** | `autorizada` | Número usado, declarado pra Receita. Imutável. |
| **Cancelada via SEFAZ** | `cancelada` | Número **usado oficialmente** (consta no SEFAZ como NF emitida + evento cancelamento). Não pode ser reaproveitado nem pulado. |
| **Rejeitada/Denegada** | `rejeitada`/`denegada` | Número **não foi declarado** (SEFAZ rejeitou). Pode (e deve) ser reaproveitado via processo de **Inutilização** (evento SEFAZ próprio) se já tiver chegado a pegar número. |
| **Inutilizada** | `inutilizado` | Número formalmente inutilizado via SEFAZ — registro permanece pra contagem fiscal. |

### Bug atual confirmado
[NfeService.php:380-398](../../../Modules/NfeBrasil/Services/NfeService.php#L380):

```php
if ($existente) {
    if (in_array($existente->status, ['autorizada', 'pendente'], true)) {
        return $existente;
    }
    // Status terminal negativo (rejeitada/denegada/cancelada) → hard delete
    Log::info('NfeService: emissão terminal negativa — force delete pra permitir retry', ...);
    $existente->forceDelete();
}
```

`cancelada` é tratada **igual a rejeitada/denegada** — sofre `forceDelete()`. Depois `proximoNumeroLocked()` ([NfeService.php:472](../../../Modules/NfeBrasil/Services/NfeService.php#L472)) faz `max(numero)` que **ignora o registro deletado** + recorre a `business.ultimo_numero_nfe` legado, mas se o registro do número cancelado foi hard-deletado E o `ultimo_numero_nfe` legacy estiver desatualizado, o próximo `emitir()` **pode escolher um número menor** ou (mais comum) o número fica **órfão** no sequencial fiscal — SEFAZ aceita o "novo" mas o cliente tem buraco entre `123 (cancelada via SEFAZ) → 124 (próxima ok)` que, se for inspecionado, **gera multa** ([CONFAZ Ajuste SINIEF 07/2005, Art. 14](https://www.confaz.fazenda.gov.br/legislacao/ajustes/2005/ajuste-007-05)).

### CU-01 Given/When/Then

**Given**
- Business `biz=1` (Wagner) com `numero_serie_nfe=1`
- NFe nº 100 emitida e **autorizada** via SEFAZ pra `transaction_id=5000`
- NFe nº 100 **cancelada via SEFAZ** (evento de cancelamento aceito — status local `cancelada`)

**When**
- Wagner cria nova venda `transaction_id=5001` e chama `NfeService::emitir($biz=1, [...])`

**Then**
- `proximoNumeroLocked()` retorna **101** (próximo sequencial após 100)
- Registro nº 100 com status `cancelada` **permanece no banco** (não é hard-deletado)
- Audit log de inspeção fiscal: `SELECT numero, status FROM nfe_emissoes WHERE business_id=1 AND modelo='55' AND serie='1' ORDER BY numero` retorna `100 cancelada, 101 autorizada` — **sem gaps**

**E (caso correlato — rejeitada com inutilização)**
- Given: NFe nº 100 com status `rejeitada` (SEFAZ não aceitou)
- When: Wagner aciona "Inutilizar nº 100" via UI ou comando
- Then: novo registro em `nfe_inutilizacoes` com `numero_de=100, numero_ate=100, justificativa, status=autorizado`. Registro em `nfe_emissoes` recebe status `inutilizado`. Próxima emissão pega **101** (não reaproveita 100). Sequencial fiscal consistente.

### Por que o forceDelete existe hoje
Comentário no código explica: `UNIQUE(business_id, transaction_id)` impede 2 registros pra mesma transaction. Pra permitir **retry** de uma emissão rejeitada, o registro precisa sair. Lógica correta, **mas mistura conceitos** — cancelamento via SEFAZ ≠ rejeição SEFAZ.

### Fix proposto (G1)
Em [NfeService.php:380](../../../Modules/NfeBrasil/Services/NfeService.php#L380):

```php
if ($existente) {
    // Idempotência caso feliz
    if (in_array($existente->status, ['autorizada', 'pendente'], true)) {
        return $existente;
    }
    // CANCELADA via SEFAZ: número FOI usado oficialmente. Bloqueia retry.
    if ($existente->status === 'cancelada') {
        throw new \RuntimeException(
            "NFe {$existente->numero} foi cancelada via SEFAZ. " .
            "Pra emitir nova NFe pra transaction {$transactionId}, " .
            "execute action FSM `emitir_nova_apos_cancelamento` (cria nova transaction)."
        );
    }
    // REJEITADA/DENEGADA: número não foi declarado. Permite retry via inutilização prévia.
    Log::info('NfeService: emissão rejeitada — inutilizar número antes de retry', ...);
    // Em vez de forceDelete, marca como inutilizado (preserva registro)
    $existente->update(['status' => 'inutilizado']);
}
```

### Fix proposto (G2) — NfeInutilizacaoService

Novo serviço `Modules\NfeBrasil\Services\NfeInutilizacaoService`:

```php
public function inutilizar(
    int $businessId,
    string $modelo,    // '55' | '65'
    string $serie,
    int $numeroDe,
    int $numeroAte,
    string $justificativa  // 15-255 chars (SEFAZ exige)
): NfeInutilizacao
```

Responsabilidades:
1. Validar `justificativa` 15-255 chars
2. Carregar cert via `CertificadoService`
3. Construir XML inutNFe + assinar
4. Enviar pra SEFAZ via NF-e PHP `Tools::sefazInutiliza()`
5. Persistir resultado em `nfe_inutilizacoes` (status `autorizado` se cstat=102)
6. Atualizar status pra `inutilizado` em `nfe_emissoes` da faixa (se existirem registros)

### Tests Pest (specs executáveis)
Arquivo: [`tests/Feature/Domain/Fsm/SequencialNfeAposCancelamentoTest.php`](../../../tests/Feature/Domain/Fsm/SequencialNfeAposCancelamentoTest.php)

Casos cobertos:
- `it('NFe cancelada via SEFAZ não sofre forceDelete')`
- `it('próximo número após cancelada não pula sequencial')`
- `it('tentativa de re-emitir mesma transaction com NFe cancelada bloqueia com mensagem clara')`
- `it('NFe rejeitada permite retry via inutilização')`
- `it('inutilização cria registro em nfe_inutilizacoes + marca status inutilizado em nfe_emissoes')`
- `it('faixa de inutilização múltiplos números marca todos status inutilizado')`
- `it('inutilização cross-tenant biz=99 falha por isolation')`

---

## CU-02 (G3) · Action FSM crítica exige role obrigatória

### Cenário real (Wagner 2026-05-12)
*"Orçamento foi para estágio voltou sem ninguém ter autorizado"*

### Comportamento atual
[ExecuteStageActionService.php:61-66](../../../app/Domain/Fsm/Services/ExecuteStageActionService.php#L61):

```php
$roleNames = $action->roles->pluck('role_name')->all();
if (! empty($roleNames) && (! $user || ! $user->hasAnyRole($roleNames))) {
    throw new UnauthorizedActionException(...);
}
```

**Se `sale_stage_action_roles` está vazia pra uma action, `empty($roleNames)` é true e libera pra qualquer user**. Isso é por design (algumas ações dispensam role) mas vira **bypass** se o seed esquecer de cadastrar role pra action crítica tipo `voltar_para_orcamento`, `iniciar_producao`, `cancelar_venda`, `reabrir_apos_faturada`.

### CU-02 Given/When/Then

**Given**
- Processo `Venda Padrão` biz=1 com stages [`rascunho` → `orcamento_enviado` → `aprovado_cliente`]
- Action `voltar_para_orcamento` (de `aprovado_cliente` → `orcamento_enviado`) marcada como `is_critical=true` (coluna criada por `2026_05_12_010001_add_is_critical_to_sale_stage_actions`)
- `sale_stage_action_roles` **sem nenhuma role** cadastrada pra essa action

**When**
- User `caixa@empresa.com` (sem nenhuma role) chama `ExecuteStageActionService::execute($venda, 'voltar_para_orcamento', $user)`

**Then (implementado — US-SELL-031, live prod biz=1)**
- Lança `UnauthorizedActionException` com mensagem: *"Action crítica 'voltar_para_orcamento' exige role configurada em sale_stage_action_roles. Adicione role no seeder ou via UI antes de executar (fail-secure US-SELL-031)."*
- Audit log em `sale_stage_history` **não é criado** (transação rollback)

**Caso correlato — action não-crítica continua aberta**
- Given: action `adicionar_observacao` com `is_critical=false`, sem roles
- When: qualquer user autenticado executa
- Then: passa (comportamento atual preservado pra actions não-críticas)

### Fix implementado (US-SELL-031 + short-circuit `grantsByPermission` do ADR 0265)
1. Migration `2026_05_12_010001_add_is_critical_to_sale_stage_actions` (default `false`)
2. Em [`ExecuteStageActionService::execute()`](../../../app/Domain/Fsm/Services/ExecuteStageActionService.php#L93):
   ```php
   if (! $grantedByPermission && empty($roleNames) && ($action->is_critical ?? false)) {
       throw new UnauthorizedActionException(
           "Action crítica '{$actionKey}' exige role configurada em " .
           "sale_stage_action_roles. Adicione role no seeder ou via UI " .
           "antes de executar (fail-secure US-SELL-031)."
       );
   }
   ```
3. Seed atualiza actions de risco com `is_critical=true` + role mínima (`vendas.gerente`, `producao.aprovar`, etc)

### Tests Pest
Arquivo: [`tests/Feature/Domain/Fsm/TransicaoCriticaExigeAutorizacaoTest.php`](../../../tests/Feature/Domain/Fsm/TransicaoCriticaExigeAutorizacaoTest.php)

- `it('action is_critical sem role cadastrada bloqueia execução')`
- `it('action não-crítica sem role mantém comportamento aberto')`
- `it('action is_critical com role exige user com role correta')`
- `it('action is_critical com role + user com role permite execução')`

---

## CU-03 (G4) · UPDATE direto em current_stage_id é bloqueado

### Cenário real (Wagner 2026-05-12)
*"Sem ninguém ter autorizado"* — implícito: alguém pode estar burlando o service via UPDATE direto.

### Surface attack
Hoje qualquer caminho do código pode fazer:
```php
$transaction->current_stage_id = 99; // direct write
$transaction->save();
```
Ou via tinker `Transaction::find(1)->update(['current_stage_id' => 99])`.

O `ExecuteStageActionService` é o **gateway recomendado** mas não o **gateway obrigatório**.

### CU-03 Given/When/Then

**Given**
- `Transaction` biz=1 com `current_stage_id=5` (stage `orcamento_enviado`)

**When**
- Código (Controller, Job, tinker) tenta:
  ```php
  $transaction->current_stage_id = 10;
  $transaction->save();
  ```

**Then (esperado, hoje NÃO ocorre)**
- Model Observer `TransactionFsmObserver::updating()` detecta mudança em `current_stage_id` sem flag `$_fsmAuthorizedTransition=true` (setada apenas pelo `ExecuteStageActionService`)
- Lança `UnauthorizedActionException`: *"Mudança direta em current_stage_id proibida — use ExecuteStageActionService::execute()"*
- `current_stage_id` permanece 5
- Log estruturado: `WARNING: FSM bypass attempt at <file:line> by user <id>`

### Fix proposto
1. Criar `App\Domain\Fsm\Observers\TransactionFsmObserver`
2. Em `updating(Transaction $tx)`:
   ```php
   if ($tx->isDirty('current_stage_id') && ! ($tx->_fsmAuthorizedTransition ?? false)) {
       throw new UnauthorizedActionException(
           'Mudança direta em current_stage_id proibida — use ExecuteStageActionService'
       );
   }
   ```
3. Em `ExecuteStageActionService::execute()`:
   ```php
   $subject->_fsmAuthorizedTransition = true;
   $subject->current_stage_id = $action->target_stage_id;
   $subject->save();
   unset($subject->_fsmAuthorizedTransition);
   ```
4. Registrar observer em `Transaction::booted()` e em qualquer model FSM-managed
5. Escape hatch documentado: `Transaction::withoutEvents(function () { ... })` ou superadmin via `_fsmAuthorizedTransition = true` explícito + log

### Tests Pest
Arquivo: [`tests/Feature/Domain/Fsm/CurrentStageIdBypassObserverTest.php`](../../../tests/Feature/Domain/Fsm/CurrentStageIdBypassObserverTest.php)

- `it('UPDATE direto em current_stage_id lança UnauthorizedActionException')`
- `it('ExecuteStageActionService transitiona normalmente')`
- `it('superadmin com flag _fsmAuthorizedTransition pode bypass com log')`
- `it('withoutEvents bypass é registrado em log estruturado')`

---

## CU-04 (G5) · Processo "Venda Com Produção" canônico

### Cenário real (Wagner 2026-05-12)
*"Produção iniciada sem pessoas ter autorizado"*

### Estado atual
3 processos seed em US-SELL-012 (PR #507):
- `Venda Sem Nota` — `[rascunho → faturada → paga]`
- `Venda Com Nota Manual` — `[rascunho → faturada → paga → emitida → enviada]`
- `Venda Com Nota Automática` — idem mas `paga` tem action `emitir_nfe` com `auto_trigger=true`

**Nenhum tem stage `producao`**. Cliente OficinaAuto/ComunicacaoVisual/Vestuario que tem fluxo produtivo (banner sendo impresso, recapagem em andamento, peça em costura) não consegue modelar "OS está sendo produzida" via FSM canônica → vira gambiarra ou fluxo informal.

### Pipeline proposto (canon)

```
quote_draft (rascunho)
    ↓ enviar_orcamento [role: vendas.enviar]
quote_sent (orçamento enviado)
    ↓ cliente_aprovou [role: vendas.confirmar_aprovacao] [side_effect: ReservarEstoque]
    ↓ cliente_rejeitou [role: vendas.confirmar_aprovacao]
quote_approved (aprovado, aguardando produção)
    ↓ iniciar_producao [role: producao.iniciar]
in_production (em produção)
    ↓ pausar_producao [role: producao.pausar]
    ↓ concluir_producao [role: producao.concluir] [side_effect: ConsumirEstoque]
ready_for_invoice (pronto pra faturar)
    ↓ faturar [role: financeiro.faturar]
invoiced (faturada)
    ↓ emitir_nfe [role: fiscal.emitir] [side_effect: EmitirNFeJob]
    ↓ marcar_pago [role: financeiro.baixar] [side_effect: BaixarFinanceiro]
paid (paga)
    ↓ entregar [role: logistica.entregar]
delivered (entregue)
    ↓ concluir [terminal]
completed (concluída)

Transições laterais (de qualquer stage não-terminal):
    ↓ cancelar_venda [role: vendas.gerente] [side_effect: CancelarVendaCascade]
cancelled (cancelada) [terminal]
    ↓ pausar [role: vendas.gerente]
on_hold (em espera)
    ↓ retomar (volta pro stage anterior, gravado em payload_snapshot)
```

### CU-04 Given/When/Then

**Given**
- Business biz=1 com processo `Venda Com Produção` seed instalado
- Venda em stage `quote_approved`
- User `caixa@empresa.com` (sem role `producao.iniciar`)

**When**
- `caixa` tenta executar action `iniciar_producao`

**Then**
- Lança `UnauthorizedActionException`
- Stage permanece `quote_approved`
- `sale_stage_history` não recebe registro

**E (caminho feliz)**
- Given: user `producao@empresa.com` com role `producao.iniciar`
- When: executa `iniciar_producao`
- Then: stage muda pra `in_production`, registro em history, side-effect `ReservarEstoque` confirma reserva criada

### Fix proposto
1. Seeder `FsmProcessoVendaComProducaoSeeder` (idempotente, cria pra todos businesses ativos)
2. 9 stages canônicos
3. 12 actions com roles obrigatórias
4. 4 side-effects ligados (`ReservarEstoque`, `ConsumirEstoque`, `BaixarFinanceiro`, `EmitirNFeJob`)
5. Seed de roles em `spatie_roles` se não existirem (`producao.iniciar`, `producao.concluir`, `vendas.gerente`, etc)

### Tests Pest
Arquivo: [`tests/Feature/Domain/Fsm/ProcessoVendaComProducaoTest.php`](../../../tests/Feature/Domain/Fsm/ProcessoVendaComProducaoTest.php)

- `it('seeder cria 9 stages + 12 actions + roles')`
- `it('iniciar_producao exige role producao.iniciar')`
- `it('concluir_producao consome reserva de estoque')`
- `it('fluxo feliz end-to-end: rascunho → entregue passa por 8 transições')`
- `it('cancelar_venda em qualquer stage não-terminal funciona')`
- `it('multi-tenant: processo biz=1 não vaza pra biz=99')`

---

## CU-05 (G6) · Cancelamento em cascata (CancelarVendaCascade)

### Cenário real (Wagner 2026-05-12)
Implícito em *"cancelam nota"* — hoje cancelar venda exige clicar em N telas (cancelar NFe, estornar boleto, liberar reserva, avisar cliente). Erros de consistência são comuns.

### CU-05 Given/When/Then

**Given**
- Venda biz=1 em stage `invoiced` (faturada) com:
  - 1 NFe modelo 55 status `autorizada`
  - 1 cobrança Asaas/Inter status `pending`
  - 1 reserva de estoque status `active`
- User `gerente@empresa.com` com role `vendas.gerente`

**When**
- Executa action `cancelar_venda` com payload `{ motivo: 'Cliente desistiu' }`

**Then (em transação atômica DB)**
1. Side-effect `CancelarVendaCascade::execute()`:
   - Dispatch `CancelarNfeJob` (envia evento cancelamento SEFAZ; **não pula sequencial** — CU-01)
   - Dispatch `EstornarBoletoJob` (Asaas/Inter API cancel)
   - Side-effect `LiberarReserva` marca reserva `released`
   - Dispatch `NotificarClienteJob` (WhatsApp/email "venda cancelada — motivo: X")
2. Stage muda pra `cancelled` (terminal)
3. Audit `sale_stage_history` registra:
   - `from_stage_id=invoiced`, `to_stage_id=cancelled`
   - `user_id=gerente.id`
   - `payload_snapshot={ motivo, side_effects_dispatched: 4 }`

**E (caso parcial — NFe já cancelada antes)**
- Given: venda com NFe status `cancelada` (cancelada via tela NFe antes)
- When: executa `cancelar_venda`
- Then: `CancelarNfeJob` é idempotente (detecta `cancelada` e pula). Outros side-effects executam normal.

### Fix proposto
1. Side-effect `App\Domain\Fsm\SideEffects\CancelarVendaCascade implements SideEffectInterface`
2. Em `execute(Transaction $venda, array $payload)`:
   ```php
   foreach ($venda->transactionDocuments as $doc) {
       if ($doc->doc_type === 'nfe' && $doc->status === 'authorized') {
           dispatch(new CancelarNfeJob($doc, $payload['motivo']));
       }
       // ...
   }
   ```
3. Cada Job é idempotente + retry em failure

### Tests Pest
Arquivo: [`tests/Feature/Domain/Fsm/CancelarVendaCascadeSideEffectTest.php`](../../../tests/Feature/Domain/Fsm/CancelarVendaCascadeSideEffectTest.php)

- `it('cancelar venda com NFe + boleto + reserva dispara 4 jobs em ordem')`
- `it('cancelar venda sem NFe não dispara CancelarNfeJob')`
- `it('NFe já cancelada antes não duplica CancelarNfeJob (idempotência)')`
- `it('failure de um side-effect não bloqueia outros (resiliência)')`
- `it('motivo é registrado em payload_snapshot do sale_stage_history')`

---

## CU-06 (G3+G4+G5 reforço) · Voltar de estágio exige autorização explícita

### Cenário real (Wagner 2026-05-12)
*"Orçamento foi para estágio voltou sem ninguém ter autorizado"*

### Variante crítica
Voltar de `quote_approved` → `quote_sent` (cliente "des-aprovou") **é caminho legítimo** mas exige decisão consciente — não pode ser acidental nem feito por qualquer usuário.

### CU-06 Given/When/Then

**Given**
- Venda biz=1 em stage `quote_approved`
- Action `reabrir_para_revisao` (volta pra `quote_sent`) com `is_critical=true`, `role=vendas.gerente`, `requires_confirmation=true`

**When**
- User `caixa@empresa.com` (sem role) tenta executar via UI

**Then**
- UI **não mostra botão** (via `StageActionPolicy::canExecute()`)
- Se forçar via curl direto: `UnauthorizedActionException` (CU-02)

**E (caminho feliz)**
- Given: user `gerente@empresa.com` com role `vendas.gerente`
- When: clica botão "Reabrir pra revisão", UI exige `requires_confirmation` (modal) com campo `motivo` obrigatório
- Then: action executa, stage volta, `sale_stage_history` registra com `motivo` no payload, side-effect `LiberarReserva` (porque desfaz aprovação que tinha reservado estoque)

### Fix proposto
- Já coberto pelos fixes G3+G4 + adição de action `reabrir_para_revisao` no seeder de CU-04
- UI honra `requires_confirmation` mostrando modal (frontend, fora do escopo Pest)

### Tests Pest
Coberto pelos testes de CU-02 e CU-04. Sem arquivo novo dedicado.

---

## CU-07 (G7) · Timeline auditável visível ao operador

### Cenário real (Wagner 2026-05-12)
*"Sem ninguém ter autorizado"* — implícito: hoje não tem como ver QUEM autorizou QUANDO.

### Estado atual
`sale_stage_history` registra tudo (US-SELL-011 done), mas **não tem UI** que exiba. Wagner não consegue ver "Maria moveu venda P-2026-0042 de `quote_approved` pra `quote_sent` em 12/05 às 14h32 com motivo X".

### CU-07 Given/When/Then

**Given**
- Venda biz=1 com 5 transições registradas em `sale_stage_history` ao longo de 7 dias

**When**
- Operador abre `/sells/{id}` na UI (Inertia/React)

**Then**
- Drawer/tab "Histórico" mostra timeline vertical:
  ```
  12/05 09:00 — Larissa criou venda (rascunho)
  12/05 09:15 — Larissa enviou orçamento (Vargas Recapagem)
  13/05 10:30 — Cliente aprovou via WhatsApp [auto-trigger]
  14/05 08:00 — João iniciou produção [role: producao.iniciar]
  14/05 16:45 — João concluiu produção [side-effect: estoque baixado]
  ```
- Cada item mostra: user, action, from/to stage, timestamp, motivo (se payload_snapshot.motivo existir), badges de side-effects disparados

### Fix proposto
- Endpoint API `/api/sells/{id}/history` retorna `sale_stage_history` com joins (user.name, action.label, stages.name)
- Componente React `<SaleTimeline />` no drawer existente `SaleSheet.tsx`
- Filtro por tipo de transição (transições críticas, side-effects fiscais, etc)

### Tests
- Pest controller test (`SaleHistoryControllerTest`)
- Frontend test deferido (Pest browser smoke biz=1)

---

## Mapeamento US → SPEC.md

| Caso de uso | US | Status |
|---|---|---|
| CU-01 G1 (cancelada não pula) | US-SELL-029 | TODO (a criar) |
| CU-01 G2 (NfeInutilizacaoService) | US-SELL-030 | TODO (a criar) |
| CU-02 G3 (is_critical role) | US-SELL-031 | TODO (a criar) |
| CU-03 G4 (Observer bypass) | US-SELL-032 | TODO (a criar) |
| CU-04 G5 (Venda Com Produção) | US-SELL-033 | TODO (a criar) |
| CU-05 G6 (CancelarVendaCascade) | US-SELL-034 | TODO (a criar) |
| CU-06 (voltar com autorização) | coberto por US-031+033 | — |
| CU-07 G7 (Timeline UI) | US-SELL-035 | TODO (a criar) |

---

## Aprovação pendente

Wagner valida ESTE documento + os 5 arquivos de teste failing-first ([`tests/Feature/Domain/Fsm/`](../../../tests/Feature/Domain/Fsm/)). Sem aprovação explícita, **zero código de produção** é alterado.

Critérios mínimos pra aprovar:
- [ ] Wagner concorda com os 7 GAPs (ou ajusta)
- [ ] Wagner concorda com pipeline canônico CU-04 (stages + actions + roles)
- [ ] Wagner aprova distinguir `cancelada via SEFAZ` ≠ `rejeitada` em [NfeService.php:380](../../../Modules/NfeBrasil/Services/NfeService.php#L380)
- [ ] Wagner aprova Observer de bypass em `current_stage_id`
- [ ] Wagner valida que 60h de esforço (recalibrado ~20-25h codáveis) é aceitável dado os pain points reais

Após aprovação: criar US-SELL-029..035 no MCP via `tasks-create` + sequenciar implementação.

---

**Refs canônicas:**
- [ADR 0129](../../decisions/0129-state-machine-canonica-fsm-rbac.md) — FSM tabular custom (mãe deste pipeline)
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 §5 SoC + §6 Tier 0
- [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) — recalibração estimates
- [CONFAZ Ajuste SINIEF 07/2005 Art. 14](https://www.confaz.fazenda.gov.br/legislacao/ajustes/2005/ajuste-007-05) — base legal sequencial NFe

**Última atualização:** 2026-05-12 — discovery + spec executável pra aprovação Wagner antes de implementar.
