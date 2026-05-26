---
artefato: runbook
escopo: Migração cliente Office Impresso Desktop (Delphi WR Comercial + Firebird) → oimpresso.com
status: ativo (validado Martinho 2026-05-13→17 + ativação formal ADR 0171 + corrigido pós-ADR 0194)
piloto: Martinho Caçambas LTDA biz=164 Tubarão SC Humaitá de Cima (sub-vertical 4 mecânica pesada caminhão basculante · errata endereço 2026-05-26)
proximos_clientes: Vargas (sub-vertical 2 recapagem V1), Extreme, Gold, Zoom, Fixar, Mhundo, Produart
ultima_atualizacao: 2026-05-26
related_adrs: [0093, 0094, 0105, 0119, 0137, 0143, 0171, 0192, 0194]
owner: [W]
---

# RUNBOOK — Migração cliente legacy Office Impresso Desktop → oimpresso.com

> "A consistência da migração vai definir o processo" — Wagner 2026-05-20

> **Refs:** [ADR 0137](../../decisions/0137-modules-oficinaauto-qualificada.md) (OficinaAuto qualificada · Martinho #1, amendado por 0194), [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) (FSM Pipeline LIVE), [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) (cliente como sinal — guiar sem mandar), [ADR 0119](../../decisions/0119-paralelismo-sessoes-whats-active-tier-1.md) (paralelismo sessões), [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) (multi-tenant Tier 0), [ADR 0171](../../decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md) (ativação piloto Martinho faseada), [ADR 0192](../../decisions/0192-auto-faturar-os-venda-jobsheet-observer.md) (auto-faturar OS→Venda), **[ADR 0194](../../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) (correção domínio mecânica pesada — 2026-05-26)**
> **Validado prod:** Martinho Caçambas LTDA biz=164 **Tubarão SC Humaitá de Cima** (Location BL0001 · `MartinhoServidor` → biz=164 · errata endereço 2026-05-26 smoke prod) · 91 vehicles + ~103k títulos + ~44k vendas (sessão maratona 2026-05-13 → 2026-05-17) · Auto-faturar OS→Venda LIVE 2026-05-25 (ADR 0192 ext) · NFSe Caminho A fix LIVE 2026-05-26 (PR #1597)
> **Scripts:** [`scripts/legacy-migration/`](../../../scripts/legacy-migration/)
>
> **⚠️ Atenção pós-ADR 0194 (2026-05-26):** Martinho é **sub-vertical 4 mecânica pesada caminhão basculante CNAE 4520** — NÃO locação caçamba container CNAE 4581 como leitura original inferiu. Vocabulário correto: **peça hidráulica · PTO · kit hidráulico · hora-trabalho** (não m³ + diária). 91 placas Firebird são **caminhões de CLIENTES** que entram pra peça/serviço (não frota própria do Martinho · não caçamba estacionária). Schema `daily_rate`/`expected_return_date` preservado nullable como sub-vertical 3 hipotético sem cliente real ancorado.

Receita pra migrar qualquer cliente Office Impresso Desktop (Delphi WR Comercial + Firebird `BANCO.FDB`) pro oimpresso.com (Laravel + MySQL Hostinger) sem cutover hard, preservando auditoria via `legacy_id` em todas as tabelas-alvo. Padrão **Strangler Fig + Anticorruption Layer** — desktop continua rodando durante coexistência.

## Estado final esperado (M3+ por cliente)

| Verificação | Como conferir |
|---|---|
| Operador-chave usa só o oimpresso pra novas operações | Wagner+operador assinam handoff escrito |
| Desktop fica read-only fallback 12 meses | Firebird ainda acessível, sem novas escritas |
| Todas as queries críticas legacy têm equivalente Laravel | RUNBOOK-debrief preenchido |
| Backup `.FDB` arquivado em local seguro | `D:\backup-clientes-legacy\<cliente>-YYYY-MM-DD.fdb` + cópia Vaultwarden |
| Métricas Jana batem ±5% vs Firebird histórico | `php artisan jana:health-check --business=<NNN>` verde |

## 0. Pré-flight (~1 dia antes — manhã)

### 0.1 Stakeholders e alinhamento comercial

- [ ] **Dono confirma migração faseada** (não cutover hard) por escrito (WhatsApp/email)
- [ ] **Operador-chave identificado** — quem opera o desktop diariamente (filho? funcionário? dono?)
- [ ] **Opção comercial fechada** — A (beta 30d) · B (faseada) · C (pacote completo). Ver [demo-script.md](demo-martinho-2026-05-13/demo-script.md) §fechamento
- [ ] **TODO/PENDENTE Wagner:** prazo de cutover combinado (canary 7d? 30d?) preenchido aqui no RUNBOOK do cliente

### 0.2 Acessos físicos e credenciais

- [ ] Credenciais Firebird canônicas validadas — `SYSDBA` / `masterkey` / charset `WIN1252` (ver [legacy-delphi-firebird.md §credenciais canônicas](../../reference/legacy-delphi-firebird.md))
- [ ] **DSN/alias servidor + path BANCO.FDB do cliente** documentado — ex: `MartinhoServidor` → `D:\DadosClientes\MartinhoCacamba\Dados\BANCO.FDB`
- [ ] **Wagner local na LAN do servidor-crm** OU VPN configurada — Firebird remoto não acessível de fora
- [ ] **Hostinger SSH OK** — `ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115` conecta
- [ ] **business_id criado em prod** (MySQL Hostinger) — popular `tax_number_1` depois que importer EMPRESA rodar
- [ ] **TODO/PENDENTE Wagner:** documentar processo canônico de criação da `business` row inicial (manual via admin? script? — preencher quando segundo cliente rodar)

### 0.3 Workspace técnico

- [ ] Worktree filho criado: `git worktree add ../../worktrees/<cliente>-migration claude/<cliente>-migration-YYYY-MM-DD`
- [ ] `scripts/legacy-migration/` revisado — checar se mudou desde último cliente (`git log -p scripts/legacy-migration/`)
- [ ] `.env` local copiado de `.env.example` (se faltar) · `FIREBIRD_PASSWORD=masterkey`
- [ ] Python venv ativo + deps: `cd scripts/legacy-migration && python -m venv .venv && pip install -r requirements.txt`
- [ ] Cliente Firebird (`fbclient.dll`) instalado na máquina do Wagner (já vem com desktop Delphi)

## 1. Discovery dados legacy (~2h)

### 1.1 Conectar e validar Firebird

- [ ] Conexão POC: `python scripts/legacy-migration/poc2-firebird-connect.py --alias <Alias>` retorna `VERSAO_BANCO` ≥ 1308 e ≥ 1 tabela
- [ ] Anotar `VERSAO_BANCO` no perfil do cliente (`memory/research/clientes-legacy-officeimpresso/NN-<cliente>/01-perfil.md`) — adapter dos importers detecta cols ausentes por versão

### 1.2 Probe schema das 7 tabelas-chave

Adapter automático via `SELECT FIRST 1 *` detecta cols ausentes. Mas pra discovery rápido:

```sql
-- Rodar em isql.exe ou via scripts/legacy-migration
SELECT COUNT(*) FROM EMPRESA;             -- N pessoas jurídicas (Martinho: 1)
SELECT COUNT(*) FROM PESSOA;              -- contacts (Martinho: TODO ainda não probe)
SELECT COUNT(*) FROM EQUIPAMENTO_VEICULO; -- vehicles (Martinho: 91)
SELECT COUNT(*) FROM VENDA;               -- transactions (Martinho: ~44.709)
SELECT COUNT(*) FROM FINANCEIRO;          -- titulos (Martinho: ~103.000)
SELECT COUNT(*) FROM PRODUTO;             -- products (Martinho: TODO ainda não probe)
SELECT COUNT(*) FROM CONTAS;              -- accounts (Martinho: TODO ainda não probe)
```

- [ ] Cardinalidades anotadas no perfil cliente · comparar com Martinho pra dimensionar esforço

### 1.3 Tabela de equivalência canônica

| Tabela legacy Firebird | Tabela alvo Laravel/MySQL | Cardinalidade Martinho |
|---|---|---|
| `EMPRESA` | `businesses` + `contacts` (entidade própria) | 1 |
| `PESSOA` | `contacts` (cliente/fornecedor) | TODO probe |
| `EQUIPAMENTO_VEICULO` | `vehicles` | **91** (87 com PLACA real, 4 placeholder `S/N-{codigo}`) |
| `VENDA` | `transactions` (`type=sell`) | **~44.709** |
| `FINANCEIRO` | `fin_titulos` + `fin_titulo_baixas` | **~103.000 títulos** |
| `PRODUTO` | `products` | TODO probe |
| `CONTAS` | `accounts` + `fin_contas_bancarias` | TODO probe (Martinho não tinha receita bancária crítica) |
| `WR_KANBAN` / `VENDA_ESTAGIO` | `oa_kanban_state` + FSM | 2 estados Martinho (mínimo viável) |

### 1.4 Sinais e quirks específicos do cliente

- [ ] **Sub-vertical identificada** (decide vocabulário + concorrentes + features prioritárias):
  - **Sub-vertical 4** (Martinho · mecânica pesada/autorizada caminhão basculante CNAE 4520): vocabulário = **peça hidráulica · PTO · kit hidráulico · hora-trabalho · OS programada · catálogo cross-ref Scania/Volvo/MB/Ford**
  - Sub-vertical 2 (Vargas · recapagem pneu caçamba caminhão CNAE 2212): vocabulário = pneu · borracha · cola · multi-placa cavalo+reboque
  - Sub-vertical 3 (hipotético · locação caçamba container CNAE 4581): vocabulário = m³ · diária · sem cliente real ancorado · schema preservado nullable
  - Ver dicionário canon [dominios-verticais-oimpresso.md §"Modules/OficinaAuto"](../../reference/dominios-verticais-oimpresso.md)
- [ ] Cliente usa cavalo+reboque? (Vargas sim, Martinho não — PLACA2 e CHASSI2 = 0%)
- [ ] Cliente tem FSM (VENDA_ESTAGIO)? Quantos estados?
- [ ] Cliente usa PCP (centro_trabalho)? (Martinho não — bate com perfil concessionária/autorizada peça+serviço, não fabricação · Vargas/oficina pesada provavelmente sim)
- [ ] Saúde financeira histórica anotada (Martinho: R$ [redacted Tier 0]M receita 12m · **76.7% inadimplência** — exigiu batch write-off via US-OFICINA-030 cleanup §B, ver §5)
- [ ] **Cadeia comercial mapeada** (fornecedores B2B / clientes finais): Martinho tem cadeia Tork PTO (fábrica Capivari) → Martinho (revenda+instala) → frota basculante terceiro — Tork virou prospect via ADR 0194 ([perfil em clientes-prospect/tork-tomadas-forca/](../../research/clientes-prospect/tork-tomadas-forca/01-perfil.md))

## 2. Importer dry-run (~2h)

Padrão de execução: **probe → dry-run → audit JSON Wagner aprova → local (Herd) → smoke local → prod**.

### 2.1 Ordem canônica dos importers

A ordem importa por causa de FKs (transactions exige contact_id NOT NULL):

1. **`import-empresas.py`** → `contacts` da própria empresa (entidade Wagner type='both')
2. **`import-contacts-from-venda.py`** → `contacts` clientes finais (extrai DISTINCT CNPJ de VENDA, popula `contacts.legacy_id=<CNPJ_normalizado>`)
3. **`import-contas-bancarias.py`** → `accounts` + `fin_contas_bancarias` (necessário pra `fin_titulo_baixas.conta_bancaria_id`)
4. **`import-vehicles.py`** → `vehicles` (NÃO depende de FK · vehicle_type default **`caminhao_basculante`** pra sub-vertical 4 mecânica pesada — antes era `cacamba_avulsa` na leitura pré-ADR 0194 errada; manter compat backwards mas novos imports usam `caminhao_basculante`)
5. **`import-vendas.py`** → `transactions` (depende de contacts + vehicles lookups)
6. **`import-financeiro.py`** → `fin_titulos` + `fin_titulo_baixas` (depende de transactions + fin_contas_bancarias)

### 2.2 Dry-run de cada importer

Padrão de comando (ajustar `--alias` e `--target-business`):

```bash
cd scripts/legacy-migration
python import-vehicles.py --alias <Alias> --target-business <NNN>
# Default --target dry-run · gera output/dry-run-vehicles-bizNNN-YYYYMMDD-HHMMSS.sql
#                            + output/audit-vehicles-bizNNN-YYYYMMDD-HHMMSS.json
```

- [ ] Cada importer rodou sem erro · audit JSON sem `errors > 0`
- [ ] Wagner inspecionou `output/*.sql` de cada importer (sample 5-10 rows) · sem PII vazada
- [ ] Stats batem com discovery §1.2 (ex: vehicles dry-run lê 91 == probe 91)

### 2.3 Idempotência via `legacy_id`

Garantia idempotência por importer (re-rodar = no-op pra existentes, update pra mudanças):

| Tabela alvo | Chave UPSERT |
|---|---|
| `contacts` | (`business_id`, `cpf_cnpj`) — fallback (`business_id`, `legacy_id`) |
| `vehicles` | (`business_id`, `legacy_id`) — schema US-OFICINA-001 PR #556 |
| `transactions` | (`business_id`, `ref_no`) — `ref_no` preserva `VENDA.CODIGO` Delphi |
| `fin_titulos` | (`business_id`, `legacy_id`) |
| `fin_titulo_baixas` | `idempotency_key` = `leg-{business_id}-{legacy_id}` |

- [ ] Rodar mesmo importer 2x dry-run em sequência · audit reporta 0 conflitos · 2ª run não mudaria nada

## 3. Apply local (~1h) + smoke Laragon

- [ ] Subir cliente em Laragon/Herd local com seed do business (mesmo `business_id` que vai pra prod)
- [ ] Rodar cada importer com `--target local` na ordem §2.1
- [ ] Após cada importer: spot-check direto MySQL (3-5 queries de validação)

```sql
-- Exemplo vehicles
SELECT COUNT(*) FROM vehicles WHERE business_id=<NNN>;          -- deve == discovery
SELECT plate, current_status FROM vehicles WHERE business_id=<NNN> LIMIT 5;
SELECT COUNT(*) FROM vehicles WHERE business_id=<NNN> AND plate LIKE 'S/N-%';  -- placeholders

-- Exemplo fin_titulos
SELECT tipo, status, COUNT(*) FROM fin_titulos WHERE business_id=<NNN> GROUP BY tipo, status;
SELECT JSON_EXTRACT(metadata, '$.is_write_off_candidate'), COUNT(*) FROM fin_titulos WHERE business_id=<NNN> GROUP BY 1;
```

- [ ] Smoke browser Laragon: login admin → `/oficina-auto/vehicles` lista 91 (ou cardinalidade do cliente) · OS aparecem · `/financeiro/titulos` lista os títulos importados
- [ ] **TODO/PENDENTE Wagner:** documentar checklist exato de telas pra smoke local (varia por bucket de cliente — oficina, vendas, gráfica, etc)

## 4. Apply prod biz=<NNN> (~1h)

### 4.1 Pré-flight prod

- [ ] Backup MySQL Hostinger antes — `php artisan db:backup --tag=pre-<cliente>-migration`
- [ ] SSH tunnel local: `python migrar-tudo.py --target prod --confirm` abre tunnel automático 127.0.0.1:33069 → Hostinger MySQL (ver [`migrar-tudo.py`](../../../scripts/legacy-migration/migrar-tudo.py))
- [ ] `--target prod` SEMPRE exige `--confirm` explícito (publication-policy §prod write)

### 4.2 Execução prod

Padrão preferido — usar orquestrador (atualmente cobre contas + empresas; outros importers ainda standalone):

```bash
cd scripts/legacy-migration
python migrar-tudo.py --target prod --confirm \
    --target-business <NNN> --alias <Alias>
```

Pra importers ainda não orquestrados (vehicles, vendas, financeiro), rodar individualmente:

```bash
# Vehicles
python import-vehicles.py --alias <Alias> --target-business <NNN> --target prod --confirm

# Vendas (FILTRAR por período pra clientes grandes — Martinho 44k vendas)
python import-vendas.py --alias <Alias> --target-business <NNN> \
    --start-date 2025-05-13 --end-date 2026-05-13 \
    --target prod --confirm

# Financeiro
python import-financeiro.py --alias <Alias> --target-business <NNN> --target prod --confirm
```

- [ ] **TODO/PENDENTE Wagner:** estender `migrar-tudo.py` pra orquestrar TODOS os 6 importers em ordem canônica (atualmente só contas + empresas) — vira US futura quando segundo cliente rodar

### 4.3 Smoke prod biz=<NNN>

- [ ] `curl -sv` em rota crítica (ver skill `smoke-prod-evidence` — NÃO declarar "OK" sem status code literal)
- [ ] Browser MCP (`mcp__Claude_in_Chrome__*`) abre `https://oimpresso.com` → login com user do cliente → sidebar DataController mostra menus corretos do bucket
- [ ] Screenshot salvo em `memory/requisitos/OficinaAuto/<cliente>/smoke-prod-YYYY-MM-DD/`
- [ ] FSM canon validada — abre Kanban Producao Oficina (US-OFICINA-001 LIVE), arrasta card, transição persiste

## 5. Coexistência paralela (1-7d · ou per ADR 0105)

### 5.1 Modo operação faseado

- Desktop **continua sendo source-of-truth** pra operações já existentes (OS abertas, boletos em andamento)
- oimpresso **recebe novas operações incrementais** (Kanban Producao novo, aprovação WhatsApp, NFe nova)
- Cliente decide ritmo — Wagner não pressiona ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md))

