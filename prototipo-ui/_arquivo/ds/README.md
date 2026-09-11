# `prototipo-ui/_arquivo/ds/` — Design Systems aposentados (append-only)

> **Regra:** [ADR 0239](../../../memory/decisions/0239-governanca-design-system-git-ssot-regressao-ia.md) **R4** — só o **DS vigente** fica na raiz do `prototipo-ui/`; os passados moram aqui.

## Fonte da verdade — MUDOU em 2026-09-11

O DS canônico **não vive mais em arquivo solto na raiz**. Ele é o espelho de UM projeto Cowork:

| O quê | Onde | Dono remoto |
|---|---|---|
| **DS canônico (tokens + componentes)** | `prototipo-ui/design-system/` | projeto Cowork `019dd02f` — *"Office Impresso — Design System"* |
| DS que a **LEI** enforce em produção | `resources/css/` | `foundation-guard.mjs` + `conformance-gate.mjs` (nenhum dos dois lê `prototipo-ui/`) |
| Cópia do DS dentro do espelho de telas | `prototipo-ui/cowork/_ds/…` | **cache derivado do preview** — não é fonte |

## Lápide — faxina de 2026-09-11 ([W]: *"pode apagar, já autorizo"*)

Havia **três** DS competindo na árvore. O terceiro (cópias soltas na raiz) estava **congelado desde
2026-06-08** — mesmo commit pra todos, `8cd20a3486`, enquanto o espelho se movia em 2026-09-09 e o
runtime em 2026-09-11. O próprio repo já tinha registrado o veredito: o
`COMPARISON-bundle-full-2026-06-26.md` (arquivado aqui ao lado) diz que o projeto DS é a
*"evolução componentizada/superset"* do kit `ds-v6/`.

**Removidos da árvore ativa** (conteúdo preservado — recuperável em `git show 844ea9b838:<path>`):

| Removido | Por quê | Onde o conteúdo sobrevive |
|---|---|---|
| `Design System v4.html` | duplicata **byte-idêntica** (blob `6e97ef1e70`) | `design-system/prototipo-ui/Design System v4.html` |
| `tokens.css` · `design-system.css` | 89 tokens, dos quais só 26 eram consumidos — e **26/26** já existem no espelho | `design-system/**` (304 tokens) |
| `ds-v6/showcase.html` · `receita.html` · `gabarito-vendas.html` | superseded pelo projeto DS (auditoria do próprio repo) | `design-system/components/**` · `cowork/ds-v6/` |
| 4× `mockup-*.html` (cliente-fields ×3, drawer-tabs) | **zero** consumidores; violavam L-21/L-23 (nunca `.html` na raiz) | git |

**Arquivados aqui** (carregam conhecimento, não bytes mortos): `ds-v6-README.md`,
`ds-v6-REUSE_MAPPING.md`, `ds-v6-COMPARISON-bundle-full-2026-06-26.md`.

⚠️ **Ressalva honesta:** o cabeçalho antigo deste arquivo dizia *"movido, nunca apagado"*. Os 10
arquivos acima foram **apagados**, não movidos — por autorização explícita de [W] nesta data. O que
a regra protege (conhecimento não se perde) fica preservado por esta lápide + git; o que se foi
foram bytes cuja íntegra sobrevive em outro dono, medido caso a caso na tabela acima.

## Arquivados (versões antigas — referência histórica)

| Arquivo | Aposentado em | Era |
|---|---|---|
| `Design System v3.html` | 2026-05-30 | spec visual v3 (superseded por v4 · ADR 0235 roxo) |
| `ds-v6-*.md` (3 docs) | 2026-09-11 | kit DS v6 (superseded pelo projeto `019dd02f`) |

> Histórico mais antigo (v1/v2) vive no export do Cowork (`_arquivo/ds-historico/`).
