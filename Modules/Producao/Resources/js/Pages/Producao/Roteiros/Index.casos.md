---
id: modules-producao-pages-producao-roteiros-index-casos
casos: Produção · Roteiros · /producao/roteiros
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do gestor + critério de aceite verificável (Dado/Quando/Então)
por_que: roteiro fora da regra vira OS com etapas que ninguém treina; roteiro de outra empresa visível é vazamento Tier 0.
owner: wagner
last_run: "2026-10-20"
last_run_ci: "_pendente_ — o trio nasce na thread 03. O veredito entra quando a lane verticais-pest rodar o ProducaoRoteirosContratoTest."
---

# Casos de Uso & Aceite — Produção · Roteiros (`/producao/roteiros`)

> **Âncora:** `prototipo-ui/cowork/Wagner/cowork-inbox/producao/Producao.casos.md` (UC-PCV-13). Os UCs não derivam do `.tsx`.

---

## UC-PCV-13 · No máximo 2 trilhas · `must`
- **Persona:** gestor de produção.
- **Aceite:** O roteiro aceita até 2 trilhas em paralelo, e elas sempre terminam numa etapa de junção nomeada · Uma 3ª trilha ou trilha sem junção é recusada ao salvar. A junção é a etapa logo depois do bloco; nome diferente também é recusado.
- **Tenant:** roteiro da empresa 98 não aparece nem é alterado pela 99.
- **Teste:** `Modules/Producao/Tests/Feature/ProducaoRoteirosContratoTest.php`.
- **Status: ⬜** — teste escrito, aguarda a lane MySQL.

---

## Etapas da OS vêm do roteiro do produto (US-PCP-026 · UC-FLX-07)

> **Âncora:** US-PCP-026 do `memory/requisitos/Producao/SPEC.md` e o UC-FLX-07 do catálogo de fluxos
> (`app/Domain/Fsm/Modelos/CasosDoPrototipo.php:60`: *"Copia o roteiro de etapas do produto para a OS"*;
> regra *"Aprovado → Cria a OP com o roteiro do produto"* em `CatalogoModelos.php:156`). Nesta entrega é o
> endpoint que liga produtos ao roteiro e a cópia na aprovação; o seletor na tela ainda não existe.
> Prova: `ProducaoCriarOsDaVendaContratoTest`.

## UC-PCROT-01 · A OS nasce com as etapas do roteiro do produto · `must`

**Dado** o roteiro Impressão (Roland, 1,5 h) → Laminação (0,5 h) → Recorte (1 h), e o produto da venda ligado a
ele pela tela de Roteiros **Quando** a venda é aprovada **Então** a OS do item tem as três etapas nessa ordem, a
Impressão no posto Roland, e cada previsão dura o tempo da etapa, uma começando quando a anterior termina.
Ligar o produto a este roteiro o tira do roteiro em que estava.

Status: 🧪

## UC-PCROT-02 · Produto sem roteiro não vira OS · `must`

**Dado** a venda com um item ligado a roteiro e outro sem **Quando** é aprovada **Então** só o item com roteiro
ganha OS. Não há roteiro padrão da empresa: é o comportamento de antes.

Status: 🧪

## UC-PCROT-03 · Editar o roteiro não muda a OS já criada · `must`

**Dado** a OS criada com as etapas do roteiro **Quando** o gestor troca as etapas do roteiro **Então** o roteiro
passa a ter as etapas novas e a OS segue com as etapas, os postos e as previsões do dia da aprovação.

Status: 🧪

## UC-PCROT-04 · Roteiro e produto de outra empresa nunca entram · `must`

**Quando** a empresa 98 tenta ligar produto ao roteiro da 99 **Então** 404; ligar produto da 99 ao roteiro dela
é recusado. **Dado** um vínculo da 98 apontando roteiro da 99 **Quando** a venda é aprovada **Então** nenhuma OS
nasce. A 99 não cria OS a partir da venda da 98.

Status: 🧪

- **[BACKLOG]** Seletor de produtos no roteiro, na tela (`Index.tsx` — charter + gate visual).
- **[BACKLOG]** Roteiro padrão da empresa para produto sem roteiro (decisão [W]).

## Trilha do tempo
- 2026-10-16 · [CL] thread 03 do playbook de Produção — trio nasce junto (charter + casos + teste).
- 2026-10-20 · [CL] UC-PCROT-01..04 — o produto se liga ao roteiro e a OS copia as etapas na aprovação (US-PCP-026 · UC-FLX-07).
