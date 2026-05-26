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
cidade: "Capivari de Baixo"
uf: SC
banco_firebird: D:\DadosClientes\MartinhoCacamba\Dados\BANCO.FDB
banco_alias_firebird: 192.168.0.55:D:\DadosClientes\MartinhoCacamba\Dados\BANCO.FDB
---

# Perfil — `Cliente_731814` (peças hidráulicas basculante + oficina autorizada · Capivari de Baixo/SC)

> **Correção 2026-05-26:** entendimento original neste perfil (e em [ADR 0137](../../../decisions/0137-modules-oficinaauto-qualificada.md) + [BRIEFING OficinaAuto](../../../requisitos/OficinaAuto/BRIEFING.md)) descrevia Martinho como "locação de caçamba estacionária pra entulho/obra (CNAE 4581-4/00)". Wagner identificou o erro de leitura — Martinho é **loja de peças hidráulicas pra caminhão basculante + oficina autorizada** (CNAE 4520-0/01). Correção formal via [ADR 0194](../../../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) (pendente aceite).

## 1. Identificação

| Campo | Valor |
|-------|-------|
| Hash ID | `Cliente_731814` |
| Razão social | MARTINHO CAÇAMBAS LTDA |
| Nome fantasia | Martinho Caçambas |
| Vertical real | Peças hidráulicas pra basculante (Polli-guindaste, plataforma, munck) + oficina autorizada caminhão pesado |
| CNAE principal | **4520-0/01** (manutenção e reparação mecânica de veículos automotores) — não 4581 (locação) |
| Porte | médio-grande (R$ 1M+/mês estimado Wagner 2026-05-26 · R$ 6.28M/12m Firebird snapshot) |
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

- **R$ 6.28M receita 12m** (Firebird snapshot 2026-05-11 — ver [03-financeiro-2026-05-11.md](03-financeiro-2026-05-11.md) se existir)
- **R$ 1M+/mês estimado** (Wagner 2026-05-26 — pode estar arredondado pra cima ou refletir período recente)
- **76.7% inadimplência legacy** (a investigar)

## 6. Sinal qualificado pra migração

- [x] **FSM formalizada (VENDA_ESTAGIO 2 estados)** — único do sample com FSM ativa
- [x] **Cliente pagante há anos** + **aceitou migração 2026-05-13** (reunião 10h)
- [x] **Faturamento alto** (R$ 1M+/mês — sinal forte ROI)
- [x] **Volume operacional saudável** (44k vendas + 91 placas)
- [x] **Ativação piloto formal** [ADR 0171](../../../decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md) (2026-05-20) — beta 30d add-on WhatsApp + base R$ 850 grandfathered

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

## 8. Decisões ADR locais

| ADR | Decisão | Status |
|-----|---------|--------|
| [0137](../../../decisions/0137-modules-oficinaauto-qualificada.md) | OficinaAuto qualificada (Vargas + Martinho) | aceito (com erro de domínio Martinho — amendado por 0194) |
| [0171](../../../decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md) | Ativação piloto Martinho faseada | aceito (amendado por 0194) |
| [0194](../../../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) | Correção domínio Martinho mecânica pesada (não locação) | **proposed 2026-05-26** |

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
- [ADR 0171](../../../decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md) aceito — homologação faseada por feature + add-on WhatsApp R$ 99/inst beta 30d + base R$ 850 grandfathered (inclui OficinaAuto + NFe + NFSe)

### 2026-05-26 — Correção de domínio
- Wagner identificou erro: NÃO é locação caçamba container — é **loja de peça hidráulica basculante + oficina autorizada** (Capivari de Baixo/SC). Empresa do filho; pai (Martinho da Caçamba Tubarão) é empresa separada (transportadora resíduo, **não cliente**).
- WebSearch confirmou: Martinho Caçambas (Rua Antonia de Bitencourt Barcelos, Capivari de Baixo SC) vende peça hidráulica pra Polli-guindaste / munck / plataforma / basculante.
- Tork (Tomadas de Força, Capivari) entra na cadeia como fornecedor PTO indústria — perfil prospect em [clientes-prospect/tork-tomadas-forca/](../../clientes-prospect/tork-tomadas-forca/01-perfil.md).
- [ADR 0194](../../../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) proposed formaliza correção (aguarda aceite Wagner).

## 10. Refs

- [Heatmap Martinho anonimizado](../../2026-05-sells-grade-heatmap/05-martinho-grade-usage-anonimizada.md)
- [_ANALISE-CROSS-CLIENTE §oficinas](../_ANALISE-CROSS-CLIENTE.md)
- [discovery Martinho reunião 2026-05-13](../../../requisitos/OficinaAuto/demo-martinho-2026-05-13/discovery-martinho.md)
- [ADR 0137](../../../decisions/0137-modules-oficinaauto-qualificada.md) — qualificação OficinaAuto (com erro de leitura domínio Martinho)
- [ADR 0171](../../../decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md) — ativação piloto faseada
- [ADR 0194](../../../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) — correção domínio (proposed)
- [Perfil prospect Tork PTO](../../clientes-prospect/tork-tomadas-forca/01-perfil.md)
- [Dicionário domínios §"Sub-vertical 4"](../../../reference/dominios-verticais-oimpresso.md)
- WebSearch fontes 2026-05-26:
  - [Martinho Caçambas (Listatudo) — Capivari de Baixo SC, peça hidráulica basculante/Polli/munck](https://listatudo.com.br/santa-catarina/florianopolis-e-regiao/tubarao/construcao-civil-e-meio-ambiente/construcao-civil/maquinas-e-equipamentos/cacambas/martinho-da-cacamba/)
  - [Jocimar dos Santos Martinho — Tubarão SC (entidade pai, NÃO cliente oimpresso)](https://cnpj.biz/27302634000155)
  - [Tork Tomadas de Força — Capivari de Baixo SC](https://lp.tork.ind.br/)
