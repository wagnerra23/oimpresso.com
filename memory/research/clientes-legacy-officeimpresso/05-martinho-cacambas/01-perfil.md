---
slug: 05-martinho-cacambas
hash_id: Cliente_731814
status: qualificado
date_first_analysis: 2026-05-11
date_last_update: 2026-05-26
controlador: Wagner
vertical_real: pecas-hidraulicas-basculante-oficina-autorizada
size: medio-grande
tipo_relacao: cliente-pagante
cidade: "Tubarão"
bairro: "Humaitá de Cima"
uf: SC
endereco_observacao: "Errata 2026-05-26 smoke biz=164 prod Location BL0001 (anterior: Capivari de Baixo — engano inferido via WebSearch)"
banco_firebird: D:\DadosClientes\MartinhoCacamba\Dados\BANCO.FDB
banco_alias_firebird: 192.168.0.55:D:\DadosClientes\MartinhoCacamba\Dados\BANCO.FDB
---

# Perfil — `Cliente_731814` (peças hidráulicas basculante + oficina autorizada · Tubarão/SC · Humaitá de Cima)

> **Correção 2026-05-26:** entendimento original neste perfil (e em [ADR 0137](../../../decisions/0137-modules-oficinaauto-qualificada.md) + [BRIEFING OficinaAuto](../../../requisitos/OficinaAuto/BRIEFING.md)) descrevia Martinho como "locação de caçamba estacionária pra entulho/obra (CNAE 4581-4/00)". Wagner identificou o erro de leitura — Martinho é **loja de peças hidráulicas pra caminhão basculante + oficina autorizada** (CNAE 4520-0/01). Correção formal via [ADR 0194](../../../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) (aceito 2026-05-26).

## 1. Identificação

| Campo | Valor |
|-------|-------|
| Hash ID | `Cliente_731814` |
| Razão social | MARTINHO CAÇAMBAS LTDA |
| Nome fantasia | Martinho Caçambas |
| Vertical real | Peças hidráulicas pra basculante (Polli-guindaste, plataforma, munck) + oficina autorizada caminhão pesado |
| CNAE principal | **4520-0/01** (manutenção e reparação mecânica de veículos automotores) — não 4581 (locação) |
| Porte | médio-grande (R$ [redacted Tier 0]M+/mês estimado Wagner 2026-05-26 · R$ [redacted Tier 0]M/12m Firebird snapshot) |
| Cidade / UF | Capivari de Baixo / SC |
| Status comercial | cliente-pagante (biz=164 prod oimpresso, ativado piloto OficinaAuto via [ADR 0171](../../../decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md)) |
| Vínculo familiar | empresa do **filho** — pai é "Martinho da Caçamba" em Tubarão/SC (transportadora resíduo, **NÃO é cliente oimpresso**) |

## 2. Tipo de negócio real

**Loja de peças hidráulicas + oficina autorizada caminhão basculante.** Caminhão de cliente terceiro entra pra **troca de peça + conserto programado** (não lataria, não batida — Wagner 2026-05-26: *"entra o caminhão pra trocar e consertar, não vejo caminhões destruídos lá"*). Perfil "quase concessionária" — autorizada de manutenção pesada perto da escala de concessionária Volvo/Scania/MB pra caminhão de transporte (basculante, Polli, munck, plataforma).

**Cadeia comercial onde Martinho está:**

```
[Tork Tomadas de Força (fábrica PTO Capivari)] → [Martinho (revenda peça hidráulica + instala)] → [Frota basculante terceiro]
```

Tork é prospect novo identificado 2026-05-26 — perfil em [clientes-prospect/tork-tomadas-forca/01-perfil.md](../../clientes-prospect/tork-tomadas-forca/01-perfil.md).

**NÃO confundir com:**
- **Vargas** (Cliente_874398) — oficina recapagem pneu caçamba caminhão, cavalo+reboque, multi-item OS (sub-vertical recapagem CNAE 2212)
- **"Locação caçamba container m³/diária"** — hipótese ainda sem cliente real ancorado ([dominios-verticais-oimpresso.md §"Sub-vertical 3"](../../../reference/dominios-verticais-oimpresso.md))
- **Martinho da Caçamba (Tubarão SC)** — empresa do pai, transportadora de resíduo sólido com frota basculante própria, **NÃO é cliente oimpresso**

## 3. Sinais Firebird (reinterpretados pós-correção 2026-05-26)

