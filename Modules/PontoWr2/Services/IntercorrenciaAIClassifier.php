<?php

namespace Modules\PontoWr2\Services;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Classificador IA para descrições livres de intercorrências.
 *
 * Input: texto livre em PT-BR do colaborador/RH ("saí mais cedo pra consulta").
 * Output: estrutura JSON com { tipo, dia_todo, prioridade, impacta_apuracao,
 *         descontar_banco_horas, justificativa_formal, confianca, motivo }.
 *
 * - Modelo: gpt-4o-mini (barato e rápido pra classificação)
 * - JSON mode ligado (resposta estrita)
 * - Cache por hash SHA-256 do input (24h) — evita chamar API pro mesmo texto
 * - Mascara CPF/PIS/email antes de enviar (privacidade LGPD)
 * - Fallback: se API offline ou key inválida, devolve estrutura com erro
 *
 * Como usar no Controller:
 *   $result = app(IntercorrenciaAIClassifier::class)->classificar($descricao);
 *   if ($result['success']) { ... preenche form com $result['data'] ... }
 */
class IntercorrenciaAIClassifier
{
    /** Tipos válidos para Intercorrencia (sincronizar com IntercorrenciaRequest) */
    protected const TIPOS_VALIDOS = [
        'CONSULTA_MEDICA',
        'ATESTADO_MEDICO',
        'REUNIAO_EXTERNA',
        'VISITA_CLIENTE',
        'HORA_EXTRA_AUTORIZADA',
        'ESQUECIMENTO_MARCACAO',
        'PROBLEMA_EQUIPAMENTO',
        'OUTRO',
    ];

    /** Tipos que NORMALMENTE impactam apuração (médicos, esquecimento) */
    protected const TIPOS_IMPACTAM_APURACAO = [
        'CONSULTA_MEDICA', 'ATESTADO_MEDICO', 'ESQUECIMENTO_MARCACAO', 'PROBLEMA_EQUIPAMENTO',
    ];

    /**
     * @return array{
     *   success: bool,
     *   data?: array<string, mixed>,
     *   error?: string,
     *   cached?: bool
     * }
     */
    public function classificar(string $descricao): array
    {
        $descricao = trim($descricao);

        if (mb_strlen($descricao) < 10) {
            return ['success' => false, 'error' => 'Descrição muito curta (mínimo 10 caracteres).'];
        }

        if (mb_strlen($descricao) > 2000) {
            return ['success' => false, 'error' => 'Descrição muito longa (máximo 2000 caracteres).'];
        }

        if (!$this->aiHabilitada()) {
            return ['success' => false, 'error' => 'IA não configurada no servidor. Preencha manualmente.'];
        }

        // Cache por hash — economia de token + resposta instantânea em inputs repetidos
        $key = 'intercorrencia_ai:' . hash('sha256', mb_strtolower($descricao));
        $cached = Cache::get($key);
        if ($cached) {
            return array_merge($cached, ['cached' => true]);
        }

        // Privacidade: remove CPF/PIS/email/telefone do texto antes de enviar
        $sanitized = $this->mascararPII($descricao);

        try {
            if (!class_exists(\OpenAI\Laravel\Facades\OpenAI::class)) {
                return ['success' => false, 'error' => 'Provider de IA não instalado neste ambiente.'];
            }

            $response = \OpenAI\Laravel\Facades\OpenAI::chat()->create([
                'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.2,
                'max_tokens' => 500,
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => "Descrição livre do colaborador:\n\n{$sanitized}"],
                ],
            ]);

            $raw = $response->choices[0]->message->content ?? '';
            $parsed = json_decode($raw, true);
            if (!is_array($parsed)) {
                return ['success' => false, 'error' => 'IA devolveu resposta em formato inesperado.'];
            }

            $normalized = $this->normalizar($parsed);

            $result = ['success' => true, 'data' => $normalized];

            // Cache 24h
            Cache::put($key, $result, now()->addHours(24));

