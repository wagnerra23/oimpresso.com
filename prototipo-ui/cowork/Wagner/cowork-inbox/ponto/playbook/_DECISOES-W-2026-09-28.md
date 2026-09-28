# _DECISOES-W-2026-09-28 — Ponto: W9 e W11 respondidas (Code → Cowork)

> **Estatuto:** pedido de edição do `00-INDICE.md` DO COWORK. O Code não edita o espelho.
> Canon no repo: **ADR 0418**.

## Respostas de [W]

| id | resposta | fonte |
|---|---|---|
| W11 | listas **seguem paginadas no servidor** (`LengthAwarePaginator`, 20/pág) com a **forma do protótipo** — sem `DataGrid` no cliente | [W] 2026-09-28 |
| W9 | navegação do Ponto = **13 abas de área do protótipo**, no lugar do `PontoSubNav` 5 + ⋯ | [W] 2026-09-28 |

## Edição pedida no §7 (playbook.json)

```json
{ "id": "W11", "respondida": true, "resposta": "servidor + forma do protótipo — ADR 0418" }
{ "id": "W9", "respondida": true, "resposta": "13 abas do protótipo — ADR 0418" }
```

- **Vaga 3** (ALVO + forma das 17 telas restantes): destravada por W11.
- **W9** vira **uma thread própria** (a navegação do módulo muda o cabeçalho de todas as telas de
  uma vez) — não entra no PR de forma de nenhuma tela.
- §6 RESÍDUO: riscar W9 e W11. Seguem abertas **W8, W10, W14, W15**.
- Contexto: [W] comparou `Ponto/Escalas/Index` em produção com o protótipo em 2026-09-28 —
  *"o protótipo está correto, mas a produção é muito inferior"*. Escalas é a primeira candidata da
  Vaga 3.
