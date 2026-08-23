---
casos: Documentacao/Index — leitura guiada do Guia do Sistema
irmaos: Index.charter.md (lei) · memory/requisitos/Documentacao/ANTI-REGRESSAO-documentacao-blade.md (paridade)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-08-06"
---

# Casos de Uso & Aceite — Documentacao/Index

> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2: UC declarado sem teste citando o id = órfão.
>
> **Fonte dos casos:** o contrato de paridade `AR-DOC-NNN` da migração Blade→Inertia — **não** o
> `.tsx`. Caso derivado do código é tautológico: passa mesmo quando o comportamento está errado.
> **Persona:** quem lê documentação do sistema é o time ([W], [F], [M], [L], [E]) e quem entra novo.
> Não é tela de cliente final — a Larissa não usa esta superfície.

---

## UC-INDEX-01 · Ler o Guia do Sistema com rail e sumário
- **Persona:** alguém que entrou no time esta semana e precisa entender o sistema sem perguntar pra ninguém.
- **Aceite:** Dado o Guia presente no deploy · Quando abre `/documentacao` · Então vê o conteúdo renderizado, o sumário da página e o rail de documentos, e o payload traz HTML (não markdown cru).
- **Teste:** `e2e/documentacao-index.spec.ts` — `UC-INDEX-01`.
- **Regressão que defende:** conversão de markdown migrar pro cliente, ou o sumário virar manifesto commitado em vez de recalculado (AR-DOC-003, AR-DOC-004).
- **Status: ⬜**

## UC-INDEX-02 · Fonte ausente falha dizendo qual arquivo falta
- **Persona:** [F] investigando por que a documentação sumiu depois de um deploy.
- **Aceite:** Dado que `memory/GUIA-DO-SISTEMA.md` não está no deploy · Quando abre `/documentacao` · Então recebe 503 cuja mensagem **nomeia o arquivo ausente** — não uma página vazia nem um 500 genérico.
- **Teste:** `e2e/documentacao-index.spec.ts` — `UC-INDEX-02`.
- **Regressão que defende:** a falha honesta virar página em branco, que custa uma investigação inteira pra diagnosticar (AR-DOC-002).
- **Status: ⬜**

## UC-INDEX-03 · Trocar de lente mantém a numeração contínua
- **Persona:** [M] que só quer os documentos de operar.
- **Aceite:** Dado o rail com documentos das duas lentes · Quando filtra pela lente `operar` · Então os itens visíveis são numerados 1, 2, 3… **sem buracos**, porque o ordinal conta a ordem visível e não vem de `nav_order`.
- **Teste:** `e2e/documentacao-index.spec.ts` — `UC-INDEX-03`.
- **Regressão que defende:** ordinal passar a sair de `nav_order` — aí filtrar deixa 1, 3, 7 e o leitor conclui que sumiu conteúdo (AR-DOC-012).
- **Status: ⬜**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** documento que perde `nav_group` some do rail sem quebrar a página (AR-DOC-011).
- **[BACKLOG]** a busca deixa de ser oferecida quando o corpus está inacessível (AR-DOC-006).

## Trilha do tempo
- 2026-08-06 · [CC] UCs reais escritos a partir do contrato de paridade AR-DOC; persona corrigida (time, não Larissa). Refs: UI-0013 · ADR 0264 G-1/G-2 · ADR 0104.
- 2026-07-11 · [CC] carimbado por criar-tela.mjs — trio nascido junto (charter + casos + teste).
