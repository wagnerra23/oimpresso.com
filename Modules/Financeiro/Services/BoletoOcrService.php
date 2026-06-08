<?php

namespace Modules\Financeiro\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Financeiro\Models\AiUsageLog;

/**
 * Serviço OCR de boleto bancário BR — Onda 23 (2026-05-20) US-FIN-029.
 *
 * KILLER feature vs Conta Azul: Eliana cola foto/PDF do boleto recebido →
 * sistema extrai linha digitável + valor + vencimento + beneficiário automaticamente.
 *
 * Pipeline:
 *   1. Calcula SHA-256 do arquivo (idempotência — não re-cobra mesmo boleto)
 *   2. Tenta OpenAI Vision API gpt-4o (~$0.01 per imagem)
 *   3. Fallback AWS Textract se OpenAI quota exceeded / timeout (~$0.015 per page)
 *   4. Valida linha digitável via módulo 11 BR — rejeita lixo OCR
 *   5. Grava AiUsageLog com cost_usd literal
 *
 * Multi-tenant Tier 0: business_id sempre vem do caller (Controller).
 * LGPD: arquivo NÃO é persistido (só hash). CPF/CNPJ do pagador é redacted em log.
 */
class BoletoOcrService
{
    private const FEATURE = 'financeiro.ocr_boleto';
    private const PROMPT = <<<'PROMPT'
Extraia do boleto bancário brasileiro os seguintes campos. Responda APENAS JSON válido (sem markdown, sem comentário).

Schema esperado:
{
  "linha_digitavel": "string 47 dígitos sem espaços/pontos (campo digitável humano) ou 44 (código de barras)",
  "valor": número decimal em reais (BRL),
  "vencimento": "YYYY-MM-DD",
  "beneficiario_nome": "string",
  "beneficiario_cnpj": "string 14 dígitos sem máscara",
  "pagador_nome": "string ou null",
  "confidence": número 0.0-1.0 (sua confiança na extração)
}

Se não conseguir extrair com confiança ≥0.7, retorne {"confidence": 0.0, "error": "razão"}.
PROMPT;

    public function __construct(
        private readonly ?string $openAiKey = null,
        private readonly ?string $awsKey = null,
        private readonly ?string $awsSecret = null,
        private readonly ?string $awsRegion = null
    ) {
    }

    /**
     * Extrai campos do boleto. Retorna array shape esperado pelo Controller.
     *
     * @param UploadedFile $file PDF/JPG/PNG max 5MB
     * @param int $businessId
     * @param int|null $userId
     * @return array{success: bool, data?: array, error?: string, cost_usd?: float, from_cache?: bool}
     */
    public function extract(UploadedFile $file, int $businessId, ?int $userId = null): array
    {
        $hash = hash_file('sha256', $file->getPathname());

        // Idempotência: já processamos esse arquivo? Devolve resultado cached.
        $cached = AiUsageLog::lookupByHash($businessId, self::FEATURE, $hash);
        if ($cached && is_array($cached->metadata) && isset($cached->metadata['extracted'])) {
            return [
                'success' => true,
                'data' => $cached->metadata['extracted'],
                'cost_usd' => (float) $cached->cost_usd,
                'from_cache' => true,
            ];
        }

        $key = $this->openAiKey ?? config('services.openai.key') ?? env('OPENAI_API_KEY');

        if (! $key) {
            $this->logFailure($businessId, $userId, $hash, 'openai', 'gpt-4o', 'error',
                'OPENAI_API_KEY ausente — config services.openai.key ou .env', $file);

            return [
                'success' => false,
                'error' => 'OCR indisponível — chave OpenAI não configurada. Tente novamente mais tarde ou cadastre o boleto manualmente.',
            ];
        }

        $openAiResult = $this->callOpenAi($file, $key, $businessId, $userId, $hash);
        if ($openAiResult['success']) {
            return $openAiResult;
        }

        // Fallback Textract se OpenAI quota_exceeded ou timeout.
        if (in_array($openAiResult['status'] ?? '', ['quota_exceeded', 'timeout'], true) && $this->awsKey) {
            return $this->callTextract($file, $businessId, $userId, $hash);
        }

        return $openAiResult;
    }

