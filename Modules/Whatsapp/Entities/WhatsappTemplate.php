<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WhatsappTemplate — espelho local dos HSM Meta + templates locais Z-API/Baileys.
 *
 * Multi-tenant Tier 0 (ADR 0093).
 *
 * Comportamento por driver:
 * - meta_cloud: status reflete aprovação Meta (PENDING/APPROVED/REJECTED/PAUSED/DISABLED)
 * - zapi/baileys: status sempre LOCAL (driver expande placeholders e manda freeform)
 *
 * @property int $id
 * @property int $business_id
 * @property string $provider
 * @property ?string $meta_template_id
 * @property string $name
 * @property string $language
 * @property string $category
 * @property string $status
 * @property array $components
 * @property ?string $rejection_reason
 * @property ?\Carbon\CarbonImmutable $last_synced_at
 */
class WhatsappTemplate extends Model
{
    use HasBusinessScope;

    protected $table = 'whatsapp_templates';

    protected $guarded = ['id'];

    protected $casts = [
        'components' => 'array',
        'last_synced_at' => 'immutable_datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(\App\Business::class, 'business_id');
    }

    /**
     * Template está disponível pra envio?
     *
     * - LOCAL (Z-API/Baileys): sempre disponível
     * - APPROVED (Meta Cloud): disponível
     * - PENDING/REJECTED/PAUSED/DISABLED (Meta Cloud): bloqueado
     */
    public function isReadyToSend(): bool
    {
        return in_array($this->status, ['LOCAL', 'APPROVED'], true);
    }

    /**
     * Expande placeholders {{1}}, {{2}}, {{nome}} no body do template.
     *
     * Usado por ZapiDriver/BaileysDriver no sendTemplate (que mandam como freeform).
     *
     * @param  array<string, string>  $params
     */
    public function expandBody(array $params): string
    {
        $body = $this->extractBodyFromComponents();

        $i = 1;
        foreach ($params as $key => $value) {
            // Suporta tanto {{1}} (Meta-style) quanto {{nome}} (named)
            $body = str_replace('{{' . $i . '}}', $value, $body);
            $body = str_replace('{{' . $key . '}}', $value, $body);
            $i++;
        }

        return $body;
    }

    private function extractBodyFromComponents(): string
    {
        foreach ($this->components ?? [] as $component) {
            if (($component['type'] ?? null) === 'BODY') {
                return $component['text'] ?? '';
            }
        }

        return '';
    }
}
