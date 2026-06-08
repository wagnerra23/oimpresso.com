# MIGRATION_NOTES — Repair Shared Refactor (Caminho A audit 2026-05-10)

> **Status:** DRAFT pra Felipe revisar. NÃO modificar arquivos reais ainda.
> **Audit que motivou:** [repair-shared-vs-oficina-auto-audit.md](../repair-shared-vs-oficina-auto-audit.md) — Caminho A.
> **Origem ADR:** [ADR 0121 §P8](../../0121-oimpresso-modular-especializado-por-vertical.md) — default temporário "shared infrastructure" agora confirmado.
> **Esforço estimado:** 14h IA-pair × margem 2x = ~2 dias úteis Felipe.

## Sumário do refactor

Refactor concentrado em **2 arquivos reais** (audit confirmou — schema BD 100% neutro):

| Arquivo real | Draft proposto | Linhas tocadas |
|---|---|---|
| `Modules/Repair/Http/Controllers/ProducaoOficinaController.php` | `ProducaoOficinaController.php.proposed` | ~120 (renames + loadRepairSettings + 35 ocorrências de keys mock) |
| `resources/js/Pages/Repair/ProducaoOficina/Index.tsx` | `Index.tsx.proposed` | ~110 (interface Card + filtros dinâmicos + 38 ocorrências de field access) |

**Total identificadores renomeados: ~73** (35 Controller + 38 Page = bate com audit §inventário).

**0 mudanças** em: migrations, Models (`JobSheet`/`RepairStatus`/`DeviceModel`), Service, lang files, Blade views, Charter (atualizar separadamente — escopo F1.5 charter rewrite).

## Renames aplicados

| De (automotivo) | Pra (genérico shared) | Tipo |
|---|---|---|
| `plate` | `code` | Card field + mock key |
| `vehicle` | `item` | Card field + mock key |
| `brand` | `brand` (mantido — já é genérico no BD) | — |
| `km` (campo) | `usage_meter` (+ `usage_unit`) | Card field + formatter |
| `formatKm()` | `formatUsage(meter, unit)` | helper |
| `box` | `slot` | Card field + filter |
| `BOXES = ['B1'..]` | `slot_config[].options` (vindo de business.repair_settings) | hardcoded → prop |
| `ELEVADORES = ['E1'..]` | `slot_config[].options` (segundo SlotGroup) | hardcoded → prop |
| `boxFilter` / `elevadorFilter` state | `slotFilters: Record<key, value>` (dinâmico) | state |
| `mecanico` | `executor` | Card field + mock key |
| `mecanico_initials` | `executor_initials` | Card field + mock key |
| `aprovacao_pendente` | `pending_approval` | Card field + totals key |
| `aprovado` | `approved` | Card field |
| `orcamento_total` | `quote_total` | Card field |
| `orcamento_pecas` | `quote_items` | Card field |
| `orcamento_status` | `quote_status` | Card field |

**Não-renomeados (intencionalmente):** `repair_status`, `repair_settings`, `JobSheet`, `RepairStatus` — todos genéricos no BD/legacy UltimatePOS-assistência-técnica. Renomear quebraria 53 arquivos fora do módulo (audit §B2). Caminho A explicitamente NÃO mexe em namespaces/Models/migrations.

## Como aplicar (Felipe)

### Pré-requisitos

- [ ] Branch dedicada: `git checkout -b refactor/repair-shared-vocabulary`
- [ ] Worktree limpa: `git status` deve estar clean antes de copiar drafts
- [ ] Pest local funcionando: `vendor/bin/pest Modules/Repair/Tests/` deve passar **antes** de qualquer mudança (baseline verde)

### Passo 1 — Copiar drafts pros arquivos reais

```bash
# Backup do estado atual (rollback rápido se algo quebrar):
cp Modules/Repair/Http/Controllers/ProducaoOficinaController.php /tmp/ProducaoOficinaController.before.php
cp resources/js/Pages/Repair/ProducaoOficina/Index.tsx /tmp/Index.before.tsx

# Aplicar drafts:
cp memory/decisions/proposals/drafts/repair-shared-refactor/ProducaoOficinaController.php.proposed \
   Modules/Repair/Http/Controllers/ProducaoOficinaController.php

cp memory/decisions/proposals/drafts/repair-shared-refactor/Index.tsx.proposed \
   resources/js/Pages/Repair/ProducaoOficina/Index.tsx
```

### Passo 2 — Pest snapshot test (CRÍTICO — fazer ANTES de qualquer commit)

⚠️ **Risco principal:** mudança de keys (`plate` → `code`, etc) é silenciosa pra TypeScript se algum sub-componente acessar `card.plate` legacy. **Pest snapshot é a rede de proteção.**