### 5.2 Daily check Wagner+operador

- [ ] Wagner liga/zap operador 1x/dia primeira semana · pergunta "alguma coisa estranha hoje?"
- [ ] Bugs reportados viram tasks MCP (`tasks-create`) com tier-priorização (P0 quebra negócio · P1 atrapalha · P2 desconforto · P3 cosmético)
- [ ] Métricas Jana cruzadas com desktop pelo menos 1x/semana — `jana:health-check --business=<NNN>` + spot-check 3 queries Firebird vs MySQL

## 6. Cleanup tools (varia)

Específicos por cliente — só faz sentido quando aparece sujeira durante coexistência:

### 6.1 Tela "Revisão pendências legadas" (US-OFICINA-005)

- [ ] Lista `vehicles` com `plate LIKE 'S/N-%'` (placeholders importados sem placa) — operador completa cadastro
- [ ] Lista `fin_titulos` com `metadata.is_write_off_candidate=true` — write-off batch (Martinho: ~76.7% inadimplência exigiu batch >1 ano vencido sem boleto sem mov)
- [ ] Lista `transactions` com `contact_id IS NULL` (skip importer por CNPJ não resolvido) — operador associa manual

### 6.2 Conciliação VENDA ↔ FINANCEIRO

- [ ] Query cruzada: `transactions` órfãs (sem `fin_titulos` associado via `metadata.transaction_id_resolved`)
- [ ] Query cruzada: `fin_titulos` órfãs (sem `transaction_id_resolved` populado · venda Delphi cancelada/excluída)

