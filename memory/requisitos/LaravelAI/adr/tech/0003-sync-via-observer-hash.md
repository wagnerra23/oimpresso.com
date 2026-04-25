# ADR TECH-0003 (LaravelAI) · Sync embeddings via observer + hash

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: tech

## Contexto

`kg_entities.embedding` precisa estar atualizado com o conteúdo fonte:

- **Spatie permissions/roles** — mudam quando admin gerencia roles
- **ADRs filesystem** — mudam quando Wagner edita arquivo `memory/requisitos/.../adr/X.md`
- **Audit log** — append-only (nunca atualiza embedding existente; só adiciona se virar entidade KG)
- **Schemas dinâmicos** (Financeiro/etc.) — não embedding (consultados on-demand)

Estratégias possíveis:

1. **Re-embed tudo periodicamente** — wasteful, custo OpenAI alto, lento
2. **Re-embed só quando mudou** — precisa detectar mudança
3. **Manual re-embed** — admin clica botão; sujeito a esquecimento

Detectar mudança:
- **Eloquent observer** — `updated` event em Models do Spatie → trigger re-embed
- **Hash do conteúdo fonte** — comparar hash atual vs `kg_entities.source_hash`
- **Timestamp** — comparar `updated_at` da fonte vs embedding (frágil; nem sempre `updated_at` muda)

## Decisão

**Estratégia 2: Observer Eloquent (mudanças DB) + hash filesystem (mudanças arquivo) + cron diário (catch-all).**

### Para Spatie permissions/roles (mudança no DB)

```php
// Modules/LaravelAI/Listeners/SyncOnPermissionChange.php
class SyncOnPermissionChange implements ShouldQueue {
    public string $queue = 'laravel-ai-sync';

    public function handle($event): void {
        // Spatie\Permission\Events\RoleAttached, etc.
        $this->graphService->upsertEntity($event->role);
        $this->graphService->upsertRelation($event->user, $event->role, 'HAS_ROLE');
    }
}

// Boot
\Spatie\Permission\Events\RoleAttached::class => [SyncOnPermissionChange::class],
\Spatie\Permission\Events\PermissionAttached::class => [SyncOnPermissionChange::class],
// + outros eventos
```

### Para ADRs filesystem (mudança em arquivo)

Cron diário escaneia + compara hash:

```php
class SyncAdrEmbeddings extends Command {
    protected $signature = 'laravel-ai:sync-adrs';

    public function handle() {
        $files = File::allFiles(base_path('memory/requisitos'));

        foreach ($files as $file) {
            if (!str_ends_with($file, '.md')) continue;
            if (!str_contains($file, '/adr/')) continue;

            $content = file_get_contents($file);
            $hash = hash('sha256', $content);

            $entity = KgEntity::firstOrNew(['external_id' => $file->getRelativePathname(), 'type' => 'adr']);

            if ($entity->source_hash === $hash) continue;  // sem mudança

            $entity->fill([
                'label' => $this->extractTitle($content),
                'properties' => [
                    'path' => $file->getRelativePathname(),
                    'snippet' => Str::limit($content, 500),
                ],
                'source_hash' => $hash,
            ]);
            $entity->save();

            // Re-embed (assíncrono pra não atrasar cron)
            ReembedEntityJob::dispatch($entity);
        }
    }
}
```

### Catch-all cron

Diário 03:00:
1. Sync ADRs (script acima)
2. Sync Spatie data (verificar consistência via diff)
3. Cleanup: deletar `kg_entities` órfãs (entity DB já não existe)

## Consequências

**Positivas:**
- Mudanças em DB sincronizam **imediatas** (observer)
- Mudanças em filesystem capturadas em até 24h (cron diário) ou < 1 min (watcher Laravel opcional)
- Hash detecta mudança real (não falso positivo de timestamp)
- Re-embed é assíncrono (não bloqueia mutação original)
- Catch-all cron pega edge cases (worker crash, evento perdido)

**Negativas:**
- Latência: filesystem mudança aparece em busca após 24h (mitigar com watcher opcional)
- Cron diário processa todos os ADRs (~100) mesmo que só 1 mudou — solução: hash check rápido
- Re-embed custa OpenAI tokens (~$0.0001 por embed; trivial)

## Watcher Laravel (opcional, real-time)

```php
// Local dev only or pequenos tenants
\Spatie\Watcher\Watch::path(base_path('memory'))
    ->onFileUpdated(fn($path) => SyncAdrFromPathJob::dispatch($path));
```

Em produção: cron diário é suficiente; tenant Enterprise pode ativar watcher.

## Cleanup (entidades órfãs)

```php
// Job mensal
class CleanupOrphanEntities {
    public function handle() {
        // Spatie roles deletadas
        $existingRoleIds = DB::table('roles')->pluck('id')->all();
        KgEntity::where('type', 'role')
            ->whereNotIn('external_id', $existingRoleIds)
            ->delete();

        // ADRs deletadas
        $files = File::allFiles(base_path('memory/requisitos'));
        $existingPaths = collect($files)->map(fn($f) => $f->getRelativePathname())->all();
        KgEntity::where('type', 'adr')
            ->whereNotIn('external_id', $existingPaths)
            ->delete();
    }
}
```

## Tests obrigatórios

```php
test('observer cria entity ao adicionar role', function () {
    $u = User::factory()->create();
    $r = Role::create(['name' => 'TestRole']);
    $u->assignRole($r);  // dispara RoleAttached

    expect(KgEntity::where('type', 'role')->where('label', 'TestRole')->exists())->toBeTrue();
});

test('cron skip ADRs com hash inalterado', function () {
    Http::fake();  // OpenAI
    Artisan::call('laravel-ai:sync-adrs');
    $countBefore = Http::recordedRequests();

    Artisan::call('laravel-ai:sync-adrs');  // 2ª vez sem mudanças
    $countAfter = Http::recordedRequests();

    expect($countAfter - $countBefore)->toBe(0);  // sem nova chamada API
});

test('catch-all cron deleta entities órfãs', function () {
    KgEntity::factory()->create(['type' => 'role', 'external_id' => 999]);  // role não existe
    Artisan::call('laravel-ai:cleanup-orphans');
    expect(KgEntity::where('external_id', 999)->exists())->toBeFalse();
});
```

## Decisões em aberto

- [ ] Watcher real-time como opção Enterprise?
- [ ] Cron de cleanup em horário diferente (semanal? mensal?)
- [ ] Re-embed forçado via comando admin (`laravel-ai:reembed --type=adr --force`)?

## Alternativas consideradas

- **Re-embed tudo diariamente** — rejeitado: wasteful, $0.50/mês desnecessário (50 ADRs × cada vez)
- **Sem cron, só observer** — rejeitado: filesystem changes perderiam
- **Webhook do Git** (commit triggers re-embed) — overkill: cron + hash resolve

## Referências

- ARQ-0001 (storage)
- TECH-0001 (embeddings)
- R-AI-005, R-AI-006 (SPEC)
