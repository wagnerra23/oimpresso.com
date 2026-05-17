# Módulo Ponto WR2

Módulo de **Ponto Eletrônico** em conformidade com a **Portaria MTP 671/2021** (REP-P/REP-C/REP-A), CLT e Reforma Trabalhista. Desenvolvido como extensão do **UltimatePOS 6 + Essentials & HRM** pela **WR2 Sistemas**.

## Recursos principais

- Marcação de ponto via REP-P (web), REP-C (homologado) e importação AFD/AFDT
- Banco de horas com saldo, compensação e multiplicadores configuráveis
- Intercorrências de expediente (saídas/retornos) com fluxo de aprovação
- Apuração automática com regras CLT (tolerâncias, intrajornada, interjornada, HE, adicional noturno)
- Geração de AFD/AFDT/AEJ para fiscalização
- Assinatura digital de marcações com certificado ICP-Brasil A1
- Integração eSocial (S-1010, S-2230, S-2240)
- Multi-empresa via `business_id` do UltimatePOS

## Requisitos

- PHP 8.1+
- Laravel 10.x
- UltimatePOS v6.12+
- Essentials & HRM v5.4+
- MySQL 8.0+ (triggers obrigatórios para imutabilidade)
- Redis 7 (filas)

## Instalação

```bash
# 1. Copiar módulo para Modules/PontoWr2
# 2. Atualizar autoload
composer dump-autoload

# 3. Ativar no nWidart
php artisan module:enable PontoWr2

# 4. Rodar migrações
php artisan module:migrate PontoWr2

# 5. Publicar assets
php artisan module:publish PontoWr2

# 6. (Opcional) Seeders de demo
php artisan module:seed PontoWr2
```

## Estrutura

```
Modules/PontoWr2/
├── Config/           # Regras CLT, banco de horas, REP, eSocial
├── Console/Commands/ # Artisan: import AFD, fechar período
├── Database/
│   ├── Migrations/   # 8 tabelas de domínio
│   └── Seeders/
├── Entities/         # Models Eloquent (Marcacao, Intercorrencia, ...)
├── Http/
│   ├── Controllers/  # 10 controllers (1 por item do menu horizontal)
│   ├── Middleware/   # CheckPontoAccess
│   └── Requests/     # Form requests (validação)
├── Services/         # Regras de negócio (Apuracao, BH, AFD)
├── Providers/        # Service + Route providers
├── Resources/
│   ├── views/        # Blade (layout + seções)
│   ├── lang/pt-BR/   # Traduções
│   └── assets/       # JS + SASS
├── Routes/
│   ├── web.php
│   └── api.php
└── Tests/
```

## Conformidade legal

- **Portaria MTP 671/2021** — NSR sequencial, AFD/AFDT/AEJ, imutabilidade
- **CLT** — Art. 58 (jornada e tolerâncias), Art. 59 (HE), Art. 66 (interjornada), Art. 71 (intrajornada), Art. 73 (noturno)
- **Lei 13.467/2017** — Banco de horas por acordo individual (6 meses)
- **Lei 13.709/2018 (LGPD)** — Dados pessoais, retenção, consentimento
- **eSocial** — Leiautes S-1.2

## Como o cliente usa (jornada funcionário)

### 1. Bater ponto (4 marcações típicas por dia)

Acessa `/ponto/marcacoes` → botão **"Registrar"** → escolhe tipo:

- **ENTRADA** (~08:00)
- **ALMOCO_INICIO** (~12:00)
- **ALMOCO_FIM** (~13:00)
- **SAIDA** (~17:00)

Cada marcação é registrada com NSR sequencial + hash SHA256 + IP/geo capturados. Imutável (Portaria 671/2021 Art. 85) — banco bloqueia via trigger + Model lança `RuntimeException` em `update()`/`delete()`.

### 2. Consultar espelho de ponto

`/ponto/espelho` mostra:
- Jornada bruta do dia (entrada → saída)
- Intervalo intrajornada (almoço — CLT Art. 71 mín. 1h se >6h trabalhadas)
- Banco de horas saldo
- Intercorrências pendentes/aprovadas
- HE (CLT Art. 59)

### 3. Justificar ausência (intercorrência)

`/ponto/intercorrencias` → **"Solicitar"** → tipo (ATESTADO/FALTA/SAIDA_ANTECIPADA…) + justificativa + anexo (atestado médico em PDF). Fluxo PENDENTE → APROVADA/REJEITADA (gestor decide).

### 4. Anular marcação (caso erro de digitação)