### 6.3 Renegociação inadimplência

- [ ] Wagner+operador discutem batch — usar relatórios Jana (`memory/research/relatorios-jana/01-inadimplencia.md`) pra priorizar

## 7. Cutover por feature (M2-M3 — quando operador aprovar)

Ritmo cliente-driven — NÃO empurrar:

- [ ] Feature A → oimpresso source-of-truth · desktop só leitura · operador assina por feature
- [ ] Feature B → mesmo padrão
- [ ] Funcionalidades novas (WhatsApp aprovação, Kanban drag-drop FSM, Jana brief diário) só existem no oimpresso — incentivo natural

**TODO/PENDENTE Wagner:** ordem canônica de cutover por bucket (oficina caçamba avulsa? oficina mecânica? gráfica? vendas avulsas?) — preencher quando 3-4 clientes tiverem rodado e padrão emergir.

## 8. Encerramento desktop (M3+)

- [ ] Wagner+operador assinam handoff escrito ("desktop fica read-only fallback a partir de YYYY-MM-DD")
- [ ] Desktop fica read-only fallback 12 meses (preserva conformidade Receita Federal · obrigação fiscal 5 anos)
- [ ] Backup `.FDB` final arquivado em D:\backup-clientes-legacy\ + cópia Vaultwarden
- [ ] Cliente registra acesso superuser oimpresso (não mais via Delphi)
- [ ] WR2 desktop pode ser desinstalado por opção do cliente (não obrigatório)

