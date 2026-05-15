---
slug: dani
cliente_slug: martinho-cacambas
first_name: Dani
nome_completo_real: TBD-perguntar-wagner-provavelmente-DANIELLI
relacao: funcionária · financeiro (não familiar Jair)
role_operacional: financeiro
cargo_formal: TBD
user_id_oimpresso: 297
username_oimpresso: danielli-164
papel_canary: champion-oimpresso
acesso_sistemas:
  - sistema: Office Comercial Delphi (WR Sistemas legacy)
    role: operadora-financeiro
  - sistema: oimpresso
    role: criado prod biz=164 (entra 19/maio)
preferencias_ux:
  - persona_financeira_resultado_orientada
  - precisa_filtro_write_off_para_dashboard_ar_ap
  - pede_co_design_presencial
sensibilidades:
  - 76_7_pct_inadimplencia_fossil_2015_2017_e_trauma_visivel
  - ataque_cardiaco_dashboard_sem_filtro_write_off
  - approach_made_together_obrigatorio
  - dani_provavelmente_e_DANIELLI_user_297
pii_vault_ref: vault://martinho-cacambas/dani
data_primeiro_contato: 2026-05-13
ultima_atualizacao: 2026-05-14
---

# Dani / DANIELLI (martinho-cacambas)

Funcionária responsável **financeiro** MARTINHO CAÇAMBAS LTDA · **champion canary oimpresso** ao lado da Lara (estoque). Não-familiar do Jair. Persona alvo das cleanup tools US-005 (write-off inadimplência fóssil 76.7%). Entra oimpresso semana 19/maio.

> ⚠️ **Mapeamento "Dani"="DANIELLI" provavelmente verdadeiro** — user `danielli-164` (id=297) já existe em prod biz=164 e Wagner se refere a ela como "Dani" no perfil cliente. **Confirmar com Martinho/Jair pré-canary**. Marcar `nome_completo_real: TBD-perguntar-wagner-provavelmente-DANIELLI`.

## 1. Papel atual

- **Role operacional:** financeiro · contas a receber (AR) + contas a pagar (AP) · cobrança · inadimplência
- **Responsabilidade primária:** acompanhar fluxo de caixa · cobrar inadimplência · fechar mês contábil
- **Cadeia decisão:** funcionária senior · reporta provavelmente direto pro Jair / Martinho · autonomia financeira
- **Família:** não-familiar do Jair (não confundir com Lara/Kamila filhas)

## 2. Acesso a sistemas

| Sistema | role/permissão | user_id/login | observação |
|---|---|---|---|
| Office Comercial Delphi (WR Sistemas legacy) | operadora-financeiro | (legacy, não levantado) | Sistema atual · sai pra oimpresso 19/maio |
| oimpresso | (criado) biz=164 | id=297 `danielli-164` | User existe em prod · Wagner precisa **confirmar mapeamento Dani=DANIELLI** |

**Pendência P0 Wagner segunda 19/maio:** confirmar `DANIELLI (id=297) = Dani` antes do canary. Mais: pegar dados pessoais reais (CPF · email · telefone — vão pra Vaultwarden).

## 3. Preferências UX

- **Persona:** financeira · resultado-orientada (números importam mais que UX bonita)
- **Idioma:** PT-BR
- **Co-design presencial:** pediu ver Wagner desenvolvendo (mesmo approach Lara)
- **Distância viável:** 20km Wagner-Martinho
- **Necessidade UX crítica:** filtro write-off no dashboard AR — sem isso vê R$ 4.82M inadimplência fóssil 2015-2017 e tem **ataque cardíaco** (frase Wagner literal CHECKLIST)

## 4. Sensibilidades

1. **76.7% inadimplência fóssil 2015-2017** — Dani vai ver dashboard ao ligar → ataque cardíaco se não filtrar write-off. Cleanup tools US-005 **OBRIGATÓRIO** antes Dani entrar `/financeiro/boletos`. Heurística atual: `tipo='receber' AND status IN ('aberto','parcial') AND vencimento < NOW-1095d` → 748 títulos R$ 844k flagados `metadata.is_write_off_candidate=true`.

2. **Approach "made together"** — pediu ver Wagner desenvolvendo (mesmo padrão Lara). Wagner deve incluir Dani em sessões presenciais 20km.

3. **Persona alvo das cleanup tools US-005** — feature foi desenhada PARA ela. Entrega errada = perda do champion financeiro.

4. **Não confundir com Larissa (ROTA LIVRE)** — persona diferente: Dani é financeira de empresa R$ 6.28M/ano com inadimplência fóssil específica; Larissa é dona-operadora vestuário sem AR vencido relevante.

5. **Mapeamento Dani↔DANIELLI provavelmente verdadeiro** — user `danielli-164` (id=297) existe em prod biz=164 desde import de users via integração Delphi. Pesquisa em [`memory/reference/cliente-martinho.md`](../../cliente-martinho.md) (antes do move) confirma "DANI / DANIELLI". **Não tratar como certo** — Wagner confirma pré-canary.

## 5. Histórico de interações

### 2026-05-13 — Dani identificada como champion canary financeiro
Reunião Wagner+Martinho 10h decidiu: Champion interno filha do Martinho (operação) + **Dani (financeiro)** — querem usar oimpresso.

### 2026-05-14 manhã — User danielli-164 (id=297) ativo em prod biz=164
11 users via integração Delphi (incluindo Danielli, Rodrigo, Eduardo, Kamila). Provável mapeamento Dani↔DANIELLI estabelecido.

### 2026-05-14 ~16:30 — Felipe presencial · Dani confirmada champion
Kamila pausou Highsoft pelo oimpresso porque Lara+Dani entram lá. Dani confirmada champion canary financeiro.

### 2026-05-14 — Cleanup write-off 748 títulos flagados
748 títulos receber vencidos >3 anos R$ 844.660 flagados `metadata.is_write_off_candidate=true` · Dani filtra UI quando entrar. Heurística write-off implementada em `import-financeiro.py`.

### 2026-05-19 (planejado) — Canary inicia · Dani entra oimpresso
Primeiro dia em prod biz=164 financeiro. Co-design presencial Wagner. Pendência: confirmar Dani↔DANIELLI · re-rodar `import-financeiro.py` quando daemon Fase 1 + Firebird estável (atual 5.546 / 98.533 = 6% importados).

## 6. Refs

- Cliente: [martinho-cacambas](../../clientes/martinho-cacambas.md)
- [CHECKLIST pós-reunião](../../../requisitos/OficinaAuto/demo-martinho-2026-05-13/CHECKLIST-POS-REUNIAO.md) — persona alvo cleanup US-005
- [Snapshot financeiro 2026-05-11](../../../research/clientes-legacy-officeimpresso/05-martinho-cacambas/03-financeiro-2026-05-11.md)
- [feedback-firebird-batch-instavel.md](../../feedback-firebird-batch-instavel.md) — import-financeiro caiu 98k rows
- [Session log 2026-05-14](../../../sessions/2026-05-14-martinho-canary-prep-massive.md)
- [Handoff 2026-05-14 18:00](../../../handoffs/2026-05-14-1800-martinho-canary-prep-jair-endossou.md)
- Funcionários relacionados: [lara](lara.md) (champion duplo · estoque) · [kamila](kamila.md) (irmã da Lara · pausou Highsoft pela Dani usar oimpresso)
