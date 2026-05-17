---
review_round: W31-R1
tela: subcomponent /nfe-brasil/manifestacao
component: resources/js/Pages/NfeBrasil/Manifestacao/_components/LinkedItens.tsx
charter: N/A (subcomponent)
reviewer: claude (W31 bulk static)
review_date: 2026-05-17
modulo: NfeBrasil
status: placeholder (US-NFE-053+ pendente)
loc: 69
---

# Review estático — Manifestacao/_components/LinkedItens

## Pontos fortes
- Comentário honesto: "Carrega itens via fetch — endpoint a ser exposto em US-NFE-053+ smoke real (placeholder hoje)"
- `max-h-64 overflow-y-auto` correto pra lista longa
- NCM + CFOP + Qtd × valor_unitario = valor_total mostrados
- `truncate` em descrição
- Empty state mensagem técnica: "Sem itens parseados (XML resNFe não inclui detalhe)" — correto pq DFe Distribuição traz só resumo NF-e

## Riscos / gaps
1. **fetch sem AbortController** (mesmo race condition do LinkedHistorico). P1
2. Endpoint `/nfe-brasil/manifestacao/${dfeId}/itens` **não existe ainda** (US-NFE-053 pendente) — todo render bate placeholder vazio "Sem itens parseados". P1 BUG (mascara como feature)
3. `r.ok ? r.json() : { itens: [] }` swallow — sem distinção entre "endpoint não existe" vs "NF-e não tem itens parsed". P2
4. Sem CTA "Solicitar XML completo via SEFAZ" — pra DFe que só veio o resumo, link pro `NFeDistribuicaoDFe` específico. P2 feature
5. NCM `null` mostra `—` mas sem hint que valor null = não parseado. P3

## Multi-tenant
- DfeId scoped backend. Cross-tenant 404 esperado.

## Recomendação
1. Bloquear render do componente se US-NFE-053 não está deployed (feature flag), OU exibir banner "Detalhes de itens disponíveis em fase futura" (P1 honestidade UX)
2. AbortController (P1)
3. Distinguir 404 (endpoint missing) vs 200 com itens=[] (P2)
