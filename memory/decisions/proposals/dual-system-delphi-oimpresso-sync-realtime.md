# Proposta: Dual-system Delphi master + oimpresso viewer com sync near-realtime

**Status:** proposed (Wagner valida)
**Origem:** insight Kamila Martinho (2026-05-14 15:51 WhatsApp) — *"continuar usando o sistema antigo e colocar o sistema novo para a lara e a dani usar... ele consegue puchar as informações em tempo real do sistema antigo"*
**Alinhamento ADR 0105:** Kamila é cliente real (filha Martinho · operadora dia-a-dia · biz=164 prod) reportando exatamente como ela quer adotar — sinal qualificado, NÃO hipótese.
**Decisão Wagner pós-reunião:** *"vou preparar algo para conseguir fazer algo assim · Assim válida tudo e fica tudo certo · vou pensar aqui como fazer"*

---

## 1) Contexto

### Reunião 14/maio (oimpresso × Martinho Caçambas LTDA biz=164)

- Wagner apresentou novo sistema (Laravel/React)
- Martinho topou testar — promessa "migrar tudo"
- 4 P0 fechados durante dia 14/maio:
  - Pricing paridade R$ 830/m + upsell módulos novos
  - Escopo Fase 1: cadastros + financeiro + produtos + compras + estoque + OS + MDe NFe
  - Cutover canary 7d tela por tela
  - Champions: **filha do Martinho (LARA · responsável estoque)** + **Dani (financeiro)** + KAMILA usa Delphi (operação)

### Fricção real (descoberta dia 14/maio)

- Algumas telas críticas oimpresso ainda Blade legacy: `/contacts`, `/sells/edit`, `/products`, `/stock-*`
- Filha + Dani não-técnicas, monitor 1280px, persona análoga ROTA LIVRE
- Wagner cortou Wave inicial de mostrar Blade: *"nada encantador, não vai vingar, elas rejeitarão fortemente"*
- Sistema Delphi (Office Comercial WR Sistemas) roda há 26 anos no Martinho · 44k vendas históricas · 4.378 produtos · 4.581 movimentações estoque · 11 users já operando

### Insight Kamila (a chave da arquitetura)

Kamila propôs por iniciativa própria:

> *"continuar usando o sistema antigo e colocar o sistema novo para a lara e a dani usar — tipo ele consegue puchar as informações em tempo real do sistema antigo"*

Tradução técnica:

- **Delphi continua MASTER OPERACIONAL** — vendas, cadastros, faturamento, POS pra Kamila/vendedores
- **oimpresso é READ-MOSTLY** — Lara consulta estoque · Dani consulta financeiro · ambas com dados FRESCOS sem fricção
- **Sync near-realtime** (~5min latency) Delphi → oimpresso via daemon polling
- **Cutover gradual feature-por-feature** quando confiança crescer

---

## 2) Decisão proposta

Adotar **arquitetura dual-system com sync near-realtime unidirectional Delphi→oimpresso** como modo padrão de adoção de clientes legacy OfficeImpresso.

Esta proposta CANCELA implicitamente a estratégia "canary cutover tela por tela com migração MWART agressiva" desenhada em [demo-martinho-2026-05-13/plano-paralelizacao.md](../../requisitos/OficinaAuto/demo-martinho-2026-05-13/plano-paralelizacao.md).

### Princípios duros (Tier 0 desta proposta)

1. **Delphi escrita = source of truth durante coexistência** — Lara/Dani lêem oimpresso, ESCREVEM no Delphi quando precisar editar dados existentes (até oimpresso virar primary)
2. **Importers idempotentes** — UPSERT por chave natural `(business_id, officeimpresso_codigo)` ou equivalente; re-aplicar = no-op pra rows iguais
3. **Multi-tenant Tier 0 IRREVOGÁVEL** — `business_id` em todo INSERT/UPDATE no oimpresso (ADR 0093); daemon NUNCA roda sem `--target-business` explícito
4. **PII redaction em logs** — daemon log NÃO ecoa CPF/CNPJ/telefone/email cliente
5. **Rollback livre** — se sync falha N tentativas, Lara/Dani trocam pro Delphi até Wagner consertar; zero impacto operacional
6. **Sem conflict resolution two-way** — oimpresso não escreve back pro Firebird (versão V1 desta proposta). Two-way fica pra V2 quando oimpresso virar primary.

