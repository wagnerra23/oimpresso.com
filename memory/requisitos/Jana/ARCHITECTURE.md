# Arquitetura

## 1. Objetivo

Dar ao gestor (dono de PME ou superadmin da plataforma) um **copiloto conversacional** que entende o estado do negócio, propõe metas concretas em cenários, aceita a escolha, e passa a **monitorar automaticamente** com apuração recorrente e alertas de desvio.

## 2. Posicionamento arquitetural

- **Não é um BI tradicional** (sem OLAP, sem drag-drop de dimensões, sem data warehouse).
- **Não é um dashboard genérico** (o dashboard é consequência, não o centro).
- **É um agente IA orientado a decisão** — proposição → escolha → acompanhamento.

O centro do módulo é o **fluxo conversacional**; todas as outras áreas (metas, períodos, apuração) servem esse fluxo.

## 3. Fluxo principal (happy path)

```
1. Gestor entra em /copiloto
2. Chat saúda + mostra snapshot atual (auto-gerado):
     - faturamento 90d
     - clientes ativos
     - tendências
3. Gestor descreve cenário OU pede "sugira metas"
4. Copiloto chama SuggestionEngine:
     - coleta contexto (PHP)
     - monta prompt com estrutura JSON
     - chama AI (LaravelAI adapter ou openai direto)
     - recebe 3-5 propostas
5. UI renderiza propostas lado a lado (fácil / realista / ambiciosa)
6. Gestor escolhe uma
7. Sistema:
     - grava Meta + MetaPeriodo
     - configura MetaFonte (driver SQL padrão)
     - agenda apuração recorrente no Horizon
     - redireciona pro Dashboard com a meta ativa
8. Job Horizon apura diariamente → grava MetaApuracao
9. AlertaService compara realizado × projetado → notifica se desvia > X%
```

## 4. Camadas

```
Presentation
├── Inertia Pages (React + shadcn)
│   ├── Chat.tsx              (entry-point)
│   ├── Dashboard.tsx         (scorecard + série temporal)
│   ├── Metas/{Index,Show,Edit}.tsx
│   ├── Periodos/Edit.tsx
│   └── Fontes/Edit.tsx
│
Controllers (Http/Controllers/)
├── ChatController@index,send,escolher
├── DashboardController@index
├── MetasController (resource CRUD)
├── PeriodosController (resource CRUD)
└── FontesController (resource CRUD)
│
Services (Services/)
├── SuggestionEngine       (contexto → prompt → IA → JSON estruturado)
├── ApuracaoService        (roda driver, grava MetaApuracao)
├── AlertaService          (compara projetado × realizado)
├── ContextSnapshotService (monta briefing atual do business)
└── AiAdapter (interface) + {LaravelAiDriver, OpenAiDirectDriver}
│
Jobs (Jobs/)
├── ApurarMetaJob          (agendado por meta, roda driver)
└── ProcessarConversaJob   (assíncrono pra chat, se IA demora)
│
Models/Entities
├── Meta
├── MetaPeriodo
├── MetaApuracao
├── MetaFonte
├── MetaConversa
├── MetaMensagem
└── MetaSugestao
│
Data
└── MySQL (mesmo banco do UltimatePOS, tabelas prefixadas `copiloto_*`)
```

## 5. Entidades

### 5.1 `copiloto_metas`
Catálogo de KPIs.

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| business_id | bigint NULL FK | null = meta da plataforma (superadmin) |
| slug | string | `faturamento`, `mrr`, `clientes_ativos`, `churn`, etc. |
| nome | string | Nome humano ("Faturamento anual") |
| unidade | enum | `R$` \| `qtd` \| `%` \| `dias` |
| tipo_agregacao | enum | `soma` \| `media` \| `ultimo` \| `contagem` |
| ativo | bool | |
| criada_por_user_id | bigint FK | |
| origem | enum | `chat_ia` \| `manual` \| `seed` |

### 5.2 `copiloto_meta_periodos`
Alvos por janela temporal.

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| meta_id | bigint FK | |
| tipo_periodo | enum | `mes` \| `trim` \| `ano` \| `custom` |
| data_ini | date | |
| data_fim | date | |
| valor_alvo | decimal(15,2) | |
| trajetoria | enum | `linear` \| `sazonal` \| `exponencial` \| `manual` |

### 5.3 `copiloto_meta_apuracoes`
Histórico realizado (append-only, materializado por job).

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| meta_id | bigint FK | |
| data_ref | date | Data de competência |
| valor_realizado | decimal(15,2) | |
| calculado_em | timestamp | |
| fonte_query_hash | string | hash do SQL/config usado, pra reexecução idempotente |