Criar `Modules/Repair/Tests/Feature/ProducaoOficinaRefactorTest.php`:

```php
<?php

use Modules\Repair\Entities\JobSheet;
use Modules\Repair\Entities\RepairStatus;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    // ADR 0101 — biz=1 (Wagner WR2), NUNCA biz=4 (cliente ROTA LIVRE)
    $this->actingAsBusinessUser(businessId: 1);
});

it('returns shared-vocabulary keys in mock columns', function () {
    $response = $this->get('/repair/producao-oficina');

    $response->assertInertia(fn ($page) => $page
        ->component('Repair/ProducaoOficina/Index')
        ->has('columns.0.cards.0', fn ($card) => $card
            ->has('code')              // antes: 'plate'
            ->has('item')              // antes: 'vehicle'
            ->has('brand')
            ->has('usage_meter')       // antes: 'km'
            ->has('usage_unit')
            ->has('executor')          // antes: 'mecanico'
            ->has('executor_initials') // antes: 'mecanico_initials'
            ->etc()
        )
        ->has('slot_config')
        ->has('label_overrides')
    );
});

it('returns pending_approval (renamed from aprovacao_pendente)', function () {
    $response = $this->get('/repair/producao-oficina');

    $response->assertInertia(fn ($page) => $page
        ->where('totals.pending_approval', 3) // antes: 'totals.aguardando_aprovacao'
    );
});

it('exposes default slot_config when business.repair_settings is null', function () {
    $response = $this->get('/repair/producao-oficina');

    $response->assertInertia(fn ($page) => $page
        ->where('slot_config.0.label', 'Box')
        ->where('slot_config.0.options.0', 'B1')
        ->where('slot_config.1.label', 'Elevador')
        ->where('slot_config.1.options.0', 'E1')
    );
});

it('uses configured slot_config when business.repair_settings has slots', function () {
    \App\Business::where('id', 1)->update([
        'repair_settings' => json_encode([
            'slots' => [
                ['key' => 'slot', 'label' => 'Bancada', 'options' => ['BC1', 'BC2']],
            ],
            'labels' => ['executor' => 'Designer'],
        ]),
    ]);

    $response = $this->get('/repair/producao-oficina');

    $response->assertInertia(fn ($page) => $page
        ->where('slot_config.0.label', 'Bancada')
        ->where('slot_config.0.options.0', 'BC1')
        ->where('label_overrides.executor', 'Designer')
    );
});

it('move endpoint preserves business_id scope (multi-tenant Tier 0)', function () {
    $jobSheetOtherBiz = JobSheet::factory()->create(['business_id' => 99]);

    $response = $this->post("/repair/producao-oficina/{$jobSheetOtherBiz->id}/move", [
        'column' => 'em-execucao',
    ]);

    $response->assertSessionHas('error');
    expect($jobSheetOtherBiz->fresh()->status_id)->toBe($jobSheetOtherBiz->status_id);
});
```

Rodar:

```bash
vendor/bin/pest Modules/Repair/Tests/Feature/ProducaoOficinaRefactorTest.php
```

**Não commitar se algum teste falhar.** Voltar pro backup `/tmp/*.before.*`.

### Passo 3 — Smoke visual local (Vite + Herd)

```bash
npm run build  # gera bundle limpo
# Abrir https://oimpresso.test/repair/producao-oficina
# Logado como user de biz=1, NÃO biz=4 (ROTA LIVRE não usa Repair)
```

Validar visualmente:
- [ ] 5 colunas renderizam (Recepção/Diagnóstico/Aguardando/Em-execução/Pronto)
- [ ] Filtros Box (B1..B4) + Elevador (E1..E2) aparecem
- [ ] Mock cards mostram "Civic 2019 / Honda" (mock fixture preservado, mesmo com keys renomeadas)
- [ ] Drag-drop card entre colunas funciona (otimistic UI)
- [ ] Drawer abre ao clicar card

### Passo 4 — Snapshot baseline (alternativa Vitest se Pest snap não cobrir UI)

Se quiser snapshot de UI puro (não só shape), usar Vitest + `@testing-library/react`:

