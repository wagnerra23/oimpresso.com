<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services\Tributacao;

use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeContador;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cadastro do contador e papel "Contador" (playbook Fiscal thread 15c · D-CONTADOR caminho 2).
 *
 * O papel dá ao contador que QUER ter usuário o mesmo que o link (aceitar) mais configurar a
 * tributação e ler o cockpit fiscal e o SPED. Nada de venda, financeiro, cadastro de cliente ou
 * configuração da empresa: a lista abaixo é a lista inteira.
 */
class ContadorService
{
    /** As permissões do papel, e só elas. */
    public const PERMISSOES_PAPEL = [
        'nfe.tributacao.manage',
        'nfe.tributacao.aceitar',
        'fiscal.access',
        'fiscal.sped.export',
    ];

    public static function nomePapel(int $businessId): string
    {
        return 'Contador#' . $businessId;
    }

    public function doBusiness(int $businessId): ?NfeContador
    {
        if (! Schema::hasTable('nfe_contadores')) {
            return null;
        }

        return NfeContador::query()->where('business_id', $businessId)->first();
    }

    /** @param array{nome: string, email: string, crc?: string|null} $dados */
    public function salvar(int $businessId, array $dados, ?int $userId): NfeContador
    {
        $contador = NfeContador::query()->updateOrCreate(
            ['business_id' => $businessId],
            [
                'nome'           => trim($dados['nome']),
                'email'          => trim($dados['email']),
                'crc'            => isset($dados['crc']) && trim((string) $dados['crc']) !== '' ? trim((string) $dados['crc']) : null,
                'atualizado_por' => $userId,
            ],
        );
        $this->garantirPapel($businessId);

        return $contador;
    }

    /**
     * Cria o papel `Contador#{business}` com as permissões acima, se ainda não existir. Se já existe,
     * não mexe: o administrador pode ter ajustado o papel, e salvar o cadastro não desfaz isso.
     */
    public function garantirPapel(int $businessId): Role
    {
        $papel = Role::query()->firstOrCreate([
            'name' => self::nomePapel($businessId), 'business_id' => $businessId, 'guard_name' => 'web',
        ]);
        if ($papel->wasRecentlyCreated) {
            foreach (self::PERMISSOES_PAPEL as $p) {
                Permission::findOrCreate($p, 'web');
            }
            $papel->syncPermissions(self::PERMISSOES_PAPEL);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return $papel;
    }
}
