---
casos: Ponto/Mobile/Index — REP-P no celular
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-09-29"
---

# Casos de Uso & Aceite — Ponto/Mobile/Index

> **Fonte dos UC:** tabela EARS §C da thread 06 do playbook do Ponto
> (`prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/06-rep-p.md`) + ADR 0383 + W5. **Não** o `.tsx`.
> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Testes: `Modules/Ponto/Tests/Feature/RepPMobileContratoTest.php` (tela) e
> `Modules/Ponto/Tests/Feature/Wave28MobileMarcacaoTest.php` (API).

---

## UC-REPP-00 · Chego na tela pela aba do Ponto, sem digitar URL
- **Persona:** colaborador (ou RH) no Ponto.
- **Aceite:** Dado o header de módulo do Ponto · Quando abro as abas · Então existe "REP-P (celular)",
  10ª, entre Colaboradores e Importações, levando a `/ponto/mobile` (200).
- **Regressão que defende:** tela no ar sem caminho de chegada.
- **Status: ⬜** — cita o UC; veredito vem da lane `ponto-pest`.

## UC-REPP-01 · Bato o ponto e a marcação nasce no MEU cadastro, com NSR do servidor
- **Persona:** Técnico Repair, no celular, em obra.
- **Aceite:** Dado colaborador com `controla_ponto` e GPS dentro do limite · Quando bato "Entrada" ·
  Então grava `REP_P` no meu `colaborador_config_id`, com NSR e hash, `dispositivo_id = mobile:{uuid}`;
  e a marcação aparece em "Hoje" quando recarrego a tela.
- **Regressão que defende:** marcação gravada em outro colaborador/empregador; NSR gerado no cliente.
- **Status: ⬜** — cita o UC; veredito vem da lane `ponto-pest`.

## UC-REPP-02 · GPS fraco não bate — sem "mesmo assim"
- **Aceite:** Dado precisão acima de 500 m · Quando tento bater · Então 422 com a mensagem do
  serviço e nada é gravado; a tela não tem caminho para forçar (W5).
- **Status: ⬜** — cita o UC; veredito vem da lane `ponto-pest`.

## UC-REPP-03 · Fora da área da empresa grava e vai para revisão
- **Aceite:** Dado geofence configurado e eu fora dele · Quando bato · Então grava **e** volta
  `revisar = true` ("fora da área").
- **Status: ⬜** — cita o UC; veredito vem da lane `ponto-pest`.

## UC-REPP-04 · Sem cadastro de ponto, a tela diz isso e não deixa bater
- **Aceite:** Dado usuário sem `ponto_colaborador_config` (ex.: o gestor abrindo a aba) · Quando
  abro `/ponto/mobile` · Então `colaborador` é nulo (estado vazio) e `POST /ponto/mobile/marcar` → 403.
- **Status: ⬜** — cita o UC; veredito vem da lane `ponto-pest`.

## UC-REPP-08 · Colaborador sem `ponto.access` também bate o ponto
- **Contrato:** [W] 2026-09-29 — *"colaborador sem ponto.access também acessa /ponto/mobile"*.
- **Aceite:** Dado colaborador com cadastro de ponto e **sem** `ponto.access` · Quando abro `/ponto/mobile` ·
  Então a tela abre (sem o cabeçalho do módulo — as abas seriam 403 pra ele), a batida grava, e o resto
  do módulo (`/ponto/espelho`) segue 403.
- **Status: ⬜** — cita o UC; veredito vem da lane `ponto-pest`.

## UC-REPP-09 · O colaborador acha "Bater ponto" no menu
- **Contrato:** [W] 2026-09-29 — *"põe item de menu 'Bater ponto' para o colaborador"*.
- **Aceite:** Dado usuário com cadastro de ponto ativo no empregador da sessão · Quando o menu é montado ·
  Então há o item "Bater ponto" levando a `/ponto/mobile` (inclusive sem `ponto.access`); sem cadastro, o item não aparece.