### 5.4 `copiloto_meta_fontes`
Como cada meta se calcula.

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| meta_id | bigint FK unique | |
| driver | enum | `sql` \| `php` \| `http` |
| config_json | json | SQL literal, callable PHP, endpoint HTTP |
| cadencia | enum | `diaria` \| `horaria` \| `manual` |

### 5.5 `copiloto_conversas`
Sessões de chat.

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| business_id | bigint NULL FK | |
| user_id | bigint FK | |
| titulo | string | Gerado pela IA ou editável |
| status | enum | `ativa` \| `arquivada` |
| iniciada_em | timestamp | |

### 5.6 `copiloto_mensagens`
Mensagens da conversa (append-only).

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| conversa_id | bigint FK | |
| role | enum | `user` \| `assistant` \| `system` |
| content | text | |
| tokens_in / tokens_out | int | Observabilidade |
| created_at | timestamp | |

### 5.7 `copiloto_sugestoes`
Propostas de meta geradas pela IA (histórico).

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| conversa_id | bigint FK | |
| meta_id | bigint NULL FK | Preenche quando Wagner escolhe |
| payload_json | json | Proposta estruturada completa |
| escolhida_em | timestamp NULL | |
| rejeitada_em | timestamp NULL | Feedback passivo pro prompt |

## 6. Integrações

### 6.1 UltimatePOS core (obrigatório)
- **Leitura:** `transactions` (vendas, compras, despesas), `businesses`, `users`, `contacts`, `business_locations`.
- **Scope:** sempre filtrar por `business_id` quando não for superadmin.
- **Middleware:** `SetSessionData`, `auth`, `timezone` (padrão dos módulos UltimatePOS).

### 6.2 LaravelAI (soft)
- Via interface `AiAdapter`. Ver [`adr/tech/0002`](adr/tech/0002-adapter-ia-laravelai-ou-openai.md).
- Se módulo LaravelAI ausente, usa `openai-php/laravel` direto.

### 6.3 Outros módulos (leitura eventual)
- **Grow** — dados de campanhas / leads (se existir meta de conversão).
- **PontoWr2** — nº colaboradores ativos (se tiver meta tipo "receita por colaborador").
- **MemCofre** — Copiloto pode citar decisões (ADRs) ao sugerir metas ("essa meta colide com ADR 0022").

### 6.4 Observabilidade
- **`spatie/activitylog`** — registra eventos críticos: meta criada, escolhida, editada, fonte alterada, job de apuração executado.
- **`spatie/permission`** — permissões granulares (ver seção 8).
- **Log de tokens IA** — pra custo + otimização de prompt.

## 7. Permissões (spatie/permission)

Padrão `{nome}#{business_id}` quando aplicável (ver auto-memória `reference_db_schema.md`):

| Permissão | Uso |
|---|---|
| `copiloto.access` | Acessar qualquer rota do módulo |
| `copiloto.chat` | Iniciar/continuar conversa com IA |
| `copiloto.metas.manage` | CRUD de metas e períodos |
| `copiloto.fontes.edit` | Editar drivers de apuração (técnico) |
| `copiloto.superadmin` | Ver/gerenciar metas da plataforma (`business_id = null`) |
| `copiloto.alertas.config` | Configurar thresholds e canais |

## 8. Decisões em aberto

- **Trajetória projetada** — linear é default simples, mas sazonalidade pode ser obrigatória (ex.: varejo dezembro). Ver se vale PHP-driven custom por meta.
- **Alertas via WhatsApp** — custo API Meta; fica pra v2.
- **Multi-idioma na conversa** — v1 é PT-BR only; configurar modelo a respeitar.
- **Cache de contexto** — `ContextSnapshotService` pode ser caro (queries pesadas). Avaliar cache de 10min.
- **Guardrails IA** — Copiloto não pode sugerir meta que implique ação ilegal/tributária inapropriada. Prompt precisa deixar isso claro.

## 9. Fora de escopo (v1)

- Análise multivariada (causa-raiz de desvios) — Copiloto v1 só detecta desvio, não explica.
- Integrações externas (contabilidade, bancos) — v2+.
- Comparação entre businesses (benchmarking) — v2+ e requer consentimento.
- Geração automática de relatórios em PDF/XLSX — v2+.
- App mobile dedicado — web é mobile-responsive, suficiente pra v1.

---

**Última atualização:** 2026-04-24
