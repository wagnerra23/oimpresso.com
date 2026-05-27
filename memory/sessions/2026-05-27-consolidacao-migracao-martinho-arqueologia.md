---
date: '2026-05-27'
hour: '14:00 BRT'
topic: 'Consolidação migração Martinho biz=164 — arqueologia 4 dimensões + decisão pattern canônico + correção overlap PR #1765 vs #1766'
authors: [W, C]
prs: [1765, 1766]
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0197-extend-contacts-absorcao-pessoas-legacy
  - 0198-hot-cold-tiering-migracao-transacional-legacy
  - 0200-contacts-sync-canon-amends-0197-0199
  - 0203-legacy-migration-pipeline-firebird-oimpresso-w29
slug: consolidacao-migracao-martinho-arqueologia
sessao: arqueologia + decisão pattern canônico migração legacy
business_id_alvo: 164
modulo: Officeimpresso
relacionado_handoffs:
  - 2026-05-14-1015-martinho-demo-reuniao-pivot-jana-saas.md
  - 2026-05-14-2100-cycle-pivot-05-to-06-martinho-fsm-jana.md
  - 2026-05-15-0030-cycle-pivot-real-secrets-tooling-checkpoint-martinho.md
  - 2026-05-17-1722-migracao-martinho-completa-perfil-canon.md
relacionado_sessions:
  - 2026-05-27-diagnostico-hostinger-martinho-biz164.md
---

# Consolidação migração Martinho biz=164 — arqueologia + decisão pattern canônico

## TL;DR

Wagner pediu (1) consolidar memórias migração, (2) decidir padrão canônico, (3) autorizou DROP biz=164 ("ainda em teste sem problemas"). Arqueologia revelou que biz=164 tem **dados reais Martinho desde nov/2024** (não é só "teste") — DROP completo perderia 10 funcionários + 1.838 produtos manuais + feedback Kamila + 1.971 produtos cadastrados ontem. **Recomendação revisada: NÃO DROP. Consolidar via cherry-pick da branch órfã + ADR + documentar pattern pra Vargas/Gold/Extreme.**

## Achados arqueologia

### Dimensão 1 — Scripts perdidos da branch órfã

Branch `claude/wip-martinho-canary-2026-05-14` (commit `db3342ae0ce0e0e318b536f3c10c307c0a75822a`, 2026-05-15 04:37 BRT) é checkpoint WIP de 93 arquivos / 22.892 linhas. **Nunca fatiado em PRs A-F.** Contém scripts legacy-migration que NÃO estão em main:

| Script órfão | Linhas | Função | Status |
|---|---:|---|---|
| `import-produtos.py` | 724 | PRODUTOS Delphi → products MySQL | Rodou só dry-run 14/05 15:24 (4.581 reads) |
| `import-compras.py` | 846 | COMPRA + NFe → transactions tipo=purchase | Rodou só dry-run 14/05 15:25 (limit 500) |
| `import-estoque.py` | 552 | ESTOQUE Delphi → product_stock_movements | Rodou só dry-run 14/05 15:24 (4.581 reads) |
| `import-contacts-from-nfe.py` | 553 | NFe emitente → contacts type=supplier | Rodou só dry-run 14/05 19:01 (391 fornecedores) |
| `daemon-sync-martinho.py` | 536 | **Daemon sync incremental dual-system** | Não rodou em prod — só dry-run manual 14/05 18:25 |
| `migrar-martinho.py` | 210 | Orquestrador específico Martinho | Idem |
| `lib/sync_checkpoint.py` | 230 | State incremental (--delta-since-last-sync) | Idem |
| `lib/firebird_reader.py` (+88) | — | Extensions adapter por versão | Versão v0.2.0 vs main v0.1.0 |
| Updates `import-{contacts-from-venda,vendas,financeiro}.py` | +72/+68/+88 | v0.2.0 com --delta-since-last-sync + --sync-type | Versão atual em main = v0.1.0 |

Outras branches órfãs paralelas com importers concorrentes:

