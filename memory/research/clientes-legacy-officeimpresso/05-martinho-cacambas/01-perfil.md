---
slug: 05-martinho-cacambas
hash_id: Cliente_731814
status: piloto-ativo
date_first_analysis: 2026-05-11
date_last_update: 2026-05-14
controlador: Wagner
vertical_real: oficina-cacambas-aluguel
size: medio
tipo_relacao: cliente-pagante-piloto-canary
banco_firebird: D:\DadosClientes\MartinhoCacamba\Dados\BANCO.FDB
banco_alias_firebird: 192.168.0.55:D:\DadosClientes\MartinhoCacamba\Dados\BANCO.FDB
business_id_oimpresso: 164
endosso_dono: Jair (dono majoritário) — 2026-05-14 noite
concorrente_pausado: Highsoft
canary_inicio: 2026-05-19 (semana)
champions_oimpresso: Lara (filha · estoque) + Dani / DANIELLI (financeiro · user_id=297)
co_design_distancia_km: 20
perfil_operacional_vivo: ../../../reference/cliente-martinho.md
---

# Perfil — `Cliente_731814` (oficina/aluguel de caçambas avulsas)

## 1. Identificação

| Campo | Valor |
|-------|-------|
| Hash ID | `Cliente_731814` |
| Vertical real | Oficina / aluguel de caçambas avulsas |
| Porte | pequeno-médio (44k vendas total mas só 91 veículos cadastrados) |
| Cidade / UF | a confirmar |
| Status comercial | cliente-pagante |

## 2. Tipo de negócio real

Empresa de **caçambas avulsas** (provavelmente caçambas estacionárias pra entulho/obra, NÃO de caminhão). Cliente típico: construtora ou particular que aluga caçamba pra construção/reforma.

**Diferenças vs Vargas:**
- Vargas: recapagem de **caçamba de caminhão** — cavalo+reboque, 2 placas, multi-item por OS
- Martinho: caçamba **avulsa** estacionária — 1 placa do caminhão de entrega/transporte, sem cavalo+reboque, sem chassi2/placa2

**Sinais Firebird:**
- **PLACA 95.6%** (87 dos 91) — quase todos veículos cadastrados são identificados
- **PLACA2 0%, CHASSI 0%, CHASSI2 0%** — não trabalha com cavalo+reboque
- **8 status distinct + 6 lookup + 2 FSM (VENDA_ESTAGIO)** — oficina/aluguel precisa rastrear estado da caçamba (entregue / em uso / recolhida / em manutenção?)
- Sem PCP industrial
- 44.709 vendas total no Delphi → empresa estabelecida

## 3. Sinais Firebird

| Dimensão | Valor | Comentário |
|----------|------:|------------|
| Vendas total | 44.709 | volumoso |
| EQUIPAMENTO_VEICULO total | 91 | caminhões pra transporte de caçamba? |
| **PLACA** | **95.6%** (87) | quase todos identificados |
| PLACA2/CHASSI/CHASSI2 | 0% | sem cavalo+reboque |
| **Status inline** | **8 distinct** | uso estruturado |
| **VENDA_SITUACAO** | **6 linhas** | catalog formal |
| **VENDA_ESTAGIO** | **2 linhas** | tem FSM — único do sample! |
| PCP centro_trabalho | 0 | sem PCP |

## 4. Módulos OfficeImpresso usados

| Módulo | Uso real | Migração necessária? |
|--------|----------|----------------------|
| Vendas (`VENDA`) | 44k linhas | **sim** |
| Status produção (8 distinct) | sim | **sim — feature oficina** |
| VENDA_ESTAGIO (FSM) | só 2 estados — leve | sim |
| Veículos | 91 caminhões com PLACA | sim |
| PCP | não usa | dispensável |

## 5. Saúde financeira (snapshot 2026-05-11 · validado em prod 2026-05-14)

| Métrica | Valor |
|---|---:|
| Receita 12m | **R$ 6.281.171,55** (~R$ 632k/mês médio) |
| Despesa 12m | R$ 4.957.121,01 |
| Resultado 12m | R$ 1.324.050,54 (margem ~21%) |
| **A receber vencidas** | **R$ 4.819.643,60 (76.7% inadimplência)** — fóssil 2015-2017 |
| A pagar vencidas | R$ 3.356.491,39 |
| Lançamentos 12m | 4.656 |
| Ticket médio | R$ 1.349,05 |
| Mediana ticket | R$ 738 |
| Top 10 clientes (% receita) | apenas 15% — base pulverizada |

**Cleanup write-off aplicado prod biz=164 (2026-05-14):** 748 títulos receber vencidos >3 anos R$ 844.660 flagados `metadata.is_write_off_candidate=true` · Dani filtra UI.

Detalhes em [03-financeiro-2026-05-11.md](03-financeiro-2026-05-11.md).

## 6. 🟢 PILOTO ATIVO — sinal qualificado SUPERADO

**Status atualizado 2026-05-14:** de "sinal baixo-médio" → **PILOTO ATIVO Modules/OficinaAuto**.

### Marcos comerciais

| Data | Evento | Decisor |
|---|---|---|
| 2026-05-11 | Análise legacy + qualificação técnica | Wagner |
| 2026-05-13 10h | Reunião Wagner + Martinho · topou testar | Martinho |
| 2026-05-13 13h | 91 vehicles + 91 service_orders importados prod biz=164 | Wagner |
| 2026-05-14 manhã | Wave A prod biz=164 (18.8k contacts + 44k transactions + 5.5k fin_titulos) | Wagner |
| 2026-05-14 15:51 | **Kamila propôs dual-system Delphi master + oimpresso viewer** (insight WhatsApp) | Kamila |
| 2026-05-14 ~16:30 | Felipe presencial Martinho · **JAIR (dono majoritário) endossou** + Kamila pausou avaliação **Highsoft** (concorrente) | Jair |
| 2026-05-19 (planejado) | Canary inicia · Lara + Dani entram oimpresso | Wagner |

