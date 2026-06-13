---
date: "2026-06-13"
topic: "Re-triage 32-thread do eixo FAILURE determinístico (155 arquivos): 91 stale→quarentena, 33 env-coupled, 11 unclear, 11 product-bugs confirmados (4 já em PR, 7→US-GOV-019); refutador adversarial matou 9 falsos-positivos. ADR 0276."
authors: [W, C]
related_adrs: ["0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes", "0062-separacao-runtime-hostinger-ct100"]
prs: []
---

# Re-triage do eixo FAILURE (US-GOV-018 / US-GOV-019) — 32 threads + refutador

> **Origem:** Wagner "faça em números acima de 30 threads". Workflow `wnw19l15c` — 32 threads de classificação (partição por módulo `NR%32`) + refutador adversarial (ADR 0276) nos claims de bug. 52 agents, 4.8M tokens, read-only sobre o repo + o dataset local do junit.
> **Insight que destravou fazer sem esperar o run limpo:** o adversário (sessão anterior) provou que **o eixo ERROR oscila** (ruído de harness, removido pela Frente A #2640) e **o eixo FAILURE é o floor determinístico** — logo os 385 ExpectationFailed em 154 arquivos são **estáveis entre runs** e podem ser triados já.

## Quadro (155 arquivos classificados)

| Classe | n | Significado |
|---|--:|---|
| stale-quarentena | **91** | teste velho, produto OK → `@group legacy-quarantine` (ou test-fix) |
| env-coupled | 33 | deferir pro floor do run limpo `20260613-100035` |
| unclear | 11 | decisão humana (perguntas abaixo) |
| **product-bug confirmado** | **11** | sobreviveu ao refutador → dev |
| bug refutado | 9 | "bug" que era stale/env (adversário filtrou) |

## 🐛 11 bugs confirmados (cada um sobreviveu a um refutador adversarial)

**4 já corrigidos (draft PRs):**
- `ads:health` não registrado → **#2649** · `superadmin:health` não registrado → **#2647** · `macro_variant_id` fora do `$fillable` (US-WA-049 quebrada em prod) → **#2646** · `business_id=4` (RotaLivre real) em fixtures → **#2652**.

**7 que precisam de design → US-GOV-019:**
1. **ChannelUserAccess** (Tier 0): `UNIQUE` em coluna nullable não enforça "1 grant ativo por (channel,user)" — `2026_05_12_160000_create_channel_user_access_table.php:55-58` (fix = generated column).
2. **CSAT**: `InboxController::updateStatus:1042-1071` não dispara `DispatchCsatJob` em open→resolved.
3. **Vestuario DataController** ausente (ADR 0024) → etiquetas sem entrada no sidebar.
4. **WithoutGlobalScopes** (Tier 0): bypass de `business_id` sem `// SUPERADMIN:` em `KbCorpusBuilder.php:164,190`, `TituloAutoService.php:690,709,727`, `NfeService.php:745,760,942`.
5. **NFSe cancelar()**: falta `OtelHelper::spanBiz` em `NfseEmissaoService.php:198` (confirmar se Wave 28 exige).
6. **DESIGN.md**: link local quebrado (o run limpo lista qual).
7. **PhpunitTestAnnotationGuard**: migrar `/** @test */` → `#[Test]`.

## 🟡 91 quarentena
`@group legacy-quarantine` com razão. Por área: tests/Feature 46 · Financeiro 14 · Whatsapp 11 · Governance 3 · Jana 3 · PaymentGateway 3 · Officeimpresso 2 · tests/Unit 2 · Vestuario 2 · Cms/Connector/ConsultaOs/OficinaAuto/Ponto 1.
**Nuance (não quarentenar cego):** vários são **test-FIX rápido** — ex `ChannelsReconcilerCommandTest` (literais `$instanceId` 1-dígito curtos vs uuid-sem-traço); `SellControllerEndpointsTest` (string esperada sem o prefixo `transactions.`); `Copiloto/InstallControllerTest` (módulo renomeado Copiloto→Jana, atualizar nomes). Padrões dominantes: snapshot de `.tsx` refatorado (Sells → `SellsTabelaUnificada`), canon-source DS/ADR-frontmatter móvel, fixture de SPEC.md que o produto mudou de propósito (ex AssetManagement ganhou na_justified/suite Wave 27 vs `ModuleGradeServiceTest` v1).

## ⏸️ 33 env-coupled
Reconfirmar no floor do run limpo (A.1+A.2 ativos). Ex: `DelphiOImpressoContractTest`, `Cms/{SiteHome,SitePage,AuthSocial}`, `Admin/AuthMatrix`, `ChannelsControllerAutoPurgeBeforeConnect`, `ArquivosHealthCheckSchedule`, `WaveZ2DocumentationGuard`.

## ❓ 11 unclear — decisão Wagner (pergunta por item)
1. **Whatsapp/FeedbackRelevanceTest** — intent ADR 0195: floor de recência (+10 fresco) + rec=1 (+9.67) torna `<30` inalcançável. Bug de score ou expectativa do teste?
2. **Memory/AdrFrontmatterTest** — a lógica de extração de número (`:142`) falha em algum ADR; corrigir o teste ou há ADR malformado?
3. **ConsultaOs/SmokeRoutesTest** — SPLIT: index 200/500 é env-coupled; o resto?
4. **PaymentGateway/OndaDoisSchemaEScopeTest** — SPLIT: `gateway_webhook_events` hasTable/hasColumn → env-coupled; resto?
5. **Whatsapp/ContactObserverCacheInvalidationTest** — CACHE-01: typo de fixture (dígitos) ou gap real de country-code na invalidação?
6. **Whatsapp/SendWhatsappMessageJobTest** — código satisfaz o contrato na leitura estática; rodar isolado pra ver o que falha.
7. **Whatsapp/AutoLinkContactTest** — rodar `--filter R-WA-078-008` pra capturar o expect() que falhou.
8. **Jana/TaskParserServiceTest** — rodar isolado contra `parseFrontmatterInline` (case `est...`).
9. **NFSe/Wave27PolishTest** — rodar status+fiscais no full-suite e logar o retorno real de `NfseEmissao...`.
10. **Ponto/Wave18RetryEscalaTurnoTest** — confirmar se o checkout MySQL tinha o trait em Escala.
11. **Utils/NumUfHeuristicPtBRTest** — não é bug (num_uf correto) nem alvo movido; fixture corrompida — checar origem.

## Validação (floor)
Run limpo `20260613-100035` (A.1+A.2 ativos) rodando — o floor real é a prova da Frente A + confirma os 33 env-coupled. _Atualizar aqui quando fechar._

## Adversarial (ADR 0276)
O refutador derrubou **9 de 20 claims de bug** (eram stale/env), deixando os 11 sólidos. Padrão: a re-triage anterior (eixo ERROR) errou 2× por premissa; aqui o eixo FAILURE é estável e cada bug tem evidência arquivo:linha.

**Segurança/PII:** read-only no repo + junit local; zero PII (só FQCN/arquivo/linha/assertion). Dataset: `run/fv-triage/*.tsv` (gitignored) + output do workflow `wnw19l15c`.