---

## 3) Arquitetura técnica

```
┌────────────────────────────────────────────────────────────────┐
│  ESCRITÓRIO MARTINHO (oficina caçambas · 11 users · biz=164)   │
│                                                                │
│  ┌──────────────┐   Kamila + Rodrigo + Eduardo (operacional)  │
│  │   Delphi     │   Cadastra cliente · vende · factura · POS  │
│  │   WR         │                                              │
│  │   Comercial  │   ────────────────────────────────┐         │
│  └──────┬───────┘                                   │         │
│         │ escreve                                   │         │
│         ▼                                           │         │
│  ┌──────────────────────────────┐                  │         │
│  │  Firebird 192.168.0.55:3050  │                  │         │
│  │  D:/DadosClientes/...BANCO   │                  │         │
│  └──────┬───────────────────────┘                  │         │
│         │                                          │         │
└─────────│──────────────────────────────────────────│─────────┘
          │                                          │
          │ delta polling (~5min)                    │ usa
          │                                          │
          ▼                                          ▼
┌─────────────────────────┐              ┌─────────────────────┐
│  DAEMON SYNC PYTHON     │              │     LARA + DANI     │
│  PC Wagner OU CT 100    │              │  (champions oimp)   │
│                         │              │                     │
│  - SSH tunnel persistent│              │  Browser → oimpresso│
│    Hostinger MySQL      │              │  (Lara estoque       │
│  - Importers idempotente│              │   Dani financeiro)  │
│  - Checkpoint table     │              └──────────┬──────────┘
│  - Monitor + WA alert   │                         │
└──────────┬──────────────┘                         │
           │                                        │
           │ POST/PUT idempotente                   │
           ▼                                        │
┌──────────────────────────────────────────────────▼─────────────┐
│  HOSTINGER MySQL · oimpresso · biz=164                         │
│                                                                │
│  contacts · transactions · vehicles · service_orders ·         │
│  fin_titulos · products · variations · variation_location_*  · │
│  purchase_lines · stock_adjustments                            │
│                                                                │
│  ✅ business_id global scope (ADR 0093 Tier 0)                 │
└────────────────────────────────────────────────────────────────┘
```

### Componentes

1. **Daemon `daemon-sync-martinho.py`** (novo)
   - Loop infinito · sleep 300s entre rodadas (5min)
   - Cada rodada: chama importers existentes com flag `--delta-since-last-sync`
   - Mantém `sync_checkpoint` table no oimpresso MySQL armazenando timestamp último sync por tipo (contacts/financeiro/vendas/produtos/estoque/compras)
   - Se um importer falha N=3 vezes consecutivas: pause + alerta WhatsApp Wagner via webhook Centrifugo OU SMTP Hostinger
   - Retoma automaticamente quando heartbeat manual via touch arquivo `/tmp/resume-sync`

2. **Flag `--delta-since-last-sync` em cada importer**
   - Lê `sync_checkpoint.last_sync_at` por tipo
   - Aplica WHERE no Firebird: `DT_ALTERACAO > last_sync_at` (cols presentes em VENDA/FINANCEIRO/PRODUTO já)
   - UPSERT na oimpresso atualiza só rows mudadas
   - Após sucesso: UPDATE `sync_checkpoint.last_sync_at = NOW()`

