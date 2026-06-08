<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Tag — classificador de conversa (US-WA-063).
 *
 * Wagner 2026-05-11: "Opção de inserir a conversa em um grupo/tag classificador."
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — global scope `business_id`.
 * Tags são por-business: "Vendas" do biz=1 ≠ "Vendas" do biz=99, mesmo slug.
 *
 * @property int $id
 * @property int $business_id
 * @property string $slug          imutável, usado em seeds reseed
 * @property string $label
 * @property string $color         Tailwind palette key
 * @property int $sort_order
 */
class Tag extends Model
{
    use HasBusinessScope;

    protected $table = 'whatsapp_tags';

    public const COLORS = [
        'blue', 'green', 'amber', 'red', 'slate', 'purple', 'emerald', 'rose', 'cyan', 'orange',
    ];

    protected $fillable = [
        'business_id', 'slug', 'label', 'color', 'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(
            Conversation::class,
            'whatsapp_conversation_tags',
            'tag_id',
            'conversation_id',
        )->withTimestamps();
    }
}
