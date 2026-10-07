---
title: "NfeBrasil — Runbook da tela Tributação (/nfe-brasil/tributacao)"
module: NfeBrasil
tela: NfeBrasil/Tributacao/Index
owner: W
status: rascunho
last_validated: "2026-10-07"
preconditions:
  - "permissão nfe.tributacao.manage para as mutações e para a leitura pelo certificado"
  - "charter resources/js/Pages/NfeBrasil/Tributacao/Index.charter.md lido (status draft)"
  - "alvo de forma = prototipo-ui/cowork/Wagner/fiscal-tributacao.jsx (D-ANCORA, playbook Fiscal)"
steps:
  - "ler charter + Index.casos.md (UC-NFTR-01..18)"
  - "resolver a âncora com node scripts/design/ancora.mjs NfeBrasil/Tributacao/Index"
  - "mudança de comportamento entra com UC + teste que o cita (casos-gate)"
  - "Pest no CT 100; e2e na lane e2e-gate.yml"
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
---

# RUNBOOK — Tributação (`/nfe-brasil/tributacao`)

> **Por que existe:** o hook `block-mwart-violation` exige um RUNBOOK por tela antes de editar o
> `.tsx` (ADR 0104 §F1). A tela já está em produção desde 2026-05-10; este arquivo nasceu na
> thread 22 do playbook Fiscal (2026-10-07) para registrar como mexer nela, não para replanejá-la.

## Onde está cada coisa

| Peça | Arquivo |
|---|---|
| Página | `resources/js/Pages/NfeBrasil/Tributacao/Index.tsx` |
| Drawer "Configurar pelo certificado" | `resources/js/Pages/NfeBrasil/Tributacao/_components/ConfigurarPeloCertificado.tsx` |
| Lei / contrato | `Index.charter.md` · `Index.casos.md` (UC-NFTR-*) |
| Controller | `Modules/NfeBrasil/Http/Controllers/TributacaoController.php` (index, aplicarTemplate, toggleAutoEmission, CRUD de regra) |
| Leitura pelo certificado | `EmpresaFiscalLookupController` → `GET /nfe-brasil/tributacao/empresa-fiscal` (read-only) |
| Templates | `TributacaoTemplateService` (`listar` · `sugerir` · `aplicar`, exige NCM padrão) |

## Fluxo "Configurar pelo certificado" (UC-NFTR-14..18)

1. Botão no cartão "Configuração rápida por setor" abre um drawer lateral (Sheet, PT-02).
2. Passo 1 mostra CNPJ e razão social lidos. Passo 2 mostra cada campo com a fonte; regime
   divergente vira escolha e bloqueia o "Continuar" até ser escolhido (UC-NFTR-16/18).
3. Escolher o regime relê `?regime=` para reordenar as sugestões (UC-NFTR-15).
4. Passo 3 exige NCM de 8 dígitos e diferente de `00000000`. Passo 4 resume e é o único com
   "Aplicar template", que faz `POST templates/{slug}/aplicar` com `ncm_default` (UC-NFTR-17).
5. Fechar em qualquer passo não grava nada.

## Verificação

| O quê | Como |
|---|---|
| Backend | Pest no CT 100: `TributacaoTemplateAplicarTest`, `EmpresaFiscalLookupTest`, `TributacaoIndexContratoTest` |
| Tela | e2e `e2e/nfe-tributacao-onboarding.spec.ts` (lane `e2e-gate.yml`, leitura mockada) |
| Pós-merge | smoke em biz=1 (nunca biz=4): abrir o drawer, ir até o passo 4, fechar sem aplicar |