3. **Tabela `sync_checkpoint`** (nova migration)
   ```sql
   CREATE TABLE sync_checkpoint (
     id INT AUTO_INCREMENT PRIMARY KEY,
     business_id INT NOT NULL,
     sync_type ENUM('contacts','financeiro','vendas','produtos','estoque','compras','vehicles'),
     last_sync_at TIMESTAMP NOT NULL,
     last_status ENUM('success','partial','failed') NOT NULL,
     rows_processed INT DEFAULT 0,
     error_msg TEXT,
     updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
     UNIQUE KEY uniq_biz_type (business_id, sync_type)
   );
   ```

4. **Onde roda o daemon?**
   - **Opção A (recomendado)** — PC Wagner ligado 24/7 (já tem acesso LAN 192.168.0.55 + SSH Hostinger)
   - **Opção B** — Mac mini / RaspberryPi dedicado no escritório Wagner (R$ 1k investimento)
   - **Opção C** — CT 100 Proxmox via Tailscale VPN reverso pro servidor-crm 192.168.0.55 (mais complexo)
   - Hipotese A é suficiente pra MVP. Migra pra B/C se Wagner viajar muito.

5. **Frequência**
   - Polling a cada **5 minutos** por padrão (latência aceitável pra estoque/financeiro)
   - Configurável por tipo: contacts pode ser 15min (cadastro novo é raro); estoque pode ser 2min (alta volatilidade)
   - Off-hours (22h-6h): aumenta intervalo pra 30min · economia CPU

### Tabelas/entidades cobertas no MVP

| Firebird | oimpresso | Importer existente | Modificação necessária |
|---|---|---|---|
| PESSOAS (via VENDA inline) | contacts | `import-contacts-from-venda.py` | + `--delta-since` flag |
| FINANCEIRO | fin_titulos + fin_titulo_baixas | `import-financeiro.py` | + `--delta-since` |
| VENDA | transactions + transaction_sell_lines | `import-vendas.py` | + `--delta-since` |
| PRODUTO | products + variations + product_variations | `import-produtos.py` | + `--delta-since` + **bug fix lookup cross-business** |
| PRODUTO_ESTOQUE | variation_location_details | `import-estoque.py` | + `--delta-since` + **bug fix structural** |
| NOTA_FISCAL_ENTRADA + NF_ENTRADA_PRODUTOS | transactions (type=purchase) + purchase_lines | `import-compras.py` | + `--delta-since` + importer fornecedores `import-contacts-from-nfe.py` (NOVO) |
| EQUIPAMENTO_VEICULO | vehicles | `import-vehicles.py` ✅ | + `--delta-since` |

---

## 4) Implementação em fases

### Fase 1 — Daemon MVP unidirectional (1-2 dias dev IA-pair)

**Entregas:**
- [ ] Migration `2026_05_15_*_create_sync_checkpoint.php`
- [ ] Flag `--delta-since-last-sync` em 6 importers (lê DT_ALTERACAO Firebird)
- [ ] Bug fix lookup cross-business em `import-estoque.py` (qualificar JOIN com `vld.product_id = v.product_id AND v.product_id IN (SELECT id FROM products WHERE business_id=X)`)
- [ ] Audit `import-produtos.py` + `import-compras.py` mesma família de bug
- [ ] `daemon-sync-martinho.py` wrapper (loop infinito · SSH tunnel persistent · checkpoint update)
- [ ] Pest cross-tenant daemon: biz=164 sync NÃO toca biz=4 ROTA LIVRE
- [ ] RUNBOOK `daemon-sync-runbook.md` (setup PC Wagner · troubleshoot · alertas)

**Critério aceite:**
- Rodar daemon por 24h no escritório Wagner em ambiente dev
- Lara cadastra peça no Delphi → ≤5min depois aparece no oimpresso
- Dani recebe boleto pago no Delphi → ≤5min depois oimpresso mostra
- ZERO toque em biz≠164 (Pest cross-tenant verde)

### Fase 2 — Lara + Dani entram (semana 2 — Wagner aprovação)

