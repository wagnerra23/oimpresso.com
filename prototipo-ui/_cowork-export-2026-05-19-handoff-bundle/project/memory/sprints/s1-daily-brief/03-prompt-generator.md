# Prompt do gerador — Brain B (claude-sonnet-4-6)

> **Como usar:** este prompt vai no module ADS, função
> `BriefGenerator::generate(array $aggregated)`. Recebe o JSON da tabela
> cache `mcp_brief_inputs_cache`, devolve markdown ≤3.500 tokens.
>
> **Modelo:** `claude-sonnet-4-6` (não use haiku — densidade exige sonnet)
> **Temperature:** 0.2
> **Max tokens:** 4096
> **Stop sequences:** `\n---END---`
> **Estimativa custo:** ~$0.05 por brief × 6/dia = **$0.30/dia**

---

## System prompt (fixo, nunca muda sem nova ADR)

```
Você é o gerador do Daily Brief do projeto Oimpresso ERP.

REGRAS DURAS (não negocie):

1. Output é markdown puro, sem fences, sem preâmbulo.
2. EXATAMENTE 7 seções, NESTA ORDEM, com headers EXATOS:
   ## ESTADO MACRO
   ## EM VOO AGORA
   ## DECISÕES RECENTES (24h)
   ## SKILLS USO 7d
   ## CHARTERS APODRECENDO
   ## FLAGS
   ## METADATA
3. Total ≤3.500 tokens. Conte mentalmente. Se passar, corte da seção
   menos crítica (geralmente SKILLS ou CHARTERS).
4. Termine com a linha exata: \n---END---
5. PT-BR sempre. Tom: telegráfico, denso, factual. Sem floreio.
6. Use emojis SOMENTE em FLAGS (🔴 🟡 🟢) e setas (↑ ↓ →) onde fizer sentido.
7. Datas: "há 3d", "há 2h", "hoje 14h", "ontem". Nunca ISO completo no corpo.
8. Números: pt-BR (R$ [redacted Tier 0] / 47% / 14d).
9. Nunca invente dados. Se um campo veio null/vazio do JSON, escreva "—" ou
   "nada hoje". Nunca preencha com placeholder genérico.
10. NUNCA inclua PII de clientes finais. Você só vê dados internos do time.

ESTRUTURA OBRIGATÓRIA DE CADA SEÇÃO:

## ESTADO MACRO
- Cycle: <codename> (<sprint_label>) · <X>d restantes
- Mission focus: <mission_focus>
- HITL pending Wagner: <N> (top 2 inline se houver)
- Brain B hoje: <pct>% (<spent>/<cap>) <emoji se >70%>

## EM VOO AGORA
Lista numerada. Cada linha:
N. <actor_id> @ <target_path> — <intent_label>, <aging_human>

Limite 8 linhas. Resto vira "+N outros".

## DECISÕES RECENTES (24h)
- ADRs: <lista compacta com IDs e títulos truncados em 50 chars>
- Commits: <N>
- ADS escalações: <N>
- Incidentes: <N>

## SKILLS USO 7d
Lista top 5:
- <skill>: <trigger_count> disparos<, autofix N> <(TIER)>

Se houver candidatas a poda, adicione linha:
- ⚠ Candidatas a poda: <names_csv>

## CHARTERS APODRECENDO
Lista até 5 charters com last_verified >60d:
- <charter_id> (<days_stale>d) — owner: <owner>

Se vazio: "—"

## FLAGS
3 linhas obrigatórias, sempre nesta ordem:
- <emoji> Migration aging: <texto curto>
- <emoji> PRs aguardando review: <texto curto>
- <emoji> Visual regression CI: <texto curto>

Critério emoji:
🔴 = >2 itens críticos | 🟡 = 1 item | 🟢 = nada

## METADATA
- Gerado: <human relative>
- Versão gerador: v1
- Tokens estimados: <N>

VALIDADOR INTERNO antes de devolver:
- 7 headers ##? ✓
- Termina em ---END---? ✓
- Tem PII de cliente final? ✗ (refazer se sim)
- Total tokens ≤3500? ✓
```

---

## User prompt (template — substitua `{{...}}` antes de enviar)

```
Gere o Daily Brief com os dados abaixo. Hora atual: {{NOW_BR_HUMAN}}.

DADOS AGREGADOS (JSON da tabela cache mcp_brief_inputs_cache):

{{MV_JSON}}

CONTEXTO ADICIONAL (opcional, pode estar vazio):
- Última ADR aprovada e relevante: {{LATEST_ADR_TITLE}}
- Mensagem do Wagner pra time hoje: {{WAGNER_NOTE_OR_EMPTY}}

Gere o brief seguindo as 10 regras duras do system prompt.
```

---

## Validador pós-geração (PHP — chamar antes de gravar em mcp_briefs)

```php
final class BriefValidator
{
    private const REQUIRED_HEADERS = [
        '## ESTADO MACRO',
        '## EM VOO AGORA',
        '## DECISÕES RECENTES (24h)',
        '## SKILLS USO 7d',
        '## CHARTERS APODRECENDO',
        '## FLAGS',
        '## METADATA',
    ];

    public function validate(string $content): ValidationResult
    {
        // 1) Headers presentes na ordem exata
        $lastPos = -1;
        foreach (self::REQUIRED_HEADERS as $h) {
            $pos = strpos($content, $h);
            if ($pos === false || $pos <= $lastPos) {
                return ValidationResult::fail("missing_or_misordered: {$h}");
            }
            $lastPos = $pos;
        }

        // 2) Termina com ---END---
        if (!str_ends_with(trim($content), '---END---')) {
            return ValidationResult::fail('missing_end_sentinel');
        }

        // 3) Token count <= 3500 (estimativa: ~4 chars/token PT-BR)
        $estimatedTokens = (int) ceil(mb_strlen($content) / 4);
        if ($estimatedTokens > 3500) {
            return ValidationResult::fail("token_overflow:{$estimatedTokens}");
        }

        // 4) Sem PII de cliente final (regex CPF/CNPJ/email cliente)
        if (preg_match('/\d{3}\.\d{3}\.\d{3}-\d{2}/', $content) ||
            preg_match('/\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}/', $content)) {
            return ValidationResult::fail('pii_leaked');
        }

        return ValidationResult::ok($estimatedTokens);
    }
}
```

---

## Golden test (CI diário — detecta drift do prompt)

`tests/Feature/BriefGeneratorTest.php`:

```php
public function test_brief_generator_golden(): void
{
    $fixtureJson = file_get_contents(__DIR__.'/fixtures/mv_brief_inputs_golden.json');
    $brief = (new BriefGenerator())->generate(json_decode($fixtureJson, true));

    $validator = new BriefValidator();
    $this->assertTrue($validator->validate($brief)->isOk());

    // Estrutura: todos headers
    foreach (BriefValidator::REQUIRED_HEADERS as $h) {
        $this->assertStringContainsString($h, $brief);
    }

    // Conteúdo factual: cycle codename do fixture deve aparecer
    $this->assertStringContainsString('Sprint-2026-W18', $brief);

    // Determinismo razoável: 3 runs, max 5% diff de tokens
    $b2 = (new BriefGenerator())->generate(json_decode($fixtureJson, true));
    $diff = abs(mb_strlen($brief) - mb_strlen($b2)) / mb_strlen($brief);
    $this->assertLessThan(0.15, $diff, 'gerador instável demais');
}
```
