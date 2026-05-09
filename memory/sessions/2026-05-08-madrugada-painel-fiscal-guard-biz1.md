# 2026-05-08 madrugada — Painel fiscal completo + guard CI biz=1 + 3 ADRs canon

**Modelo:** Opus 4.7
**Duração:** ~6h (continuação noite-2 fechando US-NFE-002)
**PRs entregues:** 8 (#208, #212, #215, #216, #217, #218, #219, #220)

## Objetivo

Consolidar US-NFE-002 com painel fiscal completo na tela do certificado, blindar regra `business_id=1` em CI, e salvar decisões canônicas como ADRs.

## O que foi feito

### Cleanup biz_id=4 → 1 (Wagner regra "não testar empresa 4")

PR #208 corrigiu 14 arquivos NfeBrasil; PR #215 pegou 8 escapados; PR #216 sweep total Whatsapp+RB+Jana+Builders + guard CI permanente. **Total: 47 arquivos / 0 violações finais**.

Padronização:
- Default test = `business_id => 1` (Wagner)
- Cross-tenant adversário = `business_id => 99`
- Cliente real (4) NUNCA aparece em fixture

### Painel fiscal completo (PR #219)

5 cards na tela `/nfe-brasil/configuracao/certificado`:
1. Status atual (existente) + fallback CNPJ business
2. **Identificação fiscal** ✨ — CNPJ business + razão + regime + localização
3. **Numeração e tributação default** ✨ — NCM, CFOP, CSOSN, série, último/próximo nº
4. **Ambiente SEFAZ** ✨ — radio Homologação/Produção + Salvar
5. Testar conexão SEFAZ (PR #215)

### Bug runtime crítico consertado (PR #217)

`Tools::model()` em sped-nfe v5+ exige `?int`, código passava `string` → TypeError em runtime real. Tests Pest mockavam Tools sem assertion de tipo. Fix: `(int) $modelo` cast + try/catch envolvendo TUDO em `consultarStatusSefaz` + payload de erro com UF/ambiente.

### ADRs canon salvos (PR #218)

- [0101](../decisions/0101-tests-business-id-1-nunca-cliente.md) Tests SEMPRE biz_id=1
- [0102](../decisions/0102-nfce-status-polling-vs-broadcast.md) UI status NFC-e via polling (broadcast adiado)
- [0103](../decisions/0103-eventos-fiscais-separados-por-modelo.md) Eventos fiscais separados por modelo NFe

## Aprendizado meta

🚨 **Mocks que ignoram tipos de assinatura escondem TypeErrors em runtime.** Tests Pest do `NfeService` mockavam `Tools` sem cobertura do contrato `Tools::model(?int $model)`. Bug só apareceu quando Wagner clicou o botão real em prod. Fix anti-regressão: 4 tests novos capturam `$arg` real recebido por `model()` e fazem `expect(...)->toBeInt()`.

Vale pra qualquer mock de lib externa: testar **forma do contrato** (tipos), não só **comportamento esperado**.

## Setup biz=1 final (Wagner WR2 Sistemas, Tubarão/SC)

Pronta pra smoke real homologação SEFAZ — falta apenas habilitar flag `NFEBRASIL_AUTO_EMISSION_NFCE=true` + criar venda fictícia.

| Pré-requisito | Status |
|---|---|
| Cert A1 + CNPJ + NCM padrão | ✅ |
| Ambiente=2 (homologação) | ✅ |
| Template Simples SC aplicado | ✅ |
| Painel fiscal UI completo | ✅ |
| Botão "Testar agora" funcional | ✅ (fix #217 em prod) |
| Flag NFC-e auto-emission | ❌ opt-in Wagner |

## PRs Maiara obsoletos

[#184](https://github.com/wagnerra23/oimpresso.com/pull/184) e [#191](https://github.com/wagnerra23/oimpresso.com/pull/191) ficaram com branches ~25 PRs atrás de main. Mergear iria reverter features importantes (templates L1, painel fiscal, guard CI). Comentei nos 2 sugerindo refazer.

## Próxima sessão

1. Smoke real homologação SEFAZ biz=1 (10min com Wagner online)
2. Habilitar flag + criar venda fictícia → emitir NFC-e teste
3. Verificar `cstat 100` autorizado em SEFAZ-SC homologação
4. Goal #5 NfeBrasil pode fechar