            return $result;
        } catch (Throwable $e) {
            report($e);
            return [
                'success' => false,
                'error'   => 'Falha na chamada da IA: ' . $e->getMessage(),
            ];
        }
    }

    public function aiHabilitada(): bool
    {
        // `env()` em runtime não reflete bem alterações via `putenv()` em testes.
        $aiEnabled = filter_var((string) getenv('AI_ENABLED'), FILTER_VALIDATE_BOOL);
        $aiClassificacao = filter_var((string) getenv('AI_CLASSIFICACAO_INTERCORRENCIA'), FILTER_VALIDATE_BOOL);
        $apiKey = (string) getenv('OPENAI_API_KEY');

        return (bool) $aiEnabled && (bool) $aiClassificacao && $apiKey !== '';
    }

    protected function systemPrompt(): string
    {
        $tipos = implode(', ', self::TIPOS_VALIDOS);

        return <<<PROMPT
Você é um assistente de RH especializado em ponto eletrônico CLT (Portaria MTP 671/2021).
Sua função é classificar descrições livres de **intercorrências de ponto** em categorias estruturadas.

Responda SEMPRE em JSON válido com esta estrutura exata:
{
  "tipo": "<um dos tipos válidos>",
  "dia_todo": true|false,
  "prioridade": "NORMAL"|"URGENTE",
  "impacta_apuracao": true|false,
  "descontar_banco_horas": true|false,
  "justificativa_formal": "<texto formal em PT-BR para o RH, 100-300 chars>",
  "confianca": 0.0-1.0,
  "motivo": "<breve explicação da classificação, até 100 chars>"
}

Tipos válidos:
- CONSULTA_MEDICA: consulta, exame, dentista
- ATESTADO_MEDICO: atestado, afastamento médico
- REUNIAO_EXTERNA: reunião fora da empresa, visita a cliente na agenda
- VISITA_CLIENTE: visita técnica, atendimento no cliente
- HORA_EXTRA_AUTORIZADA: hora extra previamente autorizada
- ESQUECIMENTO_MARCACAO: esqueci de bater ponto
- PROBLEMA_EQUIPAMENTO: REP quebrado, biometria não leu
- OUTRO: casos não cobertos acima

Regras:
- `dia_todo=true` quando o texto indica ausência de manhã ou tarde inteira ou dia inteiro
- `prioridade=URGENTE` apenas se o texto sugerir gravidade (hospital, emergência)
- `impacta_apuracao=true` para atestado/consulta/esquecimento — afeta cálculo de jornada
- `descontar_banco_horas=false` por padrão. `true` apenas se o colaborador pede explicitamente
- `justificativa_formal`: reescrita em tom profissional de RH, PT-BR, sem emojis ou gírias
- `confianca`: sua certeza de 0 a 1 sobre a classificação
- Se o texto for ambíguo, use tipo=OUTRO com confianca<0.5
PROMPT;
    }

    /**
     * Normaliza/valida a saída da IA garantindo tipos válidos e defaults seguros.
     */
    protected function normalizar(array $raw): array
    {
        $tipo = $raw['tipo'] ?? 'OUTRO';
        if (!in_array($tipo, self::TIPOS_VALIDOS, true)) {
            $tipo = 'OUTRO';
        }

        $impacta = $raw['impacta_apuracao'] ?? null;
        if ($impacta === null) {
            $impacta = in_array($tipo, self::TIPOS_IMPACTAM_APURACAO, true);
        }

        $prioridade = strtoupper($raw['prioridade'] ?? 'NORMAL');
        if (!in_array($prioridade, ['NORMAL', 'URGENTE'], true)) {
            $prioridade = 'NORMAL';
        }

        return [
            'tipo'                   => $tipo,
            'dia_todo'               => (bool) ($raw['dia_todo'] ?? false),
            'prioridade'             => $prioridade,
            'impacta_apuracao'       => (bool) $impacta,
            'descontar_banco_horas'  => (bool) ($raw['descontar_banco_horas'] ?? false),
            'justificativa_formal'   => mb_substr((string) ($raw['justificativa_formal'] ?? ''), 0, 2000),
            'confianca'              => max(0, min(1, (float) ($raw['confianca'] ?? 0.5))),
            'motivo'                 => mb_substr((string) ($raw['motivo'] ?? ''), 0, 200),
        ];
    }

    /**
     * Remove/mascara PII antes de enviar à OpenAI.
     * Proteção LGPD: o texto do colaborador pode ter nome próprio, CPF, etc.
     */
    protected function mascararPII(string $texto): string
    {
        // CPF: 000.000.000-00 ou 00000000000
        $texto = preg_replace('/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/', '[CPF]', $texto);
        // PIS: 000.00000.00-0 ou 00000000000
        $texto = preg_replace('/\b\d{3}\.?\d{5}\.?\d{2}-?\d\b/', '[PIS]', $texto);
        // Email
        $texto = preg_replace('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', '[EMAIL]', $texto);
        // Telefone com DDD
        $texto = preg_replace('/\(?\d{2}\)?\s*9?\d{4}-?\d{4}/', '[TELEFONE]', $texto);

        return $texto;
    }
}
