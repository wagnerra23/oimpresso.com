# RUNBOOK — `block-module-drift.ps1` (Mecanismo #3 ENFORCEMENT)

> Hook PreToolUse local que detecta Controllers fora do `SCOPE.md.contains[]` do módulo proprietário. Implementa **Mecanismo #3** de [memory/governance/ENFORCEMENT.md §2](../../governance/ENFORCEMENT.md) — pré-commit local pra catch drift antes de virar PR. Trabalha em camada **L5 (Module Charter — ADR 0080)** + Constituição v1.1.0 Artigo 7.

## O que faz

Quando Claude tenta `Write`/`Edit`/`MultiEdit` em `Modules/<X>/Http/Controllers/**/*Controller.php`:

1. Extrai nome do módulo `<X>` (PascalCase) + nome do Controller (basename sem `.php`).
2. Lê `Modules/<X>/SCOPE.md`.
3. Parseia o frontmatter YAML (parser pragmático sem dependência — só regex) e extrai `contains[]`.
4. Verifica se o nome do Controller aparece como substring em qualquer item de `contains[]`. Match aceita formatos:
   - `"ChatController"` (nome puro)
   - `"ChatController — UI chat principal"` (com descrição)
   - `"Admin/CustosController — dashboard custos LLM"` (com subfolder)
   - `"Api/MetaWebhookController — recebe events Meta Cloud"` (sub-sub)
5. Se **declarado** → `exit 0` silencioso. Edit prossegue.
6. Se **NÃO declarado** → modo determina ação:
   - `warn` (default) → warning em stderr + `exit 0` (Edit prossegue, Claude vê aviso)
   - `strict` → emite `{decision: deny, systemMessage: ...}` JSON no stdout (Claude Code bloqueia Edit)
   - `off` → `exit 0` sem nem ler SCOPE.md

## Quando dispara

| Path                                                                  | Dispara? |
|-----------------------------------------------------------------------|----------|
| `Modules/Jana/Http/Controllers/ChatController.php`                    | ✅       |
| `Modules/Jana/Http/Controllers/Admin/CustosController.php`            | ✅       |
| `Modules/Whatsapp/Http/Controllers/Api/MetaWebhookController.php`     | ✅       |
| `Modules/Jana/Services/ChatService.php`                               | ❌ (não é Controller) |
| `app/Http/Controllers/AlgumController.php`                            | ❌ (não é Module/<X>/) |
| `Modules/Jana/Database/Migrations/...`                                | ❌       |
| `resources/js/Pages/Jana/Chat.tsx`                                    | ❌       |

## Modos de operação

Variável de ambiente `OIMPRESSO_DRIFT_HOOK_MODE`:

| Modo     | Comportamento                                                          | Quando usar |
|----------|------------------------------------------------------------------------|-------------|
| `warn`   | **Default**. Imprime warning stderr, NÃO bloqueia. Claude vê aviso.    | 4 semanas iniciais de calibração (alinhado com ADR 0086 ActionGate warn-only) |
| `strict` | Bloqueia via JSON `{decision: deny}` no stdout.                        | Após calibração confirmar zero false positive |
| `off`    | Pula check completamente.                                              | Debug ou bypass temporário do hook |

Exemplo PowerShell pra ativar STRICT na sessão atual:

```powershell
$env:OIMPRESSO_DRIFT_HOOK_MODE = 'strict'
```

Persistente (User-level, Windows):

```powershell
[Environment]::SetEnvironmentVariable('OIMPRESSO_DRIFT_HOOK_MODE', 'strict', 'User')
```

## Override emergencial Tier 0

Wagner Tier 0 superadmin pode pular hook em emergência:

```powershell
$env:OIMPRESSO_DRIFT_OVERRIDE = '1'
```

**Obrigatório:** registrar uso (commit message, session log, ADR follow-up). Override sem registro = drift L7 audit.

## Como adicionar novo Controller em SCOPE.md.contains[] sem violar hook

Quando precisar criar Controller NOVO no módulo:

1. **Antes** de rodar `Write`/`Edit` no arquivo `.php` do Controller, EDITE `Modules/<X>/SCOPE.md` adicionando entrada em `contains[]`:

   ```yaml
   contains:
     - "ChatController -- UI chat principal"
     - "NovoController -- descricao curta do papel"  # <-- nova linha
   ```

2. Salve o SCOPE.md.
3. AGORA `Write` o Controller — hook lê SCOPE atualizado e passa limpo.

Ordem correta: **SCOPE.md primeiro, Controller depois**. Inversa funciona em modo `warn` (com aviso), mas em `strict` bloqueia.

## Troubleshooting

### "YAML parse falhou"

Hook usa parser pragmático regex (PowerShell 5.1 não tem `ConvertFrom-Yaml` nativo). Limitações conhecidas:

- ✅ Suporta itens com aspas duplas, simples ou sem aspas.
- ✅ Suporta comentários `# ...` dentro do bloco `contains:`.
- ✅ Suporta CRLF (Windows) e LF (Unix).
- ❌ NÃO suporta itens multi-linha (`>`/`|` YAML — flow scalar continuação).
- ❌ NÃO suporta `contains:` em formato flow `[item1, item2]` (só block style com `- item`).