| Dimensão | Valor | Comentário |
|----------|------:|------------|
| Vendas total | 44.709 | volumoso — compatível com loja peça B2B + serviço OS |
| EQUIPAMENTO_VEICULO total | 91 | **caminhões dos CLIENTES** que entram pra peça/serviço (NÃO frota própria) |
| **PLACA** | **95.6%** (87) | confirma caminhão de cliente identificado (placa ANTT) — **caçamba estacionária container NÃO TEM PLACA** (incompatibilidade dura com leitura original) |
| PLACA2/CHASSI/CHASSI2 | 0% | clientes têm 1 placa por veículo (basculante simples, não bitrem cavalo+reboque) |
| **Status inline** | **8 distinct** | rastreio estado OS (aberta / em peça / em serviço / aguardando peça / concluída / faturada / entregue / cancelada) |
| **VENDA_SITUACAO** | **6 linhas** | catalog formal |
| **VENDA_ESTAGIO** | **2 linhas** | FSM leve — único do sample! |
| PCP centro_trabalho | 0 | sem PCP industrial — bate com perfil concessionária/autorizada (peça + serviço, não fabricação) |

## 4. Módulos OfficeImpresso usados

| Módulo | Uso real | Migração necessária? |
|--------|----------|----------------------|
| Vendas (`VENDA`) | 44k linhas | **sim** — venda peça + venda serviço OS |
| Status produção (8 distinct) | sim | **sim** — vira FSM canon [ADR 0143](../../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) |
| VENDA_ESTAGIO (FSM) | só 2 estados — leve | sim |
| Veículos | 91 caminhões de clientes com PLACA | sim — entidade Vehicle vinculada a Contact (dono) |
| Compras peça hidráulica | sim (schema legacy a confirmar) | sim — Modules/Compras com cross-ref por modelo Scania/Volvo/MB/Ford |
| PCP | não usa | dispensável |

## 5. Saúde financeira

- **R$ [redacted Tier 0]M receita 12m** (Firebird snapshot 2026-05-11 — ver [03-financeiro-2026-05-11.md](03-financeiro-2026-05-11.md) se existir)
- **R$ [redacted Tier 0]M+/mês estimado** (Wagner 2026-05-26 — pode estar arredondado pra cima ou refletir período recente)
- **76.7% inadimplência legacy** (a investigar)

## 6. Sinal qualificado pra migração

- [x] **FSM formalizada (VENDA_ESTAGIO 2 estados)** — único do sample com FSM ativa
- [x] **Cliente pagante há anos** + **aceitou migração 2026-05-13** (reunião 10h)
- [x] **Faturamento alto** (R$ [redacted Tier 0]M+/mês — sinal forte ROI)
- [x] **Volume operacional saudável** (44k vendas + 91 placas)
- [x] **Ativação piloto formal** [ADR 0171](../../../decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md) (2026-05-20) — beta 30d add-on WhatsApp + base R$ [redacted Tier 0] grandfathered

**Status pra [ADR 0105](../../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md):** sinal **alto** (pós-correção 2026-05-26 — antes estava classificado "baixo-médio" porque sub-vertical "locação caçamba" tinha mercado pequeno; corrigido pra mecânica pesada/autorizada o sinal vira **alto** — mercado nacional de milhares de empresas).

## 7. Plano de migração preliminar

- **V0 LIVE (Fase 1 — feito):**
  - `Modules/OficinaAuto` com Vehicle (PLACA simples, sem cavalo+reboque) — ✅
  - FSM 3 estados OS Simples — ✅
  - Importer Firebird `EQUIPAMENTO_VEICULO` → `vehicles` — ✅ (91 rows migradas 2026-05-13 13:31 BRT)
- **Próximo (V1):**
  - **Catálogo peça hidráulica + cross-ref por modelo caminhão** (Modules/Compras + Modules/Sells) — gap pós-correção
  - **OS de mecânica programada** (cliente leva caminhão, trocamos peça, devolvemos) — `final_total` calculado por **hora-trabalho + peça** (NÃO `daily_rate`)
  - **Eliminar dependência de `service_orders.daily_rate`/`expected_return_date`/`delivery_address`** (introduzidos pela leitura errada "locação" — migration 2026_05_12_220002) — preservados nullable per [ADR 0194 review_trigger](../../../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md)
