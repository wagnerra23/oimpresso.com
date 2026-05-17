---
review_round: W31-R1
tela: subcomponent /nfe-brasil/manifestacao
component: resources/js/Pages/NfeBrasil/Manifestacao/_components/LinkedFornecedor.tsx
charter: N/A (subcomponent — charter cobre página pai)
reviewer: claude (W31 bulk static)
review_date: 2026-05-17
modulo: NfeBrasil
status: live
loc: 35
---

# Review estático — Manifestacao/_components/LinkedFornecedor

## Pontos fortes
- Componente puro stateless — recebe `dfe` props mínimas (cnpj/nome/valor_total)
- Formatação `formatCnpj` + `toLocaleString('pt-BR', currency)` consistente com Index pai
- Card semântico canon (border + bg-card + p-4)
- Lucide `Building2` ícone semântico
- Tipografia ADR 0110 respeitada (text-sm + font-mono pra CNPJ)

## Riscos / gaps
1. `formatCnpj` duplicado entre `Index.tsx`, `LinkedFornecedor.tsx`, `LinkedItens.tsx`... — extrair pra `@/lib/format-cnpj.ts`. P3 DRY
2. `formatBrl` também duplicado — mesmo problema. P3
3. Sem CTA "Abrir cadastro fornecedor" se `dfe.cnpj_emitente` matchar contact local — feature gap, painel fica passivo. P2
4. Sem aviso quando `nome_emitente === null` (fornecedor não cadastrado/desconhecido) além de `—`. P3
5. Sem link pra histórico de NF-e do mesmo fornecedor. P3 (feature)

## Multi-tenant
- Props vêm do pai (já scopado). Nada cross-tenant aqui.

## Recomendação
1. Extrair helpers `formatCnpj`/`formatBrl` pra `@/lib/format-br.ts` (P3 dev-ex)
2. Adicionar match contact local com link Open cadastro (P2 feature)