Se YAML do seu SCOPE.md usa pattern não suportado, o hook em `warn` só emite "AVISO: SCOPE.md sem bloco contains" (não bloqueia). Em `strict` bloqueia — converta SCOPE.md pra block style.

### Controller name não match esperado

Match é **substring case-sensitive** do nome exato extraído do path (basename sem `.php`).

- `ChatController.php` → procura substring `ChatController` em cada item de `contains[]`.
- Item `"ChatController -- UI chat principal"` contém substring `ChatController` → ✅ match.
- Item `"Chat -- UI chat"` NÃO contém `ChatController` → ❌ não match.

Regra prática: **sempre incluir o nome completo do Controller (com sufixo `Controller`) na entrada de `contains[]`**. Já é convenção em todos os 33 SCOPE.md existentes.

### Hook não dispara

Verifique:

1. `OIMPRESSO_DRIFT_HOOK_MODE` não está `off`:
   ```powershell
   echo $env:OIMPRESSO_DRIFT_HOOK_MODE
   ```
2. `OIMPRESSO_DRIFT_OVERRIDE` não está `1`:
   ```powershell
   echo $env:OIMPRESSO_DRIFT_OVERRIDE
   ```
3. Path **começa** com `Modules/<X>/Http/Controllers/` (não funciona pra `app/Http/Controllers/` ou outros lugares).
4. Hook está registrado em `.claude/settings.local.json` ou `.claude/settings.json` na seção `hooks.PreToolUse`. (Registro fora do escopo deste runbook — ver pattern dos outros hooks.)

### `SCOPE.md` ausente

Se `Modules/<X>/SCOPE.md` não existir:
- `warn` → emite "AVISO: SCOPE.md ausente, não foi possível validar drift" em stderr.
- `strict` → bloqueia com mensagem instruindo criar SCOPE.md (copiar template de outro módulo).

Em maio/2026 os 33 módulos existentes já têm SCOPE.md — só dispara em módulo novo recém-criado.

## Smoke test

```powershell
pwsh .claude/hooks/block-module-drift.test.ps1
# ou
powershell -NoProfile -File .claude/hooks/block-module-drift.test.ps1
```

7 casos validados:
1. Controller declarado em `contains[]` → passou limpo
2. Controller não declarado, modo WARN → exit 0 + warning stderr
3. Modo STRICT + drift → emite `decision: deny`
4. Modo OFF → silencioso
5. `OIMPRESSO_DRIFT_OVERRIDE=1` (mesmo em STRICT) → pula check
6. Path fora de `Modules/` → ignorado
7. Controller em subfolder `Admin/` declarado → passou limpo

## Quando virar STRICT

Recomendação ENFORCEMENT.md §2 #3: **4 semanas warn-only com zero false positive** antes de virar STRICT.

Plano de transição sugerido:

| Semana | Modo  | Ação                                                          |
|--------|-------|---------------------------------------------------------------|
| 1-2    | warn  | Coleta avisos em sessões reais. Anota false positives.        |
| 3      | warn  | Corrige false positives (ex: SCOPE.md desatualizado em módulos onde drift é legítimo). |
| 4      | warn  | Zero false positive em 7 dias consecutivos. Wagner aprova.    |
| 5+     | strict| Hook bloqueia. Drift NOVO catalogado precisa de override + ADR follow-up. |

Sugestão de data: **2026-06-13** (4 semanas após criação do hook 2026-05-15). Wagner valida via ADR derivada 0082 (Pre-commit hook canon — pendente).

## Custo / latência

- **Latência:** ~5-15ms por chamada PreToolUse (regex parse SCOPE.md + filesystem read único). Cache OS filesystem mata custo I/O em chamadas subsequentes da mesma sessão.
- **Custo IA:** Zero (hook não chama LLM).
- **Custo dev:** zero — silencioso quando Controller declarado.

## Onde está

- Hook: [`.claude/hooks/block-module-drift.ps1`](../../../.claude/hooks/block-module-drift.ps1)
- Smoke test: [`.claude/hooks/block-module-drift.test.ps1`](../../../.claude/hooks/block-module-drift.test.ps1)
- Este runbook: `memory/requisitos/Infra/RUNBOOK-block-module-drift.md`

## ADRs relacionadas

- [ADR 0080](../../decisions/0080-trust-tiers-operacional-audit-findings.md) — Module Charter (SCOPE.md)
- [ADR 0086](../../decisions/) — ActionGate warn-only durante calibração (pattern reusado)
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 §5 SoC brutal
- ADR 0082 — Pre-commit hook drift detection (derivada pendente — STRICT carries ADR Nygard "accepted")
- [ENFORCEMENT.md §2 #3](../../governance/ENFORCEMENT.md) — fonte canônica do mecanismo

## Histórico

- **v1.0.0** (2026-05-15) — Hook criado. Modo WARN default. 7/7 smoke passa.