```typescript
// resources/js/Pages/Repair/ProducaoOficina/__tests__/Index.snap.test.tsx
import { render } from '@testing-library/react';
import ProducaoOficinaIndex from '../Index';
import { mockColumnsFixture } from './fixtures'; // copiar do mockColumns() PHP

test('renders shared kanban with default slot config', () => {
  const { container } = render(
    <ProducaoOficinaIndex
      columns={mockColumnsFixture}
      totals={{ os: 17, pending_approval: 3 }}
      data_source="mock"
      slot_config={[
        { key: 'slot', label: 'Box', options: ['B1','B2','B3','B4'] },
        { key: 'area', label: 'Elevador', options: ['E1','E2'] },
      ]}
      label_overrides={{}}
    />
  );
  expect(container).toMatchSnapshot();
});

test('renders with com.visual labelOverrides + custom slot_config', () => {
  const { container } = render(
    <ProducaoOficinaIndex
      columns={mockColumnsFixture}
      totals={{ os: 17, pending_approval: 3 }}
      data_source="mock"
      slot_config={[
        { key: 'slot', label: 'Bancada', options: ['BC1','BC2'] },
      ]}
      label_overrides={{
        code: 'Nº OS', item: 'Arte', usage_unit: 'm²', executor: 'Designer'
      }}
    />
  );
  expect(container).toMatchSnapshot();
});
```

### Passo 5 — Commit + PR

```bash
git add Modules/Repair/Http/Controllers/ProducaoOficinaController.php
git add resources/js/Pages/Repair/ProducaoOficina/Index.tsx
git add Modules/Repair/Tests/Feature/ProducaoOficinaRefactorTest.php

git commit -m "refactor(repair): kanban shared vocabulary [F]

- placa→code, vehicle→item, km→usage_meter, mecanico→executor
- BOXES/ELEVADORES hardcoded → slot_config consumido de business.repair_settings
- LabelOverrides per-vertical (auto/com.visual/vestuario)
- 0 mudanças BD/Models/Service (audit confirmou neutralidade schema)
- Pest snapshot + multi-tenant biz=1 isolation tests

Refs: ADR 0121 §P8 (shared infra default), ADR 0094 §5 (SoC brutal)
Audit: memory/decisions/proposals/repair-shared-vs-oficina-auto-audit.md"
```

PR ≤300 linhas (commit-discipline Tier A). Wagner aprova merge.

## Riscos & cuidados

### Risco 1 (ALTO) — Sub-componente acessando key legacy fora do diff

⚠️ **Auditar manualmente:**

```bash
grep -rn "card\.\(plate\|vehicle\|km\|mecanico\|aprovacao_pendente\|aprovado\|orcamento_\|box\)" \
  resources/js/Pages/Repair/
```

Esperado: 0 matches após refactor. Se aparecer algo, sub-componente esquecido — corrigir antes de PR.

### Risco 2 (MÉDIO) — Backend listener/job lendo card array shape

```bash
grep -rn "'plate'\|'vehicle'\|'mecanico'\|'aprovacao_pendente'" \
  Modules/Repair/ app/ --include="*.php"
```

Se aparecer match em Listener/Job/Mailer (ex: `OrcamentoEnviado` mailer lê `$card['plate']`), atualizar shape. Audit § sugere que **NÃO existem hoje**, mas validar.

### Risco 3 (MÉDIO) — Charter MWART desalinhado

`resources/js/Pages/Repair/ProducaoOficina/Index.charter.md` tem 2 ocorrências automotivas. Atualizar **junto** ou criar issue de follow-up. NÃO deixar charter mentindo (gate F1.5).

### Risco 4 (BAIXO) — i18n strings novas

Refactor não introduz strings PT-BR novas (UI já em PT-BR via `label`). Mas labels `'Aprov?'` / `'Aguardando retirada'` continuam hardcoded. Escopo separado (i18n é US-COPI-NN).

## Reverter

Se algo quebrar em prod:

```bash
git revert <hash-do-merge>
git push
```

ROTA LIVRE (biz=4) NÃO usa Modules/Repair — risco de impacto cliente é zero. Outros 6 businesses saudáveis em construção podem usar — Felipe confere com Wagner antes do merge se algum cliente piloto está com `repair_status` configurado em prod.

## Próximos passos (depois do merge)

1. **Charter MWART rewrite** — `Index.charter.md` sem vocabulário automotivo (US-MWART-NN)
2. **Pest CI gate** — workflow `repair-shared-vocab.yml` que falha se algum dia voltar `placa|vehicle|km|mecanico` em `Modules/Repair/**` (lock-in pós-refactor)
3. **ADR amendment 0121 §P8** — confirmar "shared default" → "shared definitivo" + link este refactor
4. **Seeder OficinaAuto** — quando Modules/OficinaAuto existir, criar `Modules/OficinaAuto/Database/Seeders/RepairSettingsSeeder.php` aplicando o JSON automotivo do exemplo

---

**Última checagem antes de PR:**
- [ ] Pest verde (snapshot + multi-tenant + slot_config tests)
- [ ] Smoke local biz=1 ok (5 colunas + drag-drop + drawer)
- [ ] grep legacy keys = 0 matches
- [ ] Charter MWART atualizado OU issue follow-up criada
- [ ] PR ≤300 linhas
- [ ] Conventional commit + Refs ADR 0121
