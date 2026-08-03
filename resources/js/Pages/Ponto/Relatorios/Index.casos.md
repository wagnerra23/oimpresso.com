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

**[BACKLOG]:**

- `[BACKLOG]` **AEJ (Portaria MTP 671/2021 Anexo VI)** — US-PONTO-009, `_pendente_`. É a
  prioridade regulatória #1 e exige **revisão da Eliana [E] + ADR formal antes de codar** (o
  SPEC marca como pré-requisito duro). Não vira UC agora: US sem código gera UC órfão e o
  `casos-gate` G-2 pune ([proibicoes §5](../../../../memory/proibicoes.md) 2026-07-16).
- `[BACKLOG]` AFD legacy (Portaria MTE 1.510/2009) — US-PONTO-006, mesma situação.
- `[BACKLOG]` A tela promete 8 relatórios e o `espelho`, único marcado `disponivel: true`,
  **também** cai em 501 por esta rota. Se o botão do espelho leva ao 501 em vez de à rota F3,
  isso é defeito de produto — mas **não medi o comportamento do clique** nesta corrida e não
  vou afirmar. Fica declarado como pendência, não como achado (cf. §5 2026-07-15).

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
