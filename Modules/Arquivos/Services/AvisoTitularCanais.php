<?php

declare(strict_types=1);

namespace Modules\Arquivos\Services;

use Illuminate\Support\Facades\DB;

/**
 * AvisoTitularCanais — configuração POR NEGÓCIO dos canais do aviso ao titular.
 *
 * Decisão: [W] 2026-10-01, textual *"e-mail e whatsapp. pode ter configuração"*
 * (`playbook/_DECISOES-W-2026-10-01b.md`), registrada na ADR 0422 (emenda da 0421).
 *
 * Mora em `business.common_settings['arquivos_aviso_titular']` — o mesmo JSON que o núcleo
 * já usa pra liga/desliga por negócio (`enable_purchase_order`, `enable_lot_number`…). Nada de
 * tabela nova.
 *
 * **Default = os dois DESLIGADOS.** Nenhum titular recebe nada até o negócio ligar o canal:
 * ligar é ato consciente, não efeito colateral de deploy.
 *
 * Multi-tenant Tier 0 (ADR 0093): `business_id` é argumento explícito; lê e grava só a linha
 * daquele negócio.
 */
class AvisoTitularCanais
{
    public const CHAVE = 'arquivos_aviso_titular';

    public const CANAIS = ['email', 'whatsapp'];

    /**
     * @return array{email: bool, whatsapp: bool}
     */
    public function ativos(int $businessId): array
    {
        $config = $this->ler($businessId)[self::CHAVE] ?? [];

        return [
            'email'    => (bool) ($config['email'] ?? false),
            'whatsapp' => (bool) ($config['whatsapp'] ?? false),
        ];
    }

    public function algumAtivo(int $businessId): bool
    {
        return in_array(true, $this->ativos($businessId), true);
    }

    /**
     * Liga/desliga canais. `null` = não mexe naquele canal. Preserva o resto do JSON.
     *
     * @return array{email: bool, whatsapp: bool} o estado depois de gravar
     */
    public function definir(int $businessId, ?bool $email, ?bool $whatsapp): array
    {
        $settings = $this->ler($businessId);
        $atual = $this->ativos($businessId);

        $settings[self::CHAVE] = [
            'email'    => $email ?? $atual['email'],
            'whatsapp' => $whatsapp ?? $atual['whatsapp'],
        ];

        DB::table('business')
            ->where('id', $businessId)
            ->update(['common_settings' => json_encode($settings)]);

        return $this->ativos($businessId);
    }

    /**
     * @return array<string, mixed>
     */
    private function ler(int $businessId): array
    {
        $bruto = DB::table('business')->where('id', $businessId)->value('common_settings');
        $settings = is_string($bruto) ? json_decode($bruto, true) : null;

        return is_array($settings) ? $settings : [];
    }
}
