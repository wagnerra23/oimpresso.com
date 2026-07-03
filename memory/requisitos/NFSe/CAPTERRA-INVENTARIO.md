# CAPTERRA-INVENTÁRIO — NFSe

> Gerado por skill `comparativo-do-modulo` (v2.0) em 2026-07-03.
> Fontes: [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) + [SPEC.md](SPEC.md) + `Modules/NFSe/` + `resources/js/Pages/Nfse/`.
> ADR: [0089](../../decisions/0089-capterra-driven-module-evolution.md) (Capterra-driven) + [0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) (cliente como sinal).
> **Contexto de decisão:** Wagner confirmou 2026-07-03 **intenção de emitir NFSe real antes de 15/07/2026** → G-01 (DANFSe NT 008/2026) vira **P0 urgente**.

## Resumo

- ✅ **APROVADO: 5** de 20 (C06, C07, C11, C13, C17)
- 🟡 **PARCIAL: 8** (C01, C02, C03, C04, C08, C12, C18, C19)
- ❌ **AUSENTE: 7** (C05, C09, C10, C14, C15, C16, C20)
- **Nota de capacidade:** 45/100 (topo BR Focus/PlugNotas ~83) — ver FICHA §5.
- **Score médio dos gaps abertos:** P0-P1 (o vazio está no núcleo fiscal, não na periferia).

## Inventário detalhado (eixo Features)

| Cap | Capacidade | Score | Status | Evidência | Próximo passo |
|---|---|:-:|:-:|---|---|
| C06 | Certificado A1 encrypted + validação | P0 | ✅ APROVADO | `NfseCertificado` (cert_pfx_encrypted/senha_encrypted, pfxDecriptado/senhaDecriptada) + `validarCertificado():245` + `ImportarCertificadoCommand` + `NfseCertificadoMultiTenantIsolationTest` | — (UI de upload = US-014) |
| C07 | Multi-tenant Tier 0 (cert+emissões isolados) | P0 | ✅ APROVADO | `NfseBusinessScope` + `MultiTenantIsolationTest` + ambiente per-business `montarPayload():80` | — |
| C11 | Idempotência SHA256 | P1 | ✅ APROVADO | `NfseEmissaoService::emitirInterno:113-121` + Pest | — |
| C13 | Vínculo venda→NFSe (`transaction_id`) | P1 | ✅ APROVADO | `NfseEmissaoPayload.transactionId` + `TransactionNfseObserver` + migration `2026_05_03_add_transaction_id` | — (mapear item→LC116 = melhoria) |
| C17 | LGPD retention + PiiRedactor fiscal | P2 | ✅ APROVADO | `Config/retention.php` + `marcarErro():260` (PiiRedactor) + OTel spans + Wave25/26 Pest | — |
| C01 | Emitir NFSe + apuração ISS | P0 | 🟡 PARCIAL | `SnNfseAdapter::emitir/buildDps:51` (HTTP real) + `NfseEmissaoService::emitir:101` + 13 Pest — MAS **DPS hand-rolled** (TODO US-004 lib), **0 emissão real** | **US-NFSE-013** (emitir real) + **[NOVO] US-NFSE-017** (validar DPS vs RTC v2.00) |
| C02 | Cancelamento (evento) | P0 | 🟡 PARCIAL | `cancelarInterno:211` + `SnNfseAdapter::cancelar:98` (motivo ≥15) — MAS ambiente global + 0 prod | **US-NFSE-015** (ambiente per-business) |
| C03 | Config municipal por business | P0 | 🟡 PARCIAL | `nfse_provider_configs` (schema completo) + `NfseSeeder` — MAS sem UI | **US-NFSE-014** (tela /nfse/config) |
| C04 | Multi-prefeitura / cobertura | P0 | 🟡 PARCIAL | arquitetura SN-NFSe nacional cobre conveniados; `SnNfseAdapter` único — MAS 1 muni seeded, sem ABRASF | (later) adapter ABRASF só se surgir muni não-conveniado |
| C08 | RPS / consulta status async | P1 | 🟡 PARCIAL | RPS gerado `montarPayload:41` + `consultar():81` — MAS ambiente global + sem contingência offline | **US-NFSE-015** (mesmo fix ambiente) |
| C12 | Emissão automática por evento | P1 | 🟡 PARCIAL | `TransactionNfseObserver` cria **rascunho** — MAS não emite sozinho | (later) gatilho auto (mercado: eNotas) |
| C18 | Alerta proativo cert vencendo | P2 | 🟡 PARCIAL | `NfseHealthCommand::checkCertVencimento:144` (30d WARN) — MAS health-CLI, sem cron notificação | **[NOVO] US-NFSE-020** (cron alerta) |
| C19 | API REST pública | P3 | 🟡 PARCIAL | `Routes/api.php` existe — sem Sanctum/rate-limit/docs | (later) só se modelo SaaS-API |
| C05 | **DANFSe (PDF) próprio** | P0 | ❌ AUSENTE | depende de `data['urlDanfse']`; `NfseController:261` só proxia | **🔴 [NOVO] US-NFSE-016 URGENTE** (NT 008/2026, 15/07) |
| C09 | Substituição de NFSe | P1 | ❌ AUSENTE | grep 0 matches | **[NOVO] US-NFSE-018** |
| C10 | Webhook/callback de eventos | P1 | ❌ AUSENTE | só polling; retention prevê log sem dispatcher | **[NOVO] US-NFSE-019** |
| C14 | Carta de correção | P2 | ❌ AUSENTE | grep 0 matches | **[NOVO] US-NFSE-022** |
| C15 | UI config cert+fiscal | P2 | ❌ AUSENTE | US-014 todo | coberto por **US-NFSE-014** |
| C16 | Dashboard métricas | P2 | ❌ AUSENTE | BRIEFING gap #3 | **[NOVO] US-NFSE-021** |
| C20 | Readiness Reforma (CBS/IBS) | P3 | ❌ AUSENTE | `cTribNac` hardcoded '010100' | (later) quando RTC exigir |

