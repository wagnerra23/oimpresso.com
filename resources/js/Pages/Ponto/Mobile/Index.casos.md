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

## UC-REPP-05 · GUARD — a tela não coleta imagem (ADR 0383)
- **Aceite:** o fonte da tela não usa câmera nem captura (`getUserMedia`, `capture=`, `selfie`),
  nem oferece "mesmo assim".
- **Status: ⬜** — cita o UC; veredito vem da lane `ponto-pest`.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Meu espelho do mês corrente (PR 2b da thread).
- **[BACKLOG]** Justificar: envia intercorrência PENDENTE (PR 2b — a API já tem teste no Wave28).

## Trilha do tempo
- 2026-09-29 · [CL] carimbado por criar-tela.mjs e preenchido na thread 06 (PR 2a). Refs: UI-0013 · ADR 0264 G-1/G-2.