**Entregas:**
- [ ] User Lara criado biz=164 (Wagner pega dados pessoais com Martinho)
- [ ] Confirmar Dani = DANIELLI (id=297) ou criar novo user
- [ ] Reset senhas + envia WhatsApp credenciais
- [ ] Treinamento 1h síncrono Lara via Loom + 1h síncrono Dani
- [ ] Daemon roda 24/7 desde sábado · domingo Wagner monitora alertas
- [ ] Segunda 9h Lara entra `/oficinaauto/producao-kanban` + `/oficinaauto/vehicles` + `/products` consulta-only
- [ ] Segunda 9h Dani entra `/financeiro/dashboard` + `/financeiro/boletos`

**Critério aceite:**
- Lara reporta semana 1: estoque oimpresso bate com Delphi
- Dani reporta semana 1: boletos oimpresso bate com Delphi
- ZERO incident sev2 daemon
- Wagner WA Kamila confirmando "tá funcionando"

### Fase 3 — Cutover gradual (mes 1-3)

- Wagner habilita escrita oimpresso para área específica (ex: edição cadastro cliente) quando Lara+Dani confortáveis
- Tela por tela vira primary no oimpresso · daemon agora propaga **back to Delphi** (V2 — bidirectional)
- Kamila continua Delphi pra POS/venda até última fase
- Cutover total Delphi → oimpresso quando Martinho/Kamila decidem desligar Delphi

### Fase 4 — Bidirectional V2 (mes 4+)

- `daemon-sync-martinho.py` ganha modo `--bidirectional`
- Trigger Firebird `LOG_CHANGES` pra capturar escritas Delphi recentes
- Conflict resolution: timestamp-wins (last-write-wins) com human-review queue se mesmo row tocado <30s
- Permitir Delphi continuar como leitura-fallback se Lara quiser

---

## 5) Recovery e rollback

| Cenário | Recovery |
|---|---|
| Daemon trava | Lara/Dani continuam usando — dados ficam stale ≤5min. Wagner alerta WhatsApp em 10min sem heartbeat. |
| Daemon corrompe dados oimpresso | UPSERT idempotente → re-rodar mesmo daemon sobrescreve com valores Delphi atuais → consistência restaurada |
| Firebird Martinho cai | Daemon pausa · Lara/Dani vêem dados últimos sync (até 5min antes) · zero impacto operação Delphi continua |
| Hostinger cai | Lara/Dani não acessam oimpresso temporariamente · Kamila continua Delphi · daemon retoma quando volta |
| Cliente cancela contrato | Daemon para · Delphi continua intacto · oimpresso pode ser arquivado · zero perda dado |

---

## 6) Métricas e observabilidade

| Métrica | Meta MVP | Crítico |
|---|---:|---|
| Latência sync média | ≤5min | >15min = alerta |
| Daemon uptime 7d | ≥99% | <95% = revisar arquitetura |
| Rows divergentes daemon | <0.1% | >1% = pausar + investigar |
| Pest cross-tenant ROTA LIVRE intacta | 100% sempre | 1 falha = bloquear deploy |
| MTTR daemon falha | ≤1h | >4h = considerar HA |
| Custo Hostinger MySQL CPU diário | ≤+10% baseline | >30% = otimizar |

---

## 6.1) Lições aprendidas durante incidente 14/maio (incorporar no daemon)

Durante implementação Wave A prod 14/maio, identificados 4 issues que daemon dual-sync MVP DEVE resolver:

### Lição 1 — Firebird connection NÃO confiável pra batch grande

Durante import-financeiro tentou ler 103.997 rows num cursor único. Aos ~5.000 rows, Firebird Martinho (rede LAN 192.168.0.55) emitiu `Error writing data to the connection · connection shutdown`. Importer falhou rc=1. fin_titulos travou em 5.546 (6% do esperado).

**Fix obrigatório no daemon:**
- Leitura em **chunks paginados** por `CODIGO BETWEEN X AND X+1000` (não cursor único)
- **Connection retry** com exponential backoff: 3 tentativas (5s, 15s, 45s)
- **Checkpoint após cada chunk** — `sync_checkpoint.last_codigo_processed` salvo. Próximo retry retoma de onde parou
- **Alerta WhatsApp Wagner** se 3 retries falharem consecutivos
- Heartbeat health-check Firebird ANTES de iniciar batch (1 query SELECT 1 ping)

