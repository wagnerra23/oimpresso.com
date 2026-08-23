---
casos: Documentacao/Busca — busca no acervo
irmaos: Busca.charter.md (lei) · memory/requisitos/Documentacao/ANTI-REGRESSAO-documentacao-blade.md (paridade)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-08-06"
---

# Casos de Uso & Aceite — Documentacao/Busca

> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> **Fonte dos casos:** o contrato de paridade `AR-DOC-NNN`, não o `.tsx`.
> **Persona:** o time e quem entra novo — não é tela de cliente final.

---

## UC-BUSCA-01 · Termo curto continua achando
- **Persona:** [M] procurando "MCP" ou "NFe" — exatamente os termos mais usados no projeto.
- **Aceite:** Dado o corpus disponível · Quando busca um termo de 3 caracteres que o índice full-text descarta por ser curto · Então **ainda assim recebe resultados**, porque a busca no título entra junto como rede de segurança.
- **Teste:** `e2e/documentacao-busca.spec.ts` — `UC-BUSCA-01`.
- **Regressão que defende:** alguém "simplificar" a consulta deixando só o full-text — aí os termos mais buscados do projeto passam a devolver vazio, e a busca parece funcionar (AR-DOC-023; anti-hook do charter).
- **Status: ⬜**

## UC-BUSCA-02 · Índice fora do ar diz que está fora do ar
- **Persona:** [W] buscando durante uma janela em que o corpus não está acessível.
- **Aceite:** Dado o corpus inacessível · Quando abre a busca · Então a tela informa **"índice indisponível"** com HTTP 200 — não uma lista vazia, não um erro.
- **Teste:** `e2e/documentacao-busca.spec.ts` — `UC-BUSCA-02`.
- **Regressão que defende:** colapsar "não achei nada" e "não consegui procurar" no mesmo estado, que faz alguém concluir que o documento não existe quando ele existe (AR-DOC-021).
- **Status: ⬜**

## UC-BUSCA-03 · Termo curto demais não consulta o banco
- **Persona:** qualquer pessoa que digitou uma letra e ainda está pensando.
- **Aceite:** Dado um termo com menos de 2 caracteres · Quando a busca é submetida · Então nenhuma consulta é emitida e a tela não mostra resultado.
- **Teste:** `e2e/documentacao-busca.spec.ts` — `UC-BUSCA-03`.
- **Regressão que defende:** varredura do acervo inteiro a cada tecla (AR-DOC-022).
- **Status: ⬜**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** resultado traz título, tipo, módulo, caminho no git e trecho com o termo destacado (AR-DOC-025).
- **[BACKLOG]** ordenação por relevância e teto de resultados por página preservados (AR-DOC-024).

## Trilha do tempo
- 2026-08-06 · [CC] UCs reais escritos a partir do contrato de paridade AR-DOC; persona corrigida (time, não Larissa). Refs: UI-0013 · ADR 0264 G-1/G-2 · ADR 0104.
- 2026-07-11 · [CC] carimbado por criar-tela.mjs — trio nascido junto (charter + casos + teste).
