---
slug: 0193-nfeservice-retransmitir-sem-forcedelete
number: 193
title: "NfeService.retransmitir sem forceDelete (Wave 27 D6 · CONFAZ SINIEF 07/2005 Art. 14 IRREVOGÁVEL)"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-25"
accepted_at: null
accepted_via: "Aguarda Wagner aprovar em PR · sessão worker `agent-a0036d21083d0985f` 2026-05-25"
module: nfebrasil
quarter: 2026-Q2
tags: [nfe, fiscal, confaz, sinief, soft-delete, schema, multi-tenant, tier-0, audit-trail, append-only, pest-saturation, wave-27]
supersedes: []
supersedes_partially: []
amends: []
superseded_by: []
related:
  - "0093-multi-tenant-isolation-tier-0"
  - "0094-constituicao-v2-7-camadas-8-principios"
  - "0143-fsm-pipeline-live-prod-marco-2026-05-12"
  - "0192-auto-faturar-os-venda-jobsheet-observer"
charter_impact: []
pii: false
review_triggers:
  - "Volume de retransmissões >100/mês em qualquer biz sugerir performance penalty no caminho escolhido (soft-delete + withTrashed em proximoNumeroLocked) → revisitar índice ou GC periódico de rejeitadas"
  - "SEFAZ mudar norma sobre NF-e rejeitada vs autorizada (ex: passar a exigir histórico físico mesmo de não-autorizadas) → revisitar escopo Pest D6 e considerar Caminho C (UNIQUE composto preservando rows)"
  - "Outra entidade fiscal (NfeEvento, NfeInutilizacao, NfseEmissao) também usar `forceDelete` em path equivalente → estender escopo ADR e Pest D6 pra cobrir Services correlatos"
  - "Cliente reportar perda forense de NF rejeitada (auditor fiscal exigir reconstrução) → revisitar política GC ou migrar pra Caminho C (rows preservadas com `status='rejeitada_arquivada'` em vez de soft-delete)"
---

# ADR 0193 · NfeService.retransmitir sem forceDelete (Wave 27 D6 · CONFAZ SINIEF 07/2005)

## Contexto

O Pest `Modules/NfeBrasil/Tests/Feature/Wave27NfeSaturationTest::D6` é um GUARD de saturação (Trust L0 · source-grep + reflection) que valida invariante fiscal IRREVOGÁVEL:

```php
it('D6: CONFAZ SINIEF 07/2005 Art. 14 IRREVOGÁVEL — NfeService ZERO forceDelete em cancel', function () {
    $file = (new ReflectionClass(NfeService::class))->getFileName();
    $src = file_get_contents($file);
    expect($src)->not->toContain('forceDelete');
});
```

Hoje (2026-05-25) o teste **falha** porque `Modules/NfeBrasil/Services/NfeService.php:956` contém:

```php
// ── 5. forceDelete antigo (libera UNIQUE biz+tx; audit via Spatie) ──
$emissao->forceDelete();
```

dentro do método privado `retransmitirInterno($businessId, $nfeEmissaoId)`. A chamada opera **exclusivamente** sobre `NfeEmissao` com `status ∈ {rejeitada, denegada, erro_envio}` (validado na linha 911-917) — ou seja, **NUNCA sobre NF-e autorizada SEFAZ**. O motivo do `forceDelete` é liberar a UNIQUE constraint `nfe_emissoes_biz_tx_unique (business_id, transaction_id)` (migration `2026_05_06_002001_create_nfe_emissoes_table.php:40`) pra permitir `emitirParaTransaction($tx, $modelo)` criar nova `NfeEmissao` com próximo número fiscal (sequencial monotônico via `proximoNumeroLocked` linha 520, que JÁ usa `withTrashed()`).

### Por que o teste é canônico mesmo a NF não estar "autorizada"

