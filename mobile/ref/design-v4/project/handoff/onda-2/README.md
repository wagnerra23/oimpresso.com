# Onda 2 — Oficina (OS lista, OS detalhe, Veículos)

Depende da **Onda 1** (oi-theme.ts com `touch`, os-status.ts). Proposta para PR — revisão Wagner + Luiz.

> A pasta `-tabs-` corresponde a `mobile/app/(tabs)/` (parênteses não são permitidos no pacote).

## Arquivos

| Arquivo | Ação |
| --- | --- |
| `mobile/lib/notify.ts` | **Criar.** `useNotify()` — mesma assinatura do `addToast` antigo, usa o toast global Oi. Usado também na Onda 3. |
| `mobile/components/oi/OiForm.tsx` | **Criar.** `OiFormInput`, `OiSelectField` (picker em sheet), `OiFormFooter`. Substituem FormInput/Select/ModalDialog do erp-ui. |
| `mobile/components/oi/index.ts.patch` | **Aplicar.** Exporta o OiForm no barrel. |
| `mobile/components/oi/OiStatus.tsx` | **Substituir.** + variante `info`; fundo 10% + borda 22% (antes 18–22% sem borda). |
| `mobile/components/oi/OiBtn.tsx.patch` | **Aplicar.** `sm` 32 → 36 px + hitSlop 4 (alvo 44). |
| `-tabs-/oss.tsx` | **Substituir.** Status pelo mapa único, placa com OiPlaca, chips com contagem, estados vazio/erro, sem emoji. |
| `oss/[id].tsx` | **Substituir.** Sai ScreenContainer/erp-ui; OiDetailHeader com status, card de IA no DS, rodapé fixo Voltar/Salvar. Lógica e chamadas idênticas. |
| `-tabs-/veiculos.tsx` | **Substituir.** Linha com OiPlaca, histórico expansível inline (antes modal), formulário em OiSheet, remover dentro do editar (antes "×" na linha). |

## O que não mudou

Hooks de `erp-queries`, payloads, validações e rotas — idênticos ao `main`. Só UI.

## Conferir

- `grep -n "erp-ui\|screen-container\|🔗\|🧠" mobile/app/oss mobile/app/\(tabs\)/oss.tsx mobile/app/\(tabs\)/veiculos.tsx` → vazio.
- OS: filtrar por etapa, avançar status, copiar link, sugerir IA, salvar.
- Veículos: buscar, expandir histórico, editar, remover.
