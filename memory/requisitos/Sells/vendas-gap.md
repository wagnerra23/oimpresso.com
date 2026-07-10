---
tela: Sells/Index
prototipo: prototipo-ui/cowork/vendas-page.jsx
tela_viva: resources/js/Pages/Sells/Index.tsx
paridade_atual: ~70%
gerado_em: 2026-06-22
prototipo_sha: 9f0cc02f36
governanca: [nao-silenciado, nao-contract-locked, sem-tier0-direto]
---

# GAP — Vendas (Sells)

> Gerado pelo fluxo `aplicar-prototipo` (Fase 1). Lista/Index ~80%, drawer ~55-60%, ponderado ~70%.

| Parte | O que falta vs protótipo | Por quê | Esforço | Risco | Ação |
|---|---|---|---|---|---|
| Menu Visões | dropdown não linkava a Caixa do dia (`/vendas/caixa` existe e estava órfã de navegação) | tela criada (Onda 6) sem entrada no menu | P | só visual | ✅ **APLICADO 2026-06-22** (link adicionado) |
| Drawer fiscal | `FiscalSection` é linha plana; protótipo tem **cards NF-e/NFS-e** com timeline 5 passos + chave SEFAZ copiável + CC-e + XML | é o núcleo do charter (UC-V05, "não esconder o split fiscal") | G | só visual | próxima fatia (maior valor) |
| Foco=Comissão | só 1 card "top vendedor"; protótipo tem **ranking completo** (avatar+barra meta) | — | M | backend (agregado/vendedor) | onda backend |
| Saved tree | não subdivide "Pendentes" por vendedor | — | P | só visual | fatia rápida |
| Painel IA | falta render do **card de produto sugerido** | — | P | só visual | fatia rápida |
| Devoluções/Relatórios | sub-views previstas, sem tela/rota | — | G | backend+tela | backlog (ADR 0105 sinal) |

**Ordem:** 1) ✅ link Caixa · 2) split fiscal (drawer) · 3) pendentes-por-vendedor + card IA · 4) ranking (backend) · 5) Devoluções/Relatórios (só com sinal).
**Veredito:** PERTO — backfill incremental, sem reescrita. Fechando 2+3 sobe a ~90% sem tocar dado/Tier 0.
