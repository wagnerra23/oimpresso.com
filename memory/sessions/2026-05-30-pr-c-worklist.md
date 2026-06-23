# Sessão 2026-05-30 — PR-C WORKLIST (fila 1-por-1 + emenda FieldError Tipo 1)

**Pedido [W]:** "Faz o PR-C2: Sells Create+Edit (select→Select, rounded-xl→lg/Card, FieldError Tipo 1). Branch isolada, PARA no gate visual. Na verdade: quero fazer TUDO — gerar um workflow pro Code resolver 1 por 1, sai mais barato. Faz a lista e gera os arquivos."

## O que foi feito (eu, [CC])
- Auditei o terreno: 8 prompts PR-C já existiam (`PR-C1`..`PR-C7` + `SWEEP`) + `MATRIZ_MIGRACAO_DS.md` + `PARALELO-como-rodar.md`.
- Detectei **conflito de numeração:** MATRIZ chama Sells de "PR-C2"; os arquivos de prompt numeram por onda (Sells = C5). → Decidi: **nome do módulo = canônico; "C#" deprecado.**
- Detectei o **gap real do pedido:** todos os PR-C diziam "NÃO tocar status-text" → o **FieldError Tipo 1 nunca entrou**. Só o Tipo 2 (badge) é que espera Onda G.
- Gerei **`prototipo-ui-patch/PROMPT_PARA_CODE_PR-C-WORKLIST.md`** — fila única auto-contida: transform + Emenda FieldError T1 (regra Tipo1/Tipo2) + tabela de 10 itens (Sells→…→Financeiro/Cliente só-T1) + receita-por-módulo (cola, troca `<MOD>`) + gates (PARA no visual, merge em série).
- Inseri banner "SUPERSEDED → WORKLIST + Emenda" no topo dos 8 prompts PR-C antigos.
- Gerei URL pública + 1 prompt zero-toque pro Code.

## Decisões
- **D:** módulo é o identificador canônico da fila; números "C#" deprecados (dois docs discordavam).
- **D:** FieldError **Tipo 1** (`<p text-rose>{error}>` em form) entra em CADA PR de módulo agora; só **Tipo 2** (STATUS_STYLE/badge) fica pra Onda G — lote-badge.
- **D:** Sells lidera a fila (prioridade [W] + P0 + mais pesado, vai sozinho).

## Erros + correção
- Nenhum erro novo. Mantive o limite: **não commitei nada** — produzi ponte (arquivo no Cowork + URL + prompt). Code transporta.

## Residual
- Itens 6–8 (Admin/Whatsapp/Settings) sem prompt dedicado — Code descobre via eslint (pode virar SWEEP).
- Item 10 (Cliente só-T1): confirmar se PR-A já migrou o erro do `ClienteForm`.
- Pós-fila: Onda G (lote-badge, Tipo 2).

## Refs
- `prototipo-ui-patch/PROMPT_PARA_CODE_PR-C-WORKLIST.md` (entrega principal)
- `MATRIZ_MIGRACAO_DS.md §Nuance` (Tipo1/Tipo2) · `REGISTRY_DS_COMPONENTES.md` (FieldState) · `PARALELO-como-rodar.md` (merge em série)

## Adendo — Roadmap completo até `ds/*=0` ([W] pediu "fazer tudo, escolha a melhor forma")
Gerei o caminho inteiro, não só controles. Entrada: `PROMPT_PARA_CODE_DS-ROADMAP-ATE-ZERO.md`. Fases por dependência:
- **A** controles + FieldError T1 — `PR-C-WORKLIST.md` (já existia)
- **B** `arbitrary-color` (hex cru → token) — `SWEEP-arbitrary-color.md` (novo)
- **C** Onda G badge +5 variants — `ONDA_G_BADGE_VARIANTS.md` (já existia; é pré-req da D)
- **D** lote-badge 410 Tipo 2 → `<Badge variant>` — `LOTE-BADGE.md` (novo)
- **E** FormSection — `SWEEP-formsection.md` (novo)
- **F** icons lockdown — inline no roadmap
Pré-req geral: componentes Onda F (Segmented/FormSection/InputGroup/FieldState) do PR-A existirem. 6 URLs públicas geradas (valem ~1h). 1 prompt zero-toque entregue.

## Próximo passo
[W] cola o prompt zero-toque no Code → Code roda Fase A (Sells) + B em paralelo, C cedo (component), depois D, E por módulo, F por último. 1 PR por vez, merge em série, PARANDO no gate visual de cada.
