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
