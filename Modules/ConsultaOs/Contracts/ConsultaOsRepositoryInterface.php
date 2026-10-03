<?php

declare(strict_types=1);

namespace Modules\ConsultaOs\Contracts;

/**
 * ConsultaOsRepositoryInterface — fonte de dados do portal público de OS.
 *
 * US-CONSULTA-001 (2026-10-02): a única implementação é RepairConsultaOsRepository,
 * que lê as folhas de OS reais do Modules/Repair (repair_job_sheets). O mock de 4 OS
 * fixas foi removido.
 *
 * Contrato: o repositório devolve SÓ os campos públicos, já montados campo a campo
 * (nunca `repair_job_sheets.*`). O critério de busca chega já validado pelo
 * ConsultaPublicaRequest, mas o repositório revalida (defesa em profundidade): com
 * tipo fora da lista ou número vazio ele devolve [] sem consultar o banco.
 *
 * @see Modules\ConsultaOs\Repositories\RepairConsultaOsRepository
 */
interface ConsultaOsRepositoryInterface
{
    /** Tipos de busca aceitos — mesma lista do portal do Repair (#8527). */
    public const TIPOS = ['job_sheet_no', 'invoice_no', 'mobile_num'];

    /** Teto de OS por resposta (anti-varredura por celular compartilhado). */
    public const LIMITE = 20;

    /**
     * Busca OS pelo critério do cliente.
     *
     * @param  string       $tipo    job_sheet_no | invoice_no | mobile_num
     * @param  string       $numero  nº da OS, nº da venda ou celular (não vazio)
     * @param  string|null  $serie   nº de série do aparelho (filtro opcional, só estreita)
     * @return list<array<string, mixed>> OS públicas; [] quando nada casa ou o critério é inválido
     */
    public function buscar(string $tipo, string $numero, ?string $serie = null): array;
}
