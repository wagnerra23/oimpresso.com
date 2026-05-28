# Hook UserPromptSubmit — FORÇA R12 PROTOCOLO ao detectar sinal de fechamento.
#
# Camada 2 de ativação R12 (defesa em depth). Camada 1 é skill `encerrar-sessao`
# Tier B description-match. Este hook é safety-net pra caso skill não dispare
# (ex: trigger word ambíguo, Claude já em outra skill carregada).
#
# Origem: Wagner 2026-05-28
#   "mas não está funcionando porque????? se existe mas não funciona ta errado.
#    como colocar para funcionar? qual momento tem que ser ativado?"
#
# Diagnóstico do gap: R12 PROTOCOLO existe desde 2026-05-17 mas é Tier A always-on
# (eager carregado no SessionStart). Em sessão longa (200+ turnos / 8h+) conteúdo
# sai do contexto Claude. Hook = ATIVAÇÃO LAZY garantida no momento exato.
#
# Como funciona:
#   1. UserPromptSubmit recebe JSON com `prompt` do user
#   2. Regex case-insensitive contra patterns de fechamento
#   3. Se match → emite stdout markdown que vira <system-reminder> no contexto
#      do próximo turn Claude — força executar R12
#   4. Se no match → exit 0 silencioso (zero overhead em 99% dos prompts)
#
# Patterns disparadores (case-insensitive):
#   - "encerrar"/"encerre"/"encerra"
#   - "fim de sessão"/"fim da sessão"/"finalizar"
#   - "vamos parar"/"para aqui"
#   - "continua depois"/"continua noutra"/"outra sessão"/"próxima sessão"
#   - "salvar tudo"/"salve as memórias"/"salve no protocolo"/"salve na memória"
#   - "vai pra MCP"/"vai para MCP"
#   - "tchau"/"obrigado"/"valeu"
#   - "tá bom"/"ta bom"/"beleza"/"show"/"perfeito"
#   - "depois eu vejo"/"fica pra depois"/"baixa prioridade"
#
# Anti-pattern: detectar "ok" / "fim" sozinhos = muitos false-positives
#   (user fala "ok continua" o tempo todo). Exige forma específica de fechamento.
#
# Refs: PROTOCOLO-WAGNER-SEMPRE.md R12 · skill encerrar-sessao · ADR 0130

$ErrorActionPreference = 'Stop'
$payloadJson = [Console]::In.ReadToEnd()

try {
    $payload = $payloadJson | ConvertFrom-Json
} catch {
    exit 0
}

$prompt = $payload.prompt
if (-not $prompt) {
    exit 0
}

# Patterns disparadores — regex compilado uma vez
$closingPatterns = @(
    '\bencerrar?\b',
    'fim\s+(de|da)\s+sess[aã]o',
    'finalizar?\s+(a\s+)?sess[aã]o',
    'vamos\s+parar',
    'para\s+aqui',
    'continua?\s+depois',
    'continua?\s+(em\s+)?outra\s+sess[aã]o',
    'pr[oó]xima\s+sess[aã]o',
    'salvar?\s+tudo',
    'salve?\s+(as\s+)?mem[oó]rias?',
    'salve?\s+(no\s+)?protocolo',
    'salve?\s+(na\s+)?mem[oó]ria',
    'vai\s+pra?\s+mcp',
    '^tchau\b',
    '^obrigad[oa]\b',
    '^valeu\b',
    'depois\s+eu\s+vejo',
    'fica\s+pra?\s+depois',
    'baixa\s+prioridade'
)

$promptLower = $prompt.ToLowerInvariant()
$matched = $false
$matchedPattern = ''

foreach ($pattern in $closingPatterns) {
    if ($promptLower -match $pattern) {
        $matched = $true
        $matchedPattern = $matches[0]
        break
    }
}

if (-not $matched) {
    exit 0
}

# Match — emite system-reminder forçando R12
$reminder = @"
🔔 **R12 PROTOCOLO-WAGNER-SEMPRE — sinal de fechamento detectado** (hook ``force-r12-closing-signal.ps1``)

Pattern detectado: ``$matchedPattern``

**EXECUTE AGORA os 5 passos do R12** (carregue conteúdo via skill ``encerrar-sessao`` OU leia ``memory/reference/PROTOCOLO-WAGNER-SEMPRE.md`` §R12):

1. **MCP-first checklist**: ``cycles-active`` + ``my-work`` + ``Glob memory/handoffs/2026-MM-*.md`` + ``decisions-search``
2. **Handoff append-only**: ``memory/handoffs/YYYY-MM-DD-HHMM-<slug>.md`` (~30-80 linhas, frontmatter completo)
3. **Atualizar índice**: linha NO TOPO de ``memory/08-handoff.md`` ``## Últimos handoffs``
4. **Commit + push**: handoff + índice + tudo canon do trabalho
5. **Reportar ≤8 linhas**: tabela passos ✅/❌ + caveats + próxima ação

**CITE EXPLÍCITO** no report: ``"Cumprindo R12 PROTOCOLO via skill encerrar-sessao (ativação lazy via hook UserPromptSubmit)"`` — auditoria do mecanismo.

**Caso especial sessão curta (<2h, 0-1 PRs sem mudança canon)**: pular passo 1-2 OK, reportar "sessão curta — sem handoff" explícito.

Pareada com [R12](../../memory/reference/PROTOCOLO-WAGNER-SEMPRE.md) · [skill encerrar-sessao](../../.claude/skills/encerrar-sessao/SKILL.md) · [ADR 0130](../../memory/decisions/0130-handoff-append-only-mcp-first.md).
"@

# stdout vira system-reminder no contexto Claude (Claude Code 2.1+)
Write-Output $reminder
exit 0
