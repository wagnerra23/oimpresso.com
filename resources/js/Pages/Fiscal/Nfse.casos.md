---
id: resources-js-pages-fiscal-nfse-casos
casos: NFS-e Emitidas · /fiscal/nfse
irmaos: Nfse.charter.md (lei) · memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md (§6 CU)
tecnica: Caso de uso = narrativa do operador + critério de aceite (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-07-30"
last_run_ci: "0 UC executado nesta corrida — os 4 UC herdam testes que JÁ existem; veredito pendente das lanes PHP / Pest (NfeBrasil · MySQL) e Pest Fiscal"
related_us: [US-FISCAL-005]
---

# Casos de Uso & Aceite — NFS-e Emitidas

> Persona: **Eliana [E] (contadora)** — confere as notas de **serviço** do mês sem abrir o módulo NFSe legado.
>
> **Âncora:** `CU-FISC-04`, `CU-FISC-12` e `CU-FISC-13` do
> [SDD §6](../../../../memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md). Os UC derivam do **CU**, nunca do `.tsx`.
>
> **Status:** ✅ provado por teste verde que cita o UC · 🧪 tem teste, **veredito pendente da lane** · ⬜ não verificado · ❌ quebrou.

## Força do veredito

| Teste | Lane | Bloqueia merge? |
|---|---|---|
| `NfseCockpitMultiTenantTest` | `PHP / Pest (NfeBrasil · MySQL)` | ✅ **sim** — required no [baseline](../../../../governance/required-checks-baseline.json) e na allowlist do workflow |
| `NfseCockpitControllerTest` | `Pest Fiscal` (**pula** em SQLite) + suíte noturna CT 100 | ❌ **não** — advisory |

## Rastreabilidade

| UC | O que defende | Prio | CU (SDD §6) | Teste que o cita | Status |
|---|---|---|---|---|---|
| UC-FNFSE-01 | isolamento da listagem | `[must]` `[T0]` | CU-FISC-12 | `NfseCockpitMultiTenantTest` | 🧪 |
| UC-FNFSE-02 | gate de permissão da tela | `[must]` `[T0]` | CU-FISC-13 | `NfseCockpitControllerTest` | 🧪 |
| UC-FNFSE-03 | competência inválida não derruba | `[must]` | CU-FISC-04 | `NfseCockpitControllerTest` | 🧪 |
| UC-FNFSE-04 | os 6 indicadores da competência | `[should]` | CU-FISC-04 | `NfseCockpitControllerTest` | 🧪 |

---

## UC-FNFSE-01 — A lista nunca mostra NFS-e de outro business `[must]` `[T0]`

**Dado** notas de serviço do business ativo e de outro business
**Quando** a lista carrega
**Então** só as do business ativo aparecem.

- **Regressão que defende:** vazamento cross-tenant Tier 0 ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)). A contraparte deste teste usa um business **semeado** (não um id fictício), porque esta tabela tem chave estrangeira para o cadastro de empresas — detalhe que já derrubou o teste irmão do certificado.
- **Teste:** `Modules/Fiscal/Tests/Feature/NfseCockpitMultiTenantTest.php` — `it('UC-FNFSE-01 · NfseEmissao HasBusinessScope esconde cross-tenant da listagem do cockpit Nfse')`
- **Status:** 🧪 lane **required**; veredito pendente.

## UC-FNFSE-02 — A tela exige `fiscal.nfse.view` `[must]` `[T0]`

**Dado** um usuário sem `fiscal.nfse.view` e sem `superadmin`
**Quando** abre `/fiscal/nfse`
**Então** recebe 403.

- **Âncora de contrato:** `R-FISCAL-003` do [SPEC.md](../../../../memory/requisitos/Fiscal/SPEC.md) §3.
- **Teste:** `Modules/Fiscal/Tests/Feature/NfseCockpitControllerTest.php` — `it('UC-FNFSE-02 · GET /fiscal/nfse aborta 403 sem permission superadmin nem fiscal.nfse.view')`
- **Status:** 🧪 advisory + noturna.

## UC-FNFSE-03 — Competência malformada não derruba a tela `[must]`

**Dado** que a contadora chega com um mês inválido na URL
**Quando** a tela processa o filtro
**Então** ela ignora o valor inválido e responde normalmente — sem erro de servidor.

- **Regressão que defende:** a competência vem da URL e é interpretada como data; entrada malformada virava 500. Este é o tipo de quebra que só aparece quando alguém compartilha um link editado à mão.
- **Teste:** `NfseCockpitControllerTest` — `it('UC-FNFSE-03 · filtro mes invalido nao crasha (ignora silenciosamente)')`
- **Status:** 🧪 advisory + noturna.

## UC-FNFSE-04 — O cabeçalho traz os 6 indicadores da competência `[should]`

**Dado** a tela carregada com uma competência
**Quando** a contadora lê o topo
**Então** encontra quantas notas de serviço existem no total, quantas foram autorizadas, quantas foram rejeitadas, quantas ainda estão em processamento, quantas foram canceladas e o faturamento de serviço do período.

- **Regressão que defende:** sumir com um indicador no refactor e a conferência do mês virar contagem manual.
- **Teste:** `NfseCockpitControllerTest` — `it('UC-FNFSE-04 · counts shape canon — 6 chaves obrigatorias')` e `it('UC-FNFSE-04 · GET /fiscal/nfse renderiza Inertia Fiscal/Nfse com filters/counts canon')` · contrato de estados em `NfseCockpitMultiTenantTest` — `it('UC-FNFSE-04 · STATUS constants estão definidas no Model — Controller depende delas')`
- **Status:** 🧪 advisory + **required** (o terceiro está na lane que bloqueia).

---

## Backlog de casos (sem id — viram UC quando ganharem contrato + teste)

- **[BACKLOG · ⬜ sem teste] Os chips filtram por estado da nota de serviço** — autorizadas, rejeitadas, em processamento (rascunho + processando), canceladas. _Existe no Controller; sem teste do resultado filtrado._
- **[BACKLOG · ⬜ sem teste] A busca aceita número, código de verificação, nome e documento do tomador** — sem teste do resultado.
- **[BACKLOG · ⬜ sem teste] O filtro de competência restringe as linhas ao mês escolhido** — só o caminho de mês **inválido** tem teste; o caminho feliz não.
- **[BACKLOG · ⬜ sem teste · débito de schema] O município da prestação aparece na linha** — a coluna **não existe** no schema em produção (duelo de duas migrations para a mesma tabela, resolvido revertendo o Controller para o schema antigo e traduzindo os estados). Hoje o campo volta vazio por desenho. Ver SDD §5.2.

## Como rodar a suíte

1. **Lane required:** `PHP / Pest (NfeBrasil · MySQL)` roda `NfseCockpitMultiTenantTest`.
2. **Advisory + noturna:** `Pest Fiscal` (SQLite, pula) e a suíte noturna CT 100 (MySQL, roda) para `NfseCockpitControllerTest`.
3. ⛔ **Nunca local** ([ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).

## Trilha do tempo

- 2026-07-03 · [CC] stub criado no Passo 3 do programa de ondas — **0 UC**.
- 2026-07-27 · [CC] `sdd-from-source` (Onda 1 / S2): **4 UC** derivados do §6 do SDD; todos herdam testes existentes. Registrado que o cabeçalho do `NfseCockpitControllerTest` ainda descreve a rota como quebrada em produção — texto **desatualizado** (a correção pelo schema antigo foi aplicada); não editado aqui para não misturar escopo, reportado no session log.
