# APLICAR O DESIGN SYSTEM v4 (ROXO) NO REPO + AUDITORIA

> **Para:** Wagner · **Origem:** Cowork [CC] · 2026-05-29
> **Decisão:** full-roxo aprovado (supersede ADR 0190) + auditoria read-only primeiro.
> Este é o **único** arquivo que você precisa. Supersede a versão v3 deste guia.

---

## Como funciona (zero-toque)

```
1. Abra o Claude Code apontado pro repo oimpresso.com
2. Cole O BLOCO ÚNICO abaixo (uma vez só)
3. O Code baixa a fundação v4, roda a auditoria, comita e abre 1 PR sozinho
```

Eu (Cowork) **não escrevo no GitHub** — só leio. Quem comita é o Code. Eu produzo a ponte: os arquivos roxos prontos (URLs abaixo) + o prompt que o Code executa sem você interpretar nada.

**O que entra:** shell vira roxo (hue 295) em foco/links/abas/ativo/botões. Sidebar continua petróleo. A sidebar e os componentes (Ondas A–D) já estavam prontos — a virada v4 é só os 3 tokens de accent.

**O que NÃO entra:** reescrita de tela. O CRM/Contacts ainda é Blade legado e nem consome os tokens — migração é tela-por-tela depois, guiada pelo relatório que esta auditoria gera.

---

## BLOCO ÚNICO — cole no Claude Code

````bash
# Repo wagnerra23/oimpresso.com — fundação DS v4 (roxo) + auditoria read-only.
git checkout main && git pull origin main
git checkout -b feat/design-system-v4-roxo

mkdir -p prototipo-ui

# ── 1. Baixa a fundação roxa (URLs do Cowork, válidas ~1h) ──
curl -fL -o prototipo-ui/tokens.css \
  'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/tokens.css?t=21a1a7b198b1849142d4086cb5770a2491f21d3e0002f12fd027979cd85f4c6b.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1780075119.fp&direct=1'

curl -fL -o prototipo-ui/design-system.css \
  'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/design-system.css?t=498aae0d9c2c30fb162d4e5b988aa4fff4fa0c52922abc76ea977c07da8fdb86.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1780075120.fp&direct=1'

curl -fL -o prototipo-ui/ds-behavior.js \
  'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/ds-behavior.js?t=3e40192614482035e9a57e37bcb33da55b31b4b3b22b0901fefc4b73b2e8ca07.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1780075121.fp&direct=1'

curl -fL -o 'prototipo-ui/Design System v4.html' \
  'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/Design%20System%20v4.html?t=fac5cb02e85a37083b0f8d8e517f8dcbe17e729f9ed063df3b65324d48631006.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1780075123.fp&direct=1'

curl -fL -o prototipo-ui/CODE_DESIGN_CONTRACT.md \
  'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/CODE_DESIGN_CONTRACT.md?t=22ff1de11c4c430b0a26e0dea9cf155de348808852d5106ee0d7d63b09b03c60.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1780075124.fp&direct=1'

# ── 2. Verifica que veio o roxo (295) e as Ondas A–D ──
echo "accent roxo? (espera 295):" && grep -m1 "accent:" prototipo-ui/tokens.css
grep -c "295" prototipo-ui/tokens.css
grep -c "ONDA D" prototipo-ui/design-system.css   # espera >= 1
test -s "prototipo-ui/Design System v4.html" || { echo "FALHA: v4.html vazio"; exit 1; }

# ── 3. AUDITORIA read-only: mapeia o raio de explosão do roxo ──
#    Não altera nenhuma tela. Só gera o relatório que vai guiar a migração.
{
  echo "# AUDITORIA DS v4 (roxo) — divergências"
  echo "_Gerado pelo Claude Code em \$(date -I) na branch feat/design-system-v4-roxo._"
  echo
  echo "## 1. Telas Inertia/React que JÁ consomem os tokens (roxam automático — revisar visual)"
  grep -rln --include=\*.tsx --include=\*.jsx -e "var(--accent" -e "--accent-soft" \
    resources/js Modules/*/resources/js 2>/dev/null | sort || true
  echo
  echo "## 2. Cores AZUIS hardcoded (precisam virar token ou serão inconsistência roxo×azul)"
  grep -rn --include=\*.tsx --include=\*.jsx --include=\*.css --include=\*.scss \
    -e "#3b82f6" -e "#2563eb" -e "#1d4ed8" -e "blue-[45678]00" -e "hue 220" -e "0.09 220" \
    resources Modules/*/resources 2>/dev/null | head -80 || true
  echo
  echo "## 3. Telas Blade legadas (UltimatePOS) — NÃO consomem o DS ainda (migração F1→F3)"
  echo "### CRM / Contacts (cadastro citado pelo Wagner)"
  ls -1 Modules/Crm/Resources/views/contact_login/ 2>/dev/null || true
  echo
  echo "## 4. Resumo"
  echo "- Tokens roxos landados em prototipo-ui/tokens.css (accent hue 295)."
  echo "- ADR 0190 (shell azul) SUPERSEDED — registrar follow-up ADR."
  echo "- Próximo F1: redesenhar o cadastro de Contacts pro canon DS (Cowork)."
} > prototipo-ui/AUDITORIA_DS_V4.md

cat prototipo-ui/AUDITORIA_DS_V4.md

# ── 4. Comita tudo num PR só ──
git add prototipo-ui/
git commit -m "feat(ds): Design System v4 (shell roxo, hue 295) + auditoria de divergências

- tokens.css: accent azul 220 -> roxo 295 (supersede ADR 0190, aprovado por Wagner)
- design-system.css: Ondas A-D (estados, combobox, datepicker, pagination, data-viz)
- ds-behavior.js + Design System v4.html (showcase) + CODE_DESIGN_CONTRACT.md
- AUDITORIA_DS_V4.md: raio de explosao do roxo + telas Blade legadas pendentes"
git push -u origin feat/design-system-v4-roxo
gh pr create --title "feat(ds): Design System v4 (shell roxo) + auditoria" \
  --body "Vira o shell pra roxo (hue 295) — supersede ADR 0190 (aprovado por Wagner em 2026-05-29). Componentes (Ondas A-D) e sidebar petroleo inalterados. Inclui AUDITORIA_DS_V4.md mapeando quem roxa automatico, cores azuis hardcoded e telas Blade legadas (CRM/Contacts) pendentes de migracao tela-por-tela." \
  --base main --head feat/design-system-v4-roxo
````

---

## Depois do merge — próximos passos (tela-por-tela)

1. **Leio o `AUDITORIA_DS_V4.md`** que o Code gerou → sei exatamente o que roxou feio e o que ainda é Blade.
2. **F1 do cadastro de Contacts** — eu redesenho pro canon DS (PageHeader + form denso + foco roxo), você aprova o screenshot, eu gero o prompt zero-touch pro Code traduzir pra Inertia/React.
3. Demais telas que a auditoria apontar, por prioridade.

---

## ⚠️ Limites honestos

- **Eu não comito no GitHub.** É sempre você colando o prompt no Code. Não afirmo "está commitado".
- **As URLs valem ~1h.** Se o `curl` der 403/expirou, me peça pra regenerar — refaço em 1 tool call.
- **Auditoria é read-only:** só escreve o relatório, não mexe em tela. Migração é depois, por PR pequeno.
