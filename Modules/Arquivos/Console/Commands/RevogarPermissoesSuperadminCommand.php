<?php

declare(strict_types=1);

namespace Modules\Arquivos\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * arquivos:revogar-permissoes-superadmin — decisão [W] 2026-10-01 ("Superadmin").
 *
 * `arquivos.restore` e `arquivos.governanca` ficaram declaradas no `DataController` do módulo
 * (threads 03/04 do playbook) e, portanto, concedíveis em `/roles/{id}/edit` por qualquer admin
 * de negócio. A decisão as tornou ato de plataforma: as rotas agora pedem `can:superadmin` e as
 * duas saíram do catálogo.
 *
 * Por que um comando, e não só a saída do catálogo: o `RoleController` PRESERVA no save toda
 * permissão que o formulário não oferece (`__preservaNaoOfertadas`) — então uma concessão feita
 * no intervalo nunca mais sairia pela tela. Ela já não abre nada (o código não pergunta mais por
 * esses nomes), mas fica como lixo enganoso no papel. Este comando a remove.
 *
 * O que faz, nesta ordem, idempotente:
 *   1. desvincula as duas permissões de TODO papel (`role_has_permissions`);
 *   2. desvincula de TODO usuário com concessão direta (`model_has_permissions`);
 *   3. apaga as duas linhas de `permissions` (sem consumidor no código).
 * Rodado de novo, encontra zero e não escreve nada.
 *
 * Escopo cross-business POR DESENHO: é limpeza de catálogo de plataforma, não leitura de dado
 * de negócio. Usa `DB::table` nas tabelas do Spatie, que não têm global scope de business; o
 * relatório mostra o `business_id` de cada papel afetado.
 *
 * Uso:
 *   php artisan arquivos:revogar-permissoes-superadmin --dry-run   # só lista
 *   php artisan arquivos:revogar-permissoes-superadmin             # aplica (prod = [W])
 */
class RevogarPermissoesSuperadminCommand extends Command
{
    /** @var list<string> */
    public const PERMISSOES = ['arquivos.restore', 'arquivos.governanca'];

    protected $signature = 'arquivos:revogar-permissoes-superadmin
        {--dry-run : Só lista o que seria removido, sem escrever}';

    protected $description = 'Remove arquivos.restore/arquivos.governanca de papéis e usuários — só superadmin (decisão [W] 2026-10-01).';

    public function handle(): int
    {
        $t = config('permission.table_names');
        $col = config('permission.column_names.model_morph_key', 'model_id');

        foreach (['permissions', 'role_has_permissions', 'model_has_permissions', 'roles'] as $chave) {
            if (! Schema::hasTable($t[$chave])) {
                $this->error("Tabela {$t[$chave]} ausente — nada medido. Rode as migrations do Spatie.");

                return 2;
            }
        }

        $dryRun = (bool) $this->option('dry-run');
        $perms = DB::table($t['permissions'])->whereIn('name', self::PERMISSOES)->pluck('name', 'id');

        $papeis = DB::table($t['role_has_permissions'].' as rp')
            ->join($t['roles'].' as r', 'r.id', '=', 'rp.role_id')
            ->whereIn('rp.permission_id', $perms->keys())
            ->orderBy('r.id')
            ->get(['rp.permission_id', 'r.id', 'r.name', Schema::hasColumn($t['roles'], 'business_id') ? 'r.business_id' : DB::raw('NULL as business_id')]);

        $usuarios = DB::table($t['model_has_permissions'])
            ->whereIn('permission_id', $perms->keys())
            ->orderBy($col)
            ->get(['permission_id', 'model_type', $col.' as model_id']);

        $this->info(($dryRun ? '[dry-run] ' : '').'Permissões encontradas: '.($perms->isEmpty() ? 'nenhuma' : $perms->values()->implode(', ')));

        if ($papeis->isNotEmpty()) {
            $this->table(['permissão', 'role_id', 'papel', 'business_id'], $papeis->map(fn ($p) => [
                $perms[$p->permission_id], $p->id, $p->name, $p->business_id ?? '—',
            ])->all());
        }
        if ($usuarios->isNotEmpty()) {
            $this->table(['permissão', 'model_type', 'model_id'], $usuarios->map(fn ($u) => [
                $perms[$u->permission_id], $u->model_type, $u->model_id,
            ])->all());
        }

        $this->line(sprintf(
            '%s %d vínculo(s) de papel · %d vínculo(s) direto(s) de usuário · %d permissão(ões) no catálogo.',
            $dryRun ? 'Seriam removidos:' : 'Removendo:',
            $papeis->count(),
            $usuarios->count(),
            $perms->count()
        ));

        if ($dryRun || $perms->isEmpty()) {
            return 0;
        }

        DB::transaction(function () use ($t, $perms) {
            DB::table($t['role_has_permissions'])->whereIn('permission_id', $perms->keys())->delete();
            DB::table($t['model_has_permissions'])->whereIn('permission_id', $perms->keys())->delete();
            DB::table($t['permissions'])->whereIn('id', $perms->keys())->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->info('Concluído. Cache de permissões limpo.');

        return 0;
    }
}
