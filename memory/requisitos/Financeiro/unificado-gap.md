---
tela: Financeiro/Unificado (/financeiro/unificado)
prototipo: prototipo-ui/cowork/financeiro-page.jsx + financeiro-ops.jsx (NOVO) @ 4e3aacfc0f (import 2026-07-01; delta vs 09a0f9f7ef = 913 inserções)
tela_viva: resources/js/Pages/Financeiro/Unificado/Index.tsx (2784 ln) + _components/ (25 arquivos)
paridade_atual: ~96%
gerado_em: 2026-07-01
governanca: charter v16 (Index.charter.md) · US-FIN-024 backlog aberto · casos-gate (ADR 0264) · LICOES_F3_FINANCEIRO_REJEITADO lido · fidelidade [W] 2026-06-29 (#3391/#3413/#3415/#3417/#3418/#3419) já aplicada
---

# GAP-SPEC — Financeiro/Unificado vs protótipo re-importado 2026-07-01

> **Leitura-chave do delta:** o `financeiro-ops.jsx` (453 ln, NOVO) declara no cabeçalho
> *"paridade com produção /financeiro/unificado v16 — Espelha: FinBaixaSheet · FinAnexosPanel ·
> workflow aprovação · FinOcrBoletoSheet · ClienteCombobox"*. Ou seja: **a maior parte do delta é
> o PROTÓTIPO alcançando a tela viva**, não o contrário. O 4º veredito (no-op — protótipo nunca
> regride a tela) domina 9 de 12 partes. Restam 3 gaps genuínos, todos pequenos e frontend-only.

## Tabela Parte × mudou × porquê × esforço × risco × ação

| # | Parte | O que mudou no protótipo (âncora) | Estado no vivo | Esforço | Risco | Ação |
|---|---|---|---|---|---|---|
| 1 | Header / ações | "Novo lançamento"→"Novo título" (page.jsx:227, 1274) + item "Ler boleto (OCR)" no overflow (page.jsx:230) | Primary já é "Novo título" (Index:1449); OCR já vive no dropdown do primary (Index:1460-62), layout ADR 0313 aprovado [W]. **Resíduo:** CmdK ainda diz "Novo lançamento" e navega pro stub `/unificado/novo` (Index:2541); empty-state CTA idem (Index:1781) — canon é abrir `TituloCreateSheet` | P | só visual | **aplicar delta (resíduo):** CmdK vira 2 itens "Novo recebimento/Novo pagamento" → `setCreateTipo` (Opção A [W] preservada: tipo escolhido ANTES do form); empty-state CTA idem. Resto: **no-op** |
| 2 | KPIs | só reflow de comentários (page.jsx:406-421) | Vivo à frente: sparkline 30d real, delta_pct US-FIN-023, hints ricos, alarme projeção negativa (Index:829-956) | — | — | **no-op (4º veredito — vivo à frente)** |
| 3 | PeriodBar / Personalizado | modo `custom` com 2 date-inputs (page.jsx:50, 676-710, 1770) | JÁ ENTREGUE — `FinPeriodBar.tsx` (#3418, [W] 2026-06-29): presets + Personalizado + botão limpar + detecção de preset pela URL (mais que o protótipo) | — | — | **no-op (4º veredito — vivo à frente; convergência mútua)** |
| 4 | Filter-pills / toolbar | ContasFilter multi-conta (page.jsx:555-586) + select árvore plano de contas (page.jsx:640-656) + remove density "spacious" | Tudo já existe: `FinMultiSelectContas` (Index:365-449, 1652), Select plano DCASP real por `nivel` (Index:1658-74), spacious removido Onda 12.6. Vivo tem MAIS (arquivados, chips aprovação, data_campo) | — | — | **no-op (4º veredito — vivo à frente)** |
| 5 | Tabela / linhas | colunas Forma/Conta/Baixa (page.jsx:798-815, 880-882) + StatusBadge com fio color-mix 22% (page.jsx:108-130) + FlowIcon fio+sombra (page.jsx:723-742) | Colunas existem desde 2026-06-03/04 (Index:1109-35, 1731-33). StatusPill com fio+dot e DirIcon com color-mix 22%/28% = refino premium #3391/#3413 [W] 2026-06-29 — o protótipo COPIOU o vivo | — | — | **no-op (4º veredito — protótipo espelhou o vivo)** |
| 6 | Diálogo de baixa | `FinBaixaSheet` mock (ops.jsx:64-158) + `openBaixa`/`handleBaixa` (page.jsx:1794-1920, 1992) | JÁ ENTREGUE com backend real (charter v12, `UnificadoBaixaDialogGuardTest` 5 GUARDs): valor parcial + conta + forma + plano + data (`FinBaixaSheet.tsx:1-233`). **Micro-delta:** protótipo pré-seleciona a forma pelo `channel`/kind do título (ops.jsx:75); vivo abre "— escolha —" | P | só visual | **aplicar delta (micro):** default `meio_pagamento` = forma prevista do título (`forma_pagamento` não-realizada). Resto: **no-op** |
| 7 | Anexos no drawer | drop-zone drag&drop + `multiple` + empty-state instrutivo + seeds "do sistema" (NFe XML/DANFE/comprovante) (ops.jsx:165-235, page.jsx:1600) | Vivo tem backend REAL (GET/POST/download/DELETE, SHA-256 dedup, 10MB — `FinAnexosPanel.tsx:59-181`) mas upload é 1 arquivo por clique, **sem drag&drop, sem multiple** | M | só visual (drag&drop) / **backend** (seeds) | **aplicar delta:** drop-zone + multiple no `FinAnexosPanel.tsx` (rota POST atual aceita 1 por request — iterar client-side). Seeds "do sistema" (auto-anexar XML/DANFE da NFe vinculada + comprovante na baixa) = **virar US-ADR** (toca NfeBrasil/storage, risco backend) |
| 8 | Aprovação de pagamento | painel `fin-lens` com **motivo de rejeição inline** + **desfazer** (aprovado→none) + **reenviar** (rejeitado→pendente) (ops.jsx:241-308, page.jsx:1599) | Vivo tem endpoints reais + gate Spatie `financeiro.titulo.aprovar` (Index:2353-2415), mas motivo via `window.prompt` (Index:2388) e estados aprovado/rejeitado são terminais | P (motivo) / M (transições) | só visual (motivo) / **backend** (desfazer+reenviar: transições de estado novas + audit) | **aplicar delta:** motivo inline (input + confirmar, endpoint já aceita `motivo`). Desfazer/reenviar = **virar US-ADR** (workflow novo, append-only audit). Harmonizar como `DrawerLens` = opcional P |
| 9 | OCR boleto | sheet mock: colar linha digitável + parse Febraban client-side + "simular leitura" (ops.jsx:314-394, page.jsx:1993) | Vivo à frente: OCR REAL OpenAI Vision (foto/PDF → linha+valor+venc+beneficiário CNPJ, campos editáveis — `FinOcrBoletoSheet.tsx:1-312`). Protótipo tem fallback manual (digitar/colar a linha sem foto) que o vivo não tem | P/M | só visual | **PERGUNTA [W]:** vale adicionar fallback "colar linha digitável" (parse Febraban local, sem custo de IA) no sheet OCR do vivo? Se não, **no-op (vivo à frente)** |
| 10 | Combobox contraparte no "Novo título" | `FinClienteCombobox` ligado no campo Cliente/Fornecedor do form (ops.jsx:400-451, page.jsx:1285-91) | **GAP REAL Nº 1.** `ClienteCombobox.tsx` EXISTE no vivo (PR J, US-FIN-024, busca server-side `/buscar-cliente` debounce 300ms, WAI-ARIA) mas está **ÓRFÃO** — `TituloCreateSheet.tsx:121-145` usa `<Input>` cru | P-M | só visual (endpoint já existe e é scoped) | **aplicar delta:** wire `ClienteCombobox` no campo `cliente_descricao` do `TituloCreateSheet` (preservando o fetch sugerir-valor no onBlur). Fecha **US-FIN-024** do backlog do charter. Avaliar `TituloEditSheet` no mesmo PR. **Governança:** bump charter v17 (remover Non-Goal "Combobox cliente", marcar US-FIN-024 done) + UC novo no `Index.casos.md` |
| 11 | Drawer detalhe (estrutura) | hooks `useFinAnexos`/`useFinAprovacao` + painéis como lens (page.jsx:1351-55, 1599-1600) + `contaOf(row).detail` no KV Conta | Vivo já renderiza Aprovação+Anexos no drawer com dado real; Conta vem do shape real; drawer 3 camadas F2 + Tribunal Onda 2 (v15/v16) vão ALÉM do protótipo (veredito, vs média, lente fiscal, FSM 1-linha) | — | — | **no-op (4º veredito — vivo à frente)** |
| 12 | Artefatos do protótipo | `data-comment-anchor` (page.jsx:612, 677, 1412, 1548) · `agingCounts` vazio (page.jsx:1836) · `contaOf`/`formaOf` determinísticos por charCode (page.jsx:537-545) | Mock/instrumentação do Cowork; aging foi REVERTIDO pelo [W] 2026-06-29 ("isso eu não quero") | — | — | **rejeitar (artefato/mock — nunca portar).** `agingCounts` reintroduzido regride decisão [W] |

## Ordem de aplicação (por valor ÷ esforço)

1. **P10 — ClienteCombobox no TituloCreateSheet** (fecha US-FIN-024; componente pronto, só ligar) — P-M, só visual.
2. **P8a — Motivo de rejeição inline** (mata o `window.prompt`, único ponto "feio" do drawer) — P, só visual.
3. **P7a — Drag&drop + multi-upload no FinAnexosPanel** — M, só visual.
4. **P1 — CmdK/empty-state → TituloCreateSheet** ("Novo título" em todo lugar; mata o resíduo do stub `/unificado/novo`) — P, só visual.
5. **P6 — Pré-selecionar forma prevista na FinBaixaSheet** — P, só visual.
6. **P8c (opcional) — Aprovação/Anexos com visual DrawerLens** — P, só visual.
- **US-ADR (não aplicar direto):** anexos "do sistema" auto (P7b, backend NfeBrasil) · desfazer/reenviar aprovação (P8b, backend workflow+audit).
- **PERGUNTA [W]:** P9 fallback linha digitável manual no OCR.

## Veredito

**PERTO — e na maioria das partes o VIVO está À FRENTE do protótipo.** O delta 2026-07-01 é
majoritariamente o Cowork espelhando a produção v16 (declarado no próprio ops.jsx). 4º veredito
(no-op/protótipo-não-regride) aplicado nas partes 2, 3, 4, 5, 11 e parcialmente 1, 6, 9.
Nenhuma parte é "longe" nem "greenfield". Zero mudança multi-tenant/Tier 0 proposta; os 2 itens
com cheiro de backend viram US-ADR, não Edit. Nenhum CSS do bundle precisa ser copiado — os
equivalentes vivos já existem no idioma DS (shadcn/Tailwind/tokens), e `feedback-cowork-bundle-
aplicar-inteiro` só rege PRIMEIRA aplicação de módulo (não é o caso).
