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

## Jornada completa biz=1 (Wave 27 — fluxo end-to-end Wagner/RH)

Cenário canônico testado em prod biz=1 (clientes legacy WR2 Eliana via ROTA LIVRE pattern, NUNCA biz=4 prod cliente em testes — [ADR 0101](../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md)).

### Fase A — Setup empresa (Admin Wagner)

1. **Cadastrar escala padrão**: `/ponto/escalas` → criar `ESCALA_5X2` (seg–sex 08:00–17:00, intrajornada 12:00–13:00).
   - Validação via `StoreEscalaRequest` (Wave 18 D8): `carga_diaria_minutos ≤ 720` (CLT Art. 59), `carga_semanal_minutos ≤ 2640` (CLT Art. 7º XIII).
2. **Cadastrar colaboradores**: `/ponto/colaboradores` → vincula `business_id` + escala + REP autorizado.
3. **Configurar REP-P (mobile)**: `/ponto/configuracao` → ativar geolocalização obrigatória (Portaria 671 Anexo I §10).

### Fase B — Operação diária (Funcionário)

4. **Bater 4 marcações**: ENTRADA / ALMOCO_INICIO / ALMOCO_FIM / SAIDA via `/ponto/marcacoes` → `StoreMarcacaoRequest` (Wave 18 D8) valida `before_or_equal:now + after:-24 hours` (limite offline REP-P).
5. **Hash + NSR encadeado**: cada marcação calcula `hash = sha256(hash_anterior || payload)` via `MarcacaoService` ([app/Domain/Fsm/Support](../../app/Domain/Fsm/Support/) helpers OTel `ponto.marcar`).
6. **Consultar espelho**: `/ponto/espelho` → `Inertia::defer()` props heavy (Wave 25 D6 — switch 300ms→50ms validado em [RUNBOOK-inertia-defer-pattern.md](../../memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md)).

### Fase C — Anomalias (Funcionário + Gestor)

7. **Solicitar intercorrência (atestado)**: `/ponto/intercorrencias` → `StoreIntercorrenciaRequest` (Wave 18 D8) — upload PDF anexo + classificação automática IA (`IntercorrenciaAIClassifierTest` Wave 11 LGPD).
8. **Aprovar/rejeitar**: gestor acessa `/ponto/aprovacoes`, decide com base em política CLT Art. 6º Lei 605/49 (faltas justificadas).
9. **Anular marcação errada**: Funcionário NÃO anula (segregação de funções — Portaria 671 Anexo I §3); apenas RH/gestor via `AnularMarcacaoRequest` (Wave 27 D8) → cria NOVA marcação `ORIGEM=ANULACAO` + `marcacao_anulada_id` apontando original. **Original PERMANECE** (append-only Portaria 671 Art. 85).

### Fase D — Banco de horas (RH + Wagner)

10. **Acompanhar saldo BH**: `/ponto/banco-horas` → calculado automaticamente via `BancoHorasService`. Saldo positivo = HE compensável; negativo = falta a repor.
11. **Ajuste manual retroativo (acordo coletivo novo)**: RH usa `StoreBancoHorasMovimentoRequest` (Wave 27 D8) tipos `CREDITO`/`DEBITO`/`AJUSTE`/`QUITACAO`. `motivo` obrigatório (Portaria 671 Anexo I §4 auditoria fiscal).
12. **Quitação em folha**: `QUITACAO` zera saldo positivo após pagamento integrado eSocial S-1010 (acerto eventos remuneratórios).

### Fase E — Fechamento mensal (RH)

13. **Fechar período** dia 30: `/ponto/aprovacoes` → "Fechar período" → calcula HE/BH/faltas/atrasos por colaborador → exporta planilha pra folha + envia eventos eSocial S-1010 (rubricas) + S-2230 (afastamentos) + S-2240 (CAT).

### Fase F — Fiscalização (Auditor Fiscal do Trabalho)

14. **Gerar AFD/AFDT/AEJ**: `/ponto/relatorios` → escolhe período + colaboradores → arquivo layout Portaria 671 Anexo I (campos delimitados, encoding ASCII puro, registro tipo 9 marcações).
15. **Auditor assina recibo digital**: append `Registro 9` com timestamp + matrícula auditor (Portaria 671 Anexo I §6).

### Fase G — Cross-tenant safety (Tier 0 IRREVOGÁVEL)

16. **Isolamento garantido em camadas**:
    - **Eloquent global scope** `HasBusinessScope` em `Marcacao`/`Escala`/`BancoHorasSaldo`/`BancoHorasMovimento`/`Importacao`/`Rep` (Wave 18 D1)
    - **Trigger MySQL** `trg_ponto_marcacoes_no_delete` + `trg_ponto_marcacoes_no_update` (defesa em profundidade contra `tinker` direto)
    - **Pest cross-tenant**: `CrossTenantMarcacaoTest` (Wave 15, via `DB::table()` column-level) + `Wave27CrossTenantEscalaTest` (Wave 27, via Eloquent Model-level)

### Comandos artisan Ponto canônicos

```bash
php artisan ponto:apuracao-dia {biz} {colaborador} {data}      # recalcula 1 dia
php artisan ponto:apuracao-mes {biz} {mes:YYYY-MM}             # fecha periodo mensal
php artisan ponto:importar-afd {biz} {arquivo.afd}             # import REP-A
php artisan ponto:exportar-afdt {biz} {periodo}                # export fiscal AFDT
php artisan ponto:health-check                                  # checks 5 dimensões
```

## Smoke test E2E

```bash
php artisan test --filter=CustomerJourneyTest
```

Cobre jornada 4 marcações + append-only defesa + cross-tenant biz=99 + fluxo anulação. Roda contra MySQL real (NÃO SQLite — triggers MySQL exigidos).

## Licença

Proprietário — WR2 Sistemas
