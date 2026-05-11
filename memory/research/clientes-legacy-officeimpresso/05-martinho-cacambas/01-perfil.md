---
slug: 05-martinho-cacambas
hash_id: Cliente_731814
status: qualificado
date_first_analysis: 2026-05-11
date_last_update: 2026-05-11
controlador: Wagner
vertical_real: oficina-cacambas-aluguel
size: pequeno-medio
tipo_relacao: cliente-pagante
banco_firebird: D:\DadosClientes\MartinhoCacamba\Dados\BANCO.FDB
banco_alias_firebird: 192.168.0.55:D:\DadosClientes\MartinhoCacamba\Dados\BANCO.FDB
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

## 5. Saúde financeira

Pendente.

## 6. Sinal qualificado pra migração

- [x] **FSM formalizada (VENDA_ESTAGIO 2 estados)** — único do sample que usa FSM ativa → cliente já pensa em "estado da OS", mais fácil migrar pra `Modules/Repair` ou `Modules/OficinaAuto`
- [ ] Reclamou? — desconhecido
- [x] Volume médio operação saudável

**Status pra ADR 0105:** sinal **baixo-médio** — porte menor que Vargas/Extreme/Gold. Não migrar prioritariamente; mas é caso piloto interessante pra `Modules/OficinaAuto` (mais simples que Vargas).

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

## 10. Refs

- [Heatmap Martinho anonimizado](../../2026-05-sells-grade-heatmap/05-martinho-grade-usage-anonimizada.md)
- [_ANALISE-CROSS-CLIENTE §oficinas](../_ANALISE-CROSS-CLIENTE.md)
- [ADR 0121](../../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) — `Modules/OficinaAuto` agora qualificado
