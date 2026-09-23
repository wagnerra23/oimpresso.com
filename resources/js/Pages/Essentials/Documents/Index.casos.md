---
id: resources-js-pages-essentials-documents-index-casos
casos: Essentials · Arquivos e Memos · /essentials/document
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa + critério de aceite verificável (Dado/Quando/Então)
owner: wagner
last_run: "2026-09-23"
---

# Casos de uso — /essentials/document · Arquivos e Memos

> **Status:** ✅ passa (provado no manifesto G-7) · 🧪 teste cita o UC, sem veredito ainda ·
> ⬜ não verificado · ❌ quebrou.

> Os UC derivam do **contrato** — [`Index.charter.md`](Index.charter.md) (lei) + o
> `DocumentController` real (`index` → `Inertia::render('Essentials/Documents/Index')`,
> `store`, `destroy`) — **nunca** do `.tsx` nem do protótipo (§5 2026-06-05). O `✅` vem do
> manifesto derivado do JUnit; **não se escreve à mão** (G-7).

> ⚖️ **Onde roda.** Teste: [`tests/Feature/Essentials/DocumentsIndexContratoTest.php`](../../../../../tests/Feature/Essentials/DocumentsIndexContratoTest.php),
> MySQL-only (pula no SQLite). Medido 2026-09-23: o arquivo **não está** na allowlist de
> nenhuma lane de PR com MySQL — a lane natural é `PHP / Pest (Essentials · MySQL)`
> (`.github/workflows/essentials-pest.yml`), que **não** é required. Até ser listado lá, o UC
> não tem veredito de CI. Pest local é proibido ([ADR 0062](../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).

---

## UC-EDOC-01 · A lista de memos mostra o meu e o compartilhado comigo, e mais nada `[must]` `[T0]`
Status: 🧪 sem veredito
- **Persona:** operador do business — abre a aba **Memos** e vê os avisos que escreveu e os que a equipe compartilhou com ele.
- **Aceite:** Dado um memo meu, um memo de colega compartilhado comigo (`essentials_document_shares`, `value_type=user`), um memo de colega **não** compartilhado e um memo **meu** gravado em **outro** `business_id` · Quando a tela pede a prop deferida `memos` · Então chegam o meu (`is_mine=true`) e o compartilhado (`is_mine=false`), e **não** chegam o não-compartilhado nem o de outro tenant.
- **Teste:** `tests/Feature/Essentials/DocumentsIndexContratoTest.php` — `UC-EDOC-01 · memos: …`
- **Regressão que defende:** vazamento cross-tenant (ADR 0093) e ACL de compartilhamento frouxa. O memo de outro tenant é de **minha** autoria de propósito: só o filtro de `business_id` o segura.

## UC-EDOC-02 · Criar memo grava no tenant da sessão e volta pra aba Memos `[must]`
Status: 🧪 sem veredito
- **Persona:** operador — escreve um aviso (título + corpo) pra equipe.
- **Aceite:** Dado a tela · Quando envio `POST /essentials/document` com `name` + `body` · Então responde redirect sem erro de validação, o `Location` leva `type=memos`, e existe **uma** linha em `essentials_documents` com `business_id` da sessão, `user_id` meu, `type=memos` e `description` = corpo.
- **Teste:** `tests/Feature/Essentials/DocumentsIndexContratoTest.php` — `UC-EDOC-02 · criar memo …`
- **Regressão que defende:** memo gravado sem tenant, ou redirect pra aba errada (o usuário "perde" o que acabou de criar).

## UC-EDOC-03 · Remover só apaga item próprio `[must]`
Status: 🧪 sem veredito
- **Persona:** operador — apaga um memo que escreveu; não consegue apagar o de um colega.
- **Aceite:** Dado um memo meu e um de colega no mesmo tenant · Quando envio `DELETE /essentials/document/{id}` para o do colega · Então a linha **continua**; e para o meu · Então a linha some.
- **Teste:** `tests/Feature/Essentials/DocumentsIndexContratoTest.php` — `UC-EDOC-03 · remover …`
- **Regressão que defende:** charter Non-Goal *"NÃO permite remover item de terceiro (só `is_mine`)"*. O backend recusa em silêncio (redirect sem apagar) — o caso fixa esse comportamento, não um 403 que o controller não emite.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] Upload de arquivo (`type=document`) com MIME fora da whitelist é recusado (`StoreDocumentRequest`) — exige fixture de `UploadedFile` e storage fake.
- [BACKLOG] Download restrito a criador ou destinatário do share (`/essentials/document/download/{id}` → 403 pra terceiro).
- [BACKLOG] Compartilhar por **papel** (`value_type=role`) libera o item pra quem tem o papel.
- [BACKLOG] Remover apaga junto os compartilhamentos — o charter afirma, o `destroy` atual **não** apaga `essentials_document_shares` (lido 2026-09-23; **hipótese**, não medida por teste). Divergência charter × código a levar ao [W], não corrigida aqui.
