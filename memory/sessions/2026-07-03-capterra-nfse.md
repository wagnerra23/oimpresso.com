---
title: "Capterra sênior — Modules/NFSe (emissão ISS / NFSe serviço)"
type: session
date: 2026-07-03
module: NFSe
agent: capterra-senior
related_docs:
  - memory/requisitos/NFSe/CAPTERRA-FICHA.md
  - memory/requisitos/NFSe/BRIEFING.md
  - memory/requisitos/NFSe/SPEC.md
  - memory/requisitos/NFSe/PESQUISA_TUBARAO.md
---

# Session — Capterra sênior NFSe (2026-07-03)

## TL;DR (5-10 linhas)

- **Escopo:** benchmark de **capacidade fiscal** do `Modules/NFSe` (NFSe = serviço, ISS municipal, SN-NFSe federal) vs os líderes de API de NFSe. **Distinto de `Modules/NfeBrasil`** (NF-e produto, ICMS) — não sobrepõe.
- **Nota de capacidade: 45/100** vs topo BR (Focus/PlugNotas ~83, eNotas ~72). Gap -38.
- **O módulo é bem-arquitetado e nunca emitiu 1 NFSe** — Service+Adapter reais (idempotência, retry, cert encrypted, multi-tenant Tier 0, LGPD) mas **0 emissão em produção** (marco US-NFSE-013 `todo`, cert A1 bloqueado em Wagner+contador).
- **🔴 Achado regulatório LIVE:** NT 008/2026 descontinua a **API ADN de geração do DANFSe em 15/07/2026** (12 dias). O oimpresso **não gera** DANFSe (depende de `urlDanfse` do provider) → PDF quebra. Gap P0 imediato.
- **Contexto de mercado em choque:** **Nuvem Fiscal desliga 31/07/2026**; API DANFSe muda 15/07; LC 214/2025 tornou NFSe Nacional obrigatória em 01/01/2026 (~2000 municípios conveniados).
- **Diferenciais reais:** SN-NFSe direto = **custo zero per-emissão** + vínculo venda→NFSe nativo + Tier 0. Modelo econômico e engenharia sólidos; falta a prova de campo.
- **Recomendação (sinal fraco, ADR 0105):** **não abrir onda completa.** Manutenção mínima; gatilho pra onda = biz=164 Martinho (ou candidato ComunicacaoVisual) emitir de fato + reportar. Único item potencialmente urgente: G-01 (DANFSe NT 008) SE houver intenção de emitir antes de 15/07.

---

## 1. Método e worktree

- Worktree fresco de `origin/main` (`7442c27c43`), checkout base estava −4688 commits (stale, ignorado).
- Read-only: nenhuma edição em `Modules/NFSe/` nem em código; apenas os 2 artefatos canônicos (FICHA + este log).
- Fase 1 (pesquisa concorrentes) + Fase 3 (leitura de código/memória) — comparação honesta com evidência `file:line`.

## 2. Pesquisa expandida — concorrentes (Fase 1)

### Landscape regulatório 2026 (o que muda o jogo)
- **LC 214/2025** tornou a **NFS-e Nacional obrigatória** para todo prestador a partir de **01/01/2026**. Municípios que não aderiram perdem transferências voluntárias federais.
- **Adesão:** dos **1.472** municípios que assinaram convênio, **291** já usavam efetivamente (mai–jul/2025); **~2.000** ativaram convênio em 01/01/2026 (de 5.570 municípios). Ainda é **mundo híbrido**: Nacional (conveniados) vs ABRASF/próprio (não-conveniados) — por isso Focus mantém **2 APIs**.
- **🔴 NT 008/2026 (SE/CGNFS-e):** define padrão nacional único do **DANFSe** (layout, QR Code, tributário) e **descontinua a API do ADN que gera o DANFSe** — data prorrogada de **01/07 → 15/07/2026**. A geração passa a ser **responsabilidade do sistema emissor**.
- **Nuvem Fiscal** anunciou **desativação em 31/07/2026** — concorrente low-cost saindo do mercado (oportunidade de captura, não referência de topo).

