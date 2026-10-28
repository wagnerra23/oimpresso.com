<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Cobrança enviada à maquininha a partir do caixa (US-SELL-075).
 *
 * Não guarda número de cartão nem nome do portador: só o que a adquirente
 * devolve para o comprovante (bandeira, NSU, autorização).
 *
 * Multi-tenant Tier 0 — global scope via HasBusinessScope (ADR 0093).
 *
 * @property int $id
 * @property int $business_id
 * @property int $valor_centavos
 * @property string $status
 * @property string $modalidade
 * @property int $parcelas
 * @property int|null $transaction_id
 * @property string|null $bandeira
 * @property string|null $nsu
 * @property string|null $autorizacao
 * @property string|null $externo_id
 * @property string|null $mensagem
 * @property array|null $payload
 */
class TefTransacao extends Model
{
    use HasBusinessScope;

    public const PENDENTE = 'pendente';

    public const APROVADA = 'aprovada';

    public const NEGADA = 'negada';

    public const ERRO = 'erro';

    protected $table = 'tef_transacoes';

    protected $fillable = [
        'business_id',
        'location_id',
        'transaction_id',
        'user_id',
        'driver',
        'terminal_id',
        'valor_centavos',
        'modalidade',
        'parcelas',
        'status',
        'bandeira',
        'nsu',
        'autorizacao',
        'externo_id',
        'mensagem',
        'idempotency_key',
        'payload',
    ];

    protected $casts = [
        'valor_centavos' => 'integer',
        'parcelas'       => 'integer',
        'payload'        => 'array',
    ];

    public function finalizada(): bool
    {
        return $this->status !== self::PENDENTE;
    }

    /** O que a tela precisa para preencher a linha de pagamento. */
    public function paraCaixa(): array
    {
        return [
            'id'          => $this->id,
            'status'      => $this->status,
            'valor'       => $this->valor_centavos / 100,
            'modalidade'  => $this->modalidade,
            'parcelas'    => $this->parcelas,
            'bandeira'    => $this->bandeira,
            'nsu'         => $this->nsu,
            'autorizacao' => $this->autorizacao,
            'mensagem'    => $this->mensagem,
        ];
    }
}
