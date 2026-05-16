<?php

namespace Modules\Officeimpresso\Services;

use App\Business;
use App\Util\OtelHelper;
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
        return OtelHelper::spanBiz('officeimpresso.licenca.listar', function () use ($businessId) {
            return Licenca_Computador::where('business_id', $businessId)->get();
        }, ['module' => 'Officeimpresso']);
    }

    /**
     * Busca licenca por id dentro de um business (multi-tenant).
     */
    public function buscarParaEdit(int $id, int $businessId): Licenca_Computador
    {
        return OtelHelper::spanBiz('officeimpresso.licenca.buscar', function () use ($id, $businessId) {
            return Licenca_Computador::where('business_id', $businessId)->findOrFail($id);
        }, ['module' => 'Officeimpresso', 'licenca_id' => $id]);
    }

    /**
     * Cria nova licenca a partir de payload validado.
     */
    public function criar(array $dadosValidados): Licenca_Computador
    {
        return OtelHelper::spanBiz('officeimpresso.licenca.criar', function () use ($dadosValidados) {
            return Licenca_Computador::create($dadosValidados);
        }, ['module' => 'Officeimpresso']);
    }

    /**
     * Atualiza licenca por id.
     */
    public function atualizar(int $id, array $dadosValidados): ?Licenca_Computador
    {
        return OtelHelper::spanBiz('officeimpresso.licenca.atualizar', function () use ($id, $dadosValidados) {
            $licenca = Licenca_Computador::find($id);
            if (! $licenca) {
                return null;
            }
            $licenca->update($dadosValidados);
            return $licenca;
        }, ['module' => 'Officeimpresso', 'licenca_id' => $id]);
    }

    /**
     * Remove licenca por id.
     */
    public function remover(int $id): bool
    {
        return OtelHelper::spanBiz('officeimpresso.licenca.remover', function () use ($id) {
            $licenca = Licenca_Computador::find($id);
            if (! $licenca) {
                return false;
            }
            return (bool) $licenca->delete();
        }, ['module' => 'Officeimpresso', 'licenca_id' => $id]);
    }

    /**
     * Alterna bloqueio de uma licenca (revoke / restore).
     */
    public function alternarBloqueio(int $id): Licenca_Computador
    {
        return OtelHelper::spanBiz('officeimpresso.licenca.alternar_bloqueio', function () use ($id) {
            $licenca = Licenca_Computador::findOrFail($id);
            $licenca->bloqueado = ! $licenca->bloqueado;
            $licenca->save();
            return $licenca;
        }, ['module' => 'Officeimpresso', 'licenca_id' => $id]);
    }

    /**
     * Atualiza configuracao officeimpresso de uma empresa.
     */
    public function atualizarEmpresa(int $businessId, array $dados): Business
    {
        return OtelHelper::spanBiz('officeimpresso.empresa.atualizar', function () use ($businessId, $dados) {
            $empresa = Business::findOrFail($businessId);
            $empresa->caminho_banco_servidor = $dados['caminho_banco_servidor'] ?? $empresa->caminho_banco_servidor;
            $empresa->versao_obrigatoria = $dados['versao_obrigatoria'] ?? $empresa->versao_obrigatoria;
            $empresa->versao_disponivel = $dados['versao_disponivel'] ?? $empresa->versao_disponivel;
            $empresa->officeimpresso_numerodemaquinas = $dados['officeimpresso_numerodemaquinas']
                ?? $empresa->officeimpresso_numerodemaquinas;
            $empresa->save();
            return $empresa;
        }, ['module' => 'Officeimpresso']);
    }

    /**
     * Alterna bloqueio global da empresa pra desktop legacy.
     */
    public function alternarBloqueioEmpresa(int $businessId): Business
    {
        return OtelHelper::spanBiz('officeimpresso.empresa.alternar_bloqueio', function () use ($businessId) {
            $empresa = Business::findOrFail($businessId);
            $empresa->officeimpresso_bloqueado = ! $empresa->officeimpresso_bloqueado;
            $empresa->save();
            return $empresa;
        }, ['module' => 'Officeimpresso']);
    }

    /**
     * Lista empresas com officeimpresso ativo (superadmin view).
     */
    public function listarEmpresasComDesktop()
    {
        return OtelHelper::spanBiz('officeimpresso.empresa.listar_com_desktop', function () {
            return Business::where('is_officeimpresso', true)->get();
        }, ['module' => 'Officeimpresso']);
    }
}