### Players (parágrafo por concorrente)
- **Focus NFe** — API dev-first, **3.000+ prefeituras**, integra município novo por taxa fixa em ~15 dias. Oferece **2 APIs distintas**: NFSe Nacional (modelo unificado) + NFSe tradicional (padrões municipais/ABRASF). Webhooks, PDF, armazenamento legal. Referência-topo de cobertura. Fonte: focusnfe.com.br/nota-fiscal-servico-nfse.
- **PlugNotas (TecnoSpeed)** — hub "toda emissão numa API JSON única", **layout-agnóstico** (ABRASF + próprio + Nacional). Atualizou a rota com os campos do padrão Nacional + toggle "NFS-e Nacional" no cadastro da empresa. Fonte: plugnotas.com.br/nfse.
- **eNotas** — **automação-first** para e-commerce/infoproduto (Hotmart/Kiwify/Eduzz/30+ meios de pagamento). Emite por regra (na cobrança/pagamento/pós-garantia), **cancela automático em reembolso**, split/coprodução, **500+ prefeituras** no padrão unificado. Diferencial = gatilho automático de emissão, que o oimpresso não tem. Fonte: enotass.com.br.
- **Nuvem Fiscal** — API REST fiscal (NFe/NFCe/NFSe/MDFe/CTe), DX boa, low-cost. **Desativa 31/07/2026.** Fonte: dev.nuvemfiscal.com.br/docs/nfse.
- **TecnoSpeed** — middleware fiscal ERP (mesma casa do PlugNotas), robustez de topo BR. Fonte: blog.tecnospeed.com.br/api-nfse.
- **NFE.io** — API + bundle financeiro/contábil, cobertura ampla, DX boa.
- **Notaas** — **freemium** (até 50 notas/mês grátis, webhook no free, white-label) — modelo de captura PME. Fonte: notaas.com.br.
- **Bling/Omie/Conta Azul** — ERPs PME BR com NFSe embutida via provider; migraram pro Nacional em 2026. Concorrência do "ERP faz tudo" com UI legada.
- **SN-NFSe / Emissor Nacional (gov.br)** — portal/API **oficial gratuito**, é o padrão que o oimpresso consome direto. O "faça você mesmo" sem UX nem vínculo à venda.

### Eventos do padrão nacional (define P0/P1)
- **Cancelamento** (evento) — anula nota emitida por engano; prazos variam por município.
- **Substituição** (evento cancelamento-por-substituição) — corrige mantendo vínculo original↔nova; janela ~6 meses do fato gerador / emissão original ≤730 dias.
- **Carta de correção** — só campo **DISCRIMINAÇÃO DOS SERVIÇOS** (varia por município; SP permite).
- **Conversão de RPS** — RPS offline → NFSe posterior; **data do fato gerador = data do RPS** (base do imposto).

## 3. Comparativo com o oimpresso (Fase 3 — evidência de código)

Leitura de `Modules/NFSe/` (Service, Adapter, Models, migrations, Controller, Pages, Tests) + memória (BRIEFING/SPEC/PESQUISA).

**Real e sólido (grep-confirmado):**
- `NfseEmissaoService::emitir` — idempotência SHA256 (`idempotency_key`, guard antes de create), retry 3× backoff só em `ProviderTimeoutException`, 9 exceções tipadas PT-BR, OTel span `nfse.emissao`. (`NfseEmissaoService.php:101-196`)
- `SnNfseAdapter::emitir` — HTTP POST real `{baseUrl}/nfse` com `buildDps` + cert temporário pro cURL; `tpAmb`/endpoint **per-business** via `$payload->ambiente`. (`SnNfseAdapter.php:51-79`)
- Cert A1 encrypted (`NfseCertificado.cert_pfx_encrypted/senha_encrypted`, `pfxDecriptado/senhaDecriptada`), validação `isExpirado()` bloqueia emissão. Import via `ImportarCertificadoCommand` (artisan).
- Multi-tenant Tier 0: `NfseBusinessScope` + `withoutGlobalScopes` com business_id explícito + `NfseCertificadoMultiTenantIsolationTest` + `MultiTenantIsolationTest`.
- `nfse_provider_configs` schema completo (IBGE/IM/CNAE/LC116/alíquota/série/ambiente) + `NfseSeeder` (Tubarão 4218707).
- LGPD: `Config/retention.php` (5y CONFAZ / 1y erro+webhook) + `PiiRedactor` em `marcarErro` + logs canal `nfse`.
- `NfseHealthCommand` — 5 checks incl. `cert_vencimento_alarme` (30d WARN).
- 3 Pages Inertia (Index/Emitir/Show) por `NfseController`.
- Vínculo venda→NFSe: `transaction_id` no payload/emissão + `TransactionNfseObserver` (rascunho no recurring).

**Ausente / pendente / risco (grep 0 matches ou TODO):**
- **DANFSe próprio** — NÃO gera; `pdfUrl: $data['urlDanfse']` (provider). `NfseController:261` só proxia. 🔴 NT 008/2026.
- **1 NFSe real em prod** — US-NFSE-013 `todo` (cert A1 bloqueado). 0 emissão.
- **DPS hand-rolled** — `SnNfseAdapter.php:22` comenta "TODO US-NFSE-004: integrar lib `nfse-nacional/nfse-php`". `buildDps` é `infDps` simplificado; não validado contra XSD RTC v2.00.
- **Substituição** — ausente. **Carta de correção** — ausente. **Bulk/lote** — ausente. **Webhook** — ausente (só polling `consultar()`; retention prevê log mas sem dispatcher).
- **Tela `/nfse/config`** — US-NFSE-014 `todo`; config via seeder/DB/tinker.
- **Bug latente:** `SnNfseAdapter::consultar/cancelar` usam **bind global** `config('nfse.ambiente')`, não per-business (US-NFSE-015). Vira bug no 1º cancelamento do biz=164 em prod.

