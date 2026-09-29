---
sessao: "_saida-04"
thread: "04 · Metas — PUXAR (produção à frente, #6869)"
dono: "[CC]"
data: 2026-09-29
base_lida: wagnerra23/oimpresso.com@main c799a208a387 — resources/js/Pages/Essentials/Metas.tsx (12.261 B, lido inteiro) · DashboardController.php (10.207 B, lido inteiro) · Painel.tsx (5.831 B, lido inteiro)
prefixo_tocado: prototipo-ui/cowork/Wagner/hrm-extras.jsx (Metas) · **fora do prefixo, declarado:** hrm-page.jsx (Painel — mesma regra de VALOR) · oimpresso.com.html (bump)
---
# _saida-04

## Pedido literal
[W] 2026-09-29: RESÍDUO-5 decidido por revisão adversária — *tirar a apuração do protótipo*. Depois: *"pode sim"* para tirar folha do Painel.

## Feito
1. **Metas puxada do vivo** (`hrm-extras.jsx?v=hrm13metas`). Colunas iguais ao `Metas.tsx`: Colaborador · Faixas · Meta inicial · Meta final · Comissão (% ou faixa de %) · Situação · ação. Nota "Esta tela cadastra a meta — não apura o resultado" com a base sem/com imposto. KPIs: Com meta · Sem meta · Base do cálculo. `caption` sr-only igual ao vivo.
2. **Saiu do build:** Mês anterior · Mês atual · Faixa atingida · Progresso na faixa · Comissão em R$ · KPI "Comissão de meta apurada". Motivo: `Metas.charter.md:53` (Non-Goals, caminho de VALOR).
3. **Aviso de sobreposição corrigido:** o build dizia "o servidor aceita (achado A5)"; o vivo diz que o servidor recusa (`SalesTargetFaixaValidator`, #6799). Texto trocado.
4. **Painel alinhado ao vivo (fora do prefixo, mesma regra)** (`hrm-page.jsx?v=hrm14painel-b`): KPI "Presença de hoje — / a jornada é do Ponto" · fila só Marcações (sem contagem) + Licenças + Meta faltando · card "Presença de hoje" removido · "Minhas metas" só faixas gravadas · KPI "Folha 08/2026", card "Custo de folha por setor" e item "Folha em rascunho" removidos (R$ sem motor — `_saida-10`).

## Diff nos dois sentidos
| | vivo tem, build não tinha | build tinha, vivo não tem |
|---|---|---|
| Metas | Nota "cadastra, não apura" · colunas Faixas/Meta inicial/final · `caption` | 5 colunas de apuração + KPI comissão → **removidos** |
| Painel | "Presença de hoje —" com link | presença com número · vendido no mês · folha R$ → **removidos** |

Divergência que **fica** (intencional): o vivo mostra "Licenças pendentes —" (sem número); o build mostra a contagem. Não é VALOR — é agregado que o `hrmDashboard` não calcula hoje. Se [W] quiser o número no vivo, é pedido de 1 query ao `buildPainelPayload`, não correção do build.

## Não feito
- Nada no `main`: thread [CC], só build.
- `getUserSalesTargets` (`DashboardController.php:140`) segue vivo como Ajax legado (admin). Nenhuma Page o consome; sai com a thread 11 (O8).

## Descobertas
1. A premissa "a apuração existe no Painel" (minha, em 29/09) estava errada: o docblock de `hrmDashboard` exclui o realizado pela mesma razão da Metas.
2. `H.REALIZADO` em `hrm-data.jsx` ficou sem consumidor no build — dado de mock, inofensivo; sai na próxima limpeza do `hrm-data.jsx`.
