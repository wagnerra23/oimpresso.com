<?php

namespace Modules\Essentials\Entities;

use App\Concerns\BelongsToBusinessViaParent;
use Illuminate\Database\Eloquent\Model;

class DocumentShare extends Model
{
    use BelongsToBusinessViaParent; // ADR 0093 — multi-tenant via Document->business_id (Wave 18 D1)

    /**
     * Resolve business_id via document parent (share herda tenancy do doc).
     */
    protected string $businessParentRelation = 'document';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'essentials_document_shares';

    /**
     * Documento compartilhado (parent pra tenancy).
     */
    public function document()
    {
        return $this->belongsTo(\Modules\Essentials\Entities\Document::class, 'document_id');
    }

    public static function documentShareNotificationData($data)
    {
        return [
            'msg' => __('essentials::lang.document_share_notification', ['document_name' => $data['document_name'], 'shared_by' => $data['shared_by_name']]),
            'title' => __('essentials::lang.document_shared'),
            'link' => $data['document_type'] != 'memos' ? action([\Modules\Essentials\Http\Controllers\DocumentController::class, 'index']) :
            action([\Modules\Essentials\Http\Controllers\DocumentController::class, 'index']).'?type=memos',
            'icon' => $data['document_type'] != 'memos' ? 'fas fa-file bg-green' : 'fas fa-envelope-open bg-green',
        ];
    }
}
