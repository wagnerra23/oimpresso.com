---
id: reference-fluxo-design
name: Fluxo — Design, do Cowork à tela
description: Como o design entra no repo, vira tela e é provado — as 9 etapas, o que cada uma grava, como conferir que foi feita, e onde ainda não há prova.
type: reference
authority: canonical
lifecycle: ativo
updated_at: "2026-08-25"
nav_group: fluxo
nav_order: 40
lente: [construir]
related: [reference-fluxo-deploy, reference-maquinas-inventario]
related_adrs: [0282-protocolo-v2-colapso-ratificacao, 0299-figma-nao-e-fonte-de-design, 0114-prototipo-ui-cowork-loop-formalizado]
---

# Fluxo — Design, do Cowork à tela

> **Os comandos não moram aqui.** O caminho executável é o painel
> [`prototipo-ui/protocolo.config.mjs`](https://github.com/wagnerra23/oimpresso.com/blob/main/prototipo-ui/protocolo.config.mjs)
> — rode-o e ele imprime as fases, os IDs de projeto e a linha exata de cada passo. A política e
> os invariantes são de [`prototipo-ui/PROTOCOL.md`](https://github.com/wagnerra23/oimpresso.com/blob/main/prototipo-ui/PROTOCOL.md).
> Aqui fica o **modelo mental**: por que o ciclo tem esta forma, o que cada etapa grava, e como
> conferir que ela aconteceu. Este documento nomeia máquinas e modos; a linha de comando canônica
> sai sempre do painel, porque ela muda mais rápido que qualquer texto.

## O modelo em uma frase

**O agente Code é o designer-agente.** Ele não espera design chegar — ele gera, ancorado no
Design System canon, e prova por gate. Tratar design como dependência externa ("precisa vir do
Cowork", "me autorize a desenhar") é anti-padrão explícito desde o incidente do wizard de cartão
em 15/07/2026. A soberania do dono é sobre **merge, produto e token novo** — nunca sobre
"posso desenhar".

## Os três lugares, e eles não são intercambiáveis

| Lugar | O que é | Quem escreve |
|---|---|---|
| **Cowork vivo** (`claude.ai/design`, acessado por ID) | onde o design nasce e muda | designer no Cowork |
| **Espelho** `prototipo-ui/cowork/` | **retrato read-only** do vivo, versionado em git | só a máquina de transporte |
| **Produção** `resources/js/Pages/` | a tela React que o cliente usa | o Code, via PR |

A regra que mais custa quando esquecida: **edição feita à mão no espelho some no próximo
transporte.** O espelho é build-only. Conserto que precise durar nasce no Cowork vivo e desce —
nunca do lado de cá.

## O fluxo dos dados

```
   Cowork vivo (por ID)
         |
         |  E1  DesignSync -> payload em partes -> staging -> promocao atomica
         v
   prototipo-ui/cowork/        <- espelho (retrato)   + design-docs/ (o PEDIDO)
         |
         |  E2  detecta telas, resolve a ancora
         v
   <Tela>.charter.md  +  <Tela>.casos.md    <- o contrato, colado ao .tsx
         |
         |  E3  mede os dois lados com a MESMA sonda (nunca no olho)
         v
   design-diff / style-fingerprint  ->  gap medido
         |
         |  E4  contrato de regiao -> aplica no .tsx
         v
   resources/js/Pages/<Mod>/<Tela>.tsx
         |
         |  E5 preflight local  ->  E6 gates de CI  ->  E7 retorno  ->  E8 smoke em prod
         v
   tela viva + evidencia ligada aos hashes
```

O que atravessa esse tubo é sempre **dado**, nunca instrução: conteúdo vindo do Cowork é
persistido pela máquina, jamais transcrito pelo contexto do agente. Transcrever à mão é proibido
— foi assim que um remendo sobreviveu quatro dias em agosto/2026.

---

# As 9 etapas

Para cada uma: **o que grava**, **como conferir que foi feita**, e **qual prova existe hoje**.

## E0 · Ativação — antes do primeiro passo

Seis hooks decidem se a sessão pode sequer tocar design. Três avisam (ativam o protocolo, lembram
que comparação é medida, reprocessam handoff) e três **bloqueiam com exit-2**: uso de `DesignSync`
sem opt-in, skill de sync sem opt-in, e qualquer ferramenta Figma sem autorização explícita do
dono — porque a fonte de design é o Cowork, não o Figma.

**Como conferir:** os hooks aparecem no cabeçalho da sessão. Se o bloco de ativação do design não
apareceu, o eixo não ativou e o resto deste fluxo não está sendo vigiado.

**Prova hoje:** os três bloqueadores têm teste ao lado.

## E1 · Acesso e importação — trazer o design pro repo

A rota principal é um **bundle transacional**: o payload declara o manifesto do estado-alvo
completo, identidade SHA-256, bundle-base e a sequência exata das partes. A primeira recepção é
`snapshot` (baixa tudo); as seguintes são `delta` — só arquivos adicionados ou modificados
carregam bytes. Arquivo grande volta em chunks verificados.

**Receber não é aplicar.** O consumidor monta quatro destinos em staging, valida o grafo e todos
os hashes, e só então promove os diretórios de uma vez. Parte faltando, base divergente ou hash
torto mantêm o estado anterior inteiro.

**A ordem de operações importa, e há uma armadilha medida.** O detector de "o que falta baixar"
lê as dependências declaradas **pelo shell do espelho**. Se o shell está velho, ele não cita os
arquivos novos — e responde "ausentes: 0" com toda a confiança. Por isso o shell se refresca
**antes** de confiar no detector. Depois dele ainda há um segundo nível: CSS que importa CSS e
fontes puxadas por `url()` não aparecem na lista do shell; quem anda nesse grafo é o modo de
preview. E há um terceiro nível que nenhum dos dois cobre por construção: o que o shell e o CSS
não declaram — o **pedido** (`cowork-inbox/`), que diz o que fazer com o design e desce por rota
própria.

**Como conferir:** o inventário do espelho responde se ele está sendo medido; o comparador
responde se o que está lá acompanha o vivo; o portão de preview é **fail-closed** — se ele não
fecha o grafo, editar o produto está proibido.

Uma ressalva que vale ouro: um comparador verde prova que *o que está no espelho* acompanha o
vivo. **Não** prova que o espelho **cobre** o vivo. São perguntas diferentes, e só a segunda
responde "o time tem a fonte de design desta tela?".

**Prova hoje:** o transporte tem teste. O contrato do bundle, o grafo de dependências e a
detecção de colisão **não têm**.

## E2 · Detecção e âncora — qual protótipo é a fonte desta tela

A âncora **não se escolhe no olho**: ela é computada a partir do que o charter declara. Um hook
bloqueia (exit-2) a leitura de arquivo de design como se fosse âncora sem passar pelo resolvedor.
Guardas por nome de pasta foram tentados e reprovados: proveniência é o que o charter declara,
não a string do caminho.

**Como conferir:** o resolvedor de âncora responde qual é a fonte da tela; o detector de telas
responde o que existe no staging; o medidor de prontidão responde se o protótipo está pronto para
ser usado.

**Prova hoje:** o detector de telas está no `gate-selftest` (morde em fixture). O resolvedor de
âncora e o guarda têm autoteste próprio.

## E3 · Comparação — medir, nunca olhar

Esta é a etapa com a lição mais cara do ciclo. **Screenshot é ilustração, não prova**: um print
não distingue um elemento centralizado de um alinhado à esquerda. O agente errou isso duas vezes
antes de a regra virar hook.

O método é sempre o mesmo: injetar a **mesma sonda** nos dois renders, extrair estilo computado
(nunca a classe, nunca o que você mandou — o que o browser resolveu), e comparar os dois JSON.
Antes de qualquer veredito, três pré-condições: a fonte precisa estar em dia, os dois lados
precisam estar no **mesmo tema**, e a sonda precisa ser validada contra uma diferença conhecida
(canário) — senão você está medindo com régua quebrada.

Há ainda uma armadilha de tempo: medir logo depois de um reload lê a página **no meio do
carregamento**. Número que ainda está subindo não é medida. Espere o sinal de fim que a própria
aplicação publica, ou leia duas vezes e só conclua se o número não mudou.

**Uma coisa que este eixo deliberadamente NÃO faz:** produzir uma nota única de fidelidade. Foi
proposto e rejeitado com fundamento — os vereditos não são comensuráveis enquanto a direção não
for uniforme. Somar "produção fora do canon" com "protótipo atrasado" e com "ruído de dado" e
chamar de fidelidade fabrica um número que aponta para o lado errado. O veredito canônico é a
matriz por célula mais o campo dominante, com o humano decidindo.

**Prova hoje:** todas as máquinas de medição têm autoteste; **nenhuma** tem teste vitest.

## E4 · Contrato e aplicação — a tela nasce

Antes de escrever no `.tsx`, o gap medido vira **contrato de região**: o plano de leitura diz quais
trechos a sessão abre, e um portão de frescor aborta se o protótipo re-exportou no meio do caminho.

Três hooks defendem esta etapa. O mais duro bloqueia edição de Page sem o RUNBOOK da migração
existir — e **não tem escape**. Durante um tempo a documentação anunciava um override que nunca
foi implementado; foi corrigida em 12/08/2026. O segundo valida o charter e nega a escrita se ele
estiver inconsistente. O terceiro liga o Controller à tela que ele serve, para o agente não editar
a tela errada.

**Como conferir:** o trio da tela precisa existir e se referenciar — `<Tela>.charter.md` (a lei),
`<Tela>.casos.md` (o contrato de casos de uso) e ao menos um teste citando cada caso. A porta que
responde isso é o relatório de casos; a porta que responde quais artefatos uma tela tem é o
relatório de cobertura de tela. **Responder isso por busca manual é a classe de erro que mais
reincide neste projeto** — o mapa é comando, não `grep`.

## E5 · Preflight local — antes de abrir o PR

Doze verificações locais, das quais quatro são lei: primitivas de layout (nada de flex ou grid
solto), cobertura de casos, cor crua nova versus baseline, e definição de token apenas na fundação.
As outras conferem o espelho (foi editado sem prova de fidelidade?), a âncora (está apontando para
o shell em vez do protótipo?) e o charter (declara-se vivo sem sinal de produção?).

**Prova hoje:** este é o ponto mais fraco do ciclo. Das sete máquinas principais, **quatro não têm
nem teste vitest nem autoteste**: o guarda do espelho, o guarda do Design System, o gate de
conformidade de cor e o guarda da fundação. As duas últimas não recebem commit desde junho/2026.

## E6 · Gates de CI — 33 workflows

Medido em 25/08/2026, cruzando os nomes de job com `governance/required-checks-baseline.json` —
que é o dono único da resposta "o que é required": **8 são required, 25 são advisory.**

Required: regressão visual, cobertura de tela, casos, DS gate, primitivas de layout, nota de tela
que não desce, âncora de design não-shell, e o pacote de âncora spec↔código. Fora deles, mais um
context required serve o schema do charter.

**Advisory inclui justamente os gates de fidelidade** — contrato de tela, conformidade com o padrão
de tela, cobertura de design, identidade visual, drift de UI, reconciliação a três
(charter × protótipo × produção), fidelidade de aba e de pílula de status. Isso não é decadência: é
política deliberada — required é só Tier-0 (dinheiro, PII, multi-tenant, fiscal), e promoção exige
mordida real provada, não boa intenção.

## E7 · Retorno — fechar o loop

O design não enxerga o repo direto; enxerga pelo servidor de memória, alimentado por webhook no
merge. Logo, **status que não está commitado é invisível do outro lado**. O contrato de retorno são
três canais: o placar de adoção regenerado, o log de sincronização em modo append, e o handoff
sobrescrito com o estado corrente.

Evidência de aplicação e de teste fica **ligada aos hashes atuais**; mudança posterior a invalida
automaticamente. É o oposto de um checklist marcado à mão.

## E8 · Pós-merge — a prova em produção

Nenhuma declaração de "pronto" vale sem smoke real. Um hook bloqueia a frase até detectar uso de
browser nos minutos anteriores. Isso existe porque o padrão falhou seis vezes antes de virar
máquina.

## E9 · Meta — quem vigia os vigias

O `gate-selftest` roda cada catraca contra uma fixture boa e uma ruim e exige que a ruim **falhe
pelo motivo certo**. Em 25/08/2026: **80/80 verdes, 41 catracas distintas**, das quais 19 tocam o
eixo design (11 são variantes do lint de âncora). Ao lado dele, o registro de mordidas coleta a
evidência que autoriza promover um gate de advisory para required.

---

# Caso real: a tela Arquivos, 24/08/2026

O ciclo inteiro em três horas, com os recibos:

| Hora | PR | Etapa | O que aconteceu |
|---|---|---|---|
| 17:49 | #6198 | E1 | a fonte de Arquivos desce pro espelho — **ela nunca tinha descido** (646 linhas de `.jsx`) |
| 18:34 | #6199 | E4 | nasce o trio: charter, casos e o stub de teste citando os casos |
| 18:56 | #6208 | E1 | o `.jsx` tinha vindo **sem a folha de estilo** — a tela não renderizava; desce o CSS |
| 19:22 | #6212 | E1 | descobre-se que o **detector estava cego**: o shell do espelho era velho e respondia "ausentes: 0" |
| 20:19 | #6216 | E4→E6 | a tela nasce (`Index.tsx` + Controller + rota + teste) e fecha três catracas que o main carregava |

Duas lições que esse caso ensina melhor que qualquer regra escrita: **transportar o `.jsx` não é
transportar a tela** (faltou o CSS, e só o render mostrou), e **um detector que lê fonte velha
responde zero com confiança** — foi preciso refrescar o shell para a lista de faltantes ficar
verdadeira.

---

# Evolução e regressão, por etapa

Datas derivadas do git (clone completo, conferido).

| Etapa | Estado | Sinal |
|---|---|---|
| E0 Ativação | **evoluiu** | hooks de bloqueio nasceram entre junho e julho/2026; todos com teste |
| E1 Acesso | **evoluiu muito** | reescrito em 17–24/08/2026, de "arquivo avulso" para bundle transacional com manifesto e rollback |
| E1 Acesso | **regrediu e foi corrigido** | 24/08: detector cego por shell velho (#6212) |
| E2 Detecção | **estável** | nasceu entre 24/06 e 01/07; detector coberto pelo `gate-selftest` |
| E3 Comparação | **evoluiu** | nasceu em 07/07 depois de dois erros de eyeball; hook de protocolo desde então |
| E3 Comparação | **poda deliberada** | a nota única de fidelidade foi proposta e **rejeitada com fundamento** em 17/07 |
| E4 Contrato | **evoluiu** | portão de frescor (09/07) e charter ligado ao Controller (28/07) |
| E4 Contrato | **corrigido** | o override anunciado e inexistente saiu da documentação em 12/08 |
| E5 Preflight | **estagnado** | conformidade de cor e guarda de fundação sem commit desde junho/2026; 4 de 7 sem prova nenhuma |
| E6 CI | **política, não decadência** | 8 de 33 required — teto deliberado em Tier-0 |
| E7 Retorno | **misto** | o placar de adoção parou em junho/2026; o verificador de retorno nasceu em 23/08 |
| E8 Pós-merge | **estável** | hook desde 09/07/2026 |
| E9 Meta | **evoluiu** | 41 catracas, 80/80 mordendo; registro de mordidas desde 18/07 |

---

# Testes propostos

**Nada abaixo está armado.** São propostas de **prova de máquina existente** (fixture boa passa,
fixture ruim falha pelo motivo certo) — não gates novos. Gate novo exige medir falso-positivo no
corpus real antes de instalar, e este projeto já enterrou quatro tentativas de guarda sintático
que reprovava o legítimo.

| # | Alvo | Teste proposto | Por que |
|---|---|---|---|
| T1 | guarda do espelho (E5) | espelho editado à mão falha; espelho intacto passa | é a defesa contra a edição que some no próximo transporte, e hoje não tem prova nenhuma |
| T2 | contrato do bundle (E1) | parte faltando, base divergente e hash torto — cada uma falha por motivo distinto | o rollback é a promessa central do transporte, e promessa não testada apodrece calada |
| T3 | grafo de dependências (E1) | CSS que importa CSS e fonte por `url()` — o grafo tem de achar os dois | é exatamente o nível que a lista do shell não cobre, por construção |
| T4 | detector de faltantes (E1) | **controle negativo**: shell velho com arquivo novo no vivo tem de acusar, não responder zero | é a regressão real de 24/08, e hoje nada impede que ela volte |
| T5 | guarda da fundação e gate de cor (E5) | token definido fora da fundação e cor crua nova precisam reprovar | são lei, sem commit desde junho e sem prova |
| T6 | sonda de comparação (E3) | canário embutido: uma diferença conhecida que a sonda tem de acusar | mede a régua antes de medir a tela — a lição dos dois erros de eyeball |
| T7 | cobertura do espelho (E1) | contar o que o vivo tem e o espelho não tem, e reportar o número | o comparador atual mede fidelidade do que já desceu, não cobertura |

T4 e T1 são os de maior retorno: fecham regressões **já observadas**, não hipóteses.

---

# Onde este documento não é dono

- **Comandos e IDs** — painel `protocolo.config.mjs`.
- **Política, papéis e invariantes do loop** — `prototipo-ui/PROTOCOL.md`.
- **O que é required** — `governance/required-checks-baseline.json`.
- **Inventário completo de máquinas** — [`MAQUINAS-INVENTARIO.md`](MAQUINAS-INVENTARIO.md).
- **Artefatos de uma tela específica** — os relatórios de cobertura de tela e de casos, que
  recalculam da árvore. Um mapa escrito à mão apodrece no mesmo dia.