⚠️ NUNCA `delete()` — append-only. Fluxo correto: `Marcacao::anular(<id>, <motivo>)` cria **nova** marcação com `origem=ANULACAO` + `marcacao_anulada_id` apontando pra original. Original permanece registrada (auditoria fiscal).

### 5. Exportar fiscalização (AFD/AFDT/AEJ)

`/ponto/relatorios` → **"AFD"** (períodos parametrizáveis). Saída em layout REP-P/REP-C conforme Portaria 671/2021 Anexo I — Auditor Fiscal do Trabalho aceita direto.

### 6. RH fechar período mensal

`/ponto/aprovacoes` → **"Fechar período"** → calcula HE, banco de horas, faltas, atrasos → gera planilha pra folha de pagamento (eSocial S-1010/S-2230/S-2240).

### 7. Banco de Horas (HE compensáveis — Reforma Trabalhista 13.467/2017)

`/ponto/banco-horas` lista saldo por colaborador (credor/devedor) + 4 totais empresa
(crédito total / débito total / colaboradores em crédito / em débito).

Caso típico Larissa (vestuário): operadora trabalhou 2h extras quarta-feira →
`BancoHorasMovimento::TIPO_CREDITO` lança +120min com `multiplicador=1.0` (acordo
individual: hora extra vira hora compensável, não dinheiro). Saldo agregado em
`ponto_banco_horas_saldos` reflete em tempo real. Sexta a colaboradora sai 2h
mais cedo → `TIPO_DEBITO` lança −120min. Saldo zera. Ledger preserva 5 anos
(retention.php — CLT Art. 11 prescrição).

### 8. Ajuste manual de saldo BH (RH gestor)

`/ponto/banco-horas/{colaboradorId}` → "**Ajustar manual**" → minutos (positivo/negativo)
+ observação obrigatória. Cria `BancoHorasMovimento::TIPO_AJUSTE` com `usuario_id`
do gestor (Wave 26 D7 — `LogsActivity` registra evento auditável). Usado quando
houve erro de cálculo (apuração reapurada) ou acordo de compensação fora do fluxo.

### 9. Importar AFD do REP físico (REP-A / REP-C externo)

`/ponto/importacoes` → "**Importar AFD**" → upload arquivo `.txt` Portaria 671 Anexo I.
`AfdParserService::processar()` (com `OtelHelper::span` — D9 hot-path resilient)
parseia headers/trailer + valida NSR sequencial + lança marcações em transação.
Re-import idempotente (NSR único + hash chain detecta duplicata). Arquivo bruto
retido 2 anos (retention.php — `importacoes_afd` hard_delete=true permitido após).

### 10. Cross-tenant proof (multi-tenant Tier 0 IRREVOGÁVEL)

Larissa (`biz=4` ROTA LIVRE) NUNCA enxerga marcações da `biz=1` (WR2 Sistemas).
`HasBusinessScope` trait (ADR 0093) aplica global scope automático em TODA query
Eloquent — `Marcacao::all()` em sessão biz=4 retorna SÓ marcações biz=4. Test
`Wave18BusinessScopeTest::php` + `CrossTenantMarcacaoTest::php` provam append-only
+ isolamento simultaneamente. Auditor fiscal jamais vê dados de outra empresa.

## Smoke test E2E

```bash
php artisan test --filter=CustomerJourneyTest
php artisan test --filter=Wave26SaturationTest
```

`CustomerJourneyTest`: jornada 4 marcações + append-only defesa + cross-tenant biz=99
+ fluxo anulação. Roda contra MySQL real (NÃO SQLite — triggers MySQL exigidos).

`Wave26SaturationTest`: validações source-level zero-hit-prod (defer pattern em 4
Controllers, LogsActivity em 5 entidades sensíveis, OTel spans 6 services, retention
5 buckets append-only). Roda local sem MySQL.

## Marcos governance v4

| Wave | Foco | Lições |
|------|------|--------|
| 11 | D7.b LogsActivity em Colaborador/Escala/Intercorrencia | LGPD audit trail cadastral |
| 18 | D1 multi-tenant `HasBusinessScope` em todas Models | Tier 0 IRREVOGÁVEL ADR 0093 |
| 23 | MarcacaoService reuse contract | Single source of insert |
| 25 | D6 Inertia::defer Dashboard (300ms→50ms) | RUNBOOK defer pattern |
| 26 | D6 defer Espelho/Aprovacao/BancoHoras + D7 LogsActivity Rep/BH-Movimento | Saturação cross-dim |

## Licença

Proprietário — WR2 Sistemas
