---
id: modules-producao-pages-producao-apontamento-index-casos
casos: Producao · Apontamento do operador · /producao/apontamento
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o apontamento vira custo real e fecha etapa da OS. Errar a etapa aponta tempo no lugar errado; errar a empresa expõe produção alheia; duplicar o envio offline dobra o custo.
owner: wagner
last_run: "2026-10-15"
last_run_ci: "_pendente_ — o trio nasce na thread 04. O veredito por UC entra quando a lane verticais-pest rodar; até lá o Status é 🧪."
---

# Casos de Uso & Aceite — Produção · Apontamento (`/producao/apontamento`)

> **Âncora:** `prototipo-ui/cowork/Wagner/cowork-inbox/producao/Producao.casos.md` (UC-PCV-19..23) e
> `Producao-celular.casos.md` (UC-PCV-31, 34, 35, 36 · emenda E04), mais as decisões [W] de 2026-10-15
> (D-PONTO e fila offline). Os UCs não derivam do `.tsx`. Provas: `ProducaoApontamentoContratoTest` (regra)
> e `ProducaoApontamentoTelaTest` (HTTP).

---

## UC-PCV-19 · Minhas etapas de hoje · `must`

**Dado** o operador Diego **Quando** abre o app **Então** vê só as etapas atribuídas a ele hoje, na ordem
programada, com máquina e horário, e não vê etapas de outro operador.

Status: 🧪

## UC-PCV-20 · QR e cronômetro · `must`

**Quando** lê o QR da OS **Então** abre a etapa dele naquela OS, e Iniciar/Pausar/Concluir gravam
apontamentos append-only com usuário, máquina e horário; o cronômetro tem `role="timer"` e botões ≥ 44px.

Status: 🧪

## UC-PCV-21 · Fechar a etapa passa adiante · `must`

**Quando** o operador conclui a etapa **Então** a OS vai para a próxima etapa e o responsável seguinte é avisado.

Status: 🧪

## UC-PCV-22 · Cronômetro esquecido · `must`

**Dado** um apontamento aberto às 18h **Então** o sistema fecha às 18h marcado como automático e avisa o PCP,
e o operador pode pedir correção com justificativa (o original fica).

Status: 🧪

## UC-PCV-23 · Sem internet · `should`

**Dado** o celular offline **Quando** o operador aponta **Então** o toque fica guardado com a hora real e,
ao voltar a rede, é enviado uma vez só.

Status: 🧪

## UC-PCV-31 · Dois cronômetros: máquina e pessoa · `must`

**Dado** Diego com a Roland imprimindo **Quando** inicia o acabamento **Então** os dois ficam abertos, a hora
da Roland não conta como hora do Diego, e ele não abre um 2º apontamento de pessoa.

Status: 🧪

## UC-PCV-34 · QR de etapa alheia não aponta · `must`

**Quando** Diego lê a etiqueta da etapa do Felipe **Então** vê onde está e com quem, e nenhum apontamento é gravado.

Status: 🧪

## UC-PCV-35 · Deixar a máquina rodando · `must`

**Quando** o operador segue para outra etapa **Então** o apontamento da máquina continua aberto, sem pausa.

Status: 🧪

## UC-PCV-36 · Fecha pela saída do ponto · `must`

**Dado** apontamento de pessoa aberto e saída no ponto às 17:52 **Então** fecha às 17:52 marcado automático;
sem saída, fecha no fim do turno; horas de pessoa no dia acima da presença + 30 min geram pendência ao gestor;
o Ponto não é alterado.

Status: 🧪