O Pest D6 é **propositalmente conservador**: serve como GUARD contra evolução acidental de um método "que opera só em rejeitadas" pra outro path (`cancelar`, `inutilizar`) que opere em autorizadas. CONFAZ SINIEF 07/2005 Art. 14 (texto público: <https://www.confaz.fazenda.gov.br/legislacao/ajustes/2005/aj_007_05>) determina que NF-e **autorizada** é registro fiscal IRREVOGÁVEL — pode ser cancelada por evento 110111 (tipo de evento SEFAZ) mas a linha de banco NÃO pode ser fisicamente removida porque o auditor fiscal pode requisitar reconstrução. Embora o caso atual (NF rejeitada) esteja fora do scope estrito da norma, o test D6 estabelece **zero-tolerância** em toda a classe `NfeService` — qualquer `forceDelete` é proibido.

### Estado atual de adoção SoftDeletes em NfeBrasil

Importante: a infra de soft-delete pra `NfeEmissao` **já existe** e está em uso parcial:

- `Modules/NfeBrasil/Models/NfeEmissao.php:35` — `use SoftDeletes;` JÁ declarado
- Migration `2026_05_06_002001_create_nfe_emissoes_table.php:37` — `$table->softDeletes();` JÁ adicionado
- `Wave27NfeSaturationTest::D7` (linha 112-116) valida `expect($src)->toContain('SoftDeletes')` — invariante já passa
- `NfeService::proximoNumeroLocked` linha 520 — JÁ usa `NfeEmissao::withTrashed()` pra garantir sequencial monotônico
- `NfeInutilizacaoService.php:150` — JÁ usa `withTrashed()`
- Tests `Wave25/Wave26/NfeServiceCancelarTest/NfeServiceIdempotenciaRetryTest/NfeBrasilMultiTenantIsolationTest` — JÁ usam `withoutGlobalScopes()->withTrashed()`

Ou seja, o ecossistema NfeBrasil **já assumiu** o paradigma soft-delete; apenas o método `retransmitirInterno` ainda usa `forceDelete()`. É um resíduo legacy, não uma escolha arquitetural alinhada ao estado atual do módulo.

### Por que isso é débito técnico real, não falso positivo

- O workflow CI `modules-pest.yml` matrix roda Wave 27 NfeBrasil em **qualquer PR que toque `Modules/Repair/**`** (matrix cross-module — `Modules/Repair` é vertical alvo NF-e via `JobSheetObserver` ADR 0192).
- Pushes em `main` não cobrem `Modules/**` direto (só PR), então a regressão não é detectada lá. Falsos negativos do passado deixaram a quebra entrar.
- Resultado: cada PR Repair vê D6 vermelho · ruído mascara outras regressões reais.

### Audit trail hoje

Spatie LogsActivity (Wave 18 D7 conforme [ADR 0143 FSM Pipeline LIVE](0143-fsm-pipeline-live-prod-marco-2026-05-12.md) + `NfeEmissao::getActivitylogOptions`) registra `updated` em mudanças de `status/cstat/motivo/numero/chave_44/emitido_em` antes de qualquer delete. O evento `deleted` é capturado mas com payload mínimo (`logOnlyDirty` não captura snapshot completo). Há lacuna forense quando linha some — auditor que receba `activity_log` órfã sem `nfe_emissoes.id` correspondente precisa reconstruir do log. Caminhos B/C preservam a linha, A com soft-delete preserva linha sob `withTrashed()`.

## Avaliação de 5 caminhos

### Resumo comparativo

| Caminho | Schema change | Esforço | Risk | LOC est | Backward compat | Audit trail físico | Passa Pest D6 |
|---|---|---|---|---|---|---|---|
| **A · Soft-delete (`delete()`)** | Zero · infra já existe | **Baixo (~50 LOC)** | Baixo | ~50 | ✅ Total | ✅ `withTrashed()` preserva | ✅ Sim |
| **B · Status `superseded`** | ALTER ENUM + UNIQUE partial | Médio (~250 LOC) | Médio (ENUM ALTER prod) | ~250 | ⚠️ Parcial (queries `where status=...` precisam revisar) | ✅ Linha intacta | ✅ Sim |
| **C · UNIQUE composto** | DROP UNIQUE + CREATE composto + backfill | Alto (~150 LOC + risco backfill) | Alto (UNIQUE em tabela fiscal prod) | ~150 | ⚠️ Parcial (idempotência semântica muda) | ✅ Linha intacta | ✅ Sim |
| **D · Whitelist exceção test** | Zero | Trivial (~10 LOC test) | Médio (viola spirit guard) | ~10 | ✅ Total | ❌ Continua deletando | ✅ Sim (mas burlado) |
| **E · Extrair pra outro Service** | Zero | Trivial (~30 LOC refator) | Alto (hack de teste explícito) | ~30 | ✅ Total | ❌ Continua deletando | ✅ Sim (mas burlado) |

### Caminho A — Soft-delete via `delete()` (RECOMENDADO)

**Mudança:**

```diff
- $emissao->forceDelete();
+ $emissao->delete(); // soft-delete · preserva linha pra audit forense fiscal
```

**Esforço real:** 1 linha de código + atualizar 1 comentário + rodar suite Pest.

**Por que é trivial:**

1. `use SoftDeletes` JÁ está no `NfeEmissao` model (linha 35)
2. `softDeletes()` JÁ está na migration (`deleted_at TIMESTAMP NULL`, linha 37)
3. `proximoNumeroLocked` JÁ usa `withTrashed()` (linha 520) — sequencial monotônico preservado
4. UNIQUE constraint `(business_id, transaction_id)` automaticamente respeitado: rows soft-deleted **ainda contam** pra UNIQUE constraint do MySQL — **MAS** ver pegadinha abaixo

**Pegadinha #1 (crítica):** MySQL UNIQUE constraint **não distingue** soft-deleted de ativo. Se `NfeEmissao(biz=1, tx=42, status='rejeitada')` ficar soft-deleted (`deleted_at != null`), tentar inserir `NfeEmissao(biz=1, tx=42, status='pendente')` nova vai **violar UNIQUE** — exatamente o problema que `forceDelete` resolvia.

**Solução:** mudar a UNIQUE pra ignorar soft-deleted via partial index. MySQL 8 NÃO suporta partial unique indexes diretamente, mas há workaround canônico Laravel/MySQL: usar coluna virtual generated ou trigger. Alternativa mais limpa: **substituir** a UNIQUE `(business_id, transaction_id)` por UNIQUE `(business_id, transaction_id, deleted_at)` (treating NULL como valor distinto — comportamento padrão MySQL).

```sql
ALTER TABLE nfe_emissoes DROP INDEX nfe_emissoes_biz_tx_unique;
ALTER TABLE nfe_emissoes ADD UNIQUE KEY nfe_emissoes_biz_tx_alive_unique
  (business_id, transaction_id, deleted_at);
```

Em MySQL, NULL é tratado como "distinto de qualquer outro NULL" em UNIQUE composto — então múltiplas rejeitadas soft-deleted + 1 ativa convivem sem colisão. Migration aditiva + reversível.

**Pegadinha #2:** Queries que NÃO usam `withTrashed()` automaticamente filtram `deleted_at IS NULL`. Mapeamento dos pontos sensíveis (greppados):

- ✅ `NfeService::proximoNumeroLocked:520` → JÁ usa `withTrashed()`
- ✅ `NfeInutilizacaoService:150` → JÁ usa `withTrashed()`
- ✅ Tests críticos JÁ usam `withTrashed()`
- ⚠️ `NfeService::emitirParaTransaction` linha 401 (`NfeEmissao::where('business_id', $businessId)`) — verifica idempotência, deveria **ignorar** soft-deleted (comportamento default é correto — soft-deleted = retransmissão pendente, não duplica)
- ⚠️ `NfeEmissaoController` linhas 77/124/176/215 (listagem) — UI deve filtrar soft-deleted (comportamento default correto)
- ⚠️ `NfeStatusController:71` (consulta status) — não deve ressuscitar soft-deleted (comportamento default correto)

**Conclusão:** queries default já têm semântica correta. Único risco: alguma query futura precisar agregar rejeitadas históricas — usa `withTrashed()` explicitamente.

**Pegadinha #3 (factory tests Pest):** factories que setam `deleted_at` ou usam `forceCreate` podem confundir. Tests atuais já tratam isso. Em Pest novos, lembrar de `->withTrashed()` quando assertando count pós-retransmissão.

**Prós:**

- Esforço mínimo · alinha com paradigma JÁ adotado pelo módulo
- Audit trail físico preservado (`withTrashed()` recupera rejeitadas pra forense)
- Rollback trivial (reverter ALTER UNIQUE + restaurar `forceDelete()`)
- Passa D6 sem burlar guard
- Compatível com idempotência testada em `NfeServiceIdempotenciaRetryTest`

**Contras:**

- ALTER UNIQUE em tabela fiscal em prod requer manutenção (lock breve em tabela com volume médio · viável fora-pico)
- Backfill irrelevante (UNIQUE nova é superset da anterior · zero collision em rows existentes)
- Acumula soft-deleted ao longo do tempo (GC futuro opcional — review trigger)

### Caminho B — Status `superseded`

**Mudança:**

```diff
- $emissao->forceDelete();
+ $emissao->update(['status' => 'superseded']);
```

+ ALTER ENUM `status` adicionando `'superseded'`:

```sql
ALTER TABLE nfe_emissoes MODIFY COLUMN status
  ENUM('pendente','autorizada','rejeitada','cancelada','denegada','inutilizada','superseded')
  DEFAULT 'pendente' NOT NULL;
```

+ UNIQUE constraint precisa virar partial (`WHERE status NOT IN ('superseded', ...)`) — MySQL 8 não suporta nativo, precisa coluna virtual generated:

```sql
ALTER TABLE nfe_emissoes ADD COLUMN is_alive_for_unique TINYINT(1) AS
  (CASE WHEN status NOT IN ('superseded','rejeitada','denegada','erro_envio') THEN 1 ELSE NULL END) VIRTUAL;
ALTER TABLE nfe_emissoes DROP INDEX nfe_emissoes_biz_tx_unique;
ALTER TABLE nfe_emissoes ADD UNIQUE KEY nfe_emissoes_biz_tx_alive_unique (business_id, transaction_id, is_alive_for_unique);
```

**Prós:**

- Linha 100% preservada (forense ideal · sem `withTrashed()` indireto)
- Status change naturalmente capturado por Spatie LogsActivity (`status` está no `logOnly`)
- Semantica fiscal mais expressiva: "essa rejeitada foi superseded por X"

**Contras:**

- ALTER ENUM em prod MySQL é mais arriscado que ADD UNIQUE (ENUM modify pode causar table rewrite em tabela grande)
- Lógica de UNIQUE com coluna virtual é frágil (devs futuros não entendem na hora)
- Queries `Where('status', 'rejeitada')` em outros lugares (Wave 25/26 tests, NfeStatusController) precisam revisar pra entender se `superseded` deve aparecer ou não — superfície grande de revisão
- Wave 27 D6 atual NÃO valida `superseded` como valor canônico · risco de virar "status zombie"
- ENUM modify NÃO é reversível trivialmente (forward migration adiciona valor, backward requer UPDATE prévio · perigoso)

**Veredito:** mais "puro" semanticamente mas custo/risco desproporcional ao problema concreto (1 linha forceDelete) quando A já entrega o mesmo resultado audit-trail físico.

### Caminho C — UNIQUE composto `(biz, tx, status)`

**Mudança:**

```sql
ALTER TABLE nfe_emissoes DROP INDEX nfe_emissoes_biz_tx_unique;
ALTER TABLE nfe_emissoes ADD UNIQUE KEY nfe_emissoes_biz_tx_status_unique
  (business_id, transaction_id, status);
```

+ NfeService::retransmitir muda pra **marcar status** (`status='rejeitada_arquivada'` ou similar) em vez de delete:

```diff
- $emissao->forceDelete();
+ $emissao->update(['status' => 'rejeitada_arquivada']);
```

+ ALTER ENUM adicionando `'rejeitada_arquivada'` (mesma pegadinha B).

**Prós:**

- Permite múltiplas rejeitadas + 1 ativa convivendo natural (resolve causa raiz)
- Audit trail trivial (row intacta, status change loga)
- Idempotência semântica: re-emitir mesma TX pode criar Nova(pendente) coexistindo com Velha(rejeitada_arquivada)

**Contras:**

- ALTER UNIQUE + ENUM ALTER em prod fiscal (alto risco)
- Backfill: rows existentes podem violar nova UNIQUE se houver duplicatas históricas (improvável dado a UNIQUE atual estrita, mas requer SELECT GROUP BY HAVING COUNT > 1 antes)
- Lógica `emitirParaTransaction` precisa entender que múltiplas linhas (biz, tx, status diversos) podem existir — refactor não-trivial
- Mais 1 status no ENUM (zombie risk · idem B)
- Pega bug latente: UNIQUE atual garantia que `is_alive_for_idempotency` era simplesmente "row existe" — agora vira "row existe com status diferente de arquivada"; query precisa explicitar

**Veredito:** elegante na teoria mas refator amplo · trade-off não justifica vs Caminho A.

### Caminho D — Manter `forceDelete` + whitelist exceção no test

**Mudança:**

```php
// Wave27NfeSaturationTest D6
it('D6: ...', function () {
    $file = (new ReflectionClass(NfeService::class))->getFileName();
    $src = file_get_contents($file);

    // Excluir métodos retransmitir* do scope (operam em não-autorizadas)
    $src = preg_replace('/private function retransmitirInterno.*?\n    }/s', '', $src);
    expect($src)->not->toContain('forceDelete');
});
```

**Prós:**

- Trivial
- Zero schema change

**Contras:**

- **Viola spirit do guard.** O test serve como zero-tolerância · introduzir exceção semântica abre porta pra futuras exceções (next dev: "ah, então posso adicionar `forceDelete` em outro método que opera em rejeitadas")
- O argumento "rejeitada não está sob CONFAZ Art. 14" é válido legalmente mas FRACO operacionalmente: rejeitada ainda é parte do audit trail fiscal · auditor pode pedir reconstrução
- Hack de regex preg_replace no test é frágil (renomeio do método quebra)

**Veredito:** REJEITADO. Burla o guard sem resolver o débito real.

### Caminho E — Extrair `forceDelete` pra outro Service (FQN fora do escopo do test)

**Mudança:** criar `Modules/NfeBrasil/Services/NfeServiceLegacyOps::forceDeleteRejeitada(NfeEmissao)` · `NfeService::retransmitir` chama essa fachada externa.

**Prós:**

- Test D6 (escopo `ReflectionClass(NfeService::class)`) passa naturalmente
- Reuso teórico em outros services

**Contras:**

- **Hack de teste explícito.** Reflete "como passar no test" em vez de "como resolver o débito"
- Cria classe órfã sem razão arquitetural genuína
- Próxima saturação test pode descobrir `forceDelete` no novo Service e o ciclo recomeça
- Surface area de código aumenta sem benefício real

**Veredito:** REJEITADO. Same como D — burla guard sem resolver.

## Decisão recomendada

**Caminho A — Soft-delete via `delete()` + migration aditiva UNIQUE `(business_id, transaction_id, deleted_at)`.**

### Justificativa

1. **Esforço mínimo, alinhamento máximo.** O módulo NfeBrasil **já adotou** SoftDeletes como paradigma canônico — model trait declarada, migration `softDeletes()` aplicada, `proximoNumeroLocked` + `NfeInutilizacaoService` + 4 tests críticos já usam `withTrashed()`. O `forceDelete` em `retransmitirInterno` é resíduo legacy fora-do-padrão · resolver é completar uma transição já em curso, não introduzir paradigma novo.

2. **Audit trail físico preservado** (princípio CONFAZ aplicado generalizadamente). `withTrashed()` recupera rejeitadas pra forense · combinado com Spatie LogsActivity capturando status changes pré-soft-delete entrega trilha completa. Caminhos B/C entregam o mesmo benefício a custo 5-10x maior.

3. **Risco operacional baixo.** Único schema change é DROP+CREATE de UNIQUE (sem ENUM ALTER · sem coluna virtual generated · sem backfill que arrisque collision). Rollback trivial. Tabela `nfe_emissoes` tem volume médio (não-extremo · NF-e brasileira tem rate-limit SEFAZ natural) então ALTER fora-pico é janela curta.

4. **Alinhamento com decisões prévias do projeto** (precedente [ADR 0192 Caminho B' `cancelled_at` timestamp](0192-auto-faturar-os-venda-jobsheet-observer.md)). Naquele caso a opção foi **NÃO adotar SoftDeletes** em `Transaction` porque o módulo Sells não tinha SoftDeletes pre-existente · custo de introdução seria amplo. **Aqui é o inverso**: `NfeEmissao` JÁ tem SoftDeletes · adotar `delete()` é caminho de menor resistência.

5. **Permite override conservador.** Se review trigger "perda forense" disparar futuramente, migrar de A pra C é incremental (manter soft-delete + adicionar nova UNIQUE composta com status). Caminhos B/C bloqueiam caminho de volta.

### Por que NÃO Caminho B/C

Risco de ALTER ENUM em tabela fiscal prod não justifica ganho marginal (linha "100% intacta" vs "soft-deleted recuperável") quando o consumidor real do audit trail é o auditor fiscal que aceita `withTrashed()` SELECT.

### Por que NÃO Caminho D/E

Burlam o guard sem resolver o débito. Wave 27 D6 não é Pest "ruidoso", é GUARD de invariante fiscal · respeitá-lo é princípio Tier 0 conforme [ADR 0094 Constituição v2 §6](0094-constituicao-v2-7-camadas-8-principios.md) (Multi-tenant Tier 0 + accountability LGPD).

## Status

**proposed** — Wagner aprova depois. Esta ADR é **só decisão arquitetural**; implementação (1 linha PHP + 1 migration) fica pra PR SEPARADO numa wave futura (estimativa < 1h codable em IA-pair, [ADR 0106 fator 10x](0106-recalibracao-velocidade-fator-10x-ia-pair.md)).

## Consequências

### Positivas (se aprovado e implementado)

- **Pest Wave27 D6 passa em verde** — CI `modules-pest.yml` matrix Repair-trigger deixa de gerar falso vermelho · reduz ruído pra detectar regressões reais
- **Alinha módulo NfeBrasil ao seu próprio paradigma SoftDeletes** — remove inconsistência (model + migration + 6 query sites usam soft-delete, mas 1 método ainda `forceDelete`)
- **Audit trail forense reforçado** — auditor consegue reconstruir histórico de retransmissões via `NfeEmissao::withTrashed()->where('transaction_id', X)->get()` (hoje retorna lista incompleta porque rows somem)
- **Conserva sequencial monotônico** — `proximoNumeroLocked` já usa `withTrashed()` · zero mudança necessária
- **Idempotência preservada** — UNIQUE `(business_id, transaction_id, deleted_at)` permite múltiplas rejeitadas + 1 ativa por (biz, tx)
- **Rollback trivial** — reverter UNIQUE alteration + restaurar `forceDelete()` → 1 commit revert

### Negativas (se aprovado e implementado)

- **Tabela `nfe_emissoes` acumula soft-deleted ao longo do tempo** — em volume alto pode degradar query performance (mitigado pelo índice `business_id` + `business_id+status`). GC periódico de rejeitadas >12 meses pode ser necessário (review trigger).
- **ALTER UNIQUE em prod requer janela de manutenção** — lock breve · planejar fora-pico (madrugada ou domingo)
- **Devs novos podem se confundir** — query sem `withTrashed()` parece "perder" rejeitadas (na verdade comportamento default-correto, mas requer documentação no model)

### Pendências (não bloqueiam aceite desta ADR)

- **PR de implementação separado** (~50 LOC: 1 linha NfeService + 1 migration + atualizar comentário linha 951-955)
- **Pest test guard `NfeEmissaoUniqueAliveSoftDeletedTest`** — valida que 2 rejeitadas soft-deleted + 1 ativa convivem na mesma (biz, tx)
- **Documentação no `NfeEmissao` model** — adicionar PHPDoc `@property Carbon $deleted_at` + nota sobre `withTrashed()` pra forense
- **GC strategy** — decidir em ADR futura se rejeitadas soft-deleted >12 meses devem ser purged (review trigger ativa)
- **Estender pra `NfeEvento` e `NfeInutilizacao`** se Pest saturation futura detectar mesmo padrão

## Refs

- Pest D6: [`Modules/NfeBrasil/Tests/Feature/Wave27NfeSaturationTest.php` linha 85-91](../../Modules/NfeBrasil/Tests/Feature/Wave27NfeSaturationTest.php)
- Pest D7 (confirma SoftDeletes adoption): linha 112-116 mesmo arquivo
- Código-fonte ofensor: [`Modules/NfeBrasil/Services/NfeService.php:956`](../../Modules/NfeBrasil/Services/NfeService.php)
- `proximoNumeroLocked` que JÁ usa `withTrashed()`: [`Modules/NfeBrasil/Services/NfeService.php:520`](../../Modules/NfeBrasil/Services/NfeService.php)
- Model com `use SoftDeletes`: [`Modules/NfeBrasil/Models/NfeEmissao.php:35`](../../Modules/NfeBrasil/Models/NfeEmissao.php)
- Migration que JÁ tem `softDeletes()`: [`Modules/NfeBrasil/Database/Migrations/2026_05_06_002001_create_nfe_emissoes_table.php:37`](../../Modules/NfeBrasil/Database/Migrations/2026_05_06_002001_create_nfe_emissoes_table.php)
- CONFAZ SINIEF 07/2005 Art. 14 (texto público): <https://www.confaz.fazenda.gov.br/legislacao/ajustes/2005/aj_007_05>
- [ADR 0093 Multi-tenant Tier 0 IRREVOGÁVEL](0093-multi-tenant-isolation-tier-0.md)
- [ADR 0094 Constituição v2 (princípio 6 + 7)](0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0143 FSM Pipeline LIVE prod 2026-05-12 (Spatie LogsActivity Wave 18 D7 referência)](0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
- [ADR 0192 Auto-faturar OS→Venda (precedente Caminho B' `cancelled_at` em vez de SoftDeletes — caso espelhado oposto)](0192-auto-faturar-os-venda-jobsheet-observer.md)
- Wave 27 NfeBrasil POLISH ≥90 saturation (`Wave27NfeSaturationTest` 8 tests · D2/D6/D7/D9)
- `NfeEmissao::getActivitylogOptions` (`logOnly: status/cstat/motivo/numero/chave_44/emitido_em`)
