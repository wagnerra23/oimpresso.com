---
id: resources-js-pages-fiscal-cockpit-casos
casos: Cockpit Fiscal · /fiscal
irmaos: Cockpit.charter.md (lei) · memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md (§6 CU)
tecnica: Caso de uso = narrativa do operador + critério de aceite (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-07-30"
last_run_ci: "0 UC executado nesta corrida — os UC herdam testes que JÁ existem; veredito pendente das lanes PHP / Pest (NfeBrasil · MySQL) e Pest Fiscal"
related_us: [US-FISCAL-002, US-FISCAL-019]
---

# Casos de Uso & Aceite — Cockpit Fiscal

> Persona: **Eliana [E] (contadora)** — leitura consolidada do estado fiscal do mês.
>
> **Âncora:** `CU-FISC-01`, `CU-FISC-12` e `CU-FISC-13` do
> [SDD §6](../../../../memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md). Os UC derivam do **CU**, nunca do `.tsx`.
>
> **Status:** ✅ provado por teste verde que cita o UC · 🧪 tem teste, **veredito pendente da lane** · ⬜ não verificado · ❌ quebrou.

## Força do veredito — qual lane, e se ela **bloqueia merge**

| Teste | Lane | Bloqueia merge? |
|---|---|---|
| `CockpitMultiTenantTest` | `PHP / Pest (NfeBrasil · MySQL)` | ✅ **sim** — required no [baseline](../../../../governance/required-checks-baseline.json) e na allowlist do workflow |
| `CockpitControllerTest` · `CockpitCacheTest` | `Pest Fiscal` (SQLite — **pulam** por exigirem schema MySQL) + suíte noturna CT 100 | ❌ **não** — advisory |

## Rastreabilidade

| UC | O que defende | Prio | CU (SDD §6) | Teste que o cita | Status |
|---|---|---|---|---|---|
| UC-FCKP-01 | gate de acesso ao cockpit | `[must]` `[T0]` | CU-FISC-13 | `CockpitControllerTest` | 🧪 |
| UC-FCKP-02 | a tela entrega as 3 leituras | `[must]` | CU-FISC-01 | `CockpitControllerTest` | 🧪 |
| UC-FCKP-03 | as 7 medidas do ribbon | `[must]` | CU-FISC-01 | `CockpitControllerTest` | 🧪 |
| UC-FCKP-04 | alerta determinístico, sem IA | `[must]` | CU-FISC-01 | `CockpitControllerTest` · `CockpitMultiTenantTest` | 🧪 |
| UC-FCKP-05 | KPI não conta outro business | `[must]` `[T0]` | CU-FISC-12 | `CockpitMultiTenantTest` | 🧪 |
| UC-FCKP-06 | cache separado por business | `[must]` `[T0]` | CU-FISC-12 | `CockpitCacheTest` | 🧪 |

---

## UC-FCKP-01 — Sem `fiscal.access`, o cockpit não abre `[must]` `[T0]`

**Dado** um usuário autenticado sem `fiscal.access` e sem `superadmin`
**Quando** ele abre `/fiscal`
**Então** recebe 403.

- **Âncora de contrato:** `R-FISCAL-003` do [SPEC.md](../../../../memory/requisitos/Fiscal/SPEC.md) §3.
- **Regressão que defende:** cockpit aberto por herança de sessão a quem não tem o módulo habilitado.
- **Teste:** `Modules/Fiscal/Tests/Feature/CockpitControllerTest.php` — `it('UC-FCKP-01 · GET /fiscal aborta 403 sem permission superadmin nem fiscal.access')`
- **Status:** 🧪 advisory + noturna; veredito da lane.

## UC-FCKP-02 — O cockpit entrega as três leituras do mês numa resposta só `[must]`

**Dado** uma contadora com permissão
**Quando** abre `/fiscal`
**Então** recebe a tela do cockpit já com as três leituras que a decisão dela exige: os indicadores do mês, a série dos últimos 14 dias e a fila de alertas.

- **Regressão que defende:** a promessa do charter (*"estado fiscal do mês em até 3 segundos"*) morre se alguma das três virar carregamento tardio. O charter proíbe explicitamente adiar os indicadores.
- **Teste:** `CockpitControllerTest` — `it('UC-FCKP-02 · GET /fiscal renderiza Inertia component Fiscal/Cockpit com props canon')`
- **Status:** 🧪 advisory + noturna.

## UC-FCKP-03 — O ribbon carrega as 7 medidas que a contadora usa para decidir `[must]`

**Dado** o cockpit carregado
**Quando** a contadora lê o topo da tela
**Então** encontra: quantas notas foram emitidas no mês, quantas foram autorizadas, o percentual de sucesso, quantas foram rejeitadas, o faturamento fiscal, quantos DF-e aguardam manifestação e quantos dias restam do certificado.

- **Regressão que defende:** sumir com uma medida no refactor e a contadora precisar abrir outra tela para saber o mesmo.
- **Teste:** `CockpitControllerTest` — `it('UC-FCKP-03 · props.kpis tem shape canon (6 chaves obrigatorias)')`
- **Status:** 🧪 advisory + noturna.
- ⚠️ **Nota honesta:** o nome do teste diz "6 chaves" e a lista assertada tem **7** — divergência de rótulo, não de comportamento. Não corrigida aqui para não misturar escopo; registrada no session log.

## UC-FCKP-04 — Os alertas são determinísticos: nenhum raciocínio de IA viaja para a tela `[must]`

**Dado** rejeições recentes, certificado vencendo ou DF-e pendente
**Quando** o cockpit monta a fila de alertas
**Então** cada alerta traz nível, título, subtítulo e a ação a tomar — e **nenhum campo de raciocínio de modelo de linguagem** chega à tela. Os níveis são exatamente crítico, atenção e informativo.

- **Regressão que defende:** anti-hook do charter (*"não usar LLM para gerar alertas — receita determinística por estado"*). Alerta fiscal precisa ser reproduzível e auditável; texto gerado por modelo não é.
- **Teste:** `CockpitControllerTest` — `it('UC-FCKP-04 · props.alerts é array de items deterministicos (sem campos LLM tipo thought/reasoning)')` · `CockpitMultiTenantTest` — `it('UC-FCKP-04 · computeAlerts não usa LLM — receitas determinísticas por estado')`
- **Status:** 🧪 advisory + **required** (o segundo está na lane que bloqueia).

## UC-FCKP-05 — Os indicadores nunca somam notas de outro business `[must]` `[T0]`

**Dado** notas do business ativo e de outro business
**Quando** os indicadores do mês são calculados
**Então** apenas as notas do business ativo entram na conta.

- **Regressão que defende:** vazamento cross-tenant Tier 0 ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)) — aqui na forma mais silenciosa, um número inflado que ninguém questiona.
- **Teste:** `Modules/Fiscal/Tests/Feature/CockpitMultiTenantTest.php` — `it('UC-FCKP-05 · computeKpis scope per business: biz=99 não aparece em counts de biz=1')`
- **Status:** 🧪 lane **required**; veredito pendente.

