<?php

declare(strict_types=1);

namespace Modules\Fisio\Services\Pesquisa;

use InvalidArgumentException;
use Modules\Fisio\Ai\Agents\AssistentePesquisaAgent;
use Modules\Fisio\Entities\Study;
use Modules\Fisio\Entities\StudyAiSuggestion;
use Modules\Fisio\Services\ConfiguracaoClinica;

/**
 * Assistente de IA da pesquisadora (etapa 07 P-08; decisão [W] 2026-11-11).
 *
 * - Desligado por padrão: a empresa liga em `fisio_settings` (`pesquisa.assistente_ia`).
 * - Entrada: os CSVs da exportação (sem identificação), nunca a ficha do paciente.
 * - Saída: sugestões `pendente`; a pesquisadora aceita ou descarta, e quem decidiu fica gravado.
 * - Só a área do estudo lê a tabela. Nada vai para o paciente, o prontuário ou a tela clínica.
 */
class AssistentePesquisa
{
    public const CHAVE_CONFIG = 'pesquisa.assistente_ia';

    public function __construct(
        private readonly ConfiguracaoClinica $config,
        private readonly ExportacaoEstudo $exportacao,
    ) {}

    public function ligado(int $businessId): bool
    {
        return (bool) $this->config->ler($businessId, self::CHAVE_CONFIG, false);
    }

    public function ligar(int $businessId, bool $ligar, int $autor): void
    {
        $this->config->gravar($businessId, self::CHAVE_CONFIG, $ligar, $autor);
    }

    /** @return list<StudyAiSuggestion> sugestões novas, todas pendentes */
    public function gerar(Study $estudo, int $autor): array
    {
        if (! $this->ligado((int) $estudo->business_id)) {
            throw new InvalidArgumentException('assistente desligado nesta empresa');
        }
        $agente = new AssistentePesquisaAgent($this->dadosParaIa($estudo), (string) $estudo->title);
        $resposta = $agente->prompt($agente->montarPrompt());
        $itens = is_array($resposta['sugestoes'] ?? null) ? $resposta['sugestoes'] : [];

        $out = [];
        foreach ($itens as $s) {
            $tipo = (string) ($s['tipo'] ?? '');
            $texto = trim((string) ($s['texto'] ?? ''));
            if (! in_array($tipo, StudyAiSuggestion::TIPOS, true) || $texto === '') {
                continue;
            }
            $out[] = StudyAiSuggestion::withoutGlobalScopes()->create([ // SUPERADMIN: empresa do estudo, explícita.
                'business_id' => (int) $estudo->business_id,
                'study_id' => (int) $estudo->id,
                'kind' => $tipo,
                'text' => mb_substr($texto, 0, 2000),
                'status' => StudyAiSuggestion::PENDENTE,
                'requested_by' => $autor,
                'model' => $agente->model(),
            ]);
        }

        return $out;
    }

    /**
     * O que vai para o modelo: os mesmos CSVs da exportação para a universidade (sem nome, CPF,
     * telefone nem data), cortados em `max_linhas`. A ficha do paciente nunca entra.
     *
     * @return array{momentos:list<list<string>>,diario:list<list<string>>}
     */
    public function dadosParaIa(Study $estudo): array
    {
        $max = (int) config('fisio.pesquisa.assistente.max_linhas', 4000);

        return [
            'momentos' => array_slice($this->exportacao->linhas($estudo, 'momentos'), 0, $max),
            'diario' => array_slice($this->exportacao->linhas($estudo, 'diario'), 0, $max),
        ];
    }

    public function decidir(StudyAiSuggestion $s, bool $aceitar, int $autor): StudyAiSuggestion
    {
        if ($s->status !== StudyAiSuggestion::PENDENTE) {
            throw new InvalidArgumentException('sugestão já decidida');
        }
        $s->forceFill([
            'status' => $aceitar ? StudyAiSuggestion::ACEITA : StudyAiSuggestion::DESCARTADA,
            'decided_by' => $autor,
            'decided_at' => now(),
        ])->save();

        return $s;
    }

    /** @return list<array<string, mixed>> */
    public function doEstudo(Study $estudo): array
    {
        return StudyAiSuggestion::withoutGlobalScopes() // SUPERADMIN: empresa do estudo, explícita.
            ->where('business_id', $estudo->business_id)->where('study_id', $estudo->id)
            ->orderByDesc('id')->limit(100)->get()
            ->map(fn (StudyAiSuggestion $s) => [
                'id' => $s->id, 'kind' => $s->kind, 'text' => $s->text, 'status' => $s->status,
                'decided_by' => $s->decided_by, 'decided_at' => $s->decided_at?->toIso8601String(),
            ])->values()->all();
    }
}
