---
id: resources-js-pages-ponto-relatorios-index-casos
casos: Catálogo de relatórios do ponto · /ponto/relatorios
irmaos: Index.charter.md (lei) · SDD-espelho-e-jornada-v1.0.md §5.3 F8 + §6.5 (contrato)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é a vitrine de compliance do módulo — e a única tela cujo contrato é sobre o que ele NÃO entrega.
owner: wagner
last_run: "2026-08-02"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Ponto · MySQL)"
---

# Casos de Uso & Aceite — Catálogo de relatórios

> **Âncora:** `CU-PONTO-14` (§6.5) e o fluxo **F8** (§5.3) do
> [SDD](../../../../memory/requisitos/Ponto/SDD-espelho-e-jornada-v1.0.md) + US-PONTO-006/009
> (ambas `_pendente_` no SPEC). Fonte 4 (Delphi) **ausente** — SDD §0.1.
>
> 📌 **O contrato desta tela é a honestidade dela.** O `RelatorioController@gerar()` é
> `abort(501)` **para qualquer chave**, inclusive `espelho` (o PDF do espelho sai por outra
> rota — F3, `EspelhoController@imprimir`). O valor do caso de uso aqui não é "o relatório
> funciona", é **"a tela não promete o que não entrega"** — a US-PONTO-009 (AEJ) é a
> prioridade regulatória #1 do audit sênior, e uma tela que insinua que o AEJ existe é pior
> que uma tela que declara que ele falta.
>
> ⚖️ **Força do veredito:** lane `PHP / Pest (Ponto · MySQL)` — **advisory**: fica vermelha
> visível, não bloqueia merge (SDD §8.1).
>
> **Status:** ✅ verde na lane · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ vermelho.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-RELIDX-01 | Relatório não implementado aparece marcado como indisponível | should | `CU-PONTO-14` + F8 | `RelatorioCatalogoContratoTest` | 🧪 sem veredito |
| UC-RELIDX-02 | Nenhum relatório do catálogo entrega download sem aviso | should | `CU-PONTO-14` + F8 | `RelatorioCatalogoContratoTest` | 🧪 sem veredito |
| UC-RELIDX-03 | Relatório por colaborador não gera sem um escolhido | must | `CU-PONTO-14` + Portaria 671 Art. 85 | `RelatorioCatalogoContratoTest` | 🧪 sem veredito |
| UC-RELIDX-04 | Relatório marcado disponível leva ao gerador, não a "não implementado" | must | `CU-PONTO-14` + F3/F8 | `RelatorioCatalogoContratoTest` | 🧪 sem veredito |
| UC-RELIDX-05 | Relatório de colaborador de outro empregador é recusado | must `[T0]` | `CU-PONTO-12` + ADR 0093 | `RelatorioCatalogoContratoTest` | 🧪 sem veredito |

**[BACKLOG]:**

- `[BACKLOG]` **AEJ (Portaria MTP 671/2021 Anexo VI)** — US-PONTO-009, `_pendente_`. É a
  prioridade regulatória #1 e exige **revisão da Eliana [E] + ADR formal antes de codar** (o
  SPEC marca como pré-requisito duro). Não vira UC agora: US sem código gera UC órfão e o
  `casos-gate` G-2 pune ([proibicoes §5](../../../../memory/proibicoes.md) 2026-07-16).
- `[BACKLOG]` AFD legacy (Portaria MTE 1.510/2009) — US-PONTO-006, mesma situação.
- ~~`[BACKLOG]` O `espelho`, único `disponivel: true`, também cai em 501 por esta rota.~~
  **RESOLVIDO em 2026-08-28** — e o registro fica porque a forma dele acertou: a corrida de
  02/08 declarou a suspeita **sem afirmar**, dizendo que não tinha medido o clique. Estava
  certa. Medido em 28/08: `gerar()` era `abort(501)` para **qualquer** chave, inclusive a
  única habilitada. Decisão [W] no mesmo dia: **o espelho sai por colaborador**, nunca em
  lote — o catálogo passa a ser atalho para o gerador que já existe (F3), com o colaborador
  obrigatório. Virou `UC-RELIDX-03/04/05`.

---

## UC-RELIDX-01 · Relatório não implementado aparece marcado como indisponível · `should`

- **Persona:** RH/DP procurando o arquivo que a fiscalização pediu. Precisa saber, **antes de
  clicar**, se o sistema entrega — e não descobrir no meio de uma auditoria do MTE.
- **Aceite:** Dado o catálogo de relatórios · Quando abro `/ponto/relatorios` · Então cada
  relatório traz um indicador de disponibilidade, e os que **não** estão implementados vêm
  marcados como indisponíveis.
