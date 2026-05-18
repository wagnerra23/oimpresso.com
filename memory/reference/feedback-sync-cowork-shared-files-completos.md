---
name: feedback-sync-cowork-shared-files-completos
description: Quando user pede sync visual KB-X.X do Cowork, contar arquivos `.jsx` referenciados em `<script src>` no HTML vs `.jsx` existentes em prototipo-ui/. Sem essa checagem, sync incompleto deixa React crashando em runtime.
type: feedback
---

# Sync KB-X.X do Cowork — checar shared files antes de declarar pronto

**Regra:** Quando user manda sync visual do Cowork (curl batch de N arquivos pra `prototipo-ui/`), **antes de declarar pronto** (PR pronto / mergeado / verificado), contar quantos `.jsx` o `Oimpresso ERP - Chat.html` referencia via `<script src="...">` e comparar com quantos existem em `prototipo-ui/`. Se mismatch, sync veio incompleto — `useFooHook is not defined` em runtime.

**Why:** Sessão 2026-05-18 sync KB-9.75 v2 — Wagner forneceu URL bucket com 18 arquivos. Mergei PR #1064. Smoke Chrome via skill `brave-mcp-primeiro-sempre` revelou:

```
ReferenceError: useTweaks is not defined
    at App (<anonymous>:1763:20)
```

Causa: HTML referencia **51 `.jsx`**, mas sync trouxe só **18**. **35 shared files faltaram** (sidebar, chat, icons, tweaks-panel, kb-page, equipe, inbox-v2, etc). Sem skill `brave-mcp-primeiro-sempre`, eu teria reportado "mergeado, tudo certo" 2× (PR #1064 + #1065).

PR #1066 (fix) baixou bundle completo via Cowork API e adicionou 47 arquivos.

**How to apply:**

```bash
cd prototipo-ui/
grep -oE 'src="[^"]+\.jsx"' "Oimpresso ERP - Chat.html" | sed 's/src="//;s/"$//' | sort -u > /tmp/refed.txt
ls *.jsx 2>/dev/null | sort -u > /tmp/exist.txt
echo "Referenciados: $(wc -l < /tmp/refed.txt) · Existentes: $(wc -l < /tmp/exist.txt)"
echo "FALTANDO:"
comm -23 /tmp/refed.txt /tmp/exist.txt
```

Se output "FALTANDO" tem ≥1 linha → sync **incompleto**. Pedir bundle completo via Cowork API:

```
WebFetch URL: https://api.anthropic.com/v1/design/h/<hash>?open_file=<entry>.html
```

Retorna gzip tarball ~7MB com 600+ arquivos + README. Copiar **todos** os `.jsx`/`.css` referenciados pelo HTML pra `prototipo-ui/`.

**Triggers:**

- User cola `curl` batch com N URLs pra `prototipo-ui-patch/...`
- User pede "sync KB-X.X visual"
- Após merge de PR com `+1000 linhas` em `prototipo-ui/`

**Anti-pattern:**

- Confiar que o batch curl do user é completo (ele pode ter mandado só os arquivos NOVOS do refino, esquecendo deps shared)
- Declarar "smoke Chrome OK" sem ter rodado `read_console_messages` (skill `brave-mcp-primeiro-sempre`)

**Histórico:**

- 2026-05-18 — instalado após regressão runtime do PR #1064 detectada por smoke Chrome (PR #1066 fix).
