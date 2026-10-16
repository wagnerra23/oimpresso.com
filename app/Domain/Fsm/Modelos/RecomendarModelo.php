<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Modelos;

use Illuminate\Support\Facades\Log;
use App\Domain\Fsm\Ai\RecomendarModeloFluxoAgent;
use Throwable;

/**
 * Assistente da tela Fluxos e regras: descrição livre → modelo sugerido, motivo e casos a
 * ligar/desligar. Usa a IA do ERP (RecomendarModeloFluxoAgent, laravel/ai); sem IA — provedor
 * fora, erro ou resposta fora do catálogo — cai na regra de encaixe (CatalogoModelos::recomendar) e diz isso em `origem = regra`, como o protótipo.
 *
 * Só sugere: não grava nada. Aplicar é o POST /fluxos/modelo, sob fluxos.configurar.
 */
final class RecomendarModelo
{
    public const MOTIVO_REGRA = 'Escolhido só pelo porte e pela atividade — a IA não respondeu.';

    /**
     * @return array{modelo:string, porque:string, ligar:list<string>, desligar:list<string>, porte:string, atividade:string, origem:string}
     */
    public function recomendar(string $texto, string $porte, string $atividade, int $businessId): array
    {
        $regra = [
            'modelo' => CatalogoModelos::recomendar($porte, $atividade)[0]['id'],
            'porque' => self::MOTIVO_REGRA,
            'ligar' => [],
            'desligar' => [],
            'porte' => $porte,
            'atividade' => $atividade,
            'origem' => 'regra',
        ];

        if (! class_exists(RecomendarModeloFluxoAgent::class)) {
            return $regra;
        }

        try {
            $agent = new RecomendarModeloFluxoAgent([
                'texto' => mb_substr(trim($texto), 0, 2000),
                'porte' => $porte,
                'atividade' => $atividade,
                'modelos' => CatalogoModelos::modelos(),
                'casos' => CatalogoModelos::CASOS,
                'atividades' => CatalogoModelos::ATIVIDADES,
                'portes' => array_keys(CatalogoModelos::PORTES),
            ]);
            $r = $agent->prompt($agent->montarPrompt());
        } catch (Throwable $e) {
            Log::warning('fluxos.assistente.indisponivel', ['business_id' => $businessId, 'erro' => get_class($e)]);

            return $regra;
        }

        $modelo = (string) ($r['modelo'] ?? '');
        if (CatalogoModelos::modelo($modelo) === null) {
            return $regra;
        }
        $casos = fn ($lista) => array_values(array_filter(
            is_array($lista) ? $lista : [],
            fn ($id) => is_string($id) && isset(CatalogoModelos::CASOS[$id]),
        ));

        return [
            'modelo' => $modelo,
            'porque' => mb_substr((string) ($r['porque'] ?? ''), 0, 500),
            'ligar' => $casos($r['ligar'] ?? []),
            'desligar' => $casos($r['desligar'] ?? []),
            'porte' => isset(CatalogoModelos::PORTES[$r['porte'] ?? '']) ? (string) $r['porte'] : $porte,
            'atividade' => isset(CatalogoModelos::ATIVIDADES[$r['atividade'] ?? '']) ? (string) $r['atividade'] : $atividade,
            'origem' => 'ia',
        ];
    }
}
