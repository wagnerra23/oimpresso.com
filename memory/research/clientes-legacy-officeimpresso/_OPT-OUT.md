---
title: Opt-out — clientes que pediram não-análise
status: live
date: 2026-05-11
audience: time interno + IA-pair
purpose: registrar clientes que exerceram direito de oposição (Art. 18 LGPD)
---

# Opt-out

> Lista de clientes legacy OfficeImpresso que pediram **não ser analisados/abordados** pra migração. Honrar **sem exceção** — sem nova análise, sem nova abordagem comercial, sem inclusão em relatórios cross-cliente.

## Como entrar

Cliente comunica (verbal/escrito/email) que não quer ser objeto de análise. Wagner ou Eliana registra aqui com:
- Data
- Canal (telefone/email/WhatsApp/visita)
- Hash anonimizado do cliente (Razão social não)
- Razão (opcional, só se cliente concordou em compartilhar)

## Lista atual

(vazia em 2026-05-11)

| Data | Hash | Canal | Razão (opcional) |
|------|------|-------|------------------|
| — | — | — | — |

## Quando cliente entra em opt-out

1. Deletar `memory/research/clientes-legacy-officeimpresso/NN-slug-cliente/` (pasta inteira)
2. Deletar relatórios em `memory/research/2026-05-sells-grade-heatmap/NN-slug-*` se existirem
3. Deletar `raw-NN-slug.json` se existir local
4. Adicionar linha aqui com hash + data
5. Notificar via git commit message `chore(lgpd): cliente XXX em opt-out`

## Revogação

Cliente pode revogar opt-out (e voltar a permitir análise). Registrar nova linha com `Opt-out revogado em DATA — cliente reativado`.
