---
id: modules-producao-pages-producao-linha-index-casos
casos: Produção · Linha do tempo · /producao/linha
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do gestor + critério de aceite verificável (Dado/Quando/Então)
por_que: reprogramar para máquina que o operador não opera, ou por cima de outra OS, para a fábrica; ver a máquina de outra empresa é vazamento Tier 0.
owner: wagner
last_run: "2026-10-22"
last_run_ci: "_pendente_ — o veredito entra quando a lane MySQL rodar o ProducaoLinhaContratoTest."
---

# Casos de Uso & Aceite — Produção · Linha do tempo (`/producao/linha`)

> **Âncora:** `prototipo-ui/cowork/Wagner/cowork-inbox/producao/Producao.casos.md` (UC-PCV-08, 09, 30) com as
> regras de Fluxos do `main` (UC-FLX-06, UC-FLX-08, UC-FLX-10, D3, US-PCP-028), que o protótipo passou a seguir no
> #9763. Os UCs não derivam do `.tsx`. Teste: `Modules/Producao/Tests/Feature/ProducaoLinhaContratoTest.php`.

## UC-PCV-08 · Linha do tempo por máquina · `must`

**Dado** etapas programadas no dia **Então** cada máquina mostra a carga em horas contra a capacidade do posto (a
mesma conta do Painel) · o bloco travado por arte ou por "Aguardando material" aparece tracejado · o bloco que
termina depois do fim do turno da empresa, ou depois da entrega prometida, tem borda de estouro · "Próximo dia
útil" mostra o dia seguinte do calendário de produção, pulando folga e feriado · etapa de outro dia ou de outra
empresa não aparece. Ver a linha pede `producao.access`.

Status: 🧪

## UC-PCV-09 · Gargalo com sugestão aplicável · `should`

**Dado** uma máquina acima da capacidade e outra do mesmo setor com folga **Então** a sugestão diz as duas cargas
e propõe mover a menor etapa pendente que resolve, para a máquina cujo responsável a opera, no mesmo horário ou no
primeiro livre depois dele · **Quando** aplico **Então** a etapa muda de máquina e a de origem sai do vermelho ·
"Desfazer" volta a etapa para onde estava · máquina dentro da capacidade não gera sugestão. Regra simples (D5):
nunca move sozinho.

Status: 🧪

## UC-PCV-30 · Reprogramar arrastando na linha do tempo · `must`

**Dado** uma etapa pendente **Quando** o PCP a arrasta (15 em 15 min) ou usa Alt+setas **Então** o horário muda,
e levar para outra linha troca a máquina dentro do mesmo setor · outro setor é recusado sem mudar nada ·
horário que choca com outra etapa da máquina é recusado e diz com qual OS · etapa em andamento ou concluída
não se move · passar do fim do turno move e avisa · trocando para máquina com operadores cadastrados que não
incluem o responsável, a tela pergunta quem faz e só grava com um operador dela (UC-FLX-08) · cada
reprogramação vira evento e avisa o responsável no app. Pede `producao.etapa.mover`; etapa de outra empresa → 404.

Status: 🧪
