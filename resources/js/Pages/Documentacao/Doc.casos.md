---
casos: Documentacao/Doc — documento do acervo
irmaos: Doc.charter.md (lei) · memory/requisitos/Documentacao/ANTI-REGRESSAO-documentacao-blade.md (paridade)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-08-06"
---

# Casos de Uso & Aceite — Documentacao/Doc

> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> **Fonte dos casos:** o contrato de paridade `AR-DOC-NNN`, não o `.tsx`.
> **Persona:** o time e quem entra novo — não é tela de cliente final.

---

## UC-DOC-01 · Abrir um documento e saber onde se está
- **Persona:** [L] seguindo um link do rail para um documento específico.
- **Aceite:** Dado um slug existente no acervo · Quando abre `/documentacao/{slug}` · Então vê o documento renderizado **e** o item correspondente marcado como ativo no rail.
- **Teste:** `e2e/documentacao-doc.spec.ts` — `UC-DOC-01`.
- **Regressão que defende:** perder a marcação do item ativo, que é o que diz ao leitor onde ele está numa árvore de mais de cem documentos (AR-DOC-034).
- **Status: ⬜**

## UC-DOC-02 · Link relativo resolve pela pasta do próprio documento
- **Persona:** [F] navegando de um SPEC para uma ADR citada por caminho relativo.
- **Aceite:** Dado um documento em subpasta (por exemplo `memory/requisitos/<Mod>/`) que cita `../../decisions/NNNN-*.md` · Quando clica no link · Então chega ao documento certo, porque a base de resolução é a **pasta do próprio documento** e não `memory/`.
- **Teste:** `e2e/documentacao-doc.spec.ts` — `UC-DOC-02`.
- **Regressão que defende:** a base voltar a ser `memory/` — aí todo link relativo de documento em subpasta vira 404, e o acervo inteiro parece quebrado (AR-DOC-033).
- **Status: ⬜**

## UC-DOC-03 · Documento fora da documentação não vaza
- **Persona:** qualquer pessoa autenticada tentando abrir, pelo slug, um `session` ou `handoff`.
- **Aceite:** Dado um slug que existe no corpus mas é de tipo fora da documentação · Quando abre `/documentacao/{slug}` · Então recebe **404**, não o conteúdo.
- **Teste:** `e2e/documentacao-doc.spec.ts` — `UC-DOC-03`.
- **Regressão que defende:** ampliar os tipos visíveis "pra facilitar" e expor material que não é documentação publicada (AR-DOC-032; anti-hook do charter).
- **Status: ⬜**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** corpus inacessível nesta rota devolve 503 — diferente do 200 "indisponível" da busca (AR-DOC-031).

## Trilha do tempo
- 2026-08-06 · [CC] UCs reais escritos a partir do contrato de paridade AR-DOC; persona corrigida (time, não Larissa). Refs: UI-0013 · ADR 0264 G-1/G-2 · ADR 0104.
- 2026-07-11 · [CC] carimbado por criar-tela.mjs — trio nascido junto (charter + casos + teste).
