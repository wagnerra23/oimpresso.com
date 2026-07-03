---
date: '2026-07-03'
topic: "Capterra de capacidade do módulo Cliente (onda standalone, Passo 1) — benchmark cadastro+CRM vs 10 concorrentes BR (Bling/Tiny/Omie/Conta Azul + RD Station/Pipedrive/Agendor), foco LGPD, nota 65/100, leitura adversarial do que a nota 77 (screen-grade) esconde"
authors: [C]
tipo: session-log
agente: capterra-senior
onda: cliente-standalone
modulo: Cliente
nota_capacidade: 65
screen_grade_ref: 77
websearches: 36
related_adrs: [0089-capterra-driven-module-evolution, 0093-multi-tenant-isolation-tier-0, 0179-cliente-drawer-760px-substitui-show-fullpage, 0301-separar-cliente-deprecar-crm-pipeline]
---

# Session — Adversário de mercado do módulo Cliente (capterra-senior)

> **Data:** 2026-07-03 · **Autor:** [CC] (worktree `nostalgic-moser`, branch fresca de `origin/main@aef311d0`)
> **Onda:** standalone Cliente (programa de ondas), **Passo 1 — Adversário concorrente**. OK [W] 2026-07-03 (fila Produto→Cliente).
> **Tipo:** research read-only + geração de doc canônico. Zero código tocado.

## Objetivo

Rodar o adversário de mercado (`capterra-senior`) sobre o módulo **Cliente** (cadastro de clientes/contatos PII-heavy) e gerar `memory/requisitos/Cliente/CAPTERRA-FICHA.md` (10 seções canônicas, nota 0-100) — a **ficha de capacidade** que faltava (o módulo já tinha BRIEFING + SPEC + screen-grade, mas não a ficha vs mercado).

Pré-check T6: Cliente é **operacional core** (não pertence ao `_Roadmap_Faturamento`) → onda **standalone**, exige OK [W] (dado). Não duplica roadmap existente.

## O que foi feito

1. **Base fresca** — worktree novo de `origin/main` (o checkout da sessão estava −4687 commits; guard `git-base-freshness-guard` disparou). Todo canon lido de origin/main.
2. **Leitura de calibração** — `template-onda-modulo.md` (Passo 1), `onda-1-sells/1.1-adversario-capterra.md`, e a `Sells/CAPTERRA-FICHA.md` (nota 60) como modelo de formato.
3. **Grounding do código** (agent Explore) — mapeou LGPD/PII, crédito, extrato, import, 360, multi-tenant, testes, com evidência `file:line`.
4. **Pesquisa competitiva** (agent research, ~36 WebSearch) — Bling/Tiny/Omie/Conta Azul (cadastro ERP BR) + RD Station/Pipedrive/Agendor (+Ploomes/HubSpot/Zoho de referência) em 7 dimensões, com foco **LGPD/PII**.
5. **Verificação própria de 2 fatos contestados** (Tier-0, load-bearing) — porque o agent contradisse a SPEC.
6. **Escrita da ficha** — 19 capacidades P0-P3, nota ponderada, §8 adversarial, session log.

## Achados-chave (verificados por mim, não só pelo agent)

- **`App\Contact` NÃO tem global scope.** A SPEC §1 e o BRIEFING afirmam *"usa global scope `business_id`"*, mas `app/Contact.php` **não tem `addGlobalScope`/`HasBusinessScope`/`booted()`** — o isolamento é `where('business_id')` **manual** em cada query (padrão UPOS legado). Só `ContactAddress` (filho) tem a trait. **A doc está à frente do código.** (→ §8 achado 3, gap G-02.)
- **Sem teste cross-tenant no `Contact` pai** — só no filho (`ContactAddressMultiTenantTest`, biz=1 vs biz=99). O registro do cliente em si não tem rede.
- **DSR não cobre `contacts`** — `LgpdEsquecerTitularTool` + `DsrService` existem e são bons, mas `searchableEntityMap()` lista só Jana chat/memória. Para o módulo que **é** o repositório de PII, **não há direito ao esquecimento do titular** (LGPD Art. 18 §VI). Ironia: a máquina (`PiiRedactor`/`DsrService`) já existe pra outros dados. (→ diferencial G-01.)
- **Limite de crédito é decorativo** — `credit_limit` (col 2018) + `isCustomerCreditLimitExeeded()` calcula, mas é **advisory**; o `store()` da venda não bloqueia. Omie hard-block, Tiny medidor no PDV. (→ G-03.)
- **Import sem preview/dedupe** — 27 colunas, parse direto no DB, sem preview-before-commit nem merge de duplicado.
- **Pontos fortes reais:** mascaramento PII server-side (`maskTaxNumber` + `logOnly` sem CPF/CNPJ + testes) — **à frente de todo ERP BR**; colunas de consentimento (`whatsapp_consent`/`email_consent`) com guardas — ERPs BR não têm; command palette ⌘K — lane vazia; 360 de 9 abas (com veículos, vertical Oficina).

## Resultado

**Nota de capacidade: 65/100** — entre Bling (~60) e Conta Azul (~66), abaixo de Omie (~72).
- Diferente de Sells (design 88-90 vs capacidade 60, abismo): aqui design 77 vs capacidade 65 (gap pequeno — cadastro maduro).
- O que a nota de design esconde não é "a conta não fecha", é **"o titular não tem porta de saída"** (erasure/portabilidade), o isolamento é manual sem teste no Contact, e o crédito é decorativo.

**Duas lanes de mercado vazias** identificadas (oportunidade de produto):
1. **Anonimização fiscal-aware do titular** — anonimiza PII **preservando o registro fiscal (NF, retenção)**. CRMs têm consentimento mas não têm NF; ERPs têm NF mas não têm erasure. Ninguém faz bem. **Alinha com a postura Tier-0/PII do oimpresso e a máquina já existe.**
2. **RFM nativo + fidelidade** — ninguém no set BR faz RFM nativo (Omie só Curva ABC).

## Top 3 P0 recomendados

1. **G-01** — estender `DsrService` pra `contacts` (anonimização fiscal-aware): obrigação LGPD + diferencial de mercado. Começar por aqui.
2. **G-02** — teste cross-tenant no `Contact` pai + avaliar global scope (alinhar código ao claim da doc).
3. **G-03** — limite de crédito com bloqueio/aviso na venda (⚠️ toca valor → Regra Mestre).

## Entregáveis

- `memory/requisitos/Cliente/CAPTERRA-FICHA.md` (novo — 10 seções, nota 65, 19 capacidades, 10 concorrentes).
- Este session log.

## Próximo (Passo 2 da onda, aguarda OK [W])

`/comparativo Cliente` → `CAPTERRA-INVENTARIO.md` (buckets ✅🟡❌) + batch `tasks-create` (`parent_plan=programa-ondas`) apendendo US-CRM-079+ ao SPEC. **Não criar tasks sem OK [W]** (publication-policy).

## Notas de método

- Nenhum valor BRL commitado (Tier 0). Crédito/saldo/extrato referidos como estrutura, não valor.
- 2 fatos contestados verificados à mão antes de publicar (global scope, cross-tenant test) — o adversário não repete o claim da doc; ancora no código.
