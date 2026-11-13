---
casos: Associacoes/Painel — carimbado do Padrão de Tela
irmaos: Painel.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-11-13"
---

# Casos de Uso & Aceite — Associacoes/Painel

> Nascido de `criar-tela.mjs`. **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2: UC declarado sem teste citando o id = órfão. Os UCs são citados pelo Pest de contrato (lane `verticais-pest`, MySQL).

---

## UC-PAINEL-00 · Chego na tela pelo menu, sem digitar URL
- **Persona:** diretoria da rede ou representante de associação — abre o sistema e acha o painel no menu.
- **Aceite:** Dado usuário com `associacoes.access` e empresa com o pacote · Quando abre o sistema ·
  Então o dropdown "Associações" tem o item "Painel", que leva a `/associacoes` (rota `associacoes.painel`).
- **Teste:** `Modules/Associacoes/Tests/Feature/AssociacoesPainelContratoTest.php` — `UC-PAINEL-00` (lê o código do menu, não o menu renderizado; o resto é smoke).
- **Regressão que defende:** a tela responder 200 e ninguém alcançar — `modifyAdminMenu()` no-op.
- **Status: ⬜** — teste escrito; vira 🧪 quando a lane `verticais-pest` rodar verde.

---

## UC-PAINEL-01 · A diretoria vê a rede da empresa dela, nunca a de outra empresa
- **Persona:** diretoria da Uniafibro-SC (`associacoes.rede`, ADR 0451 D4).
- **Aceite:** Dado associações e associadas na empresa 98 e na 99 · Quando a diretoria da 98 abre o painel ·
  Então vê as associações da 98 com o total de associadas ativas certo, e nenhum número ou nome da 99;
  as linhas por associação só carregam id, nome, cidade e total (nenhum dado pessoal, D5).
- **Teste:** `Modules/Associacoes/Tests/Feature/AssociacoesPainelContratoTest.php` — `UC-PAINEL-01` (lê as props deferidas pelo partial reload).
- **Regressão que defende:** contagem sem escopo de empresa vazando a rede de outro cliente (ADR 0093).
- **Status: ⬜** — teste escrito; vira 🧪 quando a lane `verticais-pest` rodar verde.

---

## UC-PAINEL-02 · O representante vê só os números da associação dele
- **Persona:** representante de uma associação (`associacoes.access` sem `associacoes.rede`, D4).
- **Aceite:** Dado duas associações na mesma empresa e o usuário representante só de uma ·
  Quando abre o painel · Então associações = 1, cidades = 1, associadas ativas = só as ativas da dele
  (desligada não conta), e as pendências são todas da associação dele.
- **Teste:** `Modules/Associacoes/Tests/Feature/AssociacoesPainelContratoTest.php` — `UC-PAINEL-02`.
- **Regressão que defende:** consulta do painel sem `AssociacoesAcesso::restringir()`.
- **Status: ⬜** — teste escrito; vira 🧪 quando a lane `verticais-pest` rodar verde.

---

## UC-PAINEL-03 · Diretoria mudou: no prazo, e atrasada depois de 30 dias
- **Persona:** diretoria da rede acompanhando a ata nova (ADR 0451 D7).
- **Aceite:** Dado mudança de diretoria avisada sem ata nova · Quando passaram até 30 dias
  (`associacoes.prazo_ata_diretoria_dias`) · Então a pendência está **no prazo**; com 31 dias, **atrasada**.
  Ata da diretoria enviada depois da mudança resolve a pendência. Atrasada avisa, não bloqueia.
- **Teste:** `Modules/Associacoes/Tests/Feature/AssociacoesPainelContratoTest.php` — `UC-PAINEL-03` (data fixa, 10 · 30 · 31 · 40 dias).
- **Regressão que defende:** prazo contado errado (fora de um) ou ata nova ignorada.
- **Status: ⬜** — teste escrito; vira 🧪 quando a lane `verticais-pest` rodar verde.

---

## UC-PAINEL-04 · Pendências de documento: estatuto, ata e certidão vencida
- **Persona:** diretoria da rede.
- **Aceite:** Dado associação sem estatuto (o único foi removido), sem ata e com certidão vencida ontem ·
  Então aparecem as três pendências; associação com estatuto, ata e certidão válida não aparece;
  associação desligada não aparece; alcance vazio não devolve nada; pedir pela outra empresa não devolve nada.
- **Teste:** `Modules/Associacoes/Tests/Feature/AssociacoesPainelContratoTest.php` — `UC-PAINEL-04`.
- **Regressão que defende:** documento removido (`removido_em`, D3) contando como presente.
- **Status: ⬜** — teste escrito; vira 🧪 quando a lane `verticais-pest` rodar verde.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** E2E no browser do painel (Pest 4 Browser) com o representante.
- **[BACKLOG]** Unificar a regra de pendência com o Service da onda 3 (tela de associações).

## Trilha do tempo
- 2026-11-13 · [CC] UCs 00–04 escritos da ADR 0451 (D4, D5, D7) e da US-ASSOC-004; teste de contrato 98×99.
- 2026-11-13 · [CC] carimbado por criar-tela.mjs — trio nascido junto (charter + casos + teste). Refs: UI-0013 · ADR 0264 G-1/G-2.
