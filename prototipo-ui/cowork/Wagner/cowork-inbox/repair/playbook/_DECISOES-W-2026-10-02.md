# _DECISOES-W-2026-10-02 — Repair (Code → Cowork)

> **Estatuto:** pedido de edição do `00-INDICE.md` DO COWORK. O Code não edita o espelho.
> **Fonte:** [W] 2026-10-02, resposta textual às perguntas abertas pela thread 04 (portal) e pela thread 05 (recorte do `JobSheet/Index`).
> **Precedência:** complementa `_DECISOES-W-2026-10-01.md` e `_DECISOES-W-2026-10-01b.md`. Não revoga a D2 ("o portal entra agora") — diz **qual tela** é o portal.

| id | resposta [W] (textual) | efeito | destrava | revoga |
|---|---|---|---|---|
| D-PORTAL | *"Ligar o ConsultaOs ao Repair"* | o portal do cliente é o **ConsultaOs** (`/consulta-os`, Inertia, público) — **não** nasce `Pages/Repair/Portal`. Motivo: o ConsultaOs já era o portal Inertia público e a US-CONSULTA-001 já previa ligá-lo ao Repair. O `/repair-status` passa a levar o cliente pra lá. | 04 | prefixo `${PAGES}/Repair/Portal/` da thread 04 |
| D-RECORTE | *"A tela ganha as abas"* | o `JobSheet/Index` ganha o recorte **Pendentes / Concluídas / Entrega vencida / Todas** do protótipo. Fica pra thread 05, em outro PR. | 05 | — |

## Edição pedida no json
```json
[{ "id": "D-PORTAL", "respondida": true, "resposta": "o portal é o ConsultaOs; sem Pages/Repair/Portal; /repair-status leva ao /consulta-os" },
 { "id": "D-RECORTE", "respondida": true, "resposta": "JobSheet/Index ganha as abas Pendentes/Concluídas/Entrega vencida/Todas (thread 05)" }]
```

## Ajuste pedido na thread 04
- `prefixo`: trocar `${PAGES}/Repair/Portal/` por `Modules/ConsultaOs/`, `${PAGES}/ConsultaOs/` e `${MOD}/Http/Controllers/CustomerRepairStatusController.php`.
- `provas`: ver `_saida-04.md` §"Prova do índice" — o controller do Repair redireciona em vez de renderizar, então a prova `contem "Inertia::render("` não se aplica.