- `feature/legacy-migration-pessoas-sql` (commit `fbb3f98a7`, 2026-05-20) — pipeline SQL-only Cliente
- `claude/plano-migracao-entidades-v2` (commit `32b0ea31e`, 2026-05-13 20:17) — "3 importers + 3 agentes + manifest YAML + orquestrador"
- `claude/fix-bookings-route-name-conflict` (commit `fc8f10475`, 2026-05-14 07:21) — financeiro 103k títulos versão 599 LOC
- `claude/fix-route-collisions-batch` (commit `69d3ecdbe`, 2026-05-14 07:54) — financeiro 657 LOC com STATUS_FILTERS

**Anti-pattern §5 do [migracao-officeimpresso-pattern.md](../reference/migracao-officeimpresso-pattern.md) confirmado em escala:** múltiplos agentes paralelos sem `whats-active` cada um escrevendo seu próprio importer.

### Dimensão 2 — "Daemons" não são daemons scheduled

Logs `daemon-{contacts,financeiro,estoque,compras}-biz164-{ts}.log` em 14/05 18:25 BRT existem mas:
- `app/Console/Kernel.php` só tem referências a daemon **WhatsApp** Baileys (CT 100). Zero referência a daemons de migração.
- `Modules/Officeimpresso/Console/` só tem 2 commands (`InspectDelphiApiCommand`, `ParseLicencaLogCommand`) — nenhum daemon scheduled.
- Os logs revelam o "daemon" é `import-*.py --delta-since-last-sync --sync-type X --target dry-run` rodado **manualmente em PowerShell** (não scheduled, não em prod).

### Dimensão 3 — SSH count real prod biz=164

```
contacts                          = 9.938     (Firebird hoje: 11.563 distinct CNPJ → drift +1.625 em 13 dias)
products                          = 3.809
users                             = 12
vehicles                          = 91
service_orders                    = 91
transactions                      = 43.974
transaction_sell_lines_via_join   = 5.758     ← gap 92.5% CONFIRMADO (média 0.13 item/venda)
fin_titulos                       = 83.045
fin_titulo_baixas                 = 71.675
```

### Dimensão 4 — Audit JSONs cronológicos biz=164

```
13/05 21:36 contacts-from-venda    440KB  dry-run (1ª tentativa contacts)
14/05 02:48 vendas                 889KB  ⚠️ target: PROD (única migração prod-grade do dia em audits)
14/05 06:35 financeiro              33KB  primeira tentativa (provavelmente falhou)
14/05 07:15 financeiro             148KB  2ª tentativa
14/05 07:50 financeiro             222KB  3ª tentativa (final)
14/05 15:23 produtos                30KB  dry-run primeira tentativa
14/05 15:24 estoque                 65KB  dry-run
14/05 15:24 produtos               286KB  dry-run final
14/05 15:24 compras                 21KB  dry-run primeira
14/05 15:25 compras                306KB  dry-run final
14/05 19:01 contacts-from-nfe      180KB  dry-run
14/05 19:02 contacts-from-nfe      180KB  dry-run (re-run)
27/05 14:14 contacts-from-venda    443KB  ← dry-run desta sessão (11.563 distinct vs 9.938 prod)
```

**Padrão:** maratona 14/05 02:48 → 19:02 (16h ininterruptas), só vendas marked `target: prod`. Demais (produtos/estoque/compras/contacts-from-nfe) ficaram dry-run — nunca aplicados em prod via Python.

## Origem temporal biz=164 (descoberta crítica)

