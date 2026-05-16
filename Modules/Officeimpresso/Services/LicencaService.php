<?php

namespace Modules\Officeimpresso\Services;

use App\Business;
use Modules\Officeimpresso\Entities\Licenca_Computador;

/**
 * Service de licenca de computador desktop legacy.
 *
 * Extraido de LicencaComputadorController (Wave 16 governance D4 Architecture).
 * Encapsula regras de bloqueio/desbloqueio + update de empresa (campos
 * caminho_banco_servidor / versao_obrigatoria / versao_disponivel /
 * officeimpresso_numerodemaquinas / officeimpresso_bloqueado).
 *
 * Multi-tenant Tier 0 (ADR 0093): cada metodo recebe business_id explicito
 * pra suportar callers de jobs assincronos (session() nao funciona em fila).
 */
class LicencaService
{
    /**
     * Lista licencas de uma empresa.
     */
    public function listarPorEmpresa(int $businessId)
    {
        return Licenca_Computador::where('business_id', $businessId)->get();
    }

    /**
     * Busca licenca por id dentro de um business (multi-tenant).
     */
    public function buscarParaEdit(int $id, int $businessId): Licenca_Computador
    {
        return Licenca_Computador::where('business_id', $businessId)->findOrFail($id);
    }

    /**
     * Cria nova licenca a partir de payload validado.
     */
    public function criar(array $dadosValidados): Licenca_Computador
    {
        return Licenca_Computador::create($dadosValidados);
    }

    /**
     * Atualiza licenca por id.
     */
    public function atualizar(int $id, array $dadosValidados): ?Licenca_Computador
    {
        $licenca = Licenca_Computador::find($id);
        if (! $licenca) {
            return null;
        }
        $licenca->update($dadosValidados);
        return $licenca;
    }

    /**
     * Remove licenca por id.
     */
    public function remover(int $id): bool
    {
        $licenca = Licenca_Computador::find($id);
        if (! $licenca) {
            return false;
        }
        return (bool) $licenca->delete();
    }

    /**
     * Alterna bloqueio de uma licenca (revoke / restore).
     */
    public function alternarBloqueio(int $id): Licenca_Computador
    {
        $licenca = Licenca_Computador::findOrFail($id);
        $licenca->bloqueado = ! $licenca->bloqueado;
        $licenca->save();
        return $licenca;
    }

    /**
     * Atualiza configuracao officeimpresso de uma empresa.
     */
    public function atualizarEmpresa(int $businessId, array $dados): Business
    {
        $empresa = Business::findOrFail($businessId);
        $empresa->caminho_banco_servidor = $dados['caminho_banco_servidor'] ?? $empresa->caminho_banco_servidor;
        $empresa->versao_obrigatoria = $dados['versao_obrigatoria'] ?? $empresa->versao_obrigatoria;
        $empresa->versao_disponivel = $dados['versao_disponivel'] ?? $empresa->versao_disponivel;
        $empresa->officeimpresso_numerodemaquinas = $dados['officeimpresso_numerodemaquinas']
            ?? $empresa->officeimpresso_numerodemaquinas;
        $empresa->save();
        return $empresa;
    }

    /**
     * Alterna bloqueio global da empresa pra desktop legacy.
     */
    public function alternarBloqueioEmpresa(int $businessId): Business
    {
        $empresa = Business::findOrFail($businessId);
        $empresa->officeimpresso_bloqueado = ! $empresa->officeimpresso_bloqueado;
        $empresa->save();
        return $empresa;
    }

    /**
     * Lista empresas com officeimpresso ativo (superadmin view).
     */
    public function listarEmpresasComDesktop()
    {
        return Business::where('is_officeimpresso', true)->get();
    }
}
