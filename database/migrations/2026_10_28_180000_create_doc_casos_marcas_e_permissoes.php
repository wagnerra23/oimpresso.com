<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Página "Casos de uso por tela" dentro do sistema (/documentacao/casos-por-tela).
 *
 * TABELA `doc_casos_marcas` — o que antes gravava no banco do artefato (`conferencia/c<n>` e
 * `decisoes/D-NN`). Uma linha por marca: `tipo` = conferencia (chave P-NNN) ou decisao (chave
 * D-NN). Os pedidos e as decisões em si continuam nos casos.md; aqui fica só quem conferiu ou
 * respondeu, e quando.
 *
 * SEM business_id, DE PROPÓSITO (ADR 0093): é documentação da PLATAFORMA — o backlog dos
 * casos.md do repositório —, não dado de empresa. Mesmo caso das tasks MCP (ADR 0070). Nenhuma
 * empresa-cliente lê ou escreve aqui; a rota só abre para a equipe (permissões abaixo).
 *
 * MIGRAÇÃO DAS MARCAS JÁ FEITAS: medido em 2026-10-28 no artefato
 * claude.ai/artifact/PbYHY5EqMLjWoRgJFWZWR4 — as coleções `conferencia` e `decisoes` estavam
 * VAZIAS. Não há o que importar.
 *
 * PERMISSÕES — checadas com `checkPermissionTo` (Spatie direto), nunca com `can()`: o
 * `Gate::before` dá tudo ao dono `Admin#{biz}` de qualquer empresa, e esta página não é dele.
 *   documentacao.casos_tela.ver       — abrir a página
 *   documentacao.casos_tela.conferir  — marcar "Conferido contra as regras"
 *   documentacao.casos_tela.decidir   — responder as decisões [W]
 * Quem está em `administrator_usernames` passa nas três (ver CasosPorTelaController).
 * Backfill: `ver` e `conferir` copiam os vínculos de `jana.superadmin` (a equipe que já lê
 * dado de plataforma; em produção, o papel Operacional#1 com Maiara e Felipe). `decidir` não é
 * dada a ninguém: responde o [W], pelo administrator_usernames.
 *
 * IDEMPOTENTE: hasTable + firstOrCreate + insertOrIgnore.
 */
return new class extends Migration
{
    private const ORIGEM = 'jana.superadmin';

    private const COM_BACKFILL = ['documentacao.casos_tela.ver', 'documentacao.casos_tela.conferir'];

    private const SEM_BACKFILL = ['documentacao.casos_tela.decidir'];

    public function up(): void
    {
        if (! Schema::hasTable('doc_casos_marcas')) {
            Schema::create('doc_casos_marcas', function (Blueprint $t) {
                $t->id();
                $t->string('tipo', 20);
                $t->string('chave', 20);
                $t->string('opcao', 120)->nullable();
                $t->text('nota')->nullable();
                $t->unsignedInteger('user_id');
                $t->timestamps();
                $t->unique(['tipo', 'chave'], 'doc_casos_marcas_tipo_chave_unique');
                $t->foreign('user_id')->references('id')->on('users');
            });
        }

        if (! Schema::hasTable('permissions')) {
            return;
        }

        $origemId = DB::table('permissions')->where('name', self::ORIGEM)->where('guard_name', 'web')->value('id');

        foreach ([...self::COM_BACKFILL, ...self::SEM_BACKFILL] as $nova) {
            $id = (int) Permission::firstOrCreate(['name' => $nova, 'guard_name' => 'web'])->id;
            if ($origemId === null || ! in_array($nova, self::COM_BACKFILL, true)) {
                continue;
            }
            DB::table('role_has_permissions')->insertOrIgnore(
                DB::table('role_has_permissions')->where('permission_id', $origemId)->pluck('role_id')
                    ->map(fn ($roleId) => ['permission_id' => $id, 'role_id' => (int) $roleId])->values()->all()
            );
            DB::table('model_has_permissions')->insertOrIgnore(
                DB::table('model_has_permissions')->where('permission_id', $origemId)->get(['model_type', 'model_id'])
                    ->map(fn ($v) => ['permission_id' => $id, 'model_type' => $v->model_type, 'model_id' => $v->model_id])->values()->all()
            );
        }

        $this->limparCache();
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_casos_marcas');
        Permission::whereIn('name', [...self::COM_BACKFILL, ...self::SEM_BACKFILL])->where('guard_name', 'web')->delete();
        $this->limparCache();
    }

    private function limparCache(): void
    {
        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // ambiente sem cache configurado
        }
    }
};