- **V2 LIVE descoberta 2026-05-27 (Fase 3 + Fase 4 — feito sem RUNBOOK formal):**
  - `transactions` biz=164 — **43.974 vendas** em prod desde 2012-03 (14 anos · receita 2025: R$ [redacted Tier 0]M) — ✅
  - `fin_titulos` + `fin_titulo_baixas` — **83.045 títulos + 71.675 baixas** em prod — ✅ (SEM cleanup-first · 76.7% inadimplência legacy migrada — review trigger archive opt-in [ADR 0198 §Mitigação 3](../../../decisions/0198-hot-cold-tiering-migracao-transacional-legacy.md))
  - **Diagnóstico SSH Hostinger** ([sessão 2026-05-27](../../../sessions/2026-05-27-diagnostico-hostinger-martinho-biz164.md)) confirmou execução paralela não-documentada (provavelmente Felipe pré-RUNBOOK 13:23 BRT)
  - **⚠️ Gap crítico** descoberto: **92.5% das vendas SEM linhas em `transaction_sell_lines`** (40.644/43.951 órfãs · média 0.13 item/venda — irrealista). US-OFICINA-XXX pendente Felipe investigar (3 hipóteses documentadas em sessão diagnóstico)
- **V3 LIVE 2026-05-27 (cadastros migrados):**
  - `contacts` biz=164 — **9.938 contacts** (PESSOAS Delphi migradas além das 4 EMPRESAs Fase 1) — ✅
  - `products` biz=164 — **3.809 produtos** catalogados — ✅ (origem mista revisada 2026-05-27: 1.838 manuais 2024-12/2025-01 + 1.971 manuais 2026-05-26 + 0 Delphi-migrated · `import-produtos.py` órfão recuperado [ADR 0203 canon](../../../decisions/0203-legacy-migration-pipeline-firebird-oimpresso-w29.md) + [ADR 0332](../../../decisions/0332-importers-complementares-wave2-compras-estoque-contacts-nfe-daemon.md))
  - `service_orders` biz=164 — 91 OS (1 por veículo Fase 2) — ✅
  - `users` biz=164 — 12 operadores Martinho — ✅ (10 nomeados cadastrados manualmente 2024-12-10 + 1 sistema 2024-11-08 + 1 wagner-dev 2026-05-14 · nenhum migrado via Python)
  - **Schema canon contacts pronto pra sync bidirecional** Delphi ↔ oimpresso ([ADR 0200](../../../decisions/0200-contacts-sync-canon-amends-0197-0199.md) — `officeimpresso_codigo` + `officeimpresso_dt_alteracao` em prod 14:00 BRT)
- **⚠️ Descoberta crítica 2026-05-27 (arqueologia):** biz=164 existe em prod desde **2024-11-08** (não 2026-05) — originalmente "JAIR UMBELINA VARGAS ME" → renomeado MARTINHO em 2026-05-15 (per [handoff 2026-05-17 17:22 §"corrigido em prod"](../../../handoffs/2026-05-17-1722-migracao-martinho-completa-perfil-canon.md)). 10 funcionários reais (Kamila/Evandro/Andre/Luiza/etc.) cadastrados manualmente 2024-12-10. **6+ meses de operação real** — proposta DROP Wagner 2026-05-27 revertida em [ADR 0203 §Caminho A](../../../decisions/proposals/0203-migracao-legacy-pattern-canonico-consolidado.md).

## 8. Decisões ADR locais

