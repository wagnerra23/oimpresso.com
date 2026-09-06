---
id: requisitos-suporte-suporte-empresas-gap
tela: Suporte/Empresas (/suporte/empresas)
prototipo: prototipo-ui/cowork/suporte-page.jsx
tela_viva: resources/js/Pages/Suporte/Empresas.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — Suporte/Empresas

> **Natureza da fonte (medida, não suposta):** o cabeçalho do `suporte-page.jsx:2` declara
> *"Espelho do vivo resources/js/Pages/Suporte/{Empresas,Visao}.tsx"* — é **porte reverso do
> código vivo**, e o charter declara `related_prototype: n/a (herda PT-01 Lista)`. A âncora
> usada aqui é a do **bundle** (`suporte-page.jsx`, regra dura do `ancora.mjs`), e o detector
> do design-sync a resolve por `mapping: "alias"`. Consequência: divergência aqui **não** é
> "o vivo está atrasado em relação a um design aprovado" — é o espelho tendo desenhado a mais.
> Nada nesta tabela promove `suporte-page.jsx` a `related_prototype` do charter.
>
> **Escopo:** o protótipo cobre três vistas (`empresas` · `visao` · `log`, `:2-6`). Só a
> primeira é esta tela. `Visao` (`:130-213`) e `LogAcessos` (`:215-250`) são outras telas e
> **não** são partes daqui.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Cabeçalho da página | `Empresas.tsx:35-49` monta título e subtítulo com `<h1>` + `Inline` cru; a copy é idêntica à do protótipo (`suporte-page.jsx:93`, `:99`). Não usa o `PageHeader` do Design System (medido: `grep -c PageHeader` = 0 no arquivo; 116 de 412 `.tsx` sob `Pages/` usam), e não tem o botão "Log de acessos" que o protótipo põe nas ações (`suporte-page.jsx:94`). | **Decidir.** Duas coisas distintas na mesma região do mockup (`suporte-page.jsx:96-101`): (a) trocar `<h1>`+`Inline` por `PageHeader` do DS em `Empresas.tsx:35-49` — é conformidade de Shell (Constituição UI v2), não estética; (b) o botão "Log de acessos" abre a vista `log` (`support_access_logs`, ONDA O5) que **não existe como rota viva** — construir a rota é pré-requisito, e nem o charter nem o SPEC de Suporte a declaram. Construir ou rejeitar por escrito, item a item. |
| Busca local por nome/ID | `Empresas.tsx:23-28` filtra por `name` e `id` em memória; input em `:42-48` com o mesmo `placeholder` ("Buscar empresa…") e o mesmo `aria-label` do protótipo (`suporte-page.jsx:61-65`, `:94`). Atende G3 do charter. | Nada — paridade. |
| Aviso "Somente leitura" | Não existe no vivo. O protótipo traz uma `Nota tone="warn"` própria (`suporte-page.jsx:109-114`, `data-contract="aviso-escopo"`) explicando que nada é editado ali e que a autorização/auditoria vivem no middleware `EnsureSupportAccess`. | **Decidir.** Região do mockup: `suporte-page.jsx:109-114`. Entraria em `Empresas.tsx` entre o cabeçalho (`:49`) e a moldura da tabela (`:51`). Não é mock de dado — é copy de afordância que reforça o Non-Goal "read-only" do charter. O charter não a lista nos UX targets, então é adição. Construir ou rejeitar por escrito. |
| Tabela de empresas | `Empresas.tsx:51-77` tem 3 colunas (Empresa, ID, Ação) em `<table>` cru. O protótipo define 5 (`suporte-page.jsx:75-81`): Empresa (com ícone e submetadados "N usuários · N contatos"), Plano (StatusBadge), Cliente desde, ID, Ação — e usa o `DataTable` do DS (`:122`). | **Decidir.** Região do mockup: `suporte-page.jsx:75-91` (colunas) e `:122` (`DataTable`). No vivo, o ponto é `Empresas.tsx:51-77`, e há **pré-condição de payload**: a `interface Empresa` (`Empresas.tsx:13-16`) só recebe `id` e `name` — plano, data de início e contagens exigem prop nova do controller, escopada por `business_id` (ADR 0093). Os valores do protótipo são mock (`suporte-page.jsx:16-22`); a capacidade em disputa é a coluna, não o dado. Construir ou rejeitar por escrito. |
| Estados vazio / sem-resultado | `Empresas.tsx:78-84` distingue os dois casos e usa a mesma frase-âncora do charter ("Nenhuma empresa-cliente acessível."). O protótipo acrescenta uma descrição a cada um (`suporte-page.jsx:117-120`) explicando o porquê da lista vazia (concessão em `support_agents`) e como refinar a busca. | **Decidir.** Só a **descrição** está em jogo — os dois estados já existem e cobrem os UX targets do charter. Região do mockup: `suporte-page.jsx:117-120`; ponto no vivo: `Empresas.tsx:78-84`. Construir ou rejeitar por escrito. |
| Estados erro / carregando | Não existem no vivo: `Empresas.tsx` renderiza direto a partir da prop `empresas` (`:22`, `:61`), sem branch de falha nem de carregamento. O protótipo tem os dois (`suporte-page.jsx:103-107`): `Vazio variant="error"` para o `SupportAccessService` não responder (`:104-105`), e `Skeleton` de 5 linhas (`:107`). | **Decidir.** Região do mockup: `suporte-page.jsx:103-107`; ponto no vivo: `Empresas.tsx:61`, envolvendo o `map`. O charter lista nos UX targets só três estados (cheia · busca-sem-resultado · vazia) — estes dois são adição do espelho, e o de carregamento só faz sentido se a prop virar `Inertia::defer`. Construir ou rejeitar por escrito. |
| Navegação para a visão do cliente | `Empresas.tsx:68-72` leva à visão pelo botão "Entrar (suporte)" na última coluna — cumpre G1 do charter (≤2 cliques). O protótipo mantém o mesmo botão (`suporte-page.jsx:89`) e ainda torna a **linha inteira** clicável (`onRowClick`, `:122`). | **Decidir.** Só a linha clicável. Região do mockup: `suporte-page.jsx:122`; ponto no vivo: `Empresas.tsx:62-65` (o `<tr>`). É afordância redundante com o botão, e exige tratar acessibilidade de teclado se adotada. Construir ou rejeitar por escrito. |