## 4. Cálculo da nota (tabela bruta cap × peso)

| ID | Cap | Tier | nota/10 | peso | contrib |
|---|---|:-:|:-:|:-:|:-:|
| C01 | Emitir NFSe + ISS | P0 | 5 | 4 | 20 |
| C02 | Cancelamento | P0 | 4 | 4 | 16 |
| C03 | Config municipal/business | P0 | 5 | 4 | 20 |
| C04 | Multi-prefeitura/cobertura | P0 | 4 | 4 | 16 |
| C05 | DANFSe (PDF) | P0 | 1 | 4 | 4 |
| C06 | Cert A1 encrypted | P0 | 8 | 4 | 32 |
| C07 | Multi-tenant Tier 0 | P0 | 9 | 4 | 36 |
| C08 | RPS/consulta async | P1 | 4 | 2 | 8 |
| C09 | Substituição | P1 | 0 | 2 | 0 |
| C10 | Webhook | P1 | 1 | 2 | 2 |
| C11 | Idempotência | P1 | 9 | 2 | 18 |
| C12 | Emissão automática por evento | P1 | 3 | 2 | 6 |
| C13 | Vínculo venda→NFSe | P1 | 7 | 2 | 14 |
| C14 | Carta correção | P2 | 0 | 1 | 0 |
| C15 | UI config | P2 | 1 | 1 | 1 |
| C16 | Dashboard métricas | P2 | 2 | 1 | 2 |
| C17 | LGPD retention+PII | P2 | 8 | 1 | 8 |
| C18 | Alerta cert vencendo | P2 | 4 | 1 | 4 |
| C19 | API pública | P3 | 2 | 0.5 | 1 |
| C20 | Readiness reforma CBS/IBS | P3 | 1 | 0.5 | 0.5 |

Σ ponderado = 208.5 · Máx = 460 · **nota = 208.5/460×100 = 45.3 → 45/100**.

Referência-topo (Focus/PlugNotas) estimada ~83; eNotas ~72 (mais automação, menos cobertura).

## 5. Diferenciais defensivos (pra call comercial)

1. **Custo zero per-emissão** (SN-NFSe direto) — provedores cobram assinatura + por-nota + município novo. Argumento de TCO forte pra quem emite volume.
2. **NFSe emitida da própria tela de venda** (`transaction_id`) — sem alt-tab pro portal, sem re-digitar tomador. ERP nativo, não API client.
3. **Multi-tenant Tier 0 auditável** — cert e emissões isolados por business, ambiente per-business (produção de 1 cliente não vaza).
4. **LGPD fiscal by-design** — retention CONFAZ 5y + PiiRedactor no payload de erro.

**Contra-argumento honesto do concorrente:** "vocês nunca emitiram 1 nota, não geram o DANFSe que a lei passa a exigir, e cobrem 1 cidade — nós cobrimos 3000 e emitimos milhões." Verdadeiro hoje.

## 6. Recomendação e próximos passos

- **Não abrir onda completa** (ADR 0105 sinal fraco: 0 cliente emitindo).
- **Avaliar G-01 (DANFSe NT 008/2026) como hotfix defensivo** só se houver intenção real de emitir antes de 15/07/2026. Sem intenção de emitir → dano hoje = zero (não emite).
- **Gatilho pra onda cheia (G-01..G-06):** biz=164 Martinho começar a emitir + reportar, OU `Modules/ComunicacaoVisual` ativar com candidato prestador de serviço.
- Ficha arquivada como **decisão-suporte**. Próxima revisão 2026-10-03 ou no gatilho.

## 7. Pergunta ao Wagner

Wagner, a ficha está pronta (`memory/requisitos/NFSe/CAPTERRA-FICHA.md`, nota **45/100**). Dois pontos que precisam da sua decisão:

1. **Há intenção de emitir NFSe real (biz=1 ou Martinho biz=164) antes de 15/07/2026?** Se sim, o G-01 (gerar DANFSe conforme NT 008/2026) vira urgente — a API oficial que gera o PDF hoje é descontinuada nessa data. Se não, o módulo fica em manutenção mínima e o dano é zero.
2. Quer rodar `/comparativo NFSe` pra cruzar esta ficha com o SPEC e propor batch de tasks (US) — ou deixa como decisão-suporte arquivada até haver sinal de cliente emitindo?
