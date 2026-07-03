---
casos: Configuração Fiscal · /fiscal/config
irmaos: Config.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-07-03"
---

# Casos de Uso & Aceite — Configuração Fiscal

> Persona: **Eliana (contadora)** — leitura/conferência fiscal. Cockpit Fiscal (agregador thin).
> Passo 3 do template-onda-modulo (régua por tela) — complementa a CAPTERRA-FICHA Fiscal (nota 75).
>
> **Status:** ✅ passa (UC-id citado por teste) · 🧪 tem teste Feature mas **sem UC-id** (débito G-2 · ADR 0264) · ⬜ não verificado · ❌ quebrou.
>
> ⚠️ **Débito = rastreabilidade, não ausência de teste.** Comportamento defendido por `ConfigControllerTest` + `SimplesOnlyGateConfigTest` + `SimplesOnlyGateTest`. Falta G-2: nenhum teste cita `UC-FISCAL-NN`. CT100 (ADR 0062).

## Backlog de casos (sem id — entram quando um teste citar o UC-id)
- **[BACKLOG · 🧪 tem teste · Tier 0] Senha do certificado A1 nunca vaza no payload** — Dado um cert A1 com `encrypted_password` · Quando a tela serializa o certificado pro Inertia · Então a senha encriptada está em `$hidden` e não aparece. _Coberto por `ConfigControllerTest::'NfeCertificado encrypted_password é hidden'`._
- **[BACKLOG · 🧪 tem teste · Tier 0] Certificados de outros tenants nunca aparecem** — Dado que Eliana está no business 1 · Quando a Config lista o cert ativo · Então só vê cert do business 1 (HasBusinessScope ADR 0093). _Coberto por `ConfigControllerTest::'NfeCertificado HasBusinessScope esconde certs de outros tenants'`._
- **[BACKLOG · 🧪 tem teste · feature-gate · Tier 0] SimplesOnly bloqueia export SPED de user comum com 503** — Dado a flag `fiscal.sped_simples_only_lock=true` e user com `fiscal.sped.export` · Quando pede `/fiscal/sped/icms-ipi/2026/5` · Então recebe 503 com "temporariamente bloqueado" + "GAP-FISCAL-003". _Coberto por `SimplesOnlyGateTest::'user comum com fiscal.sped.export é bloqueado por 503'`._
- **[BACKLOG · 🧪 tem teste · feature-gate] Superadmin bypassa o gate SimplesOnly** — Dado a flag true e user superadmin · Quando pede o SPED · Então NÃO recebe 503 (bypass Wagner). _Coberto por `SimplesOnlyGateTest::'superadmin bypassa flag'`._
- **[BACKLOG · 🧪 tem teste · feature-gate] Flag false libera o export pra user comum** — Dado `fiscal.sped_simples_only_lock=false` e user com permissão · Quando pede o SPED · Então NÃO recebe 503. _Coberto por `SimplesOnlyGateTest::'flag false libera download'`._
- **[BACKLOG · 🧪 tem teste · Tier 0] Gate de permissão é anterior ao gate de flag** — Dado user sem `fiscal.sped.export` · Quando pede o SPED (flag true) · Então recebe 403 (permissão), não 503. _Coberto por `SimplesOnlyGateTest::'user sem permissão recebe 403'`._
- **[BACKLOG · 🧪 tem teste] Flag SimplesOnly default = true e vive em config canon** — Dado a config do módulo · Quando checada · Então `fiscal.sped_simples_only_lock` é `true` por default e vive em `config/fiscal.php` (env `FISCAL_SPED_SIMPLES_ONLY_LOCK`), não hardcoded; `SpedController::gerar` referencia a flag. _Coberto por `SimplesOnlyGateConfigTest::'flag default = true'`, `'config key vive em config/fiscal.php'`, `'SpedController referencia a flag'`._
- **[BACKLOG · ⬜ sem teste] Gate `fiscal.config.edit` no acesso à tela** — Dado user sem `fiscal.config.edit` nem `superadmin` · Quando abre `/fiscal/config` · Então recebe 403 (`ConfigController::index` linha 29). _Comportamento no Controller, sem teste Feature dedicado._
- **[BACKLOG · ⬜ sem teste] Pílula de vencimento do cert (vencido / ≤30d proximo_vencimento / ok)** — Dado cert com `valido_ate` · Quando renderiza · Então `dias<0`→vencido, `dias<=30`→proximo_vencimento, senão ok. _Comportamento no Controller (`index`), sem teste._
- **[BACKLOG · ⬜ sem teste] Tributação default (CFOP/CSOSN/CST) exibida read-only** — Dado `NfeBusinessConfig.tributacao_default` · Quando o painel monta · Então mostra CFOP/CSOSN/CST + regime + série NFe + próximo número, sem permitir edição (edição vive em NfeBrasil canon). _Comportamento em `montarPainelFiscal`, sem teste; nota: `seriesMock` ainda é mock (Onda 2 I, TODO real)._

## Como rodar a suíte
1. **Pest (MySQL real):** lane Fiscal no CT100 (ADR 0062) — `ConfigControllerTest` + `SimplesOnlyGateTest` verdes; `SimplesOnlyGateConfigTest` roda sempre (config puro, sem DB). (SQLite skipa os que tocam `nfe_certificados`/`nfe_emissoes`/users, ADR 0101.)
2. **Cadência:** rodar ao fim de toda mexida. UC ❌ = regressão.

## Trilha do tempo
- 2026-07-03 · [CC] criado no Passo 3 do programa de ondas (régua por tela). Débito = UC-traceability.
