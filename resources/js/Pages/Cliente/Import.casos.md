---
casos: Importar clientes em massa · /contacts/import
irmaos: Import.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o assistente de importação abrir (não cair no Blade legacy) não muda no refactor.
owner: wagner
last_run: "2026-07-08"
---

# Casos de Uso & Aceite — Importar clientes em massa

> Fase 2 (lanes do Cliente). Âncora comportamental REAL escrita nesta onda (`ClienteImportInertiaTest`, Pest/CT100) — **não** o `Wave1ImportInertiaTest` (source-grep + `@group legacy-quarantine`, fora de lane).
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

## Como rodar a suíte
1. **Pest:** `docker exec oimpresso-staging php artisan test --filter=ClienteImportInertiaTest` no CT100 (nunca local/Hostinger).
2. **Manifesto:** `npm run casos:results` → 🧪 vira ✅.
3. **Cadência:** rodar ao fim de toda mexida em `Import.tsx` / `ContactController::getImportContacts`.

## Trilha do tempo
- 2026-07-08 · [CC] criado — Fase 2 (lanes Cliente). Teste-âncora `ClienteImportInertiaTest` escrito nesta onda (o Wave1* era quarentena/source-grep). Refs: [ADR 0264](../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md) G-1/G-2.
