---
id: documentacao-programa-casos
casos: Programa de documentação · Trilha D · /documentacao/programa
irmaos: Programa.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela que descreve o programa anti-drift é a primeira que apodrece se virar cópia.
owner: wagner
last_run: null
---

# Casos de Uso & Aceite — Programa de documentação (`/documentacao/programa`)

> **Âncora:** `PLANO-MESTRE.md` § Trilha D (D.2 ciclo · D.5 ondas · D.6 unidade de trabalho · D.8 DoD).
> Os UC derivam do **plano**, nunca do `.tsx`.
>
> ⚠️ **Força do veredito:** nenhum teste escrito ainda — todos nascem **❌ não coberto**.
> Não afirmar cobertura antes de teste vermelho→verde no CT100.

## Rastreabilidade

| UC | Caso de uso | Prio | Contrato | Teste | Status |
|----|-------------|------|----------|-------|--------|
| UC-PROGDOC-01 | Estado de execução vem do MCP, nunca do markdown | must | Trilha D §D.3 | — | ❌ |
| UC-PROGDOC-02 | O texto é do dono no git — a tela não guarda cópia | must | ADR 0239 · Trilha D §D.3 | — | ❌ |
| UC-PROGDOC-03 | Tela é read-only: nada na UI altera plano, onda ou task | must | charter §Non-Goals | — | ❌ |
| UC-PROGDOC-04 | A vista é linkável e a navegação é underline, não pill | should | ADR 0286 · DS | — | ❌ |
| UC-PROGDOC-05 | Rota autenticada, sem segredo e sem dado de tenant | must `[T0]` | ADR 0093 | — | ❌ |

---

## UC-PROGDOC-01 · Estado de execução vem do MCP, nunca do markdown · `must`

- **Persona:** [W] abre a tela pra saber em que onda a trilha está. Se a resposta vier de um markdown
  editado à mão, ela estará errada no dia seguinte — e ninguém saberá.
- **Aceite:** Dado que a task `US-INFRA-048` mudou de `doing` pra `done` no MCP · Quando abro a vista
  **Ondas** · Então o estado exibido acompanha a task, e **não** existe string de status
  (`doing`/`done`/`em execução`) fixada no `.tsx` nem lida do markdown do plano.
- **Contrato:** Trilha D §D.3 ("Execução · tasks MCP · `todo/doing/done` nunca duplicado aqui") +
  ADR 0294 (1 plano = 1 registro) + ADR 0070.
- **Regressão que defende:** o protótipo Cowork marca D0 como "em execução" **literalmente**. Portar
  esse literal cria a segunda fonte de verdade que a Trilha D existe pra impedir.
- **Status: ❌**

---

## UC-PROGDOC-02 · O texto é do dono no git — a tela não guarda cópia · `must`

- **Persona:** alguém corrige a § Trilha D num PR. A tela precisa mudar junto, sem segundo PR.
- **Aceite:** Dado um merge que altera `PLANO-MESTRE.md` § Trilha D · Quando o sync acontece e eu
  recarrego a página · Então o conteúdo exibido reflete o arquivo novo · E nenhum parágrafo do plano
  aparece como literal no componente.
- **Contrato:** ADR 0239 (git SSOT) + Trilha D §D.3 ("ponteiro > cópia").
- **Regressão que defende:** a tela vira folheto: bonita, citada em onboarding, e mentindo há três meses.
- **Status: ❌**

---

## UC-PROGDOC-03 · Tela é read-only · `must`

- **Aceite:** Dado qualquer vista aberta · Quando percorro a página inteira por teclado · Então não
  existe controle que grave (sem checkbox de DoD clicável, sem "marcar onda", sem editar) · E as
  únicas ações são navegar entre vistas, voltar pra `/documentacao` e abrir o plano no git.
- **Contrato:** charter §Non-Goals + §Anti-hooks.
- **Regressão que defende:** um "só um checkbox pra marcar o que já fizemos" transforma a tela em
  registro paralelo — e o plano deixa de ser o dono.
- **Status: ❌**

---

## UC-PROGDOC-04 · A vista é linkável e underline · `should`

- **Aceite:** Dado que escolho a vista **Ondas** · Então a URL vira `?vista=ondas` e o link colado num
  handoff abre exatamente ali · E as abas usam underline-active em accent (`TabBar` do DS), nunca pill.
- **Contrato:** contrato de tela `programa-doc.contract.json` (seções + copy) + guia do DS
  ("tabs underline-active in primary, never pill-active").
- **Regressão que defende:** o seletor volta pro canto do header e ninguém acha a vista — foi
  literalmente o feedback de [W] em 2026-08-06 sobre a lente da Documentação.
- **Status: ❌**

---

## UC-PROGDOC-05 · Rota autenticada, sem segredo, sem tenant · `must` `[T0]`

- **Aceite:** Dado um visitante não autenticado · Quando pede `/documentacao/programa` · Então é
  barrado pelo mesmo middleware de `/documentacao` · E o HTML servido não contém host, credencial,
  token nem `business_id` — máquinas aparecem por nome e ponteiro pro cofre.
- **Contrato:** ADR 0093 + Trilha D §D.3 ("segredo só por referência ao Vaultwarden").
- **Regressão que defende:** documentação de infraestrutura é o lugar mais tentador do repo pra colar
  um IP com senha ao lado.
- **Status: ❌**

---

## Backlog de casos (entram quando houver teste que os defenda)

- **[BACKLOG]** KPI "ondas n/11" derivado das tasks, não contado na mão.
- **[BACKLOG]** Estação do ciclo linkável (`?vista=ciclo&estacao=07`) pra citar num incidente.
- **[BACKLOG]** Vista **Pronto** mostra o recibo real do `documentation-loop` no lugar do estado estático.

---

## Refs

- Charter (lei): [`Programa.charter.md`](Programa.charter.md)
- Plano (âncora): `memory/requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md` § Trilha D
- Patch do plano: `cowork-inbox/PLANO-MESTRE-trilha-d-ciclo-completo.md`
- Protótipo: `programa-doc-page.jsx` (Cowork) · rota `programa-doc`
- Contrato: `prototipo-ui/contrato/programa-doc.contract.json`
- Gate: `scripts/casos-coverage-guard.mjs` (ADR 0264)
