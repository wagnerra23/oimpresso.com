---
sessao: "05"
titulo: Smoke/canary da grade tam×cor (US-COM-005) — gate humano
dono: "[W]" / "[W2]"
base: 9101f86af501
prefixo: — (nenhum arquivo destrava este gate)
nao_toca: resources/js/Pages/Purchase/_components/GradeMatrixInput.tsx
depende: D-GRADE
---
# 05 · Grade tam×cor — GATE, não código

## Estado medido hoje
- `resources/js/Pages/Purchase/_components/GradeMatrixInput.tsx` — **14.229 B, existe**.
- `GradeProductCombobox.tsx` — 7.611 B, existe.
- `resources/js/Pages/Purchase/Create.tsx` — **importa** a grade em `:26` e a usa em `:459`. Não é mais "declarado pelo charter": está no `.tsx`.
- `Create.charter.md` — `status_note: "F3 implementado + modo grade tam×cor (US-COM-005, aguarda smoke/canary Wagner)"`.
- `Create.casos.md` — 22.643 B (existe; o doc de 04/09 pedia criar).

**O único 🟠 que o FRESCOR apontava no módulo está implementado.** O que falta não é arquivo: é você olhar a tela rodando.

## O que o smoke tem de cobrir (invariantes do domínio)
1. 1 célula = 1 `variation_id`; o envio é **1 POST único** de N `purchase_lines` (não N requests).
2. Produto sem variação composta → grade de **1 eixo** por auto-detect no backend — **nunca grade vazia silenciosa**.
3. Estoque entra **só após `received`** (R-PUR-004): preencher a grade e salvar não move estoque.
4. `permitted_locations` filtra as filiais visíveis (R-PUR-002).
5. Sem `purchase.create` → 403 (R-PUR-003).
6. Canary declarado: **biz=4 (Larissa)**, 1280px, tema dark.

## O que NÃO fazer
- Não "reimplementar pra garantir": o código existe e tem 10 Pest no módulo.
- Não pedir ao Code que aprove: aprovação de screenshot é [W2], por definição.
- Não fechar o módulo como paridade antes do **T7** (`design-diff --compare --check` nos dois renders, prod deployada). Nada aqui autoriza dizer "0 bug".

## Prova
`_saida-05.md` escrito por [W]/[W2] com o veredito do canary (aprovado / reprovado + o que falhou). Enquanto `D-GRADE.respondida = false`, o placar mostra `bloqueada` — e isso **não** é pendência do Code.
