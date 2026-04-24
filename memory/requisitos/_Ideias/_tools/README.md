---
type: meta
description: Ferramentas de manutenção do incubador `_Ideias/`
last_updated: 2026-04-24
---

# _tools — utilitários do incubador

## `scrape_claude_conversation.py`

Extrai conversa de claude.ai (mobile ou desktop) e salva como markdown
de evidência em `_Ideias/{Modulo}/evidencias/`. Reusa sessão autenticada
via profile Playwright dedicado, então **só precisa logar uma vez**.

### Setup (uma vez)

```bash
pip install playwright
playwright install chromium
```

### Uso single

```bash
python scrape_claude_conversation.py \
    https://claude.ai/chat/<UUID> \
    D:/oimpresso.com/memory/requisitos/_Ideias/<Modulo>/evidencias/conversa-claude-2026-04-mobile.md
```

Na primeira execução abre janela do Chromium → faça login em claude.ai → pressione Enter no console pra prosseguir. Próximas execuções já entram autenticadas.

### Uso batch

```bash
python scrape_claude_conversation.py --batch conversas-pendentes.tsv
```

O arquivo `conversas-pendentes.tsv` tem uma conversa por linha no formato:

```
<url>\t<output_path>
```

Linhas em branco e iniciadas com `#` são ignoradas.

### Frontmatter gerado

```yaml
---
type: evidencia
origin_url: https://claude.ai/chat/<UUID>
origin_title: "<título da conversa>"
extracted_at: 2026-04-24
extraction_method: playwright + chromium profile autenticado
---
```

Padrão idêntico ao usado nas evidências existentes
(`NfeBrasil/evidencias/conversa-claude-2026-04-mobile.md`,
`Financeiro/evidencias/conversa-claude-2026-04-mobile.md`).

### Por que Python + Playwright (e não Claude)

- **Custo**: scraping local = R$0 em tokens vs ~20k tokens/conversa em screenshot+OCR
- **Estrutura**: extrai por turno (Wagner / Claude) preservando ordem
- **Reproduzível**: roda em batch sem supervisão depois do login inicial
- **Origem rastreável**: frontmatter padrão já liga a conversa de volta à URL

### Limitações conhecidas

- Não captura **artifacts** (painel lateral) — claude.ai os renderiza em iframe/portal separado.
  Pra esses, abrir manualmente e copiar (ou estender script com selector específico do `aside`).
- Captcha/checkpoint do claude.ai aparece na janela; resolve manual e o script continua.
- Conversas muito longas: usa scroll automático até `scrollHeight` parar de crescer.

## `conversas-pendentes.tsv`

Lista das conversas mobile do Claude que Wagner identificou em 2026-04-24
mas ainda não foram extraídas pra `_Ideias/`. Já populada com as 3 que
sobraram da consolidação:

| URL                                       | Idea destino       |
| ----------------------------------------- | ------------------ |
| `dda41749-...`                            | CobrancaRecorrente |
| (UUID Laravel AI SDK / RAG / KG)          | LaravelAI          |
| (UUID Automatizar Laravel React CC)       | AutomacaoLaravelCC |

Atualize o TSV quando descobrir UUIDs e rode em batch.