## UC-FCKP-06 — O cache de indicadores é separado por business e é invalidado pela mesma chave `[must]` `[T0]`

**Dado** dois businesses com cockpit em cache
**Quando** um deles tem o cache invalidado por uma nota autorizada
**Então** o outro sobrevive intacto; e a chave que o invalidador apaga é exatamente a que a tela lê.

- **Regressão que defende:** duas de uma vez — (a) cache agregado sem `business_id` serviria o número de um cliente para outro (o charter **já mandou o oposto uma vez** e foi corrigido em 2026-07-06 justamente por isso); (b) chave que não casa com o invalidador deixa número velho na tela por 60 s sem que ninguém perceba.
- **Teste:** `Modules/Fiscal/Tests/Feature/CockpitCacheTest.php` — `it('UC-FCKP-06 · cache keys de businesses diferentes são INDEPENDENTES (multi-tenant ADR 0093)')`, `it('UC-FCKP-06 · cache key prefix bate com InvalidaCockpitCacheListener (consistency contract)')`, `it('UC-FCKP-06 · Listener invalida a key correta dado um event com business_id')`
- **Status:** 🧪 advisory + noturna.

---

## Backlog de casos (sem id — viram UC quando ganharem contrato + teste)

- **[BACKLOG · ⬜ sem teste · decisão [W]] A tela distingue dado real de dado de demonstração** — Dado que o cockpit serve **quatro superfícies inventadas** (lista unificada de notas, eventos do cabeçalho, contadores das visões salvas, situação da SEFAZ) mais o pacote da contabilidade e o resumo de baixas incobráveis, todos com número fixo no código · Quando a contadora lê a tela · Então ela precisa conseguir dizer o que é leitura real e o que é demonstração. _Âncora: `CU-FISC-16` do SDD §6.5 + §5.4.1 (varredura contada: 4 props com sufixo de mock, 8 métodos, 9 marcadores de pendência). **Não virou UC com id de propósito** — não há contrato em 2 fontes dizendo qual é a saída certa (marcar procedência × esconder atrás de flag × declarar Non-Goal), e UC órfão bloqueia o merge de quem for atendê-lo. **Precisa de decisão [W].**_
- **[BACKLOG · ⬜ sem teste] A série de 14 dias sai de uma consulta agrupada, sem repetição por dia** — Dado emissões nas últimas duas semanas · Quando a série é montada · Então há um ponto por dia por status, calculado de uma vez só. _Anti-hook do charter; sem teste dedicado._
- **[BACKLOG · ⬜ sem teste] O faturamento aparece formatado em moeda brasileira** — comportamento só de frontend, sem cobertura de Feature.
- **[BACKLOG · ⬜ sem teste] Filtros, visões salvas, densidade e seleção em lote da tabela unificada** — hoje operam sobre dado de demonstração (ver primeiro item); só vira contrato quando a fonte real existir.

## Como rodar a suíte

1. **Lane required:** `PHP / Pest (NfeBrasil · MySQL)` roda `CockpitMultiTenantTest` em todo PR que toque `Modules/Fiscal/Tests/**`.
2. **Advisory:** `Pest Fiscal` roda o diretório inteiro em SQLite — `CockpitControllerTest` e `CockpitCacheTest` **pulam** lá (schema MySQL).
3. **Noturna CT 100:** `phpunit.xml` inclui `./Modules/Fiscal/Tests/Feature`; é onde os dois advisory realmente correm.
4. ⛔ **Nunca local** ([ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).

## Trilha do tempo

- 2026-07-03 · [CC] stub criado no Passo 3 do programa de ondas — **0 UC**.
- 2026-07-27 · [CC] `sdd-from-source` (Onda 1 / S2): **6 UC** derivados do §6 do SDD; todos herdam testes existentes. O achado do dado de demonstração ficou como `[BACKLOG]` + `CU-FISC-16` ⬜, por ser decisão de produto.