## 9. RUNBOOK debrief (M3+)

- [ ] Lições do cliente NN apendadas em §10 CHANGELOG aqui
- [ ] Comparar tempo real vs estimado — calibrar pros próximos
- [ ] Skill `automem-pending` ou commit em `memory/requisitos/OficinaAuto/<cliente>/debrief.md` com:
  - Cardinalidades reais
  - Surpresas por tabela (quirks Delphi específicos)
  - Tempo gasto por fase (descobrir × estimado pra calibrar)
  - Bugs corrigidos durante o processo (vira PR em `scripts/legacy-migration/`)

## 10. Gotchas conhecidas (acumular por cliente)

### Martinho (piloto · 2026-05-13 → 2026-05-17 · sub-vertical 4 mecânica pesada caminhão basculante)

- **🚨 LEITURA ORIGINAL ERRADA pré-ADR 0194 (descoberta 2026-05-26):** classificado como "locação caçamba container avulsa CNAE 4581" com vocabulário m³ + diária. CORREÇÃO: Martinho é loja de **peça hidráulica + oficina autorizada caminhão basculante CNAE 4520** (Capivari de Baixo/SC). 96% PLACA Firebird = caminhões de CLIENTES (caçamba container estacionária não tem placa). Faturamento R$ [redacted Tier 0]M+/mês compatível com mecânica pesada autorizada, não locação container ticket R$ [redacted Tier 0]-500/diária. Ver ADR 0194 + [perfil cliente](../../research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md).
- **Vocabulário canon sub-vertical 4**: peça hidráulica · PTO (tomada de força) · kit hidráulico · hora-trabalho · OS programada · catálogo cross-ref Scania/Volvo/MB/Ford · NÃO m³/diária/locação.
- **m³ caçamba volume** mantido APENAS pra sub-vertical 3 hipotético (locação container) — sem cliente real ancorado em 2026-05-26. Schema `daily_rate`/`expected_return_date`/`delivery_address` preservado nullable, review_trigger M6+ caso cliente real surgir.
- **4 caçambas sem placa** → importer usa `S/N-{codigo}` como placeholder em `vehicles.plate` (campo é NOT NULL · operador completa manual depois via tela cleanup US-OFICINA-029)
- **76.7% inadimplência** — exigiu batch write-off `metadata.is_write_off_candidate=true` via heurística `import-financeiro.py`: TIPO='A RECEBER' AND VENCTO < (NOW - 365d) AND DATAPAGTO IS NULL AND BOLETO_NOSSO_NR IS NULL AND JUROS=0 AND DESCONTO=0
- **FINANCEIRO 103k títulos** — STATUS_FILTERS detecta lixo/duplicação (`ATIVO*` saldo virtual, `INATIVO EXCLUIDO` venda cancelada, `INATIVO AGRUPADO` filha agrupada · ver `STATUS_FILTERS` em [`import-financeiro.py:78`](../../../scripts/legacy-migration/import-financeiro.py))
- **VENDA 44k vendas — FILTRAR por período** com `--start-date`/`--end-date` (importar tudo em uma transação pode estourar timeout Hostinger)
- **`transactions.contact_id` NOT NULL FK** — pré-req: rodar `import-contacts-from-venda.py` ANTES de `import-vendas.py` (popula `contacts.legacy_id=<CNPJ_normalizado>` pro lookup)
- **`fin_titulos` UK (business_id, origem, origem_id, parcela_numero)** — múltiplos FINANCEIRO Delphi apontam pra mesma VENDA com parcela colidente → workaround: `origem='manual'` + `origem_id=NULL` + link Delphi em `metadata.delphi_codpedido`
- **PII redaction obrigatória em audit JSON** — RAZAOSOCIAL, DOCUMENTO, NOTAFISCAL, CPFCNPJ → `[REDACTED]` (ADR LGPD · ver feedback-nunca-publicar-credenciais.md)
- **Segredos EMPRESA NUNCA migrar** — CERTIFICADO (PKCS#12), CERTIFICADO_SENHA, NFE_NUMSERIE, NFCE_*_CSC, WEB_SERVICE_SENHA, NFSE_SENHA, APP_SENHA · decisão Vaultwarden integration pendente ADR

### `<Próximo cliente>` (Vargas? Extreme?)

(preencher quando rodar — provavelmente cavalo+reboque PLACA2/CHASSI2, PCP centro_trabalho, oficina mecânica vocabulário diferente)

## 11. Refs

- [ADR 0137](../../decisions/0137-modules-oficinaauto-qualificada.md) — OficinaAuto qualificada · Martinho #1
- [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — FSM Pipeline LIVE prod
- [ADR 0119](../../decisions/0119-paralelismo-sessoes-whats-active-tier-1.md) — Migration Factory (sessões paralelas)
- [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — cliente como sinal · guiar sem mandar
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0
- [discovery-martinho.md](demo-martinho-2026-05-13/discovery-martinho.md) — sessão pós-reunião 2026-05-13 10h
- [01-perfil Martinho](../../research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md) — sinais Firebird piloto
- [03-financeiro Martinho](../../research/clientes-legacy-officeimpresso/05-martinho-cacambas/03-financeiro-2026-05-11.md) — R$ [redacted Tier 0]M receita 12m · 76.7% inadimplência
- [legacy-delphi-firebird.md](../../reference/legacy-delphi-firebird.md) — credenciais SYSDBA/masterkey + DSN aliases + schema CONTAS/EMPRESA
- [`scripts/legacy-migration/`](../../../scripts/legacy-migration/) — 6 importers + orquestrador + POCs
- [`scripts/legacy-migration/README.md`](../../../scripts/legacy-migration/README.md) — Strangler Fig + ACL pattern
- [feedback-legacy-migration-importer.md](../../reference/feedback-legacy-migration-importer.md) — lições da Fase 5 (biz=1 Wagner)
- [migracao-officeimpresso-pattern.md](../../reference/migracao-officeimpresso-pattern.md) — pattern canônico
- Skill `multi-tenant-patterns` · skill `smoke-prod-evidence` · skill `publication-policy`

---

## CHANGELOG

- **2026-05-26** — Reescrita pós-[ADR 0194](../../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) correção domínio Martinho (US-OFICINA-028 parte 3/5). Vocabulário sub-vertical 4 mecânica pesada caminhão basculante CNAE 4520 (era classificação errada CNAE 4581 locação container até 2026-05-25). Adicionados: bloco de aviso vocabulário no topo, §1.4 expansão sub-vertical identificação (4 sub-verticais distintas), §2.1 vehicle_type default `caminhao_basculante` (era `cacamba_avulsa`), §10 Gotchas Martinho item de correção origem do erro de leitura. Adicionada cadeia comercial Tork PTO prospect 2026-05-26. RUNBOOK status `draft` → `ativo` (validado prod biz=164 + ativação formal ADR 0171 2026-05-20 + auto-faturar LIVE 2026-05-25 + NFSe fix LIVE 2026-05-26).
- **2026-05-20** — Skeleton inicial criado por Claude (sessão worktree `frosty-greider-83ab2f`). Baseado em migração maratona Martinho 2026-05-13 → 2026-05-17 (perfil + discovery + 6 importers + commits + audit JSONs reais). 4 lacunas TODO/PENDENTE marcadas pro Wagner preencher quando 2º cliente rodar: (a) prazo cutover canônico por bucket, (b) processo canônico criação `business` row, (c) checklist exato smoke local por bucket, (d) orquestração `migrar-tudo.py` cobrindo TODOS 6 importers.
