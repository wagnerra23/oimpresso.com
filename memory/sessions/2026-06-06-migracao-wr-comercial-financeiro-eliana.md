---
date: '2026-06-06'
topic: 'Migração WR Comercial Delphi → oimpresso biz=1 WR2 Sistemas (sessão Eliana, 3 dias)'
authors: ['E', 'C']
prs: [2205, 2274, 2288, 2363]
related_handoff: memory/handoffs/2026-06-07-0220-migracao-financeira-wr2-completa-fix-kpi-juros.md
---

# Session log — migração WR Comercial Eliana

## Contexto

Sessão de ~3 dias com Eliana [E] (financeiro WR2 Sistemas, esposa Wagner) migrando dados do WR Comercial Delphi/Firebird legacy pro oimpresso biz=1 (WR2 Sistemas). Eliana é leiga em programação — sessão exigiu **comunicação não-técnica** o tempo todo (cortou várias vezes "estais sendo técnica, quero clareza e explicação").

## Linha do tempo resumida

### 5/jun — Etapa 1 (tipo Outros canon)

- Diagnóstico inicial: ADR 0246 escrito + accepted (Wagner aprova fallback "Outros" como tipo canônico pra migrações legacy)
- Migration `add_is_other_flag_to_contacts` (PR #2205)
- UI: 6ª aba "Outros" + 5º chip ClassificacaoTab
- Bug whitelist `$types` faltava 'other' (PR #2274 hotfix)
- Deploy não atualizava por OPcache LSPHP — PR #2288 ajustou deploy.yml

### 5/jun noite — Acesso bloqueado

- SSH key canônica `id_ed25519_oimpresso` só no PC do Wagner
- Token Hostinger API 🔴 EXPIRED desde 28/mai
- Eliana frustrada: "se vira tem que fazer isso sem a minha intervenção como fez na martinho"
- Solução: gerei keypair novo `claude-eliana-20260605` + Wagner cola public key na `~/.ssh/authorized_keys` Hostinger via SSH local dele (30 segundos)

### 6/jun manhã — Migração 13.703 contatos

- Conexão Firebird via `Servidor-crm:Banco` (LAN WR2)
- Python `firebird-driver` + `fbclient.dll` x64 (download GitHub)
- Export 13.703 PESSOAS → CSV 24MB (42 col SIMPLES + 277 extras JSON)
- Upload Hostinger + PHP batch INSERT (LOAD DATA INFILE bloqueado shared hosting)
- UPSERT staging → contacts biz=1: 13.388 novos + 315 dups CPF Firebird (ON DUPLICATE)
- Fix `tipo` PF/PJ inferindo do tax_number (170 PF + 1.270 PJ)
- Validação paridade (com clareza pra Eliana entender)

### 6/jun tarde — Plano de contas + limpeza

Eliana autorizou limpa + migra:
- Backup defensivo `dump-biz1-2026-06-06.sql` (257 INSERTs)
- DELETE em ordem FK: baixas → titulos → categorias → planos
- INSERT 159 PLANOCONTAS Firebird preservando hierarquia (UPDATE parent_id JOIN)
- 19 órfãos identificados (códigos `11.x`/`12.x` sem pai — dado sujo Delphi)

### 6/jun noite — Restrição Operacional#1

Eliana pediu: "não quero que Maiara/Felipe/Luiz acessem o financeiro da WR2".
- 1ª tentativa: mover pra biz=43 Suporte (REVERTIDO — precisavam WhatsApp em biz=1)
- 2ª solução: role custom `Operacional#1` (18 perms) com WhatsApp + Jana + print_invoice + superadmin, sem financeiro/paymentgateway/profit_loss
- Validado: 0 perms financeiras dos 3, 6 perms WhatsApp mantidas

### 7/jun madrugada — Migração 38.442 títulos + 35.315 baixas

Eliana copiou banco Firebird via IBExpert remoto:
- Backup .fbk 8.05 GB → .rar 1.84 GB → transferiu via `\\Servidor-crm\Dados`
- Local: `gbak.exe -c` restaurou pra `BANCO_VIVO.fdb` 9.5 GB

Escopo refinado iterativo:
- Inicial: "Plano de contas limpa e migra tudo"
- Eliana detalhou: "Mensalidades A RECEBER só até 30/06/2026, demais ilimitado"
- Eliana detalhou: "verifica DOCUMENTO pq às vezes coloco código cliente+mês mesmo sem plano 1.2.1"

Achei pelo padrão DOCUMENTO regex `^\d+(-\d+)?/(JAN|FEV|...)/\d{4}$`: 117 mensalidades escondidas em outros planos.

Bug PK composta detectado: 13.703 → 24.625 só inserindo. Investigando RDB$INDEX_SEGMENTS descobri PK = (CODPEDIDO, CODIGO, CODEMPRESA). Refiz com legacy_id composto + origem_id = raw_csv_line. 38.442 inseridos limpo.

35.315 baixas históricas migradas (RECEBIDA + PAGA com data_baixa populada).

### 7/jun madrugada — Validação paridade WR2 ↔ oimpresso

Eliana validou jan/2026:
- Tela mostra PAGO R$ [redacted Tier 0] / 36 entradas
- WR2 mostra DÉBITO R$ [redacted Tier 0]
- Diff R$ [redacted Tier 0]

Diagnóstico: oimpresso card só soma `valor_baixa`, WR2 mostra valor + juros. R$ [redacted Tier 0] de juros pagos preservados em `fin_titulo_baixas.juros`. Diff R$ [redacted Tier 0] = arredondamento histórico Delphi.

### 7/jun 02:15 — Fix KPI cards mergeado

PR #2363:
- 3 controllers ajustados (UnificadoController + DashboardController + RelatoriosController)
- Fórmula nova: `COALESCE(SUM(valor_baixa + juros + multa - desconto), 0)`
- REGRA MESTRE cálculo de valor cumprida (dupla confirmação SQL ↔ WR2, impacto antes→depois, aprovação Eliana + Wagner)
- CI 15/15 verde
- Wagner mergeou via Eliana intermediando: "pode revisar e mergear?"
- Deploy confirmado: commit `c30e870a2` em prod

## Comunicação não-técnica catalogada

Eliana cortou várias vezes pra exigir clareza:
- "estais falando muito tecnica, não entendo muito de programação"
- "comigo nao fale mais com essa linguagem tecnica. eu preciso de clareza e expllicação sempre"
- "de novo sendo tecnico. quero clarezaaaa e explicaçãoooo"

Padrão que funcionou:
- Mostrar tabelas antes→depois em PT-BR claro
- Nunca usar termos como "controller", "framework", "endpoint" sem traduzir
- Explicar via analogias ("é como se a WR2 fosse uma casa cofre")
- Dar frase pronta pro Wagner colar no Claude dele (Eliana é a ponte)
- Cada passo: Status visual claro ("✅ feito · 🔄 em andamento · ⏳ aguarda")

## Pegadinhas técnicas detectadas

1. **PK FINANCEIRO Firebird composta** (CODPEDIDO+CODIGO+CODEMPRESA) — CODIGO=1 aparece 1.044x no banco
2. **WR Comercial DATAPAGTO digitação errada** (ano 2009 quando deveria ser 2010) — 1.555 baixas com ANO baixa ≠ ANO vencimento, 113 com 6+ meses diff
3. **CoworkDataMapper** carrega só hoje±meses + limit 500, ignora filtros UI data customizada
4. **MariaDB collation default `utf8mb4_uca1400_ai_ci`** ≠ UPOS `utf8mb4_unicode_ci` — JOIN precisa COLLATE explícito
5. **PowerShell hook bloqueia checkout em D:\oimpresso.com** (Herd serve) — worktree obrigatório

## Defesas mecânicas validadas

- ✅ Backup defensivo SEMPRE antes de DELETE/UPDATE massivo (5 backups gerados em `output/`)
- ✅ Tier 0 anti-vazamento (biz=4 ROTA LIVRE + biz=164 Martinho confirmados intactos em cada etapa)
- ✅ REGRA MESTRE cálculo valor aplicada no fix KPI (PR #2363 documenta dupla confirmação)
- ✅ Worktree disciplina (`fix-kpi-juros` isolado, não tocou checkout principal)

## Outputs finais

- 13.703 contatos · 159 plano de contas · 20 contas bancárias · 38.442 títulos · 35.315 baixas
- 5 backups defensivos (4 SQL + 1 .fbk 9.5GB)
- 12 scripts Python + SQL canon em `scripts/legacy-migration/sql-wr2-pessoas/`
- 4 PRs mergeados: #2205 #2274 #2288 #2363

## Refs

- Handoff: [2026-06-07-0220-migracao-financeira-wr2-completa-fix-kpi-juros](../handoffs/2026-06-07-0220-migracao-financeira-wr2-completa-fix-kpi-juros.md)
- ADR 0246: [tipo Outros default migrações legacy](../decisions/0246-tipo-outros-default-migracoes-legacy.md)
- PESSOAS mapping completo: [memory/requisitos/Officeimpresso/PESSOAS-MAPPING-COMPLETO.md](../requisitos/Officeimpresso/PESSOAS-MAPPING-COMPLETO.md)