| Data | Evento | Implicação |
|---|---|---|
| 2024-11-08 | User `officelocal25db7` "Sistema" criado | biz=164 nasceu nov/2024 (não 2026) |
| 2024-12-10 | 10 users nomeados criados (`evandro-164`, `andre-164`, **`kamila gonçalves-164`**, `luiza correa-164`, `rodrigo da silva-164`, `vendas2-164`, `teste-164`, `danielli-164`, `eduardo-164`, `junior-164`) | Funcionários reais Martinho cadastrados manualmente |
| 2024-12-26 | 15 produtos cadastrados | Catálogo inicial manual |
| 2025-01-14 | 1.823 produtos cadastrados (1731 + 92) | Wave grande catálogo manual |
| 2026-05-13 13:31 | 91 vehicles migrados via `import-vehicles.py` | Fase 2 |
| 2026-05-14 02:48 | 44.018 transactions migradas via `import-vendas.py` | Fase 3 (prod-grade) |
| 2026-05-14 06:35→07:50 | 83.040 fin_titulos migrados via `import-financeiro.py` | Fase 4 (3 tentativas) |
| 2026-05-14 15:19 | User `wagner-dev` criado | Durante maratona |
| 2026-05-15 | Business renomeado "JAIR UMBELINA VARGAS ME" → "MARTINHO CAÇAMBAS LTDA" | Per [handoff 2026-05-17 17:22](../handoffs/2026-05-17-1722-migracao-martinho-completa-perfil-canon.md) §"corrigido em prod" |
| 2026-05-26 14:00→15:00 | 1.971 produtos novos cadastrados | Operação real Kamila/equipe Martinho |
| 2026-05-27 (hoje) | Feedback Kamila Sicoob API + Bucket A contacts mergeado | Cliente paga ativo, 11 PRs |

**Conclusão sobre DROP biz=164:** Wagner autorizou DROP achando "está em teste sem problemas". A arqueologia revela que **biz=164 tem 6+ meses de operação real Martinho** — DROP completo perderia 10 funcionários nomeados, 1.838 produtos manuais pré-2026, 1.971 produtos cadastrados ontem, feedback Kamila esta semana.

## Decisão arquitetural

**NÃO DROP biz=164.** Trocar pelo caminho A (consolidar via cherry-pick + documentação) — sinal qualificado contradiz a premissa de "teste sem problemas".

### Caminho A — Consolidar branch órfã (recomendado)

**Passo 1:** Cherry-pick seletivo da branch `claude/wip-martinho-canary-2026-05-14`:
- ✅ `scripts/legacy-migration/import-produtos.py` (724 LOC)
- ✅ `scripts/legacy-migration/import-compras.py` (846 LOC)
- ✅ `scripts/legacy-migration/import-estoque.py` (552 LOC)
- ✅ `scripts/legacy-migration/import-contacts-from-nfe.py` (553 LOC)
- ✅ `scripts/legacy-migration/daemon-sync-martinho.py` (536 LOC) — adicionar com banner `STATUS: experimental, manual-run only`
- ✅ `scripts/legacy-migration/migrar-martinho.py` (210 LOC) — orquestrador
- ✅ `scripts/legacy-migration/lib/sync_checkpoint.py` (230 LOC)
- ✅ Updates `import-{contacts-from-venda,vendas,financeiro}.py` v0.2.0 (+72/+68/+88 lines)
- ✅ Updates `lib/firebird_reader.py` (+88 lines)
- ❌ NÃO cherry-pick os outros 84 arquivos da branch (MWART /contacts + /products + sells/edit + sidebar custom + tests + cliente-funcionario collector — esses são outros PRs em escopo separado)

**Passo 2:** Atualizar [memory/reference/migracao-officeimpresso-pattern.md](../reference/migracao-officeimpresso-pattern.md):
- Adicionar Fase 6 (Produtos), Fase 7 (Compras), Fase 8 (Estoque), Fase 9 (Contacts fornecedores NFe)
- Anti-pattern §5: adicionar caso real branch órfã `claude/wip-martinho-canary` (3 semanas órfã)
- §8 checklist próximo cliente: incluir os scripts novos

**Passo 3:** Criar ADR nova proposta `0203-migracao-legacy-pattern-canonico-consolidado.md` (amends 0197/0198):
- Documenta pipeline completo Python standalone via SSH tunnel
- Justifica daemon-sync-martinho experimental (sem scheduled, manual-run only)
- Plano explicit pra Vargas/Gold/Extreme

