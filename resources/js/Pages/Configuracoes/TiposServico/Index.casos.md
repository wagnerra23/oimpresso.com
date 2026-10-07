---
id: resources-js-pages-configuracoes-tiposservico-index-casos
casos: Tipos de serviço · /types-of-service
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a taxa e a tabela de preço por local entram no total da venda; a de outro negócio na tela, ou um número diferente do gravado, é incidente.
owner: wagner
last_run: "2026-10-07"
---

# Casos de Uso & Aceite — Tipos de serviço

> Thread `sistema/playbook/05`, tela 2 de 3. Derivados do `TypesOfServiceController` e da Blade `types_of_service/*`
> (RUNBOOK-tipos-servico, `tipos-servico-parity.md`) — não do `Index.tsx`.
> Teste: [`tests/Feature/Configuracoes/TiposServicoContratoTest.php`](../../../../../tests/Feature/Configuracoes/TiposServicoContratoTest.php),
> lane `acessos-pest.yml` (MySQL). A Blade está travada em `TiposServicoBaselineTest`.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC (veredito do CI) · ⬜ não verificado · ❌ quebrou.

---

## UC-TSERV-01 · Com a flag ligada, a tela abre em React
- **Persona:** quem configura como a loja atende (balcão, entrega, montagem).
- **Aceite:** Dado `access_types_of_service` e a flag `useV2ConfiguracoesTiposServico` ligada · Quando faço
  `GET /types-of-service` como o browser faz (`X-Inertia` **e** `X-Requested-With`) · Então renderiza
  **`Configuracoes/TiposServico/Index`**.
- **Status: 🧪**

## UC-TSERV-02 · Com a flag desligada, a Blade continua
- **Aceite:** Dado a flag desligada · Quando faço `GET /types-of-service` · Então a resposta é a Blade `types_of_service.index`.
- **Status: 🧪**

## UC-TSERV-03 · Os tipos do meu negócio, com a taxa e a tabela por local do banco · `[T0]` `[valor]`
- **Aceite:** Dado um tipo meu com taxa 8,5 % e a tabela "Atacado" no meu local, um tipo de outro negócio, e no JSON do
  meu tipo uma tabela de outro negócio · Quando a lista carrega (prop deferida `tipos`) · Então vejo o meu com `taxa` 8.5,
  `tipo_taxa` percent e só a minha tabela por local · E não vejo o tipo alheio nem a tabela alheia.
- **Status: 🧪**

## UC-TSERV-04 · O drawer grava a taxa do texto e edita sem mover taxa nem tabela · `[valor]`
- **Aceite:** Dado o corpo do drawer com taxa `"35,00"` fixa e o meu local na tabela "Atacado" · Quando cadastro · Então
  o banco guarda 35 (o mesmo que o `num_uf` devolve) · E, ao editar só o nome a partir da prop (`paraTexto(taxa)` +
  `tabela_por_local`), a taxa segue 35 e a tabela por local segue a mesma.
- **Status: 🧪**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

## Trilha do tempo
- 2026-10-07 · [CL] criado com a F3 da thread `sistema/playbook/05`.
