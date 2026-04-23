# ADR UI-0001 (PontoWr2) · Espelho Show com totalizadores e gráfico dia-a-dia

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner, Claude
- **Categoria**: ui

## Contexto

O espelho de ponto é documento legal — auditor/colaborador precisa validar marcações do mês num olhar. Tela antiga Blade mostrava só tabela de marcações dia-a-dia, sem totalizadores nem visualização de padrões.

## Decisão

`Ponto/Espelho/Show.tsx` renderiza:

1. **Header** com colaborador, competência (mês/ano), navegação ← / → entre meses.
2. **4 KPIs**: horas trabalhadas, extras, faltas, banco de horas (saldo).
3. **Gráfico de barras dia-a-dia** (canvas custom sem lib externa) — barra por dia colorida conforme excesso/deficit.
4. **Tabela de marcações** com fonte monoespaçada, linhas destacando inconsistências (falta par, horário divergente).
5. **Botão Imprimir** com layout A4 otimizado (CSS `@media print`).

## Consequências

**Positivas:**
- Dashboard visual: problemas saltam aos olhos sem precisar ler 30 linhas.
- Imprimível: mantém o papel oficial.
- Sem dependência de biblioteca de chart pesada.

**Negativas:**
- Canvas custom = código próprio pra manter.
- Print CSS pode quebrar em PDF gerado por alguns navegadores.

## Alternativas consideradas

- **Chart.js / Recharts**: rejeitado — bundle pesado pra 1 gráfico de barras simples.
- **Só tabela**: rejeitado — UX idêntica à Blade, não justifica migração.
- **Timeline horizontal com ApexCharts**: avaliado, descartado por overhead visual.