**Passo 4:** Atualizar perfil Martinho + RUNBOOK historical:
- §7 perfil: adicionar dimensão "Origem biz=164 nov/2024 manual + rename 15/05" descoberta hoje
- §8 RUNBOOK: link pra ADR 0203 + nota "pattern atual" vs "plano original"

**Passo 5:** Backlog (não-bloqueador):
- US-OFICINA-XXX: investigar gap 92.5% sub-linhas (Felipe owner per session diagnóstico)
- US-OFICINA-YYY: avaliar archive job opt-in pra 76.7% inadimplência legacy (ADR 0198 §Mitigação 3)
- US-OFICINA-ZZZ (futura): se aparecer dor real → ativar daemon-sync-martinho.py via scheduler app/Console/Kernel.php (CT 100, NÃO Hostinger per [ADR 0062](../decisions/0062-separacao-runtime-hostinger-ct100.md))

### Caminhos B e C — descartados

**B) DROP parcial seletivo** (filtrar por `WR2:*` em contacts, `WR-%` em fin_titulos): viável mas custo > benefício. Sub-linhas faltantes não se resolvem com DROP — é problema de extração, não de schema.

**C) DROP total**: descartado por descoberta Kamila + 1.971 produtos 26/05.

## Plano de execução (proposto pra aprovação Wagner)

1. **Wagner aprova caminho A** (este doc + perguntar)
2. Criar branch `chore/consolidar-importers-orfaos-2026-05-27` baseada em main
3. Cherry-pick seletivo dos 9 scripts da branch órfã (ver passo 1 acima)
4. Smoke local: rodar `python migrar-martinho.py --target dry-run` (validar pipeline consolidado funciona)
5. Atualizar `memory/reference/migracao-officeimpresso-pattern.md` com Fases 6-9
6. Atualizar perfil Martinho §7 + RUNBOOK §8 com link ADR 0203
7. Criar ADR proposal 0203 (PR + Wagner aprova)
8. PR consolidado (8-10 arquivos, ~3.000 LOC scripts + 4 docs)
9. Backlog: criar 2-3 US-OFICINA-XXX pra gaps abertos (sub-linhas + write-off + daemon)
10. Após Wagner aprovar 0203 + merge PR → atualizar [`AUTO-MEM-PENDING.md`](../../AUTO-MEM-PENDING.md) se aplicável

## Tempo estimado consolidado

| Passo | Tempo |
|---|---|
| Cherry-pick scripts | 15min |
| Smoke dry-run pipeline | 10min |
| Update pattern canônico | 20min |
| Update perfil + RUNBOOK | 15min |
| Escrever ADR 0203 proposta | 30min |
| PR + revisão Wagner | 1h |
| Backlog tasks MCP | 15min |
| **Total** | **~3h** |

## Refs

