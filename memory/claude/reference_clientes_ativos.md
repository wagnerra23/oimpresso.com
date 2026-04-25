---
name: Clientes ativos e dormentes no oimpresso.com
description: Mapeamento dos 56 businesses — só 7 têm vendas; ROTA LIVRE concentra 99% do volume. Todos em America/Sao_Paulo exceto #116 (Cuiabá, dormente). Usar pra priorizar fixes e comunicação.
type: reference
originSessionId: 6cbda521-1ac7-4ff2-9419-9acdb42822ac
---
Snapshot em 2026-04-24:

**Businesses COM atividade (7 de 56):**

| id | name | TZ | vendas total | obs |
|---|---|---|---|---|
| 4 | **ROTA LIVRE** | SP | **17.251** | ~99% das vendas do sistema; Larissa é a operadora principal (users id=10, id=11 rota.vendas-04) |
| 1 | WR2 Sistemas | SP | 165 | Wagner (user id=1); dados de teste/demo mais que produção |
| 3 | EL TECNOLOGIA | SP | 10 | inativo — última venda 2021 |
| 117 | Fantasia | SP | 7 | inativo — 2023 |
| 8 | Fast Contact | SP | 6 | inativo — 2021 |
| 2 | Felipe WR2 | SP | 2 | teste |
| 41 | Dona Cartolina | SP | 2 | teste |

**49 businesses restantes = 0 vendas** — conta dormente / cadastro de teste / lead inativo.

**Timezone:** 56/56 businesses em `America/Sao_Paulo` (uniformizado em 2026-04-24; `#116 MFS GRÁFICA` estava em `America/Cuiaba` e foi movido pra SP — tinha 0 vendas, ninguém afetado).

**Implicações operacionais:**

- "Todos os clientes em SP" é afirmação operacional verdadeira (o único outlier Cuiabá nunca operou).
- Qualquer fix/migration sistêmica impacta na prática **apenas 1 cliente (ROTA LIVRE)**. Comunicação é 1:1, não broadcast.
- ROTA LIVRE é o único "cliente real" — nenhum teste de feature em outro business vai cobrir cenário de produção.
- ROTA LIVRE pode estar operando variantes de comportamento que WR2 Sistemas (teste) não tem.

**How to update:** regerar com query em `reference_hostinger_analise.md` (template SSH + mysql CLI). Se um business dormente começar a vender, atualizar esta lista.
