---
id: documentacao-programa-casos
casos: Programa de documentação · Trilha D · /documentacao/programa
irmaos: Programa.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
owner: wagner
last_run: "2026-09-23"
---

# Casos de uso & aceite — Programa de documentação (`/documentacao/programa`)

> **Âncora (ordem de fonte 1):** [`PLANO-MESTRE.md` § Trilha D](../../../../memory/requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md) (D.2–D.7) + `US-INFRA-048` no [Infra SPEC](../../../../memory/requisitos/Infra/SPEC.md).
> Os casos derivam do **plano**, nunca do `.tsx` (que ainda não existe) nem da Blade.
>
> **Por que não há `## UC-` aqui ainda.** O casos-gate exige que todo UC declarado seja citado por teste (ADR 0264 G-2). O `.tsx` só nasce na thread 02, e esta thread (01) só pode tocar `resources/js/Pages/Documentacao/`, onde teste não conta. Declarar os UC agora produziria 5 órfãos no gate required, ou stubs `fixme` que só provam presença (LC-11).
> Por isso os casos entram como `[BACKLOG]`: prosa visível, sem id, sem gate. Viram `## UC-PROGDOC-NN` no PR da thread 02, **junto** com os testes que os citam. Os ids `UC-PROGDOC-01..05` ficam reservados pra isso (a proposta do Cowork e o `PEDIDO-CL-programa-doc-react.md` já os usam).
>
> A coluna **"hoje, na Blade"** mostra o que o `tests/Feature/DocumentacaoRouteTest.php` já defende na rota viva. Não cita id de UC, logo não conta como cobertura deste arquivo.

## Rastreabilidade (pré-UC)

| Id reservado | Caso | Prio | Contrato | Hoje, na Blade |
|---|---|---|---|---|
| UC-PROGDOC-01 | Estado de execução da onda tem uma fonte só | must | D.2 · ADR 0294 (`metodo-dual-track`) · ADR 0070 | lido da linha do `## Status vivo` — teste "a linha da Trilha D no plano continua legível…" |
| UC-PROGDOC-02 | O texto é do dono no git — a tela não guarda cópia | must | D.2 ("ponteiro > cópia") · ADR 0239 | teste "a tela do programa NAO carrega a lista de estacoes escrita a mao" |
| UC-PROGDOC-03 | A tela é read-only | must | D.6 ("não decide conteúdo nem merge") | sem teste |
| UC-PROGDOC-04 | A vista é linkável e a navegação é underline | should | charter §Automation hooks · RUNBOOK-contrato-de-tela | sem teste |
| UC-PROGDOC-05 | Rota autenticada, sem segredo, sem dado de tenant | must `[T0]` | D.2 ("exige autenticação"; "segredo só por referência ao Vaultwarden") · D.7 · ADR 0093 | teste "exige login nas tres rotas de documentacao" (só a metade do login) |

---

## [BACKLOG] O estado de execução da onda vem de uma fonte só, e a tela não o fixa

- **Persona:** [W] abre a tela pra saber em que onda a trilha está. Se a resposta estiver escrita à mão num lugar que ninguém atualiza, ela estará errada no dia seguinte — e ninguém vai saber.
- **Aceite:** Dado que o estado da Trilha D mudou na sua fonte · Quando abro a vista **Ondas** · Então a tela mostra o estado novo · E nenhuma string de status (`doing`/`done`/`em execução`) está fixa no componente.
- **Contrato:** D.2 do plano + ADR 0294 + ADR 0070.
- **⚠️ Qual é "a fonte" é decisão [W], não deste arquivo** (charter §Perguntas abertas, 1). A D.2 diz *tasks MCP*; a Blade e o teste verde dizem *a linha do `## Status vivo`*. Pela regra de precedência, hoje ganha o teste verde. O aceite acima vale para qualquer das duas; a thread 02 fixa a fonte quando [W] responder.
- **Regressão que defende:** o protótipo Cowork marca D0 "em execução" como literal. Portar esse literal cria uma segunda fonte de verdade.

## [BACKLOG] O texto é do dono no git — a tela não guarda cópia

- **Persona:** alguém corrige a § Trilha D num PR. A tela precisa mudar junto, sem um segundo PR.
- **Aceite:** Dado um merge que altera `PLANO-MESTRE.md` § Trilha D · Quando o arquivo chega ao deploy e recarrego a página · Então o conteúdo mostrado reflete o arquivo novo · E nenhum título de estação, onda ou item da DoD aparece como literal no componente.
- **Contrato:** D.2 ("Fatos técnicos… ponteiro > cópia") + ADR 0239 (git SSOT).
- **Regressão que defende:** a tela vira folheto — bonita, citada no onboarding, e errada há três meses.

## [BACKLOG] A tela é read-only

- **Aceite:** Dado qualquer vista aberta · Quando percorro a página inteira pelo teclado · Então não existe controle que grave (sem checkbox de DoD clicável, sem "marcar onda", sem editar) · E as únicas ações são trocar de vista, voltar pra `/documentacao` e abrir o plano no git.
- **Contrato:** D.6 — o batimento "detecta e oferece trabalho, mas não decide conteúdo nem merge"; a mudança do plano é PR + merge de [W] (D.4, estação 8).
- **Regressão que defende:** um "só um checkbox pra marcar o que já fizemos" transforma a tela num registro paralelo, e o plano deixa de ser o dono.

## [BACKLOG] A vista é linkável e a navegação é underline

- **Aceite:** Dado que escolho a vista **Ondas** · Então a URL passa a ter `?vista=ondas`, e o link colado num handoff abre exatamente ali · E as abas usam underline-active em accent, nunca pill.
- **Contrato:** charter §Automation hooks + guia do DS (tabs underline-active) + contrato de tela (`RUNBOOK-contrato-de-tela.md`), quando ele existir em `governance/design/contracts/`.
- **Regressão que defende:** o seletor de vista vai pro canto do header e ninguém o acha.

## [BACKLOG] Rota autenticada, sem segredo, sem dado de tenant `[T0]`

- **Aceite:** Dado um visitante não autenticado · Quando pede `/documentacao/programa` · Então é barrado pelo mesmo middleware de `/documentacao` · E o HTML servido a um usuário autenticado não contém host, credencial, token nem `business_id` — máquinas aparecem por nome e ponteiro pro cofre.
- **Contrato:** D.2 (visão humana "exige autenticação"; "segredo só por referência ao Vaultwarden") + D.7 ("nenhum documento da trilha carrega segredo em claro") + ADR 0093.
- **Regressão que defende:** documentação de infraestrutura é o lugar mais tentador do repo pra colar um IP com a senha do lado.

---

## Mais tarde (sem prioridade)

- **[BACKLOG]** Estação do ciclo linkável (`?vista=ciclo&estacao=07`) pra citar num incidente.
- **[BACKLOG]** A vista **Pronto** mostra o recibo real do `documentation-loop` em vez de estado estático.

---

## Refs

- Charter (lei): [`Programa.charter.md`](Programa.charter.md)
- Plano (âncora): `memory/requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md` § Trilha D
- Rota viva: `app/Http/Controllers/DocumentacaoController.php::programa()` + `resources/views/documentacao/programa.blade.php`
- Testes da rota viva: `tests/Feature/DocumentacaoRouteTest.php`
- Proposta original do Cowork: `prototipo-ui/cowork/Wagner/cowork-inbox/programa-doc/Programa.casos.md`
- Protótipo: `prototipo-ui/cowork/Wagner/programa-doc-page.jsx`
- Gate: `scripts/casos-coverage-guard.mjs` (ADR 0264)
