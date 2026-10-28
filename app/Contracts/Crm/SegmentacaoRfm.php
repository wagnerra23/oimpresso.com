<?php

declare(strict_types=1);

namespace App\Contracts\Crm;

/**
 * Segmentação RFM dos clientes (recência, frequência e valor), vista pelo núcleo e pelos módulos.
 *
 * Consumidores previstos: o público da campanha de WhatsApp (US-WA-323) e a régua de
 * relacionamento (US-WA-326). Quem implementa é `Modules\Crm\Services\Fidelidade\SegmentacaoRfmDoCrm`,
 * ligado pelo CrmServiceProvider; o Crm é dono de `crm_rfm_clientes`. Sem o módulo, a interface não
 * está ligada: quem consome confere `app()->bound(SegmentacaoRfm::class)` e segue sem filtro.
 *
 * O núcleo (`app/`) não importa de módulo (tests/Feature/Architecture/DependencyDirectionTest).
 * Multi-tenant Tier 0 (ADR 0093): `business_id` é argumento explícito.
 */
interface SegmentacaoRfm
{
    /** @return array<string, string> chave do segmento => rótulo em PT */
    public function segmentos(): array;

    /** @return list<int> ids de `contacts` da empresa no segmento, no último cálculo */
    public function contatosDoSegmento(int $businessId, string $segmento): array;
}
