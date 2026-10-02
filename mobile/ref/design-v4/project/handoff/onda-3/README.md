# Onda 3 — Telas antigas + descontinuar erp-ui

Depende das Ondas 1 e 2 (`OiForm.tsx`, `notify.ts`, `OiStatus` com `info`). Proposta para PR.

## Ideia

Em vez de reescrever 15 telas, a Onda 3 troca **o miolo** dos dois arquivos antigos mantendo a API:

| Arquivo | Ação |
| --- | --- |
| `mobile/components/erp-ui.tsx` | **Substituir.** Mesmos exports e props; desenha com OiFormInput, OiSelectField, OiSheet, OiBtn, OiKpi, OiStatus. Sem emoji, sem hex. |
| `mobile/components/screen-container.tsx` | **Substituir.** Delega ao OiScreen. |
| `mobile/lib/status-tones.ts` | **Criar.** Orçamento, fiscal, pagamento, movimento de estoque e nível de estoque → OiStatus. |
| `PATCHES.md` | **Aplicar.** Trechos exatos (linha + conteúdo do main) de hex e emoji dentro das telas. |

Telas que mudam de cara só com o adaptador: clientes, dashboard, estoque, estoque/[id], fiscal, orçamentos, pagamentos, produção, produtos, relatórios, vendas, empresas, chat.

## Apagar erp-ui de vez

O adaptador é ponte, não destino. Quando cada tela trocar `from "@/components/erp-ui"` por `@/components/oi` (+ `useNotify` no lugar de `ui.toasts`/`Toast`), `grep -rn "erp-ui" mobile/` zera e o arquivo sai. Mesmo para `screen-container`.

## Risco

Baixo: nenhuma prop pública muda. O `Select` deixa de abrir lista inline e passa a abrir sheet — comportamento novo, mesmo contrato (`onValueChange`).