    private function callOpenAi(UploadedFile $file, string $key, int $businessId, ?int $userId, string $hash): array
    {
        $base64 = base64_encode(file_get_contents($file->getPathname()));
        $mime = $file->getMimeType() ?: 'image/jpeg';

        // Vision API só aceita imagem direto. PDF: converter primeira página (TODO Onda 24+).
        if (str_starts_with($mime, 'application/pdf')) {
            $this->logFailure($businessId, $userId, $hash, 'openai', 'gpt-4o', 'error',
                'PDF não suportado direto — converta pra JPG/PNG ou cadastre manualmente', $file);

            return [
                'success' => false,
                'error' => 'PDF ainda não suportado nesta versão. Tire screenshot do boleto e envie como imagem (PNG/JPG).',
            ];
        }

        try {
            $response = Http::withToken($key)
                ->timeout(45)
                ->retry(2, 1000, throw: false)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o',
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0,
                    'max_tokens' => 800,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'text', 'text' => self::PROMPT],
                                ['type' => 'image_url', 'image_url' => ['url' => "data:{$mime};base64,{$base64}"]],
                            ],
                        ],
                    ],
                ]);

            if ($response->status() === 429) {
                $this->logFailure($businessId, $userId, $hash, 'openai', 'gpt-4o', 'quota_exceeded',
                    'Rate limit OpenAI', $file);

                return ['success' => false, 'status' => 'quota_exceeded', 'error' => 'OCR sobrecarregado momentaneamente. Tente novamente em 1 minuto.'];
            }

            if (! $response->successful()) {
                $this->logFailure($businessId, $userId, $hash, 'openai', 'gpt-4o', 'error',
                    "HTTP {$response->status()}: " . substr($response->body(), 0, 200), $file);

                return ['success' => false, 'error' => 'OCR falhou. Tente outro arquivo ou cadastre manualmente.'];
            }

            $body = $response->json();
            $content = $body['choices'][0]['message']['content'] ?? '';
            $usage = $body['usage'] ?? [];
            $inputTokens = (int) ($usage['prompt_tokens'] ?? 0);
            $outputTokens = (int) ($usage['completion_tokens'] ?? 0);

            // gpt-4o pricing 2026-05: $5/1M input + $15/1M output + image tokens incluídos no input
            $costUsd = ($inputTokens * 0.000005) + ($outputTokens * 0.000015);

            $extracted = json_decode($content, true) ?: [];
            if (! is_array($extracted) || ! isset($extracted['linha_digitavel'])) {
                $this->logFailure($businessId, $userId, $hash, 'openai', 'gpt-4o', 'error',
                    'JSON inválido na resposta OpenAI', $file, $costUsd, $inputTokens, $outputTokens);

                return ['success' => false, 'error' => 'OCR não conseguiu identificar campos do boleto. Tente uma foto mais nítida.'];
            }

            // Normalizar linha_digitavel (remover separadores).
            $linhaRaw = (string) ($extracted['linha_digitavel'] ?? '');
            $linha = preg_replace('/[^0-9]/', '', $linhaRaw);

            if (! LinhaDigitavelValidator::validar($linha)) {
                $this->logFailure($businessId, $userId, $hash, 'openai', 'gpt-4o', 'error',
                    "Linha digitavel invalida (mod 11): [REDACTED]", $file, $costUsd, $inputTokens, $outputTokens);

                return ['success' => false, 'error' => 'Linha digitável extraída não passou na validação (dígito verificador). Verifique a foto.'];
            }

            $extracted['linha_digitavel'] = $linha;

            // Grava sucesso.
            AiUsageLog::create([
                'business_id' => $businessId,
                'feature' => self::FEATURE,
                'provider' => 'openai',
                'model' => 'gpt-4o',
                'operation' => 'extract',
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost_usd' => $costUsd,
                'idempotency_hash' => $hash,
                'status' => 'ok',
                'metadata' => [
                    'filename' => $file->getClientOriginalName(),
                    'mime' => $mime,
                    'size_bytes' => $file->getSize(),
                    'confidence' => $extracted['confidence'] ?? null,
                    'extracted' => $extracted,
                ],
                'user_id' => $userId,
            ]);

            return [
                'success' => true,
                'data' => $extracted,
                'cost_usd' => $costUsd,
                'from_cache' => false,
            ];
        } catch (ConnectionException $e) {
            $this->logFailure($businessId, $userId, $hash, 'openai', 'gpt-4o', 'timeout', $e->getMessage(), $file);

            return ['success' => false, 'status' => 'timeout', 'error' => 'OCR demorou demais. Tente novamente.'];
        }
    }

    private function callTextract(UploadedFile $file, int $businessId, ?int $userId, string $hash): array
    {
        // Stub: integração real Textract requer aws-sdk-php — sem fallback ativo nesta fase,
        // só registra que não conseguiu cair via Textract pra Wagner instalar SDK depois.
        $this->logFailure($businessId, $userId, $hash, 'aws_textract', 'textract-async', 'error',
            'AWS Textract SDK nao instalado (composer require aws/aws-sdk-php pendente)', $file);

        Log::info('financeiro.ocr_boleto: fallback Textract solicitado mas SDK indisponivel', [
            'business_id' => $businessId,
            'idempotency_hash' => $hash,
        ]);

        return [
            'success' => false,
            'error' => 'Tente novamente em 1 minuto. Se persistir, cadastre manualmente.',
        ];
    }

    private function logFailure(int $businessId, ?int $userId, string $hash, string $provider, string $model,
        string $status, string $errorMsg, UploadedFile $file, float $costUsd = 0.0, int $inputTokens = 0, int $outputTokens = 0): void
    {
        AiUsageLog::create([
            'business_id' => $businessId,
            'feature' => self::FEATURE,
            'provider' => $provider,
            'model' => $model,
            'operation' => 'extract',
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_usd' => $costUsd,
            'idempotency_hash' => $hash,
            'status' => $status,
            'error_message' => substr($errorMsg, 0, 500),
            'metadata' => [
                'filename' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
            ],
            'user_id' => $userId,
        ]);
    }
}
