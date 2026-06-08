---
date: "2026-06-07"
topic: "Mapa de governança & autoridade do oimpresso — o que protege o quê e quem pode afrouxar (trilho pro time MCP entrante)"
authors: [W, C]
---

# Mapa de Governança & Autoridade — o que protege o quê

> Rascunho 2026-06-07 (Wagner + Claude). Objetivo: trilho único pro time MCP
> (Felipe/Maiara/Eliana/Luiz) que está entrando. Responde: "o que cada um pode,
> não pode, quem programa onde, quem afrouxa regra perigosa."
> Complementa `2026-06-07-understand-governanca-modulo-tela.md`.

## TL;DR

Você já tem **~80% da governança montada**. A peça genuinamente faltando era a
**autoridade de DEV** (quem pode mexer no código por path) → agora rascunhada em
`.github/CODEOWNERS`. Falta só ativar (handles + toggle GitHub).

---

## As 5 dimensões × o que JÁ enforça cada uma

| # | Dimensão | Mecanismo | Status | Onde vive |
|---|---|---|---|---|
| 1 | **Meta** (pra que serve) | `SCOPE.purpose` + charter Mission | ✅ existe | 36/36 `Modules/*/SCOPE.md` |
| 2 | **Deve / pode / não pode** | `SCOPE.contains` / `not_contains` + charter Goals/Non-Goals | ✅ existe | SCOPE + ~100 charters |
| 3 | **Permissão** (quem ACESSA) | Spatie `can:modulo.acao` no controller + UI esconde | ✅ existe | 260+ permissions |
| 4 | **Autoridade DEV** (quem PROGRAMA) | **CODEOWNERS + branch protection** | 🟡 RASCUNHO | `.github/CODEOWNERS` (novo) |
| 5 | **Autoridade OVERRIDE** (quem afrouxa Tier 0) | ADR append-only + CODEOWNERS lock em `memory/decisions/**` | 🟡 agora explícito | constitucional + CODEOWNERS |

---

## Camada FÍSICA (regra que BLOQUEIA, não papel) — o que você JÁ tem

Texto não para ninguém; gate para. Inventário do que já trava PR/build:

| Guard | Protege | Arquivo |
|---|---|---|
| ✅ withoutGlobalScopes comment guard | multi-tenant Tier 0 (vazamento) | `tests/Unit/Guards/WithoutGlobalScopesCommentGuardTest.php` |
| ✅ PHPStan tenant scope | model sem `business_id` scope | `app/PhpStan/Rules/NoMissingTenantScopeRule.php` |
| ✅ phpunit annotation guard | disciplina de teste | `tests/Unit/Guards/PhpunitTestAnnotationGuardTest.php` |
| ✅ no-mock-in-prod (ratchet) | dado de mentira em prod | `scripts/no-mock-in-prod.mjs` |
| ✅ ds-guard / integrity-check | regressão de design-memory | `prototipo-ui/*.mjs` |
| ✅ FSM feature tests | comportamento FSM (red se quebrar) | `tests/Feature/Domain/Fsm/*` |
| 🟡 **CODEOWNERS** | **quem pode mexer no código** | `.github/CODEOWNERS` (RASCUNHO) |
| ⚪ dangerous_rules no SCOPE | educa o "porquê" antes do bloqueio | (opcional, não feito) |

---

## Proteção do FSM especificamente (a maior preocupação do Wagner)

Medo: time não conhece FSM → tende a arrancar por desconhecimento. **Já coberto por 3 camadas:**

1. **CODEOWNERS** — `app/Domain/Fsm/**` → `@wagner`. PR que toca FSM **não funde** sem você. _(após ativar)_
2. **Feature tests** — `ConsumirEstoqueAuditTest`, `CurrentStageIdBypassObserverTest` ficam **VERMELHOS** se a lógica FSM for arrancada/burlada.
3. **ADR 0143 append-only** — pra "remover FSM" precisa ADR nova com `supersedes`, que **só Wagner aprova**.

➡️ Conclusão: FSM já está bem defendido. CODEOWNERS fecha o último furo (autoridade de quem-edita).

---

## Tiers por zona (modelo conservador — ponto de partida)

| Zona | Tier | Quem mexe |
|---|---|---|
| FSM, dinheiro, estoque, fiscal, migrations, ADRs, motor mcp_* | **0** | só Wagner |
| Fiscal compartilhado (futuro) | **1** | Wagner + sênior |
| Cadastros / CRM / CMS / KB / planilha / ponto + testes dessas áreas | **2** | time livre |

Detalhe completo em `.github/CODEOWNERS`.

---

## Lição de casa do Wagner (só ele consegue — eu não)

1. **@handles do GitHub** de Felipe / Maiara / Eliana / Luiz (+ confirmar `@wagnerra23`).
2. **Toggle**: GitHub → Settings → Branches → regra da `main` → ✅ "Require review from Code Owners".
   _Sem esse toggle, o CODEOWNERS é só documentação._

## O que o Claude faz a seguir (quando Wagner quiser)

- Finalizar CODEOWNERS com os handles reais → abrir PR próprio (commit-discipline: 1 PR = 1 intent).
- (opcional) Adicionar `dangerous_rules:` nos SCOPE.md dos módulos $ / estoque / fiscal — camada de educação.
- (opcional) Promover ADR mãe consolidando as 5 dimensões (formaliza pro time).
