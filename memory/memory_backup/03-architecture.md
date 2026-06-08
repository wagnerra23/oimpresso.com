# 03 — Arquitetura

## Estilo arquitetural

**Modular Monolith com DDD leve**, dentro de uma instalação Laravel única que também hospeda UltimatePOS e Essentials & HRM.

```
                ┌──────────────────────────────────────┐
                │  Laravel 10 (instalação UltimatePOS) │
                │                                      │
                │  Modules/                            │
                │  ├── Essentials/     (HRM core)     │
                │  └── PontoWr2/       ← este projeto │
                │                                      │
                │  app/  (core UltimatePOS)            │
                │  routes/web.php  (sidebar original)  │
                └──────────────────────────────────────┘
```

## Decisão-chave: Opção C (Extensão)

Ver [`decisions/0001-estender-ultimatepos-opcao-c.md`](decisions/0001-estender-ultimatepos-opcao-c.md).

Três caminhos foram considerados:
- **A — Build from scratch**: greenfield Laravel novo. Rejeitado (cliente já tem UltimatePOS).
- **B — Fork do UltimatePOS**: forkar e modificar. Rejeitado (perde upgrades do upstream).
- **C — Extend (adotado)**: módulo que estende sem modificar core. Escolhido.

## Camadas dentro do módulo PontoWr2

```
HTTP (Controllers)
   ↓
Services (lógica de negócio, regras CLT)
   ↓
Entities (Eloquent Models)
   ↓
Database (migrations, triggers, índices)
```

- **Controllers** são finos — orquestram e delegam
- **Services** concentram regras de negócio (ApuracaoService, BancoHorasService, AfdParserService, IntercorrenciaService, NsrService)
- **Entities** encapsulam invariantes (ex.: `Marcacao::update()` lança exceção)
- **Banco** tem defesa em profundidade (triggers MySQL bloqueiam UPDATE/DELETE em marcações e movimentos de BH)

## Pattern principal por serviço

| Serviço | Pattern |
|---|---|
| `ApuracaoService` | Chain of Responsibility — cada regra CLT é um elo |
| `BancoHorasService` | Event Sourcing simplificado (ledger append-only) |
| `AfdParserService` | Strategy — um parser por tipo de registro (1..9) |
| `IntercorrenciaService` | State Machine — transições controladas |
| `NsrService` | Pessimistic Lock — sequencial por REP sem buracos |

## Multi-tenancy

- Herdado do UltimatePOS via coluna `business_id` em toda tabela
- Middleware `CheckPontoAccess` garante que o usuário tem business ativo na sessão
- Evolução futura (opcional): physical isolation com `stancl/tenancy` em Fase 12 — registrado no roadmap

## Bridge com UltimatePOS

Tabela `ponto_colaborador_config`:

```
users (UltimatePOS)
  ↓ FK
ponto_colaborador_config  ← aqui moram: matrícula, PIS, escala, flags
  ↓ FK
ponto_marcacoes, ponto_apuracao_dia, ponto_banco_horas_saldo, etc.
```

Nunca tocamos em `users`, `employees`, `business` do core. Ver [`decisions/0004-bridge-colaborador-config.md`](decisions/0004-bridge-colaborador-config.md).

## Imutabilidade (conformidade legal)

Três tabelas são append-only por força de lei:

1. `ponto_marcacoes` — Portaria 671/2021
2. `ponto_banco_horas_movimentos` — requisito de auditoria
3. `ponto_reps.ultimo_nsr` — sequencial inviolável

Defesa em profundidade:
- **Camada de aplicação**: `Marcacao::update()` e `::delete()` lançam `RuntimeException`
- **Camada de banco**: triggers `BEFORE UPDATE` / `BEFORE DELETE` com `SIGNAL SQLSTATE '45000'`
- **Camada de auditoria**: `spatie/laravel-activitylog` loga tudo

Ver [`decisions/0003-marcacoes-append-only-triggers.md`](decisions/0003-marcacoes-append-only-triggers.md).

## Filas assíncronas

Executadas via Laravel Horizon:

- `ProcessarImportacaoAfdJob` — parse de arquivo AFD em chunks
- `ReapurarDiaJob` — recalcula apuração quando intercorrência é aprovada
- `GerarRelatorioJob` — geração pesada de PDF/AFD
- `EnviarEventosEsocialJob` — (futuro) envio assíncrono ao eSocial

## Integrações externas

| Sistema | Direção | Protocolo | Frequência |
|---|---|---|---|
| eSocial | Out | SOAP (XML assinado) | Tempo real |
| REP-C homologado | In | Arquivo AFD (SFTP/upload) | Diário/mensal |
| Folha externa | In/Out | CSV/API REST | Mensal |
| Notificações (email/WhatsApp) | Out | SMTP/API | Event-driven |

## Observabilidade

- `spatie/laravel-activitylog` — log funcional (quem aprovou INC-123)
- `laravel-pulse` — performance
- Logs estruturados JSON em produção (LogStash/CloudWatch)

---

**Última atualização:** 2026-04-18
