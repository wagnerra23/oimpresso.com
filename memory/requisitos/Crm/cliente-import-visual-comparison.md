# Visual Comparison — Cliente/Import (W1-B3)

## Divergência (ADR 0149 §"Casos que NÃO se qualificam")
**Wizard upload XLSX** — layout divergente do Index lista. Aprovado utility/admin page.

## Justificativa
- Wizard 2-step (download template + upload dropzone) — fluxo distinto de list-detail
- Foco em ação única (upload arquivo) vs navegação multi-row
- Progress bar dinâmica (UX moderno) — não cabe em padrão lista

## Layout
- Header 3xl + breadcrumb voltar
- Card download template (passo 1)
- Card upload form (passo 2)
- Notification banner pós-import (success/erro)

## Acessibilidade
- File input nativo escondido + button trigger
- Dropzone click handler + label visual
- Progress bar com aria-live polite

## PII
Não logar conteúdo do XLSX. Erros server-side limitados a "row N: campo X inválido" sem PII.

## Gate F1.5
✅ Divergência aprovada via ADR 0149 (utility page genérica)
⏳ Pendente: screenshot Wagner pós-canary
