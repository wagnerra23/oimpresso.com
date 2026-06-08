<?php

declare(strict_types=1);

namespace Modules\KB\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\KB\Entities\Concerns\BelongsToBusinessTrait;

/**
 * KbPath — trilha de aprendizado por persona.
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §6
 *
 * Cloud sync de progresso (kb_path_user_progress) fora do escopo V1 —
 * progresso fica em localStorage `oimpresso.kb.paths` por persona.
 *
 * @property int    $id
 * @property int    $business_id
 * @property string $slug
 * @property string $title
 * @property string|null $audience
 * @property string|null $description
 * @property int    $hue
 * @property string $status        draft|published|archived
 * @property int|null $author_user_id
 */
class KbPath extends Model
{
    use BelongsToBusinessTrait, SoftDeletes;

    protected $table = 'kb_paths';

    protected $fillable = [
        'business_id', 'slug', 'title', 'audience',
        'description', 'hue', 'status', 'author_user_id',
    ];

    protected $casts = [
        'business_id'    => 'integer',
        'hue'            => 'integer',
        'author_user_id' => 'integer',
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(KbPathStep::class, 'path_id')->orderBy('position');
    }
}
