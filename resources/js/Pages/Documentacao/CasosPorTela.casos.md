---
casos: Documentacao/CasosPorTela — pedidos de clientes e contrato de cada tela, lidos dos casos.md
irmaos: CasosPorTela.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: a página substitui um artefato publicado à mão; o contrato prova que ela não envelhece e que não vaza pro dono de empresa.
owner: wagner
last_run: "2026-10-29"
---

> ℹ️ **`last_run` 2026-10-28 → 2026-10-29 (G-6): troca de componentes, não de comportamento.**
> Para os gates de layout (ADR 0253) e ESLint do DS, a tela passou a usar `<Grid>`/`<Inline>`, as abas
> viraram `<SubNav>`, os filtros `<Select>` (sentinela no "Todos") e as opções da decisão `<RadioGroup>`.
> Mesmos dados, mesmas permissões, mesmas ações. **Nenhum UC foi reexecutado** — os vereditos seguem os
> do `CasosPorTelaContratoTest` e da paridade; o bump é o trio reconciliado com a tela nesta data.

# Casos de Uso & Aceite — Documentacao/CasosPorTela

> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Fonte: o plano autorizado por [W] (artefato "Casos de uso por tela" passa a viver dentro do
> sistema, ADR 0256) e o charter. Testes: `tests/Feature/Documentacao/CasosPorTelaContratoTest.php`
> (Pest, MySQL) e `scripts/casos-backlog/paridade-php.test.mjs` (Node + PHP).

---

## UC-CASOSP-00 · Chego na página pela capa da documentação
- **Persona:** Maiara — abre `/documentacao` e quer a lista de pedidos.
- **Aceite:** Dado usuário com `documentacao.casos_tela.ver` · Quando abre `/documentacao` · Então
  vê o botão "Casos de uso por tela"; sem a permissão, o botão não aparece.
- **Regressão que defende:** a página existir e ninguém chegar nela sem digitar a URL.
- **Status: ⬜**

---

## UC-CASOSP-01 · A página mostra o que está nos casos.md agora
- **Persona:** Felipe — acabou de mergear um pedido novo no casos.md de uma tela.
- **Aceite:** Dado um usuário da equipe · Quando abre `/documentacao/casos-por-tela` · Então os
  pedidos, o resumo e o contrato das telas vêm do leitor dos casos.md (os mesmos ids e números),
  sem ninguém republicar nada.
- **Regressão que defende:** a página voltar a ser uma cópia que envelhece.
- **Status: ⬜**

---

## UC-CASOSP-02 · O dono de uma empresa-cliente não abre a página
- **Persona:** dono de uma empresa que usa o sistema (papel `Admin#{biz}`).
- **Aceite:** Dado um usuário com o papel Admin da própria empresa e sem a permissão da página ·
  Quando abre `/documentacao/casos-por-tela` · Então recebe 403.
- **Regressão que defende:** o `Gate::before` dar a página (que cita clientes por nome) a todo dono
  de empresa — é documentação da plataforma, não dado da empresa dele (ADR 0093).
- **Status: ⬜**

---

## UC-CASOSP-03 · Marcar "Conferido" grava quem conferiu
- **Persona:** Maiara — conferiu um pedido contra as regras de Fluxos.
- **Aceite:** Dado usuário com `documentacao.casos_tela.conferir` · Quando marca o pedido · Então a
  marca fica gravada com o usuário dela e aparece na página; quando desmarca, a marca some.
- **Regressão que defende:** a conferência voltar a depender do banco do artefato.
- **Status: ⬜**

---

## UC-CASOSP-04 · Só o [W] responde as decisões
- **Persona:** Wagner — responde uma decisão D-NN.
- **Aceite:** Dado usuário só com `conferir` · Quando tenta responder · Então 403 e nada é gravado.
  Dado usuário com `decidir` · Quando responde opção e nota · Então a resposta fica gravada com ele.
- **Regressão que defende:** qualquer pessoa da equipe registrar decisão em nome do dono.
- **Status: ⬜**

---

## UC-CASOSP-05 · Os números são os mesmos do `npm run casos:pagina -- --resumo`
- **Persona:** Wagner — compara a página com o resumo do gerador.
- **Aceite:** Dado o repositório inteiro · Quando o leitor PHP e o leitor Node leem os casos.md ·
  Então pedidos, decisões, contratos, telas, regras, resumo e problemas são iguais campo a campo.
- **Teste:** `scripts/casos-backlog/paridade-php.test.mjs`.
- **Regressão que defende:** mudar o formato do casos.md num leitor só e a página divergir do gerador.
- **Status: ⬜**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** E2E Playwright do caminho feliz (abrir, filtrar, abrir um caso), quando a lane E2E tiver um usuário da equipe semeado.

## Trilha do tempo
- 2026-10-28 · [CC] tela criada por criar-tela.mjs e preenchida; artefato substituído. Refs: UI-0013 · ADR 0256 · ADR 0264.
