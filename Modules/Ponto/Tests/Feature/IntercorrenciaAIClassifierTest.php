<?php

namespace Modules\Ponto\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Modules\Ponto\Services\IntercorrenciaAIClassifier;
use Tests\TestCase;

/**
 * Unit-ish test do classificador IA. Não chama OpenAI — valida fallbacks,
 * validação de input, mascaramento PII e normalização.
 */
class IntercorrenciaAIClassifierTest extends TestCase
{
    protected IntercorrenciaAIClassifier $ai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ai = new IntercorrenciaAIClassifier();
        Cache::flush();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function rejeita_descricao_muito_curta(): void
    {
        $r = $this->ai->classificar('curto');
        $this->assertFalse($r['success']);
        $this->assertStringContainsString('muito curta', $r['error']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function rejeita_descricao_muito_longa(): void
    {
        $r = $this->ai->classificar(str_repeat('a', 2001));
        $this->assertFalse($r['success']);
        $this->assertStringContainsString('muito longa', $r['error']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function retorna_erro_quando_ai_desativada(): void
    {
        config(['app.env' => 'testing']);
        putenv('AI_ENABLED=false');

        $r = $this->ai->classificar('tive consulta médica às 14h, retornei às 17h');
        $this->assertFalse($r['success']);
        $this->assertStringContainsString('IA não configurada', $r['error']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function mascara_cpf_pis_email_telefone(): void
    {
        $mask = new class extends IntercorrenciaAIClassifier {
            public function exposeMascarar(string $t): string { return $this->mascararPII($t); }
        };

        $input = 'Wagner CPF 123.456.789-00 PIS 123.45678.90-1 email wagner@exemplo.com tel (11) 91234-5678';
        $output = $mask->exposeMascarar($input);

        $this->assertStringNotContainsString('123.456.789-00', $output);
        $this->assertStringNotContainsString('wagner@exemplo.com', $output);
        $this->assertStringNotContainsString('91234-5678', $output);
        // Wave 11 D7.a — delegação ao PiiRedactor canônico (Modules/Jana/Services/Privacy)
        // mudou placeholder de `[CPF]` para `[REDACTED:CPF]`. PII continua mascarada.
        $this->assertStringContainsString('[REDACTED:CPF]', $output);
        $this->assertStringContainsString('[REDACTED:EMAIL]', $output);
        $this->assertStringContainsString('[REDACTED:PHONE]', $output);
        $this->assertStringContainsString('[REDACTED:PIS]', $output);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function normaliza_tipo_invalido_para_OUTRO(): void
    {
        $reflection = new \ReflectionClass(IntercorrenciaAIClassifier::class);
        $method = $reflection->getMethod('normalizar');
        $method->setAccessible(true);

        $r = $method->invoke($this->ai, [
            'tipo' => 'TIPO_INEXISTENTE_DA_IA',
            'prioridade' => 'XPTO',
            'confianca' => 5.0, // fora do range
        ]);

        $this->assertEquals('OUTRO', $r['tipo']);
        $this->assertEquals('NORMAL', $r['prioridade']);
        $this->assertEquals(1.0, $r['confianca']); // clamped
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function impacta_apuracao_default_por_tipo(): void
    {
        $reflection = new \ReflectionClass(IntercorrenciaAIClassifier::class);
        $method = $reflection->getMethod('normalizar');
        $method->setAccessible(true);

        // ATESTADO sem explicit `impacta_apuracao` → true (padrão por tipo)
        $r = $method->invoke($this->ai, ['tipo' => 'ATESTADO_MEDICO']);
        $this->assertTrue($r['impacta_apuracao']);

        // REUNIAO_EXTERNA → false (trabalho normal)
        $r2 = $method->invoke($this->ai, ['tipo' => 'REUNIAO_EXTERNA']);
        $this->assertFalse($r2['impacta_apuracao']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function ai_habilitada_exige_flags_e_api_key(): void
    {
        putenv('AI_ENABLED=true');
        putenv('AI_CLASSIFICACAO_INTERCORRENCIA=true');
        putenv('OPENAI_API_KEY=');
        $this->assertFalse($this->ai->aiHabilitada(), 'Sem API key deve retornar false');

        putenv('OPENAI_API_KEY=sk-fake');
        $this->assertTrue($this->ai->aiHabilitada());

        putenv('AI_CLASSIFICACAO_INTERCORRENCIA=false');
        $this->assertFalse($this->ai->aiHabilitada());
    }
}
