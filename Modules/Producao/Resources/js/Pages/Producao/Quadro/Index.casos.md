---
id: modules-producao-pages-producao-quadro-index-casos
casos: Produção · Quadro · /producao/quadro
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do gestor + critério de aceite verificável (Dado/Quando/Então)
por_que: OS em duas colunas ou na coluna errada engana o PCP; OS de outra empresa no quadro é vazamento Tier 0.
owner: wagner
last_run: "2026-10-21"
last_run_ci: "_pendente_ — o veredito entra quando a lane MySQL rodar o ProducaoQuadroContratoTest."
---

# Casos de Uso & Aceite — Produção · Quadro (`/producao/quadro`)

> **Âncora:** `prototipo-ui/cowork/Wagner/cowork-inbox/producao/Producao.casos.md` (UC-PCV-05) e o catálogo de
> fluxos (`app/Domain/Fsm/Modelos/CasosDoPrototipo.php`, UC-FLX-10 e UC-FLX-13). Os UCs não derivam do `.tsx`.
> Teste: `Modules/Producao/Tests/Feature/ProducaoQuadroContratoTest.php`.

## UC-PCV-05 · Quadro por setor ou por máquina · `must`

**Dado** OS em etapas de setores diferentes **Então** cada OS aparece uma vez, na coluna do setor da etapa atual
(a em andamento; sem ela, a próxima pendente) · OS com todas as etapas concluídas sai do quadro · O cartão traz
nº, cliente, etapa atual + máquina, progresso, prazo e responsável · OS da empresa 99 nunca aparece para a 98.
Ver o quadro pede `producao.access`.

Status: 🧪

## UC-PCV-06 · Filtro igual no quadro e na linha · `must`

**Dado** o filtro "Travadas por arte" + responsável "Marcos" **Quando** troco do Quadro para a Linha **Então** o
filtro continua e os blocos visíveis são os das mesmas OS · "Limpar" volta tudo. O filtro inteiro (busca,
situação, responsável, setor) mora na URL e o servidor o devolve normalizado em `filtro`; a regra de passar é
uma só, `Pages/Producao/_lib/filtro.ts` (teste `tests/js/producao-filtro.test.ts`). A metade "na Linha" vale
quando a Linha do tempo existir (thread 07), importando o mesmo módulo.

Status: 🧪

## UC-PCV-07 · O PCP move a OS de coluna à mão · `should`

**Dado** a OS no setor Impressão **Quando** o PCP arrasta o cartão para a coluna Acabamento, ou usa ⋮ › "Mover
para Acabamento" (teclado) **Então** a OS muda de coluna · o histórico da OS ganha "Moveu para Acabamento (à mão)"
com o nome de quem moveu · vale para qualquer setor da empresa, para frente ou para trás, como no protótipo.
Mover só troca a coluna: nenhuma etapa, apontamento ou situação muda. A coluna manual some quando o operador
inicia ou conclui uma etapa depois do movimento. Pede `producao.etapa.mover`; OS de outra empresa → 404.

**Diverge do caso do Cowork:** `Producao.casos.md` pede também que o apontamento aberto na etapa anterior seja
fechado com motivo "movida pelo PCP". Não entra: o protótipo não fecha apontamento, e a coordenação escolheu
seguir o protótipo (UI-0029). Fica registrado no `_saida-06.md`.

Status: 🧪

## UC-FLX-10 · Alocar quem e quando começa, pelo cartão · `must`

**Dado** a etapa pendente de uma OS no quadro **Quando** o gestor (com `producao.etapa.mover`) escolhe outro
responsável e outro início **Então** o cartão passa a mostrar a pessoa e o horário novos, e o fim previsto mantém
a duração da etapa · Sem a permissão, o botão não aparece e a lista de pessoas vem vazia.

Status: 🧪

## UC-FLX-13 · Simular a bobina com as OS marcadas · `must`

**Dado** OS da comunicação visual com peças medidas numa mídia **Quando** o gestor marca as OS e abre "Simular
bobina" **Então** escolhe a mídia, cadastra a largura útil se faltar, e vê as faixas desenhadas com os metros e a
perda do arranjo contra imprimir uma a uma · OS sem peça medida não pode ser marcada.

Status: 🧪