- [Handoff 2026-05-17 17:22 migração completa](../handoffs/2026-05-17-1722-migracao-martinho-completa-perfil-canon.md) — "snapshot fechamento"
- [Session 2026-05-27 diagnóstico Hostinger](2026-05-27-diagnostico-hostinger-martinho-biz164.md) — origem do exercício de hoje
- [Pattern canônico migração](../reference/migracao-officeimpresso-pattern.md) — atualizar com Fases 6-9
- [Perfil Martinho](../research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md) §7 §8 §9 — atualizar §7 com origem nov/2024
- [RUNBOOK Martinho historical](../requisitos/Officeimpresso/RUNBOOK-migracao-martinho-fase3-fase4.md) — apender §8 com link ADR 0203
- Branches identificadas na arqueologia (5 catalogadas · ⚠️ re-classificação 18:10 BRT pós-questionamento Wagner): `claude/wip-martinho-canary-2026-05-14` (Wagner · 93 arquivos · **82 não-extraídos valiosos** · manter), **`feature/legacy-migration-pessoas-sql` (PR #1204 SupportWR · TRABALHO ATIVO · NÃO TOCAR)**, `claude/plano-migracao-entidades-v2` (PR #812 · confirmar Wagner), `claude/fix-bookings-route-name-conflict` (sem PR · confirmar), `claude/fix-route-collisions-batch` (sem PR · confirmar). Erro inicial: chamar todas de "órfãs a deletar" sem cross-check autor + atividade recente. Lição: anti-pattern §5 não termina em "branches >7d" — termina em "verificar autor + cc-search MCP antes de delete"
- ADRs canon: [0093](../decisions/0093-multi-tenant-isolation-tier-0.md), [0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md), [0197](../decisions/0197-extend-contacts-absorcao-pessoas-legacy.md), [0198](../decisions/0198-hot-cold-tiering-migracao-transacional-legacy.md), [0200](../decisions/0200-contacts-sync-canon-amends-0197-0199.md)

---

**Última atualização:** 2026-05-27 ~17:50 BRT · sessão `frosty-greider-83ab2f`.

**Adendo 17:45 BRT — overlap PR #1765 detectado:**

Após criar PR #1766 com ADR 0203 proposal "pattern canônico consolidado", detectei PR #1765 criado 1min26s ANTES (17:40:15Z) por agente Claude paralelo (Felipe ou outro) trabalhando EXATAMENTE o mesmo problema com solução mais completa: pipeline end-to-end Wave 29-1 já rodado em prod (venda Mario Franz validada), 39 arquivos / 6.268 LOC, ADR 0203 já canon (não proposal). Resolve gap 92.5% sub-linhas via `import-venda-itens.py` (US-OFICINA-XXX que coloquei no backlog) + NFe handling + enrich produtos + fix WireCrypt FB 3.0.12 + dedup 8.832 contacts.

**Resolução aprovada Wagner (caminho 1):** mergear #1765 primeiro (mais completo + já validado prod), reduzir #1766 pra zero conflito:
- ADR proposal renumerada 0203 → **0204** ("Importers complementares Wave 2 + reflexão arqueológica · amends 0197+0198+0203")
- 3 arquivos em conflito removidos do #1766: `import-produtos.py` (git rm) + `import-financeiro.py` + `lib/firebird_reader.py` (restaurados pra origin/main — versão canon = #1765)
- 6 arquivos complementares MANTIDOS no #1766 (não em #1765): `import-compras.py`, `import-estoque.py`, `import-contacts-from-nfe.py`, `daemon-sync-martinho.py`, `migrar-martinho.py`, `lib/sync_checkpoint.py`
- 2 updates v0.2.0 MANTIDOS (Felipe não tocou): `import-contacts-from-venda.py`, `import-vendas.py`
- Pattern conceitual expandido pra 13 fases (9 originais + 4 ADR 0203 canon)

**Anti-pattern §5 do pattern recursivamente acontecendo:** múltiplos agentes Claude paralelos sem `whats-active`. Acontece desde 2026-05-13 (Wave 0 Martinho rename) + 2026-05-14 (5 branches órfãs maratona) + 2026-05-27 (PR #1765 vs PR #1766 1m26s diferença). Mitigação real exige promover hook `whats-active` MCP pra Tier A always-on — listado em US-MEMORIA-ZZZ backlog [ADR 0204 §Implementação](../decisions/proposals/0204-importers-complementares-wave2-compras-estoque-contacts-nfe-daemon.md).

---

## ⚠️ Lições de comportamento do Claude (registradas pós-merge #1766)

Adendo final 2026-05-27 ~18:30 BRT após Wagner corrigir 2 padrões meus que causaram ida-e-volta desnecessária na sessão. Registrado em git pra evitar repetição em sessões futuras.

### Lição 1 — Confiar em ADR canon vigente · NÃO re-propor decisão recente

**Contexto:** Pós-merge #1766, Wagner reforçou canon dedupe `officeimpresso_codigo` + `officeimpresso_dt_alteracao` (de [ADR 0200](../decisions/0200-contacts-sync-canon-amends-0197-0199.md), aceita 5h antes na mesma sessão). Eu auditei meus 4 importers Wave 2, achei `import-contacts-from-nfe.py` usando `legacy_id=CNPJ_normalizado` como chave dedup (sem `officeimpresso_codigo` preenchido), e propus **"follow-up PR pra ajustar pra preencher `officeimpresso_codigo=NULL` explicitamente"**.

**Wagner corrigiu:** *"isso é errado tu ja tinha corrigido essas coisas e feito plano melhor para não duplicar e ficar ida e volta"*.

**Canon que eu ignorei** (ADR 0200 §"PII leak — drift detection" + §"Riscos"):
> *"Cliente Delphi PUSH com `officeimpresso_codigo` que conflita com `legacy_id` | App layer trata como **chaves distintas**"*
>
> *"campo `officeimpresso_codigo` é CODIGO Delphi internal (não PII); validar trimestralmente que **não foi misturado com CNPJ no importer**"*

**Implementação atual estava CORRETA:**
- `legacy_id = CNPJ_normalizado` ✅ canon (chave dedup natural pra fornecedor sem CODIGO Delphi origem)
- `officeimpresso_codigo = NULL` (default · comportamento correto · fornecedor extraído de NFe NÃO tem código Delphi internal)
- Preencher `NULL` explicitamente seria **anti-pattern** que ADR 0200 lista como drift trimestral a detectar (porta pra alguém erradamente colocar CNPJ ali achando "tem que preencher canon")

**Anti-pattern de comportamento meu:**
1. Propor "ajuste" em decisão canon aceita **na mesma sessão** sem re-ler ADR vigente
2. Gerar ida-e-volta com Wagner pra confirmar canon já estabelecido
3. Confiar em raciocínio próprio sobre "consistência" em vez de confiar no design intencional do ADR

**Mitigação operacional pra sessões futuras:**
- ANTES de propor "ajuste/follow-up" em código tocado por ADR aceita recentemente → re-ler ADR completa (incluindo §Riscos e §Drift detection)
- Se propor mudança, citar EXPLICITAMENTE o que o ADR canon diz contra
- Se o ADR não fala contra, ainda é mais seguro perguntar antes de propor follow-up

### Lição 2 — "Órfã" não é status auto-deduzível · cross-check autor + atividade recente

**Contexto:** Logo antes (mesma sessão ~18:00 BRT), eu chamei 5 branches de "órfãs a deletar" no ADR 0204 inicial sem cross-check.

**Wagner corrigiu:** *"tem certeza que são orfão porque tem sessões trabalhando"*.

**Re-classificação real:** PR #1204 (`feature/legacy-migration-pessoas-sql`) é **trabalho ATIVO do SupportWR/Felipe** (5 commits 20/05 com US-VEST-020 etiqueta + skill migration-status + Page Inertia /vestuario/etiquetas misturados). Não é órfã.

**Mitigação operacional:**
- ANTES de propor delete branch >7d → cross-check: (a) autor (não-Wagner = pode ser time ativo), (b) PR aberto + CI status, (c) `cc-search` MCP pra sessões CC paralelas, (d) conteúdo dos commits (pode ter trabalho não-relacionado misturado)
- "Órfão" exige evidência POSITIVA de abandono — não inferência por idade isolada

### Padrão comum das 2 lições

Em ambos casos eu propus mudança baseado em **raciocínio próprio sobre "consistência/limpeza"** sem cross-check com (a) ADR canon vigente OU (b) atividade real do time. Wagner teve que corrigir minha proposta — gerando o ida-e-volta que ele explicitamente reclamou.

**Princípio destilado:** *"Antes de propor 'ajuste/limpeza', verifique se o estado atual é resultado de decisão deliberada (ADR canon) OU trabalho ativo (autor + PR). Se sim, não propor mudança — confiar no design vigente."*

Possível candidato a **skill nova Tier B** auto-trigger: `confiar-canon-vigente` — ativada quando agente vai propor "follow-up PR" / "ajuste" / "consistência" em código tocado por ADR canon aceita nos últimos 7d.
