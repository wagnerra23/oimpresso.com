---
data: 2026-07-20
slug: produto-design-trio-briefing-modulo
autores: [M, CC]
tema: "Da 'grade do design da tela de produto' ao BRIEFING do módulo inteiro — trio do Create, premissa do multiplicador, mapa Blade→React, poda anti-apodrecimento"
prs: [4417, 4449, 4464, 4579]
tipo: session-log
---

# Session log — Produto: design da tela → trio → BRIEFING do módulo inteiro

Sessão longa que começou em "compare em grade o design da tela de produto" e evoluiu, por
correções sucessivas da **Maiara [M]** (e uma do Wagner), até o BRIEFING do módulo inteiro.

## Pontos discutidos

1. **Grade comparativa** do cadastro de produto — usabilidade (15 dimensões canônicas) + paridade legado (Delphi) × charter × testes; formação de preço; preço especial.
2. **Adversário de integração** — 3 subagents em paralelo, instruídos a REFUTAR meus achados.
3. **Contrato da tela** (charter/casos/teste) — o que existe, o que falta; criação do trio do Create.
4. Os **"4 erros" do Create** — reclassificação.
5. **Mapa de migração Blade→React** do cadastro.
6. **Organização dos docs** do módulo (fragmentação da paridade).
7. **BRIEFING do módulo inteiro** — varredura completa, poda anti-apodrecimento, links.
8. Prompt reutilizável para gerar briefings dos próximos módulos.

## Acordado (com resultado)

- **#4417 (mergeado)** — trio do Create (`Create.casos.md` + `CadastroProdutoContratoTest`) + fix `create()` 500→404 (`findOrFail`).
- **#4464 (mergeado)** — premissa falsa do multiplicador corrigida em SDD/FICHA/INVENTARIO/BRIEFING + Errata na ADR ARQ-0001.
- **#4449 (mergeado)** — regra de processo "pedido explícito de contrato de tela" + ordem de fonte (doc→código→Delphi→concorrentes→perguntar).
- **#4579 (aberto)** — BRIEFING do Produto reescrito pra módulo inteiro (18 áreas), podado, links validados; gates verdes.
- **Estoque + Restaurant = âncoras relacionadas** (decisão Wagner — dependem do produto, não saem do briefing).
- **Poda anti-apodrecimento** — números derivados (grades/%/status/nota) saem do corpo, apontam pros geradores (UI-CATALOG, casos-coverage, module:grade); todo arquivo-fonte vira link validado.
- **Não criar mapa paralelo** — consolidar no `PARIDADE-charter-vs-legado` (pendente, próxima sessão).
- Prompt reutilizável de briefing entregue (não salvo como doc ainda).

## Desacordado / onde eu errei e fui corrigido

- **Meus achados de integração foram REFUTADOS pelos próprios adversários que eu spawnei:** o passo 3 (tabela→cliente) existe (via `customer_groups`); o preço da tabela **chega na venda** (`getVariationGroupPrice`→`SellPosController`); `price_type='percentage'` **funciona** (3 implementações + golden). Eu tinha concluído o contrário lendo código.
- **2 dos "4 bugs" do Create NÃO eram bugs** — validação e defaults vivem no Blade (client-side + form), por design UltimatePOS. Eram gaps de paridade. [M] pegou.
- **Tratei a casca React (`Create.tsx` draft) como o cadastro** — o real em produção é o **Blade**. [M] corrigiu.
- **Disse "`api.php` não tem produto"** — falso: a **API REST vive no módulo Connector**. [M] mandou procurar.
- **Tirei Estoque e Restaurant do briefing** — Wagner reverteu: são âncoras, não saem.
- **Chamei a usuária de Felipe a sessão inteira** — é a **Maiara [M]**. Commits saíram [F+CC] por engano.
- **Ia commitar "UC-PCAD-05 aberto"** — já resolvido pela sessão irmã (#4554). Pego na varredura de fechamento.
- **Enchi o briefing de números que apodrecem** — [M] questionou "isso vai apodrecer?" → poda.
- **Links inconsistentes** (uns arquivos com link, outros não) — [M] pegou → critério explícito + 49 links validados.
- **Repeti a premissa falsa do multiplicador** ("oco/1:1") — a sessão do [F] já tinha achado que `percentage` é multiplicador desde 2023.

## Lição (repetida, catalogada em proibicoes §5)

Reincidi ~5× em **concluir sem varrer completo** (achado/causa/contagem derivados de leitura parcial). A Maiara pegou cada um insistindo na varredura. Regra: **varrer view + controller + app/Utils + API (Connector!) ANTES de escrever**, não depois. O adversário-antes-de-concluir e a varredura completa são o que separou verdade de narrativa nesta sessão.
