---
slug: <slug-kebab-curto>                       # ex: rotalivre, martinho-cacambas, vargas-recapagem
business_id: <int>                              # business_id em prod oimpresso (null se ainda não criado)
razao_social: <RAZAO SOCIAL LTDA>
cnpj: <00.000.000/0000-00>                      # OK em git (público registro)
status: prospect                                # prospect | qualificado | piloto-ativo | producao | churned | feature-wish
vertical_principal: <slug>                       # vestuario | comunicacao-visual | oficina-auto-locacao-cacamba | etc
sub_vertical: <descritivo curto>                 # ex: caçamba-avulsa-entulho-obra
cnae: <0000-0/00>
cidade_uf: <CIDADE>/<UF>
distancia_km_wagner: <int|null>                  # km até escritório Wagner · null se não relevante
inicio_relacionamento: <YYYY-MM-DD>
canary_inicio: <YYYY-MM-DD|null>
faturamento_anual_brl: <decimal|null>            # snapshot mais recente
funcionarios_total: <int|null>
champions_oimpresso:                             # NÃO-donos que adotam; lista vazia se piloto não iniciou
  - slug: <funcionario-slug>
    role: <responsabilidade>
decisor_principal: <funcionario-slug|null>       # quem dá luz verde formal (dono ou sócio)
sistema_anterior: <descritivo>                   # ex: Office Comercial Delphi (WR Sistemas legacy)
concorrentes_avaliados_pausados: []              # SaaS/ERP que cliente avaliou e pausou pela escolha do oimpresso
pricing_mensal_brl: <decimal|null>
arquitetura_migracao: <slug|null>                # dual-sync-delphi-master-oimpresso-viewer | cutover-completo | etc
perfil_legacy: <path relativo|null>              # cross-link pra research/clientes-legacy-officeimpresso/ se aplicável
timezone: America/Sao_Paulo
ultima_atualizacao: <YYYY-MM-DD>
proxima_revisao: <YYYY-MM-DD>                     # forçar revisão proativa (skill alerta se atrasada)
---

# <RAZAO SOCIAL CURTA> (business_id = <N>)

<!-- 1-2 frases descrevendo cliente em alto nível. Vertical real + porte + sinalização do que importa. -->

## 1. Identificação

<!--
Tabela com campos legais + operacionais. Inclua:
- Razão social completa
- CNPJ (OK em git)
- Endereço comercial (OK em git — público registro)
- Telefone comercial (OK em git — público)
- Localização (BL000N "NOME LOJA" — Cidade/UF, CEP)
- Timezone operacional
- Cadastro original no oimpresso (data)
- Volume operacional (vendas total, primeira venda, frequência)
-->

## 2. Stakeholders

<!--
Tabela cross-link funcionarios/<cliente>/<slug>.md por linha:
| Nome curto | Papel | user biz=N | sistema usado | papel canary |
Champions destacados em negrito. Decisor principal explícito.
-->

## 3. Saúde financeira

<!--
Snapshot 12 meses (ou janela mais recente):
- Receita 12m
- Despesa 12m (se levantada)
- Margem operacional %
- Inadimplência vencida (R$ + %)
- Ticket médio + mediana
- Top 10 clientes % receita
- Lançamentos 12m
Cross-link pra snapshot detalhado em research/clientes-legacy-officeimpresso/ se aplicável.
-->

## 4. Sistema atual

<!--
- Sistema legacy (nome + fornecedor + versão se souber)
- Sistemas paralelos avaliados/pausados (concorrentes)
- Pain-points reportados
- Quem opera (cross-link funcionarios/)
-->

## 5. Arquitetura migração

<!--
Estratégia escolhida + cronograma:
- Dual-sync? Cutover? Big-bang?
- Champions entram quando
- Operadores legacy continuam onde
- Daemon/scripts envolvidos
- ADRs relacionadas
-->

## 6. Pricing + comercial

<!--
- Pricing mensal acordado
- Upsells (WhatsApp Inbox, Jana IA, NFSe, etc)
- Contratos/trial
- Forma de pagamento
- Vencimento
-->

## 7. Sensibilidades operacionais — NÃO MEXER SEM AVISAR

<!--
Caixa-preta CRÍTICA. Cada item = comportamento esperado que vira regressão se mexer.
Exemplos:
1. Larissa decorou shift +3h `format_date` (ADR 0066)
2. Vocabulário m³ caçamba (NÃO m²)
3. transaction_date retroativo é normal (não tentar "corrigir")
4. Monitor 1280px (cuidar layout)
-->

## 8. Estado prod oimpresso

<!--
Tabela com rows importadas + features ligadas + features escondidas:
| Tabela | Rows | Status |
| feature | ligada/escondida | quem habilitou |
-->

## 9. Histórico de marcos

<!--
Datado e cross-linkado:
### YYYY-MM-DD — Evento curto
Resumo. Cross-link session/handoff/ADR.
-->

## 10. Refs

<!--
- ADRs relacionadas
- Sessions onde aparece
- Handoffs relacionados
- Research legacy
- RUNBOOKs
- Feedbacks catalogados
-->
