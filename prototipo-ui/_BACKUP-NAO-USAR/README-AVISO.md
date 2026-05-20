# ⛔ _BACKUP-NAO-USAR — Quarentena de protótipos legados

> **AVISO PARA TODA SESSÃO CLAUDE (e agentes/devs):** os arquivos sob `_BACKUP-NAO-USAR/` **NÃO** devem ser tratados como referência canônica de design. São SNAPSHOTS HISTÓRICOS preservados só pra rastreabilidade.

## Por que existe

Wagner ordem 2026-05-20: múltiplos snapshots/tentativas de protótipo estavam confundindo sessões Claude — agentes usavam versões antigas como gold standard e geravam código divergente da realidade. Tudo foi movido pra cá pra eliminar interpretações erradas.

## ❌ NÃO FAZER

- ❌ NÃO usar arquivos daqui como referência pra implementar feature
- ❌ NÃO citar como "design canon" em PRs, ADRs ou docs
- ❌ NÃO restaurar pra raiz `prototipo-ui/` sem ordem explícita do Wagner
- ❌ NÃO comparar layouts atuais com estes (vai dar diff falso)

## ✅ Fonte canônica REAL (use estes)

| Domínio | Path canônico atual |
|---|---|
| **Financeiro + shell ERP** | `public/cowork-preview/erp-shell/Oimpresso ERP - Chat.html` |
| **Jana + Cobrança Recorrente + Fiscal** | `public/cowork-preview/erp-shell-v2/Oimpresso ERP - Chat.html` |
| **Runtime real em prod** | `resources/js/Pages/*` + `resources/css/cowork-canon-financeiro-bundle.css` |
| **Docs canon** | `prototipo-ui/*.md` (PROTOCOL, README, COWORK_NOTES, etc — fora desta pasta) |

## Estrutura

```
_BACKUP-NAO-USAR/
├── README-AVISO.md      ← este arquivo
├── jsx-individuais/     ← 44 jsx + mockup-bodies.js (vendas-*, kb-*, inbox-*, etc)
├── css-individuais/     ← 12 css (styles, vendas, kb-page, inbox-page, etc)
├── html-individuais/    ← 2 html (Diagnóstico Vendas KB-9.75 + Método KB-9.75)
├── png-audits/          ← 5 png (audit-boletos/compras/financeiro/oficina/os)
├── tarballs-originais/  ← 2 tar.gz (origens dos erp-shell e erp-shell-v2)
├── snapshots-cowork/    ← _cowork-export-2026-05-15 + 2026-05-19-handoff + _incoming-*
├── prototipos-individuais/  ← 24 sub-prototypes em prototipos/
├── sells-snapshots/     ← sells/ + sells-responsive-preview/
└── templates-antigos/   ← templates/
```

## Política de exclusão

Quando confirmar que NADA daqui é necessário (após próximo trimestre / após template canon estabilizar):
- `git rm -r prototipo-ui/_BACKUP-NAO-USAR/`
- `git rm -r memory/requisitos/_DesignSystem/ui_kits/_BACKUP-NAO-USAR-*`

Mas só com aprovação Wagner explícita. Por enquanto: backup → histórico → não-usar.

## Quem move pra cá

Apenas Wagner pode decidir mover novos artefatos pra `_BACKUP-NAO-USAR/`. Agente Claude **não move por conta própria** — propõe lista e aguarda OK.

---

**Última atualização:** 2026-05-20 — quarentena inicial (Fase 2 protocolo aplicação template canon)
