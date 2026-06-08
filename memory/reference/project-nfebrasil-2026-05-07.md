---
name: NfeBrasil estado 2026-05-07 — US-NFE-002 fechada server-side, biz=1 pronta pra smoke
description: Estado consolidado pós-sessão noite-2 2026-05-07. Pipeline NFC-e completo, biz=1 (Wagner) pronta pra primeiro smoke real SEFAZ-SC homologação
type: project
---
**Snapshot:** 2026-05-07 — sessão noite-2 (Opus 4.7 + Wagner)

## Pipeline US-NFE-002 server-side (FECHADO)

7 PRs mergeados em sequência fecharam emissão NFC-e ponta-a-ponta:

```
Venda finalizada → SellCreatedOrModified
  → EmitirNfceAoFinalizarVenda           [PR #193]
  → EmitirNfceJob                        [PR #193 + #198 + #201]
     → NfeService::emitirParaTransaction [PR #198]  ← XML + assina A1 + envia SEFAZ
        → cstat 100 → status='autorizada'
     → event(NFCeAutorizada)             [PR #201]
        → EnviarDanfeNFCePorEmail        [PR #200]  (flag opt-in default off)
UI Page /nfe-brasil/transactions/{tx}/status [PR #203]
  → useNfceStatus polla 2s × 30 → NfceStatusBadge atualiza
```

**PRs envolvidos:** #193 (listener), #194 (templates L1), #195 (+4 templates), #196 (TransactionBuilder), #198 (fase 2A), #199 (+3 templates MEI/MG/RS), #200 (NFCeAutorizada+listener email), #201 (Job dispatch event), #203 (UI status polling), #208 (fix biz=4→1 tests), #212 (template SC).

## Biblioteca templates L1 (auto-discovery `Modules/NfeBrasil/Resources/templates/`)

11 templates. Cobertura: 4 UF (SP/RJ/MG/RS/SC com SC novo) + 4 regimes (simples/presumido/real/mei) + 3 setores (comércio varejo/atacado/indústria gráfica).

| Slug | Setor | Regime | UF |
|---|---|---|---|
| comercio-varejo-simples-sp | comércio | simples | SP |
| comercio-atacado-simples-sp | comércio | simples | SP |
| industria-grafica-simples-sp | indústria | simples | SP |
| comercio-varejo-presumido-sp | comércio | presumido | SP |
| industria-grafica-presumido-sp | indústria | presumido | SP |
| comercio-varejo-real-sp | comércio | real | SP |
| comercio-varejo-simples-rj | comércio | simples | RJ |
| comercio-varejo-simples-mg | comércio | simples | MG |
| comercio-varejo-simples-rs | comércio | simples | RS |
| **comercio-varejo-simples-sc** | comércio | simples | SC |
| mei-varejo-sp | comércio | mei | SP |

**Cobertura UF com FCP:** RJ + MG + RS (faltam GO + PA pra 5/5 dos estados FCP-2%).

## Estado biz=1 (Wagner, WR2 Sistemas)

- CNPJ presente, NCM padrão `49111000` (impressos)
- Cert A1 ativo (válido até 2026-08-06, ~91 dias)
- Ambiente SEFAZ = **2 (homologação)** — emissão teste segura
- UF = SC (Santa Catarina, Tubarão)
- Template `comercio-varejo-simples-sc` **aplicado** via UI (sessão noite-2)
- `nfe_business_configs` row criada (regime=simples, cfop=5102, csosn=102)
- `NFEBRASIL_AUTO_EMISSION_NFCE` não setado no .env — default false (Job não dispara em venda)
- `NFEBRASIL_EMAIL_DANFE_NFCE` default false (consumidor anônimo é caso normal NFC-e)

## Pendências pra próxima sessão

1. **Smoke real homologação SEFAZ-SC** — usar runbook em `runbook_smoke_sefaz_biz1.md`
2. **Habilitar flag NFEBRASIL_AUTO_EMISSION_NFCE=true** no .env Hostinger (decisão Wagner — opt-in)
3. **Templates GO + PA** (FCP 2%) pra fechar cobertura 5/5
4. **Integração Blade POS** legacy (`sale_pos/create.blade.php`) → Inertia + plugar `<NfceStatusBadge />` (PR grande)
5. **Broadcast Centrifugo CT 100** real-time — precisa ADR arquitetural sobre HTTP bridge

## Anti-regressão (testes existentes)

- 11 Pest tests `EnviarDanfeNFCePorEmailTest` (flag/modelo/chave/cross-tenant/happy/retry)
- 9 Pest tests `EmitirNfceJobTest` (cross-tenant/idempotência/dispatch terminal/non-terminal)
- 8 Pest tests `NfeStatusControllerTest` (sem business/sem emissão/cross-tenant/modelo 55/pendente/autorizada/rejeitada/múltiplas)
- 12 smoke tests `TransactionBuilder` fluent API
- Tests de `EnviarDanfePorEmail` (NFe55), `MotorTributarioService`, `CertificadoService`, etc

## Refs

- US-NFE-002 spec: `memory/requisitos/NfeBrasil/SPEC.md`
- ADR ARQ-0006 (motor tributário cascade): `Modules/NfeBrasil/adr/arq/0006-...`
- ADR 0058 (Centrifugo > Reverb), ADR 0062 (Hostinger ≠ CT 100 runtime)