- **Status: ⬜** — cita o UC; veredito vem da lane `ponto-pest`.

## UC-REPP-10 · O gestor vê o header do módulo completo; o colaborador não recebe número da empresa
- **Contrato:** header de módulo do Ponto com contagens nas abas e linha de contexto (W9, ADR 0418) +
  charter §Non-Goals (*"o cabeçalho de abas só aparece pra quem tem o módulo"*) + [W] 2026-09-29
  (*"faz o PR dos 3 pontos do REP-P"*, após o smoke em produção mostrar o header da tela sem contagens).
- **Aceite:** Dado usuário com `ponto.access` · Quando abro `/ponto/mobile` · Então a resposta traz
  `ponto_abas` e `ponto_contexto` como props diferidas, como toda tela do Ponto. Dado colaborador **sem**
  `ponto.access` · Quando abro a mesma tela · Então nenhuma das duas vem — nem diferida.
- **Status: ⬜** — cita o UC; veredito vem da lane `ponto-pest`.

## UC-REPP-05 · GUARD — a tela não coleta imagem (ADR 0383)
- **Aceite:** o fonte da tela não usa câmera nem captura (`getUserMedia`, `capture=`, `selfie`),
  nem oferece "mesmo assim".
- **Status: ⬜** — cita o UC; veredito vem da lane `ponto-pest`.

## UC-REPP-06 · Meu espelho mostra o MEU mês
- **Aceite:** Dado apuração do dia para mim e para um colega · Quando abro "Meu espelho" · Então os
  totais e o dia a dia são os meus (mesmos builders do Espelho/Show), nunca os do colega.
- **Status: ⬜** — cita o UC; veredito vem da lane `ponto-pest`.

## UC-REPP-07 · Justificar envia para a fila do gestor
- **Aceite:** Dado o motivo, o dia e a justificativa · Quando envio · Então nasce intercorrência
  `PENDENTE` no meu cadastro e no meu empregador — a marcação original não muda.
- **Status: ⬜** — cita o UC; veredito vem da lane `ponto-pest`.

---

## UC-REPP-11 · No app, meu celular passa a receber o lembrete de bater ponto
- **Persona:** colaborador com o app da loja (Capacitor) instalado.
- **Aceite:** Dado colaborador com `controla_ponto` · Quando a tela envia o token do aparelho ·
  Então o aparelho fica ativo no MEU usuário e no MEU business, mesmo que o corpo traga outro
  `business_id`/`user_id`; sem cadastro de ponto → 403 e nada gravado.
- **Fonte:** ADR 0422 §3. **Regressão que defende:** lembrete indo para outra empresa ou pessoa.
- **Status: ⬜** — cita o UC; veredito vem da lane `ponto-pest` (`PushDispositivoContratoTest`).

## UC-REPP-12 · Celular da loja compartilhado: o lembrete é de quem logou por último
- **Aceite:** Dado um token já registrado por outro usuário (de qualquer business) · Quando eu
  registro o mesmo token · Então existe UMA linha, agora minha e ativa.
- **Fonte:** ADR 0422 §3. **Status: ⬜**

## UC-REPP-13 · Paro os lembretes, e só os MEUS
- **Aceite:** Dado meu aparelho ativo · Quando peço para parar · Então ele fica `ativo=false`;
  outro usuário (de outro business) pedindo o mesmo token não desativa nada.
- **Fonte:** ADR 0422 §4. **Status: ⬜**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Fila do gestor com filtro de origem mobile em Aprovações (passo 3 da thread).

## Trilha do tempo
- 2026-09-29 · [CL] carimbado por criar-tela.mjs e preenchido na thread 06 (PR 2a); UC-REPP-06/07 no PR 2b. Refs: UI-0013 · ADR 0264 G-1/G-2.
- 2026-10-01 · [CL] UC-REPP-11/12/13 — registro do aparelho para o lembrete (ADR 0422, PR 1).
