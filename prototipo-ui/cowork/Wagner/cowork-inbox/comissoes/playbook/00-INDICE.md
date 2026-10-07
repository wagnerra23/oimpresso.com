# Comissões · playbook 00 — índice

```json
{
  "modulo": "comissoes",
  "base": "wagnerra23/oimpresso.com@main 23c3f080aa94 (2026-10-06)",
  "threads": [
    {
      "id": "01",
      "titulo": "Comissionados -> Inertia",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "(ponteiro) cowork-inbox/sistema/playbook/03-comissionados-salescommissionagent-inert.md",
      "prefixo": [
        "app/Http/Controllers/SalesCommissionAgentController.php",
        "resources/js/Pages/SalesCommissionAgent/"
      ],
      "nao_toca": [
        "Modules/"
      ],
      "provas": [],
      "nota": "dono e o playbook sistema; nao duplicar"
    },
    {
      "id": "02",
      "titulo": "Relatorio de comissao por vendedor -> Inertia",
      "dono": "CL",
      "vaga": 2,
      "arquivo": "02-relatorio-comissao.md",
      "prefixo": [
        "app/Http/Controllers/ReportController.php",
        "resources/js/Pages/Report/SalesRepresentative/"
      ],
      "nao_toca": [
        "Modules/"
      ],
      "depende_threads": [
        "A1"
      ],
      "provas": []
    },
    {
      "id": "03",
      "titulo": "Split de comissao auditado (activity log)",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "03-split-auditado.md",
      "prefixo": [
        "app/Transaction.php",
        "app/Http/Controllers/SellCommissionSplitController.php",
        "tests/Feature/Sells/"
      ],
      "nao_toca": [
        "resources/js/"
      ],
      "provas": []
    },
    {
      "id": "A1",
      "titulo": "ALVO comissoes (rota comissoes do prototipo)",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "A1-alvo.md",
      "prefixo": [
        "governance/design/targets/"
      ],
      "provas": []
    }
  ],
  "decisoes": [
    {
      "id": "D-COM-1",
      "pergunta": "ADR 0151 (Modules/Comissao feature-wish) segue proposta: o playbook fica so no legado (commission_agent, cmmsn_percent, commission_split), sem Modules/Comissao?",
      "respondida": true,
      "resposta": "[W] 2026-10-07 \"o que recomenda?\" → recomendação [CC]: só no legado (commission_agent / cmmsn_percent / commission_split), sem Modules/Comissao — a ADR 0151 segue proposta",
      "quando": "2026-10-07"
    },
    {
      "id": "D-COM-2",
      "pergunta": "Relatorio: comissao sobre venda paga ou faturada? Ler a regra que o ReportController ja usa e manter; mudar exige ADR",
      "respondida": true,
      "resposta": "[W] 2026-10-07 \"o que recomenda?\" → recomendação [CC]: manter a regra que o ReportController já usa hoje; mudar mexe em valor (regra mestre: dupla prova + antes→depois + ADR)",
      "quando": "2026-10-07"
    }
  ]
}
```

**Lido no `main` @23c3f080aa94 (2026-10-06):** ADR 0151 (`status: proposto`, `kind: feature-wish`: **nenhum código em `Modules/Comissao/`** até um gatilho) · `SellCommissionSplitController.php` inteiro · `ReportController.php` linhas 1221–1313 (`getSalesRepresentative{Report,TotalExpense,TotalSell,TotalCommission}`) só pela assinatura, corpo **não lido** · `app/Transaction.php` inteiro (`logOnly` sem campos de comissão) · `sistema/playbook/03` (Comissionados → Inertia, sem `_saida-03`) · nenhuma Page React de comissionados ou de relatório de comissão.

## Fronteira (o que este playbook NÃO faz)
- **Não cria `Modules/Comissao`** nem as 14 US-COMM (ADR 0151). Multi-papel, faixas, clawback = só depois de um gatilho da ADR + ADR de ativação [W].
- **Comissionados** é do playbook `sistema` (thread 03). Aqui é só ponteiro (anti-scatter).

| # | Thread | Vaga | Arquivo |
|---|---|---|---|
| 01 | Comissionados → Inertia | 1 | ponteiro: `sistema/playbook/03` |
| 03 | Split de comissão auditado | 1 | `03-split-auditado.md` |
| A1 | ALVO `comissoes` | 1 | `A1-alvo.md` |
| 02 | Relatório por vendedor → Inertia | 2 | `02-relatorio-comissao.md` (depende A1 · D-COM-2) |

## Decisões [W]
- **D-COM-1** — Fica só no legado? **Proposta [CC]: sim** (é o que a ADR 0151 já manda).
- **D-COM-2** — Base do relatório: o Code **lê** a regra que o `ReportController` usa hoje e mantém. Mudar a base (paga × faturada) é outra ADR.
