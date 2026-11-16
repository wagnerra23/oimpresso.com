# Conteúdo de /direitos (uniafibro.com.br)

Cada arquivo `<slug>.md` desta pasta vira a página `https://uniafibro.com.br/direitos/<slug>`
e o PDF `https://uniafibro.com.br/direitos/<slug>.pdf`. Arquivo começado por `_` (como este)
nunca vira página. O slug aceita só letras minúsculas, números e hífen.

Frontmatter obrigatório:

```yaml
---
title: "Lei federal"
resumo: "Uma frase que aparece no cartão do índice."
secao: direitos            # direitos | guias
ordem: 1                   # posição dentro da seção
updated_at: "2026-11-15"   # data da última revisão do texto (sempre entre aspas)
disclaimer_version: 2      # versão do aviso legal (App\Support\Uniafibro\Layout::AVISO_VERSAO)
fontes:
  - titulo: "Lei 15.176/2025 (Planalto)"
    url: "https://www.planalto.gov.br/..."
---
```

Regras do texto: cada afirmação de direito traz o link da fonte oficial no próprio parágrafo;
nada de jurisprudência sem passar pelo [W]; nada de prometer benefício automático. Seções com
`## ` viram o índice lateral. HTML cru é removido na renderização: só Markdown.

Ver memory/requisitos/Site/RUNBOOK-uniafibro.md §6.