## Tasks propostas (aguardando aprovação Wagner)

> Priorizadas P0→P3. **3 gaps já têm US no SPEC** (`todo`) — proposta é DESBLOQUEAR, não recriar.

### 🔴 P0 urgente (prazo regulatório 15/07/2026)
1. **[P0 · NOVO] US-NFSE-016 — Geração própria do DANFSe (NT 008/2026)** — a API ADN de DANFSe é descontinuada 15/07; responsabilidade passa ao emissor. Sem isso o PDF de qualquer nota quebra. _Cap C05. Esforço M-L (~10-14h)._

### P0 (desbloquear emissão real)
2. **[P0 · NOVO] US-NFSE-017 — Validar DPS vs leiaute RTC v2.00 / integrar `nfse-nacional/nfse-php`** — o DPS hoje é hand-rolled; pré-req da 1ª emissão real não ser rejeitada pela SEFIN. _Cap C01. Esforço M (~6-8h)._
3. **[P0 · EXISTE `todo`] US-NFSE-013 — Emitir 1 NFSe real** (marco da SPEC) — desbloquear cert A1 (Wagner+contador). _Cap C01. Humano-limitado._
4. **[P0 · EXISTE `todo`] US-NFSE-015 — Ambiente per-business em `consultar()`/`cancelar()`** — fecha bug latente antes do 1º cancelamento do biz=164 em prod. _Cap C02/C08. Esforço S (~2-3h)._

### P1 (ciclo de vida da nota)
5. **[P1 · NOVO] US-NFSE-018 — Substituição de NFSe** (evento, mantém vínculo original↔nova, janela ≤730d). _Cap C09._
6. **[P1 · NOVO] US-NFSE-019 — Webhook/callback assíncrono** de eventos SEFIN (substitui polling). _Cap C10._
7. **[P1 · EXISTE `todo`] US-NFSE-014 — Tela `/nfse/config`** self-service (cert + dados fiscais por business). _Cap C03/C15._

### P2 (operação)
8. **[P2 · NOVO] US-NFSE-020 — Alerta proativo cert A1 vencendo** (cron D-30/D-7/D-1 reaproveitando `NfseHealthCommand`). _Cap C18. Esforço S (~2h)._
9. **[P2 · NOVO] US-NFSE-021 — Dashboard métricas NFSe** (volume/erro/ISS pago). _Cap C16._
10. **[P2 · NOVO] US-NFSE-022 — Carta de correção** (campo discriminação). _Cap C14._

> **later / sem sinal (ADR 0105):** adapter ABRASF (C04), emissão auto por evento (C12), API pública Sanctum (C19), readiness CBS/IBS (C20) — só quando houver sinal de cliente/regulatório.

**Aprovação:** responda `todos` / `nenhum` / `P0` / `P0+P1` / lista (`1,2,4`). As NOVAS (016-022) viram `tasks-create` no MCP + apêndice no SPEC; as EXISTENTES (013/014/015) eu só re-priorizo/comento.
