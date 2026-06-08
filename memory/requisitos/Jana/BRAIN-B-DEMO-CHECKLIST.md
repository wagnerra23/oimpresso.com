# Brain B — Checklist Demo Jana V2 (CYCLE-06 G3)

> Pré-requisitos pra acender Brain B em ambiente de demo sem custo descontrolado. Companion de `BRAIN-B-STATUS-2026-05-16.md`.

## Checklist sequencial (Wagner executa)

### 1. Verificar ANTHROPIC_API_KEY no .env

```bash
ssh -4 -o ConnectTimeout=900 -o ServerAliveInterval=3 \
    -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
    'grep -E "^ANTHROPIC_API_KEY=" /home/u906587222/domains/oimpresso.com/.env | sed "s/=.*/=<SET>/"'
```

**Esperado:** `ANTHROPIC_API_KEY=<SET>` (não retornar a chave).

- [ ] Key presente no Hostinger
- [ ] Se ausente → buscar em Vaultwarden (`vault.oimpresso.com`), adicionar via `php artisan tinker` `Artisan::call('env:set', ...)` OU editar `.env` direto (single-line, sem expor em logs)

### 2. Confirmar config/ai.php aponta pra modelo correto

**Estado atual:** `config/ai.php` NÃO tem bloco `models.text` pra provider Anthropic (só OpenAI tem). Modelo é escolhido em runtime pelo `laravel/ai` SDK ou hardcoded por Agent.

**Modelo alvo demo:** `claude-haiku-4-5-20251001` (mais barato, suficiente pra demo conversacional simples).

- [ ] Inspeção: `grep -r "claude-" Modules/Jana/Ai/Agents/ | head -20` (identifica se há model hardcoded num Agent)
- [ ] Se quiser ENV-driven, abrir PR adicionando `AI_ANTHROPIC_TEXT_DEFAULT` em `config/ai.php` simétrico ao bloco OpenAI (fora do escopo desta demo — backlog)

### 3. Smoke test manual via tinker (Wagner local OU Hostinger)

```bash
php artisan tinker
```

```php
// Dentro do tinker — chamada DIRETA pra validar Anthropic API alive
use Laravel\Ai\Facades\Ai;
use Laravel\Ai\Enums\Lab;

$response = Ai::provider(Lab::Anthropic)
    ->text()
    ->model('claude-haiku-4-5-20251001')
    ->withSystem('Você é Jana, assistente de gestão. Responda em 1 frase PT-BR.')
    ->withMessages([
        ['role' => 'user', 'content' => 'Diga "demo CYCLE-06 G3 funcional" e nada mais.'],
    ])
    ->generate();

echo $response->text;
echo "\nTokens in: {$response->usage->inputTokens} | out: {$response->usage->outputTokens}\n";
```

- [ ] Retornou texto esperado
- [ ] Tokens contabilizados
- [ ] Sem erro 401 (key inválida) / 429 (rate limit) / 500 (API down)

### 4. Validar gravação em jana_mensagens (métrica)

```sql
SELECT created_at, tokens_in, tokens_out, model
FROM jana_mensagens
ORDER BY created_at DESC
LIMIT 5;
```

- [ ] Última mensagem da tinker apareceu na tabela
- [ ] `tokens_in`/`tokens_out` não-zero
- [ ] Próximo brief diário 06:00 BRT vai mostrar `brain_b: tokens_in: X, tokens_out: Y, custo_brl_24h: Z`

### 5. Cap diário 8 chamadas

**Estado atual:** brief reportou "Brain B hoje: 0% (0/50)" — cap 50/dia (não 8).

- [ ] Confirmar cap real onde está definido (procurar `BRAIN_B_DAILY_CAP` ou similar no código)
- [ ] Pra demo seguro, sobrescrever temporariamente `BRAIN_B_DAILY_CAP=8` no `.env` (limita custo blast radius)
- [ ] Após demo, reverter pro cap normal

### 6. Pós-demo

- [ ] Executar query Brain B contadora e adicionar resultado em `memory/sessions/2026-05-16-demo-cycle-06-g3.md`
- [ ] Reverter `BRAIN_B_DAILY_CAP` se alterado
- [ ] Spawnar task follow-up: `AI_ANTHROPIC_TEXT_DEFAULT` ENV-driven em `config/ai.php` (gap arquitetural)
- [ ] Brief diário 2026-05-17 06:00 BRT deve mostrar Brain B > 0%

## Restrições Tier 0

- ⛔ NÃO inflar consumo artificialmente — 1 chamada de smoke OK; loop de N chamadas falsas viola ADR 0105 (cliente como sinal)
- ⛔ NÃO commitar `ANTHROPIC_API_KEY` em código/.env.example — git-ignore + Vaultwarden
- ⛔ NÃO testar contra biz=4 (ROTA LIVRE) — usar biz=1 conforme ADR 0101
- ✅ PT-BR em todo texto de prompt durante demo

## Custo estimado demo

- 1 chamada Haiku ~500 tokens in + 200 tokens out
- USD: `(500 × 0.80/1M) + (200 × 4.00/1M) ≈ $0.0012` (Haiku 4.5)
- BRL: ~R$ [redacted Tier 0] (sub-centavo)
- 8 chamadas cap: R$ [redacted Tier 0] (totalmente seguro)

## Referências

- `BRAIN-B-STATUS-2026-05-16.md` (diagnóstico companion)
- ADR 0035 Stack IA canônica
- `Modules/Jana/Services/HealthSnapshotService.php:108-128` (medição brain_b)
- `Modules/Jana/Ai/Agents/ChatCopilotoAgent.php:184-214` (Anthropic prompt caching live)