### Por que virou piloto

- ✅ Volume R$ 6.28M/ano operação real (não demo)
- ✅ Champions duplos NÃO-dono (Lara estoque + Dani financeiro) — adopção orgânica
- ✅ Co-design presencial viável (20km Wagner-Martinho)
- ✅ Schema técnico simples (sub-vertical "locação caçamba avulsa" vs Vargas multi-placa)
- ✅ Concorrente eliminado (Highsoft pausado)
- ✅ Dono majoritário (Jair) endossou explicitamente

### Arquitetura adotada

**Dual-system** (Delphi master + oimpresso viewer com sync near-realtime · daemon polling 5min) — ver [ADR proposal dual-sync](../../../decisions/proposals/dual-system-delphi-oimpresso-sync-realtime.md).

Kamila + 4 vendedores continuam Delphi (operação) · Lara + Dani entram oimpresso (estoque + financeiro). Cutover gradual feature por feature quando confiança crescer.

## 7. Plano de migração preliminar

- **Pré-requisito**: `Modules/OficinaAuto` ter:
  - Cadastro veículo com PLACA simples (sem cavalo+reboque — caso Vargas é mais complexo)
  - FSM da OS com 2-3 estados (entregue / em uso / recolhida ou em manutenção)
- **Custom necessário**: pequeno — esse cliente é "caso simples" de oficina
- **Vantagem**: bom **piloto** pra `Modules/OficinaAuto` antes de migrar Vargas (que é mais complexo)

## 8. Decisões ADR locais

| ADR | Decisão | Status |
|-----|---------|--------|
| (futuro) | Modelo "Veículo simples" pra `Modules/OficinaAuto` valida com Martinho ANTES de Vargas | pendente |

## 9. Histórico

### 2026-05-11 — Primeira análise
- Heatmap UI v2 coletado
- Round 5 (final) do exercício do dia
- Confirma: oficina simples, bom piloto

### 2026-05-13 — Reunião Martinho + import vehicles
- 10h Wagner-Martinho · topou testar oimpresso
- 13h `import-vehicles.py --target prod --confirm` aplicou 91 caçambas + 91 service_orders biz=164
- biz=164 MARTINHO CAÇAMBAS LTDA criado em prod Hostinger

### 2026-05-14 — Wave A prod + incidente ROTA LIVRE + endosso Jair
- Manhã: Wave A imports prod biz=164 (contacts 18.845 + transactions 44k + fin_titulos parcial 5.546 + products 1.838)
- 14:30: **incidente cross-business** — import-estoque tocou 5 VLDs ROTA LIVRE biz=4 (CARDIGAN+JAQUETA+BLUSA · 3 unidades perdidas) · recovery via backup Hostinger 13/maio 19:55 BRT
- 15:30: cleanup write-off 748 títulos R$ 844k flagados
- 15:51 WhatsApp Kamila: *"continuar usando o sistema antigo e colocar o sistema novo para a lara e a dani usar... ele consegue puchar as informações em tempo real do sistema antigo"* — **insight estratégico dual-system**
- 16:30 Felipe presencial Martinho · **JAIR endossou** + Kamila pausou Highsoft
- 17h-18h: 3 importers fortalecidos cinto-suspensório · MWART /contacts entregue (21 Pest) · Sidebar customizada (26 Pest) · ADR proposal dual-sync escrita · Fix /sells/create dual-render biz=164 · 2 agents BG (Daemon Fase 1 + MWART /products) spawned

### 2026-05-19 (planejado) — Canary inicia
- Lara + Dani entram oimpresso · co-design presencial Wagner 20km
- Operação Kamila + vendedores continua Delphi (dual-system arquitetura)

## 10. Refs

- [Heatmap Martinho anonimizado](../../2026-05-sells-grade-heatmap/05-martinho-grade-usage-anonimizada.md)
- [_ANALISE-CROSS-CLIENTE §oficinas](../_ANALISE-CROSS-CLIENTE.md)
- [Snapshot financeiro 2026-05-11](03-financeiro-2026-05-11.md)
- [Perfil operacional vivo (referência rápida)](../../../reference/cliente-martinho.md)
- [Feedback cross-business bug 2026-05-14](../../../reference/feedback-importer-cross-business-bug.md)
- [Feedback Firebird batch instável 2026-05-14](../../../reference/feedback-firebird-batch-instavel.md)
- [ADR 0121](../../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) — `Modules/OficinaAuto` agora qualificado
- [ADR 0137](../../../decisions/0137-modules-oficinaauto-qualificada.md) — OficinaAuto qualificada formalmente
- [ADR proposal dual-sync](../../../decisions/proposals/dual-system-delphi-oimpresso-sync-realtime.md) — Delphi master + oimpresso viewer arquitetura
- [Session log 2026-05-14](../../../sessions/2026-05-14-martinho-canary-prep-massive.md)
- [Handoff 2026-05-14 18:00](../../../handoffs/2026-05-14-1800-martinho-canary-prep-jair-endossou.md)
- [CHECKLIST pós-reunião](../../../requisitos/OficinaAuto/demo-martinho-2026-05-13/CHECKLIST-POS-REUNIAO.md)
