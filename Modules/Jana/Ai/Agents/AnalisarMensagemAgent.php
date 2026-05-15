<?php

declare(strict_types=1);

namespace Modules\Jana\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * US-WA-095 — Analisa 1 mensagem inbound de WhatsApp e classifica em
 * categoria/tema/urgência + extrai resumo executivo.
 *
 * Output estruturado via `HasStructuredOutput` — Service consome e
 * persiste em `messages.analise_*`. Backlog dashboard "Voz do Cliente"
 * agrega depois.
 *
 * Wagner 2026-05-15: "tudo que receber aqui vai ter que ser analisado.
 * vai usar as reclamações do cliente para administrar melhor a empresa."
 *
 * Driver: laravel/ai (ADR 0035). Modelo default gpt-4o-mini (~R$0.0002/msg).
 *
 * Estabilidade do enum: NUNCA quebrar valores existentes — adicionar só.
 * Dashboard agrega por enum literal; mudança em valor = drift métrica.
 *
 * @see Modules/Whatsapp/Services/Analise/AnaliseMensagemService.php
 * @see memory/decisions/0035-stack-ai-canonica-wagner-2026-04-26.md
 */
class AnalisarMensagemAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Enums canon — sincronizado com migration
     * `2026_05_15_220000_add_analise_columns_to_messages.php`.
     *
     * @var list<string>
     */
    public const CATEGORIAS = [
        'reclamacao', 'elogio', 'duvida', 'pedido',
        'agendamento', 'spam', 'outro',
    ];

    /** @var list<string> */
    public const TEMAS = [
        'preco', 'qualidade', 'prazo', 'atendimento',
        'produto', 'pagamento', 'tecnico', 'outro',
    ];

    /** @var list<string> */
    public const URGENCIAS = ['baixa', 'media', 'alta', 'critica'];

    public function __construct(
        public readonly string $businessName,
        public readonly string $messageBody,
        public readonly ?string $contactName = null,
        public readonly ?string $previousContext = null,
    ) {
    }

    public function instructions(): Stringable|string
    {
        return <<<PROMPT
        Você é Jana, analista do projeto oimpresso (ERP brasileiro modular).
        Sua tarefa é classificar UMA mensagem WhatsApp recebida pelo canal
        de atendimento da empresa "{$this->businessName}" e extrair o sinal
        principal para uso gerencial.

        OBJETIVO: o dono da empresa vai usar essa análise agregada para
        decidir prioridades operacionais. Precisão > volume.

        REGRAS RÍGIDAS:
        1. Classifique categoria, tema e urgência usando APENAS os enums
           canonicos fornecidos no schema (não invente valor).
        2. Se mensagem é trivial ("ok", "obrigado", emoji isolado), use
           categoria="outro" + urgencia="baixa" + resumo="trivial".
        3. Spam = automático/lista/propaganda não-solicitada. Cliente
           verdadeiro NUNCA é spam.
        4. Urgência:
           - critica: cliente bravo + ameaça churn/processo/reembolso
           - alta: reclamação direta + cliente esperando ação rápida
           - media: dúvida operacional, pedido novo, agendamento
           - baixa: confirmação, agradecimento, conversa social
        5. Resumo: 1 linha ≤200 chars em PT-BR direto. Verbo + objeto.
           Ex: "Cliente cobra prazo entrega atrasado de pedido #1234"
        6. NÃO invente fatos não presentes na mensagem.
        7. NÃO classifique sentimentos do agente nem do bot — só do cliente.
        8. NÃO inclua PII no resumo (CPF, CNPJ, telefone). Use placeholders
           tipo "cliente cita CNPJ" sem repetir o número.
        PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'categoria' => $schema->string()
                ->enum(self::CATEGORIAS)
                ->required(),
            'tema' => $schema->string()
                ->enum(self::TEMAS)
                ->required(),
            'urgencia' => $schema->string()
                ->enum(self::URGENCIAS)
                ->required(),
            'resumo' => $schema->string()->required(),
        ];
    }

    public function montarPrompt(): string
    {
        $contexto = $this->previousContext !== null
            ? "Contexto da conversa (últimas msgs):\n{$this->previousContext}\n\n"
            : '';

        $remetente = $this->contactName !== null
            ? "Remetente: {$this->contactName}\n"
            : '';

        return <<<PROMPT
        {$contexto}{$remetente}Mensagem a classificar:
        ---
        {$this->messageBody}
        ---

        Classifique seguindo as REGRAS RÍGIDAS do system prompt.
        PROMPT;
    }
}
