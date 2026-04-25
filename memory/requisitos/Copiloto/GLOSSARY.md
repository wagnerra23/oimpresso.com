# Glossário — Copiloto

Vocabulário canônico. Sempre usar estes termos em código, UI e conversas com o módulo.

| Termo | Definição |
|---|---|
| **Copiloto** | O módulo inteiro. Também o nome visual na UI. Marca comercial = técnica. |
| **Conversa** | Sessão de chat entre gestor e IA. Uma por objetivo (ex.: "planejar 2026"). Persiste no banco; pode ser arquivada. |
| **Mensagem** | Unidade da conversa. `role` ∈ {user, assistant, system}. Append-only — nunca edita. |
| **Sugestão** | Proposta estruturada gerada pela IA. Pode virar Meta (se escolhida) ou ficar registrada como rejeitada. |
| **Meta** | KPI com alvo. Ex.: "Faturamento anual 2026". Pertence a um business ou à plataforma. |
| **Período** | Janela temporal com valor-alvo. Uma Meta pode ter vários (mensais, trimestrais, anual). |
| **Apuração** | Registro do realizado em uma `data_ref`. Materializada por job; append-only com reexecução idempotente via `fonte_query_hash`. |
| **Fonte** | Config de como a Meta se calcula. Driver `sql` / `php` / `http` + payload JSON. |
| **Driver** | Implementação concreta do cálculo. SQL = string parametrizada; PHP = callable registrado; HTTP = endpoint externo. |
| **Cenário** | Enquadramento qualitativo de uma proposta: `fácil` / `realista` / `ambicioso`. |
| **Trajetória** | Curva esperada entre início e fim do período. `linear` (default), `sazonal`, `exponencial`, `manual`. |
| **Farol** | Indicador verde/amarelo/vermelho baseado em `realizado vs projetado na data`. |
| **Desvio** | `(realizado_no_periodo − projetado_no_periodo) / projetado_no_periodo`. Quando negativo além do threshold → alerta. |
| **Briefing** | Snapshot automático do estado do negócio que o Copiloto apresenta ao iniciar conversa. |
| **Contexto** | Conjunto de dados que o `ContextSnapshotService` coleta pra alimentar a IA (transactions 90d, clientes ativos, módulos, etc.). |
| **Adapter de IA** | Interface `AiAdapter` com implementações `LaravelAiDriver` (se módulo existir) ou `OpenAiDirectDriver` (fallback). |
| **Threshold** | Limite % de desvio aceitável antes de disparar alerta. Configurável por meta ou globalmente. |
| **Superadmin Copiloto** | Perfil `copiloto.superadmin` — vê metas de todos businesses + metas da plataforma (`business_id = null`). |
| **Meta da plataforma** | Meta com `business_id = null`. Representa objetivos da oimpresso como SaaS (ex.: R$ 5mi/ano). Só superadmin acessa. |
| **Origem** | Como a meta foi criada: `chat_ia` (via Copiloto), `manual` (wizard), `seed` (importada do `memory/11-metas-negocio.md`). |

## Termos que NÃO são do Copiloto

Pra evitar confusão — se aparecerem em conversa com cliente:

| Termo a evitar | Por quê | Usar em vez disso |
|---|---|---|
| **Dashboard** (como produto) | Dashboard é tela, não é o produto | "Copiloto" ou "o painel do Copiloto" |
| **BI** | Reserva a palavra pra módulo futuro se tivermos um de verdade | "Copiloto conversacional" ou "Copiloto de Metas" |
| **OKR** | Copiloto v1 não implementa hierarquia OKR (key results ligados a objetivos) | "Meta" + "Período". OKR é v2+ se pedirem. |
| **Quota** / **Cota de vendedor** | Domínio diferente (comissões, comercial), pode virar módulo separado | "Meta por colaborador" se for isso |

---

**Última atualização:** 2026-04-24