- **Teste:** `Modules/Ponto/Tests/Feature/RelatorioCatalogoContratoTest.php` — `UC-RELIDX-01`.
- **Contrato:** `CU-PONTO-14` (SDD §6.5, *"o catálogo de relatórios não promete o que não
  entrega"*) · F8 (§5.3) · o próprio docblock do controller (*"chave `disponivel`: se o
  relatório já foi implementado"*).
- **Regressão que defende:** a flag é **mantida à mão** — o docblock diz literalmente *"quando
  cada um for implementado, trocar `false` por `true`"*. Nada liga a flag ao
  `ReportService` real. O modo de falha natural é o **inverso do habitual**: alguém marca
  `true` "porque vai implementar em seguida", e a tela passa a prometer AEJ que não existe.
  Este UC fixa que **pelo menos um** relatório não-implementado siga declarado como tal,
  travando a marcação em massa.
- **Nota de escrita:** o assert **não** enumera as 7 chaves indisponíveis de hoje — isso
  transformaria toda implementação legítima (que vira uma `false` em `true`) numa reprova. Ele
  verifica a **forma do contrato**: todo item tem o indicador, e o conjunto dos indisponíveis
  não é vazio enquanto `gerar()` for 501.
- **Status: 🧪 sem veredito.**

---

## UC-RELIDX-02 · Nenhum relatório do catálogo entrega download sem aviso · `should`

- **Persona:** a mesma. O dano aqui não é o erro — é o **silêncio**: pedir o AFD numa
  fiscalização, receber um arquivo vazio ou uma página de erro crua, e só perceber depois.
- **Aceite:** Dado um relatório marcado como indisponível · Quando peço a geração dele ·
  Então recebo uma recusa **explícita** — nunca um arquivo, nunca uma resposta de sucesso.
- **Teste:** `RelatorioCatalogoContratoTest.php` — `UC-RELIDX-02`.
- **Contrato:** `CU-PONTO-14` (*"nenhum botão leva a erro 501 sem aviso"*) · F8
  (*"`RelatorioController@gerar()` é `abort(501)` para qualquer chave"*).
- **Regressão que defende:** o par catálogo↔geração pode divergir em duas direções, e as duas
  machucam: a flag vira `true` sem o gerador existir (promessa falsa), ou o gerador passa a
  devolver algo (um PDF vazio, um CSV sem linhas) em vez de recusar. Este UC ancora o lado da
  **recusa explícita**: enquanto não houver implementação, a resposta tem de ser uma negativa
  reconhecível, não conteúdo.
- **Nota de escrita:** o assert é *"não é sucesso"*, não *"é exatamente 501"*. Trocar o 501 por
  um 404 com mensagem, ou por um redirect com toast, é correção **legítima** de UX — e um
  assert cravado no 501 reprovaria a melhoria.
- **Status: 🧪 sem veredito.**

---

## UC-RELIDX-03 · Relatório por colaborador não gera sem um escolhido · `must`

- **Persona:** RH/DP fechando folha. Pede o espelho no catálogo sem reparar que não escolheu
  ninguém no filtro.
- **Aceite:** Dado um relatório que sai por colaborador · Quando peço a geração sem informar
  colaborador · Então recebo uma **recusa** — nunca um documento escolhido por padrão.
- **Teste:** `RelatorioCatalogoContratoTest.php` — `UC-RELIDX-03`.
- **Contrato:** `CU-PONTO-14` · **Portaria MTP 671/2021 Art. 85** (o espelho é peça de
  fiscalização, e o de outra pessoa não serve).
- **Regressão que defende:** o modo de falha é *silencioso e plausível* — alguém torna
  `colaborador` opcional "pra facilitar", o gerador assume um default, e o operador leva pra
  auditoria o espelho **de outra pessoa**. Não dá erro, não dá tela branca: dá o documento
  errado com cara de certo.
- **Nota de escrita:** o caso **não** hardcoda a chave `espelho` — ele deriva do catálogo pelo
  par (`disponivel` && `requer_colaborador`). Um segundo relatório por-colaborador no futuro
  passa a ser exercido de graça, em vez de nascer descoberto.
- **Status: 🧪 sem veredito.**

---

## UC-RELIDX-04 · Relatório marcado disponível leva ao gerador, não a "não implementado" · `must`

- **Persona:** a mesma. Ela escolheu o colaborador e o período; o botão está habilitado.
- **Aceite:** Dado um relatório marcado como **disponível** · Quando peço a geração com os
  insumos que ele declara exigir · Então **não** recebo "não implementado", e sim o gerador.
- **Teste:** `RelatorioCatalogoContratoTest.php` — `UC-RELIDX-04`.
- **Contrato:** `CU-PONTO-14` (o outro lado dele) · F3 (`EspelhoController@imprimir`, o PDF que
  existe) · F8 (o catálogo).
- **Regressão que defende:** é a **direção oposta** do UC-RELIDX-01/02, e o defeito que ela
  descreve estava vivo em produção: o catálogo marcava `disponivel: true` e a rota respondia
  501 assim mesmo. O 01/02 impede prometer o que não existe; este impede **negar o que existe**.
- **Nota de escrita:** o assert é *"não é 501"* + *"redireciona carregando o colaborador escolhido"*.
  Não crava a URL final: o destino do PDF é contrato do F3, e mudá-lo lá não pode reprovar aqui.
- **Status: 🧪 sem veredito.**

---

## UC-RELIDX-05 · Relatório de colaborador de outro empregador é recusado · `must` `[T0]`

- **Persona:** adversário — um id de colaborador alheio digitado na URL.
- **Aceite:** Dado um colaborador de **outro** empregador · Quando peço o espelho dele pelo
  catálogo · Então recebo **404** — nunca o PDF, nunca redirect para o gerador.
- **Teste:** `RelatorioCatalogoContratoTest.php` — `UC-RELIDX-05`.
- **Contrato:** `CU-PONTO-12` (SDD §6.5) · [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
  · LGPD Art. 7º.
- **Regressão que defende:** o catálogo **redireciona** para outro controller, e a tentação é
  deixar a defesa por conta do destino (o `EspelhoController` de fato valida o tenant). Isso
  faria o isolamento depender de quem está do outro lado do redirect — e um redirect a mais
  no meio do caminho, um dia, quebraria a corrente sem ninguém notar. O caso trava a recusa
  **na entrada**.
- **Nota de escrita:** aqui o assert **crava o 404** (diferente do 01/02, que evitam cravar
  status). É deliberado: em vazamento cross-tenant o código importa — 404 é "não existe pra
  você", e 403 já confirmaria a existência do recurso alheio.
- **Status: 🧪 sem veredito.**