| ADR | Decisão | Status |
|-----|---------|--------|
| [0137](../../../decisions/0137-modules-oficinaauto-qualificada.md) | OficinaAuto qualificada (Vargas + Martinho) | aceito (com erro de domínio Martinho — amendado por 0194) |
| [0171](../../../decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md) | Ativação piloto Martinho faseada | aceito (amendado por 0194) |
| [0194](../../../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) | Correção domínio Martinho mecânica pesada (não locação) | **aceito 2026-05-26** |
| [0197](../../../decisions/0197-extend-contacts-absorcao-pessoas-legacy.md) | Extend `contacts` pra absorver schema PESSOAS legacy (Bucket A 13 cols aplicadas biz=164) | aceito 2026-05-27 — amendado por 0199 + 0200 |
| [0198](../../../decisions/0198-hot-cold-tiering-migracao-transacional-legacy.md) | Hot/cold tiering migração transacional · prospectivo (DB 594MB · 21TB livre não bloqueia) | aceito 2026-05-27 |
| [0199](../../../decisions/0199-errata-bucket-b-json-catchall-amends-0197.md) | Errata Bucket B JSON catch-all (amends 0197) | aceito 2026-05-27 |
| [0200](../../../decisions/0200-contacts-sync-canon-amends-0197-0199.md) | `contacts` adopta canon sync Wagner 2024-11 (amends 0197+0199) | aceito 2026-05-27 |
| [0203](../../../decisions/0203-legacy-migration-pipeline-firebird-oimpresso-w29.md) | Pipeline legacy-migration Firebird → oimpresso completo (Wave 29-1 Felipe · 4 importers novos + venda-itens + nfe + enrich + WireCrypt fix + PHP service amplo) | aceito 2026-05-26 (mergeado #1765) |
| [0332](../../../decisions/0332-importers-complementares-wave2-compras-estoque-contacts-nfe-daemon.md) | Importers complementares Wave 2 (compras/estoque/contacts-NFe/daemon-sync) + reflexão arqueológica · amends 0197+0198+0203 (renumerado de 0204 — colisão) | aceito 2026-07-09 |

## 9. Histórico

### 2026-05-11 — Primeira análise
- Heatmap UI v2 coletado
- Round 5 (final) do exercício do dia
- **Erro de leitura:** classificado como "oficina/aluguel de caçambas avulsas estacionárias" — descrição incompatível com PLACA 96% (entrou em ADR 0137 e propagou pra BRIEFING/charter/RUNBOOK/dominios-verticais)

### 2026-05-13 — Reunião 10h piloto aceito
- Martinho aceitou migração via importer Firebird
- 91 veículos + 91 service_orders importados 13:31 BRT pra `business_id=164`
- 4 perguntas comerciais pendentes (respondidas em 2026-05-20)

### 2026-05-20 — Ativação formal piloto
- [ADR 0171](../../../decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md) aceito — homologação faseada por feature + add-on WhatsApp R$ [redacted Tier 0]/inst beta 30d + base R$ [redacted Tier 0] grandfathered (inclui OficinaAuto + NFe + NFSe)

### 2026-05-26 — Correção de domínio
- Wagner identificou erro: NÃO é locação caçamba container — é **loja de peça hidráulica basculante + oficina autorizada** (Capivari de Baixo/SC). Empresa do filho; pai (Martinho da Caçamba Tubarão) é empresa separada (transportadora resíduo, **não cliente**).
- WebSearch confirmou: Martinho Caçambas (Rua Antonia de Bitencourt Barcelos, Capivari de Baixo SC) vende peça hidráulica pra Polli-guindaste / munck / plataforma / basculante.
- Tork (Tomadas de Força, Capivari) entra na cadeia como fornecedor PTO indústria — perfil prospect em [clientes-prospect/tork-tomadas-forca/](../../clientes-prospect/tork-tomadas-forca/01-perfil.md).
- [ADR 0194](../../../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) formaliza correção (aceito Wagner 2026-05-26 no PR #1593).

### 2026-05-27 — Diagnóstico Hostinger + estabelecimento schema canon contacts (11 PRs sessão)
- **SSH Hostinger** revelou Fase 3+4 já em prod (43.974 vendas + 83.045 títulos + 9.938 contacts + 3.809 produtos · 14 anos de dados desde 2012-03). Execução paralela não-documentada (provavelmente Felipe pré-RUNBOOK).
- **Bucket A do contacts mergeado em prod 14:00 BRT** ([ADR 0197](../../../decisions/0197-extend-contacts-absorcao-pessoas-legacy.md) + migration `2026_05_27_120000_extend_contacts_bucket_a_legacy_absorption`) — 13 cols nullable absorvem PESSOAS legacy.
- **Canon sync bidirecional adotado** ([ADR 0200](../../../decisions/0200-contacts-sync-canon-amends-0197-0199.md)) — `officeimpresso_codigo` + `officeimpresso_dt_alteracao` alinha `contacts` com 11 outras tabelas canon Wagner 2024-11. Permite Delphi LOCAL + oimpresso ONLINE em paralelo via `BaseApiController::syncData`.
- **Gap crítico descoberto:** 92.5% das vendas Martinho SEM linhas em `transaction_sell_lines` (40.644/43.951 órfãs). US-OFICINA-XXX pra Felipe investigar — não bloqueia operação atual.
- **Receita real validada:** R$ [redacted Tier 0]M em 2025 (R$ [redacted Tier 0]M/mês) — bate com perfil conservador "R$ [redacted Tier 0]M+/mês" Wagner.
- Sessão completa em [memory/sessions/2026-05-27-diagnostico-hostinger-martinho-biz164.md](../../../sessions/2026-05-27-diagnostico-hostinger-martinho-biz164.md). 11 PRs (#1717/#1723/#1727/#1731/#1735/#1741/#1744/#1747/#1751 + cleanup desta).

### 2026-05-27 — Consolidação arqueologia + recuperação 7 importers órfãos (caminho A aprovado Wagner)

- **Wagner pediu** consolidar memórias migração + decidir pattern canônico + autorizou DROP biz=164 ("teste sem problemas").
- **Arqueologia revelou** drift gigantesco: biz=164 nasceu 2024-11-08 (não 2026-05) como "JAIR UMBELINA VARGAS ME" → renomeado MARTINHO em 2026-05-15. 10 funcionários nomeados (Kamila/Evandro/Andre/Luiza/Rodrigo/Vendas2/Teste/Danielli/Eduardo/Junior) cadastrados manualmente 2024-12-10. 1.838 produtos manuais 2024-12/2025-01 + 1.971 produtos 2026-05-26 + feedback Kamila Sicoob ([memory/clientes/martinho-cacambas/feedback/2026-05-27-sicoob-api-cobranca-realtime.md](../../../clientes/martinho-cacambas/feedback/2026-05-27-sicoob-api-cobranca-realtime.md)). **6+ meses de operação real** — DROP completo perderia tudo isso.
- **5 branches órfãs paralelas** com importers concorrentes catalogadas (principal `claude/wip-martinho-canary-2026-05-14` · 93 arquivos · 22.892 LOC · 7 importers Python).
- **Caminho A aprovado:** consolidar via cherry-pick 11 scripts da branch órfã + criar [ADR 0203 canon](../../../decisions/0203-legacy-migration-pipeline-firebird-oimpresso-w29.md) + [ADR 0332](../../../decisions/0332-importers-complementares-wave2-compras-estoque-contacts-nfe-daemon.md) + atualizar pattern canônico com Fases 6-9 + atualizar perfil + RUNBOOK.
- **Smoke dry-run 2026-05-27 14:36** — `import-produtos.py --target dry-run` lê 4.378 produtos do Firebird (1.310 com EAN, 3.068 placeholder LEG-*). Pipeline validado funcionando.
- Sessão completa em [memory/sessions/2026-05-27-consolidacao-migracao-martinho-arqueologia.md](../../../sessions/2026-05-27-consolidacao-migracao-martinho-arqueologia.md).

## 10. Refs

- [Heatmap Martinho anonimizado](../../2026-05-sells-grade-heatmap/05-martinho-grade-usage-anonimizada.md)
- [_ANALISE-CROSS-CLIENTE §oficinas](../_ANALISE-CROSS-CLIENTE.md)
- [discovery Martinho reunião 2026-05-13](../../../requisitos/OficinaAuto/demo-martinho-2026-05-13/discovery-martinho.md)
- [ADR 0137](../../../decisions/0137-modules-oficinaauto-qualificada.md) — qualificação OficinaAuto (com erro de leitura domínio Martinho)
- [ADR 0171](../../../decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md) — ativação piloto faseada
- [ADR 0194](../../../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) — correção domínio (aceito 2026-05-26)
- [Perfil prospect Tork PTO](../../clientes-prospect/tork-tomadas-forca/01-perfil.md)
- [Dicionário domínios §"Sub-vertical 4"](../../../reference/dominios-verticais-oimpresso.md)
- WebSearch fontes 2026-05-26:
  - [Martinho Caçambas (Listatudo) — Capivari de Baixo SC, peça hidráulica basculante/Polli/munck](https://listatudo.com.br/santa-catarina/florianopolis-e-regiao/tubarao/construcao-civil-e-meio-ambiente/construcao-civil/maquinas-e-equipamentos/cacambas/martinho-da-cacamba/)
  - [Jocimar dos Santos Martinho — Tubarão SC (entidade pai, NÃO cliente oimpresso)](https://cnpj.biz/27302634000155)
  - [Tork Tomadas de Força — Capivari de Baixo SC](https://lp.tork.ind.br/)
