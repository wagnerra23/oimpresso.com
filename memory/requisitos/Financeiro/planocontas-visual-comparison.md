---
id: requisitos-financeiro-planocontas-visual-comparison
tela: /financeiro/plano-contas
component: resources/js/Pages/Financeiro/PlanoContas/Index.tsx
charter: resources/js/Pages/Financeiro/PlanoContas/Index.charter.md
prototipo: prototipo-ui/cowork/Wagner/financeiro-telas-extras.jsx (TelaPContas)
data: 2026-09-23
---

# Comparativo visual — Financeiro · Plano de contas (FIN-6)

Até 2026-09-23 o charter declarava `n/a (herda PT-01 Lista)` enquanto o protótipo tinha `TelaPContas` —
divergência entre dois artefatos canônicos (RUNBOOK-paridade-ondas §4.3). **Decisão [W] 2026-09-23:
seguir o protótipo só na forma.** Medido nos dois lados no mesmo dia (protótipo servido com o DS pela
porta local; produção em `/financeiro/plano-contas`, empresa 1).

## FIN-6a — forma

| Dimensão | Protótipo | Produção antes | FIN-6a | Veredito |
|---|---|---|---|---|
| Título | "Financeiro · Plano de contas" | "Plano de Contas · Estrutura contábil BR" | igual ao protótipo | IGUAL |
| Primário | "Novo título" | "Nova conta" → **404** (`/financeiro/plano-contas/create` não existe) | "Novo título" (só navega) | IGUAL · o botão quebrado sai |
| Subtítulo | "Mês · Empresa · caixa unificado" | contagem + texto explicativo | "Empresa · caixa unificado" (sem mês: a tela não é por período e o backend não manda data) | IGUAL na forma, sem o mês |
| Cartão da lista | cartão único com título "Plano de contas" + subtítulo + busca à direita | tabela solta, busca na barra de filtros | cartão único; subtítulo "Receita Federal/DCASP · N níveis" (N medido do plano) | IGUAL |
| Árvore | recuo por nível + "└" + peso por nível + 1º nível com fundo | recuo no código, peso 600/400 | recuo na conta + "└" + peso por nível + 1º nível com fundo | IGUAL |
| Selo de tipo | pílula com ponto (Receita/Despesa) | retângulo, texto minúsculo | pílula com ponto; os 4 tipos que o protótipo não desenha usam o tom mais próximo | IGUAL |
| Cor crua | — | 8 usos (`stone`, `blue`, `amber`) | 0 | IGUAL |

## Mantido por decisão [W] (não é dívida)

- 5 KPIs (total, receita, despesa, ativo, passivo+patrimônio) e as abas por tipo com contagem.
- Colunas Natureza · Aceita lanç. · Protegido.

## Fica para depois (depende de backend)

- **"Lanç. mês" e "Saldo mês"** — soma de lançamentos por conta no mês: dado novo e **regra de valor**
  (prova por dois caminhos + antes→depois). FIN-6b.
- **"Importar", "+ Nova" e "editar"** do protótipo — o backend só tem `plano-contas.index`. Sem rota, o botão
  seria promessa falsa; entram quando existirem as ações.
