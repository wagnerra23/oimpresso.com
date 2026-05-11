---
slug: NN-cliente-slug
hash_id: Cliente_XXXXXX
status: rascunho | qualificado | em-migracao | migrado | opt-out | cancelado
date_first_analysis: YYYY-MM-DD
date_last_update: YYYY-MM-DD
controlador: Wagner
vertical_real: grafica | comunicacao_visual | oficina_auto | oficina_cacambas | recapagem | hibrido
size: micro | pequeno | medio | grande
tipo_relacao: cliente-pagante | cliente-inativo | prospect | ex-cliente
banco_firebird: D:\DadosClientes\<Nome>\Dados\BANCO.FDB
banco_alias_firebird: 192.168.0.55:D:\DadosClientes\<Nome>\Dados\BANCO.FDB
---

# Perfil — `Cliente_XXXXXX` (anonimizado)

> **LGPD:** este arquivo é **commitável** (versão anonimizada). Razão social/CNPJ/contato vivem em `01-perfil-COM-NOMES.md` (gitignored). Ver [_LGPD.md](../_LGPD.md).

## 1. Identificação anonimizada

| Campo | Valor |
|-------|-------|
| Hash ID | `Cliente_XXXXXX` |
| Vertical real (heatmap) | a preencher |
| Cidade / UF | XXX / XX |
| Porte (volume vendas/mês) | a preencher |
| Tempo de relação WR | a preencher |
| Status comercial | a preencher |

## 2. Tipo de negócio real

Descrever em 2-3 frases o que o cliente faz **de fato** (não o que o nome sugere). Cruzar com sinais do heatmap UI.

**Exemplos de descobertas possíveis:**
- "Aparente gráfica X é na verdade oficina de Y (sinal Q7 PLACA > 30%)"
- "Aparente comvis é gráfica industrial PCP (sinal Q8 centro_trabalho > 1k)"
- "Aparente comércio é serviço de Z (sinal Q5 itens/venda < 1.5)"

## 3. Módulos OfficeImpresso usados (sinais Firebird)

Cruzar com [02-heatmap-ui-YYYY-MM-DD.md](.) (link relativo).

| Módulo | Tabela-prova | % uso | Migração necessária? |
|--------|--------------|-------|----------------------|
| Vendas/POS | VENDA | XXX vendas/24m | sim |
| Financeiro | FINANCEIRO | XX% das linhas | sim |
| Agrupamento | CODFINANCEIRO_GRUPO | X% | (depende Grade Avançada) |
| Veículos | EQUIPAMENTO_VEICULO | XX% PLACA | só se oficina |
| PCP | VENDA_PRODUTO_CENTRO_TRABALHO | XX linhas | só se gráfica industrial |
| Status produção | VENDA_SITUACAO / VENDA_ESTAGIO | X distinct | depende vertical |

## 4. Saúde financeira (resumo)

Cruzar com [03-financeiro-YYYY-MM-DD.md](.) (gerado por skill `officeimpresso-financial-snapshot`).

| Métrica | Valor | Avaliação |
|---------|------:|-----------|
| Receita 12m | R$ XXX | — |
| MRR atual | R$ XX | — |
| Inadimplência (a receber vencidas) | R$ XX | — |
| Inadimplência (a pagar vencidas) | R$ XX | — |
| Resultado 12m | R$ XX | — |
| Top 1 cliente do cliente (concentração) | XX% | flag se >50% |

## 5. Sinal qualificado pra migração ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md))

Qual o sinal real de que ESSE cliente quer/precisa migrar?

- [ ] Reclamou explicitamente do Delphi (data, canal)
- [ ] Pediu uma feature nova que só faz sentido no oimpresso.com
- [ ] Tá deixando de pagar (sinal de descontentamento — ou só caixa apertado)
- [ ] Inadimplência crescendo (sinal de cuidado — migrar não resolve)
- [ ] Pediu demo do oimpresso.com
- [ ] Sem sinal — não migrar agora ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md))

## 6. Decisor + janela de abordagem (anonimizada)

- Função do decisor: (ex: "sócio-operador", "filho do dono", "gerente financeiro")
- Conhecimento técnico: (alto/médio/baixo)
- Estilo decisão: (rápido/cauteloso/comitê)
- Janela ideal pra abordagem: (manhã segunda? final de mês não?)
- **Detalhes nominais vão em [01-perfil-COM-NOMES.md](.) (gitignored)**

## 7. Plano de migração preliminar

Cruzar com [04-plano-migracao.md](.) se já existir.

- **Quando**: estimativa de cutover
- **Quem migra (interno)**: Felipe/Maiara/IA-pair
- **Quanto tempo**: estimativa horas
- **Customizações específicas**: lista
- **Risco principal**: descrever

## 8. Decisões ADR locais ao cliente

Decisões arquiteturais específicas (ex: "preservar campo Z customizado", "não migrar tabela X").

| ADR | Decisão | Data | Status |
|-----|---------|------|--------|
| — | — | — | — |

## 9. Histórico de análises (timeline)

Append-only — não rescrever entries antigas, só adicionar novas.

### YYYY-MM-DD — Primeira análise
- Resumo do que foi coletado
- Quem fez (Wagner / Felipe / Claude)
- Output: link pros relatórios gerados

## 10. Refs

- [HEATMAP-CONSOLIDADO §X](../../2026-05-sells-grade-heatmap/HEATMAP-CONSOLIDADO.md) — análise comparativa
- [_ANALISE-CROSS-CLIENTE.md](../_ANALISE-CROSS-CLIENTE.md) — onde esse cliente se encaixa
- [Skill financial-snapshot](../../../../.claude/skills/officeimpresso-financial-snapshot/SKILL.md) — pra rodar análise financeira

---

**Última atualização:** YYYY-MM-DD — autor (Wagner/Felipe/Claude). Próxima atualização sugerida: quando houver novo dado (heatmap re-rodado, financeiro atualizado, mudança de status comercial).