### Lição 2 — Importer NÃO pode sobrescrever metadata user-added

Durante incidente, importer-financeiro re-rodou e regrediu 748→134 flags `metadata.is_write_off_candidate`. Importer SOBRESCREVEU metadata existente com novo metadata do Firebird (UPDATE com SET metadata=%s).

Write-off flag foi adicionada POST-importer via cleanup batch. Dani vai adicionar tags/observações no UI. Esses dados PRECISAM ser preservados em re-runs.

**Fix obrigatório no daemon:**
- Pattern correto SQL: `UPDATE ... SET metadata = JSON_MERGE_PATCH(COALESCE(metadata, '{}'), %s)` (merge ao invés de replace)
- **Reservar namespaces JSON:**
  - `metadata.import_*` — importer pode reescrever (versão, source legacy_id, audit timestamps)
  - `metadata.user_*` — preservar SEMPRE (write-off, tags Dani/Lara, observações Wagner)
  - `metadata.system_*` — preservar (Jana hooks, FSM transitions audit)
- Pest test: re-rodar importer 2× verifica que `metadata.user_*` intacto

### Lição 3 — Bug família "lookup cross-business sem JOIN+WHERE business_id"

ROTA LIVRE (biz=4) teve 5 VLDs atualizadas indevidamente quando import-estoque rodou pra biz=164 — incidente catalogado [memory/sessions/2026-05-14-incidente-rota-livre-vld-recovery.md](../../sessions/2026-05-14-incidente-rota-livre-vld-recovery.md) (a criar).

Causa raiz: SELECT/UPDATE em `variation_location_details` usava `WHERE product_id=X AND variation_id=Y AND location_id=Z` SEM JOIN com products + filter business_id.

**Fix aplicado em 3 importers (14/maio 17h):**
- `import-estoque.py` linhas 359-388 — SELECT/UPDATE qualificados com `INNER JOIN products p + WHERE p.business_id=%s` + `wcur.rowcount==0 → skip + log`
- `import-produtos.py` linhas 560-571 — SELECT defensivo qualificado
- `import-compras.py` linhas 624-632 — UPDATE transactions com `AND business_id=%s` + rowcount check

**Pattern canônico daemon:**
- TODO SELECT/UPDATE em tabelas tenant deve ser qualificado com business_id (não confiar em variáveis Python só)
- Pest cross-tenant biz=1 vs biz=99 vs biz=164 obrigatório por importer
- Recovery rollback documentado caso aconteça novamente: query backup `~/.cagefs/tmp/oimpresso-dump-DATE.sql.gz` → extrair VLDs afetadas → UPDATE restore

### Lição 4 — SSH tunnel persistent precisa robustez

Background `migrar-martinho.py --target prod --confirm` travou 2× — output size=0 indica processo morreu sem printar (provavelmente subprocess.Popen + buffering Windows interagiram mal).

**Fix obrigatório no daemon:**
- Não usar `tee` pipe em background — usar `python -u` (unbuffered) + redirect `> log 2>&1` direto
- SSH tunnel com `ServerAliveInterval=3 ServerAliveCountMax=200` (já implementado) MAS adicionar **auto-reconnect via supervisord-style loop** (kill tunnel + reopen se ping localhost:33069 falhar)
- Heartbeat monitor: thread paralela escreve `daemon-heartbeat-DATETIME.json` a cada 60s — externo pode detectar zombie

---

## 7) Riscos e mitigação

| Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|
| Daemon escreve em biz errado (família bug ROTA LIVRE descoberto 14/maio) | Média (já vi 1×) | Alto (dados cliente errado) | Pest cross-tenant biz=1 vs biz=99 obrigatório · audit todos importers · qualificar JOINs |
| Latência 5min muito alta pra Dani notar pagamento entrou | Baixa | Médio | Configurar polling 2min financeiro |
| PC Wagner desligado fim de semana | Média | Médio | Alert Wagner se sem heartbeat 30min · pré-aviso Lara via WA "sistema vai parar" |
| Firebird DT_ALTERACAO não populada em rows antigos | Alta | Baixo | First full sync · daemon delta só depois |
| Conflito DT_ALTERACAO timezone (Firebird local Brasil vs MySQL Hostinger UTC) | Média | Médio | UTC tudo · documentar `tz business` armadilha A11 |
| Kamila esquece de marcar venda Delphi e venda some no oimpresso | Baixa | Alto | Reconciliação noturna semanal (compara COUNT) · alert se delta >5% |

---

## 8) Cronograma proposto

| Data | Marco |
|---|---|
| 2026-05-15 sex | Wagner valida proposta · aprova proceder |
| 2026-05-15 a 16 | Fase 1 — daemon MVP + bug fixes (1-2 dias dev) |
| 2026-05-17 sáb-dom | Daemon roda 24h dev test no PC Wagner · ele monitora |
| 2026-05-19 seg 9h | Fase 2 inicia · Lara + Dani entram canary |
| 2026-05-19 a 26 | Canary 7d · Wagner daily check · WA Lara/Dani feedback |
| 2026-05-26 seg | Avaliar canary · prorrogar 7d ou avançar pra cutover gradual |
| 2026-06-01 a 30 | Fase 3 cutover gradual feature-por-feature |
| 2026-09-01 | Avaliar Fase 4 bidirectional V2 (mes 4+) |

---

## 9) Quando descontinuar (review trigger)

Reabrir proposta como `superseded` se:

- Lara/Dani não reportam uso semanal (zero engagement) — sinal de que dual-system não é o que elas querem
- Daemon falha >10% rows em qualquer semana
- Wagner decide cutover total Delphi off antes do esperado (não precisa mais sync)
- Outro cliente OfficeImpresso peça approach diferente (sync push do Delphi via webhooks · etc)

---

## 10) ADRs relacionadas

- [ADR 0093](../0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0101](../0101-tests-business-id-1-nunca-cliente.md) — Pest biz=1 vs biz=99 nunca cliente
- [ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md) — Kamila é sinal qualificado
- [ADR 0119](../0119-paralelismo-sessoes-whats-active-tier-1.md) — Migration Factory pattern
- [ADR 0121](../0121-oimpresso-modular-especializado-por-vertical.md) — Modular especializado por vertical
- [ADR 0137](../0137-modules-oficinaauto-qualificada.md) — OficinaAuto qualificada Martinho
- [ADR 0143](../0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — FSM Pipeline LIVE prod
- [Migration pattern OfficeImpresso](../../reference/migracao-officeimpresso-pattern.md) — pattern existente importers

---

## 11) Próximos passos imediatos (se Wagner aprovar)

1. Wagner valida esta proposta + aprova como `accepted` virando ADR 0144
2. Spawn agent implementar Fase 1 daemon MVP (~1-2 dias IA-pair · Felipe[F] valida)
3. Bug fix import-estoque + audit produtos/compras (3 família-bug catalogados)
4. Re-aplicar import-financeiro completar 98k títulos biz=164 (já está rodando background)
5. Daemon teste no PC Wagner sábado/domingo
6. Lara + Dani entram segunda-feira 19/maio
7. Documentação em [memory/requisitos/Crm/RUNBOOK-daemon-sync-officeimpresso.md](../../requisitos/Crm/RUNBOOK-daemon-sync-officeimpresso.md) (NEW)

---

**Criado:** 2026-05-14 16:30 BRT
**Status:** proposed (aguarda Wagner aprovação)
**Autor:** Wagner + Claude (worktree naughty-euclid-2ab744)
**Origem:** insight Kamila Martinho (chat WhatsApp 15:51 14/maio)
**Versão:** 1.0 draft inicial
