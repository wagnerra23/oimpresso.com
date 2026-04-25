# ADR TECH-0002 — Adapter de IA (LaravelAI preferido, OpenAI direto como fallback)

**Data:** 2026-04-24
**Status:** Aceita
**Escopo:** Módulo Copiloto — integração com LLM para o fluxo conversacional
**Autor/a:** Claude

---

## Contexto

Copiloto depende de LLM pra:
1. **Montar briefing** (transformar contexto em texto natural que o usuário leia).
2. **Propor metas** (retornar 3–5 propostas estruturadas JSON).
3. **Conversar** (esclarecer dúvidas, ajustar escopo, explicar desvios).

A auto-memória registra que **LaravelAI** foi promovido a módulo spec-ready em 2026-04-24 (knowledge graph + RAG + agente central). Mas:

- LaravelAI ainda **não tem código**, só spec.
- Depender dele bloqueia Copiloto.
- Se mesmo assim Copiloto "espera LaravelAI", a ordem de execução ficaria errada (Copiloto paga muito por feature do LaravelAI).

Ao mesmo tempo, **não dá pra ignorar LaravelAI** — quando ele existir, Copiloto deve se plugar nele (caso contrário duplica prompt management, logging, cache).

## Decisão

**Interface de adapter** no Copiloto com duas implementações:

```php
interface AiAdapter {
    public function gerarBriefing(ContextoNegocio $ctx): string;
    public function sugerirMetas(ContextoNegocio $ctx, string $prompt): array;
    public function responderChat(Conversa $conv, string $mensagem): string;
}
```

Duas implementações iniciais:

1. **`OpenAiDirectDriver`** (fallback, default em v1)
   - Usa `openai-php/laravel` já instalado (ver `composer.json` raiz).
   - Prompt templates em `Modules/Copiloto/Resources/prompts/`.
   - JSON mode nativo do OpenAI pra propostas estruturadas.
   - Cache de briefing por hash de contexto (TTL 10min).

2. **`LaravelAiDriver`** (preferido quando disponível)
   - Delega pra `Modules\LaravelAI\Contracts\Agent::class`.
   - LaravelAI traz RAG sobre ADRs + audit log + permissões — Copiloto **sugere melhor** porque a IA conhece o contexto arquitetural.
   - Prompt templates do LaravelAI; Copiloto passa só o contexto específico.

**Resolver automático** no `CopilotoServiceProvider`:

```php
$this->app->bind(AiAdapter::class, function () {
    if (Module::has('LaravelAI') && Module::find('LaravelAI')->isEnabled()) {
        return app(LaravelAiDriver::class);
    }
    return app(OpenAiDirectDriver::class);
});
```

Usuário pode forçar via config: `config('copiloto.ai_adapter') = 'openai_direct' | 'laravel_ai' | 'auto'`.

## Fallback gracioso

Se o adapter escolhido **falha** (timeout, erro 5xx, key inválida):

1. Copiloto **não explode** — retorna mensagem amigável no chat ("Estou sem conexão com IA no momento, você quer criar a meta manualmente?").
2. Dashboard / CRUD manual continua funcionando (Copiloto sem chat é um CRUD tradicional).
3. Erro loga em `storage/logs/copiloto-ai.log` + alerta superadmin se >3 falhas em 5min.

## Alternativas consideradas e rejeitadas

- **Depender direto do LaravelAI** — bloqueia Copiloto até LaravelAI existir.
- **Ignorar LaravelAI, usar só openai-php** — amarra Copiloto ao OpenAI; quando Wagner quiser trocar de modelo (Claude, Gemini, local llama) é refactor grande.
- **Abstração genérica estilo `Prism`/`laravel-llm`** — adiciona uma camada a mais sem ganho imediato.
- **LangChain-PHP ou outras libs emergentes** — ecossistema raso, risco de abandonware.

## Consequências

**Positivas:**
- Copiloto **entrega em v1** sem depender de outro módulo ainda não codado.
- **Migração transparente** quando LaravelAI entrar: só troca binding.
- Prompts ficam no Copiloto v1 → migram pro LaravelAI v1 quando ele existir (e passam a ser reutilizados por outros módulos que usem o mesmo agente).
- Fallback preserva experiência quando IA quebra.

**Negativas/Custos:**
- Prompts **duplicam temporariamente** (Copiloto tem seus, LaravelAI terá os dele) — custo de migração no futuro.
- Adapter adiciona indireção que pode ser prematuramente abstrata se LaravelAI nunca sair do papel.
- Cada driver tem seu próprio logging/observabilidade — unificar depois.

## Contrato mínimo de `ContextoNegocio`

DTO passado aos métodos do adapter:

```php
final class ContextoNegocio {
    public function __construct(
        public readonly ?int $businessId,
        public readonly string $businessName,
        public readonly array $faturamento90d, // [['mes' => '2026-04', 'valor' => 1234]]
        public readonly int $clientesAtivos,
        public readonly array $modulosAtivos, // ['PontoWr2', 'Essentials', ...]
        public readonly array $metasAtivas,   // [['nome' => 'MRR', 'valor_alvo' => 417000, 'realizado' => 120000]]
        public readonly ?string $observacoes, // free-form input do gestor
    ) {}
}
```

Material suficiente pra IA gerar sugestões qualificadas sem vazar dados desnecessários.

## Sanitização (obrigatória)

- **CPF/CNPJ mascarados** antes de enviar pro provedor IA (ex.: `XXX.XXX.123-45`).
- Nomes de pessoas físicas só se o usuário explicitamente citar — contexto automático usa nomes de **businesses**.
- Senhas, tokens, dados bancários: **nunca**.

## Testes obrigatórios (DoD)

- Módulo LaravelAI ausente → driver é `OpenAiDirectDriver`.
- Módulo LaravelAI presente e ativo → driver é `LaravelAiDriver`.
- Config `COPILOTO_AI_ADAPTER=openai_direct` força OpenAI mesmo com LaravelAI ativo.
- Erro 5xx no adapter → Copiloto retorna resposta de fallback, não exception.
- CPF em contexto → é mascarado antes da chamada (snapshot test).

## Referências

- `composer.json` raiz — `openai-php/laravel` já instalado.
- Auto-memória `reference_revenue_thesis_modulos.md` — LaravelAI tem tese comercial própria; Copiloto pode virar **cliente interno** de LaravelAI no futuro (boa prova de conceito).

---

**Última atualização:** 2026-04-24
