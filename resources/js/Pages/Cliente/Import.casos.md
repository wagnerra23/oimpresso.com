---
id: resources-js-pages-cliente-import-casos
casos: Importar clientes em massa · /contacts/import
irmaos: Import.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o assistente de importação abrir (não cair no Blade legacy) não muda no refactor.
owner: wagner
last_run: "2026-07-08"
---

# Casos de Uso & Aceite — Importar clientes em massa

> Fase 2 (lanes do Cliente). Âncora comportamental REAL (`ClienteImportInertiaTest`, Pest/CT100) — **não** o `Wave1ImportInertiaTest` (source-grep + `@group legacy-quarantine`). Deriva do SDD [§6.4 CU-CLI-13](../../../../memory/requisitos/Cliente/SDD-cadastro-cliente-v1.0.md).
>
> ⚖️ **Onde este UC roda, e com que força** (medido 2026-07-27): lane `PHP / Pest (Cliente · MySQL)` — [`cliente-pest.yml`](../../../../.github/workflows/cliente-pest.yml), criada 2026-07-27, **advisory** (não está em [`required-checks-baseline.json`](../../../../governance/required-checks-baseline.json): reprova visível, **não bloqueia merge**). **Antes dela** o teste rodava só no nightly do CT 100 e em nenhuma lane de PR. Onde a linha abaixo diz "passa no CI", leia-se **passava no nightly**.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC e passa (manifesto não regravado) · ⬜ não verificado · ❌ quebrou.

---

## UC-CIMP-01 · Abrir o assistente de importação (React, não o Blade velho)
- **Persona:** Larissa — vai importar a base de clientes de uma planilha; abre a tela de importação e vê o assistente moderno.
- **Aceite:** Dado a flag `MWART_CLIENTE_IMPORT` ligada · Quando faço `GET /contacts/import` · Então renderiza Inertia **`Cliente/Import`** (não o Blade `contact.import`) e o payload traz `zip_available` (banner de aviso se o PHP Zip faltar).
- **Teste:** `tests/Feature/Cliente/ClienteImportInertiaTest.php` — `GET /contacts/import renderiza Inertia Cliente/Import quando a flag liga`.
- **Regressão que defende:** a flag desligada silenciosa faria a tela cair no Blade legacy (dual-render) — o teste trava o branch Inertia.
- **Status: 🧪** — feature test HTTP passa no CI; ✅ com o manifesto regravado.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG] Baixar o template XLSX (27 colunas UPOS)** — exige spec de download no harness.
- **[BACKLOG] Upload valida extensão + retorna count de sucesso/erro** — `postImportContacts` (multipart) exige teste de upload dedicado.
- **[BACKLOG] Banner de erro quando o PHP Zip não está disponível** — assertar `zip_available=false` num ambiente sem a extensão.

## Rastreabilidade (UC → CU do SDD → US do SPEC)

| UC | CU (SDD §6) | US (SPEC) |
|---|---|---|
| UC-CIMP-01 | CU-CLI-13 | — |

> Coluna US vazia: a US de import (US-CRM-082, com dedupe/preview) está `todo` — o que existe hoje é o assistente básico, sem US própria.

## Como rodar a suíte
1. **Pest:** `docker exec oimpresso-staging php artisan test --filter=ClienteImportInertiaTest` no CT100 (nunca local/Hostinger).
2. **Manifesto:** `npm run casos:results` → 🧪 vira ✅.
3. **Cadência:** rodar ao fim de toda mexida em `Import.tsx` / `ContactController::getImportContacts`.

## Trilha do tempo
- 2026-07-27 · [CC] chip S-Cliente do passo 5 (agent `sdd-from-source`). **Nenhum UC reescrito.** Lane `PHP / Pest (Cliente · MySQL)` criada (**advisory**) — antes o teste não rodava em lane de PR nenhuma; + ponteiro pro SDD §6.4. Refs: [SDD Cliente](../../../../memory/requisitos/Cliente/SDD-cadastro-cliente-v1.0.md) · [ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md).
- 2026-07-08 · [CC] criado — Fase 2 (lanes Cliente). Teste-âncora `ClienteImportInertiaTest` escrito nesta onda (o Wave1* era quarentena/source-grep). Refs: [ADR 0264](../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md) G-1/G-2.
