---
sessao: "08"
titulo: Configurações da empresa em React
dono: "[CL]"
base: 348b1498bebe
---
# 08 · Configurações da empresa em React

Hoje: `getBusinessSettings` devolve `business.settings` (Blade, ~20 abas). Forma: rota `cfg-empresa` do protótipo (`data.jsx:200`). Fatiar por grupo de abas, 1 PR ≤300 linhas cada; o `postBusinessSettings` não muda. Processo MWART (ADR 0104): trio (.tsx + charter + casos) + teste de contrato tenant 98×99; Blade fica como fallback (`?classico=1`) até o cutover. **Não reli o controller neste turno além da assinatura do método** — linhas exatas saem da sua leitura.
