---
id: requisitos-fiscal-das-gap
tela: Fiscal/Das (/fiscal/das)
prototipo: prototipo-ui/cowork/Wagner/fiscal-das-page.jsx
tela_viva: resources/js/Pages/Fiscal/Das.tsx
gerado_em: 2026-11-05
comparacao: memory/requisitos/Fiscal/fiscal-das-visual-comparison.md
---

# GAP-SPEC — Fiscal/Das

Inventário por parte, lido nos dois arquivos (protótipo e `.tsx`) em 2026-11-05. Não é medida de
estilo: a sonda do `design-diff` não alcança o protótipo desta tela (ver o visual-comparison).

| Parte | Estado no vivo | Ação |
|---|---|---|
| Cabeçalho e filtros | Título, competência e anexo existem (`data-contract=cabecalho`); o protótipo tem também o seletor "Cenário (só no protótipo)" | **Nada.** O seletor de cenário é instrumento do protótipo, não da tela. |
| Estado sem anexo | Card com "Escolha o anexo que o contador usa" (`data-contract=vazio`); o protótipo cita "aba Serviços" para o Fator R, o vivo não | **Decisão [W]**: a "aba Serviços" não existe no subnav do vivo. |
| KPIs | Quatro números (`data-contract=kpis`): receita do mês, RBT12, alíquota efetiva, DAS do mês | **Nada.** Mesmos quatro rótulos. |
| A conta | Fórmula da LC 123 art. 18 §1º-A com os números (`data-contract=conta`) | **Nada.** |
| Faixas do anexo | Tabela com a faixa atual marcada (`data-contract=faixas`) | **Nada.** |
| Estimativa × apuração | Não existe no vivo | **Nada nesta fatia.** Non-Goal do charter: a receita recebida tem dono no `ImpostosController`. |
| Perguntas ao contador | As quatro perguntas (`data-contract=contador`) | **Nada.** Mesmo texto. |
