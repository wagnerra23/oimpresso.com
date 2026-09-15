---
id: cowork-inbox-fiscal-config-casos
casos: Certificado e configuração fiscal · /fiscal/config
irmaos: Config.charter.md (lei proposta) · Config.divergencia.md (o registro do impasse, agora superado)
owner: wagner
autor: "[CC]"
status: proposta [CC] — aguarda ratificação [W]
last_run: "nunca"
---

# Casos de Uso & Aceite — Config fiscal

> Personas: **Wagner [W]** (dono, único com `fiscal.config.ambiente`) · **Eliana [E]** (contadora, lê tudo e edita envio de documentos).
>
> ⚠️ Estes UC nascem de uma decisão tomada nesta sessão, não de canon ratificado. Se [W] mudar de ideia sobre a tela ser editável, **UC-FCFG-02 a 06 caem inteiros**.

## Rastreabilidade

| UC | O que defende | Prio | Teste | Status |
|---|---|---|---|---|
| UC-FCFG-01 | leitura da config sem gate de risco | `[must]` | herda `ConfigControllerTest` (vivo) | 🧪 |
| UC-FCFG-02 | sem `fiscal.config.ambiente`, campos travados | `[must]` | `FiscalOndasF1Test` | ❌ nasce vermelho |
| UC-FCFG-03 | troca de ambiente exige destino digitado + motivo | `[must]` | `FiscalOndasF1Test` | ❌ nasce vermelho |
| UC-FCFG-04 | troca de ambiente vira evento de auditoria | `[must]` | `FiscalOndasF1Test` | ❌ nasce vermelho |
| UC-FCFG-05 | senha do certificado nunca volta | `[must]` | `FiscalOndasF1Test` | ⬜ |
| UC-FCFG-06 | séries vêm de leitura, não de mock | `[should]` | `FiscalOndasF1Test` | ❌ nasce vermelho |

---

## UC-FCFG-01 — Ler a configuração não exige permissão de risco `[must]`

**Dado** [E] com `fiscal.config.view`
**Quando** abre a tela
**Então** vê certificado, regime, tributação, séries e envio de documentos — e a aba de ambiente **existe**, com os campos travados e o motivo dito.

- **Regressão que defende:** esconder a aba faz a contadora abrir um chamado pra saber em que ambiente a empresa emite. Ver não é poder mudar.

## UC-FCFG-02 — Sem o gate próprio, nada é editável `[must]`

**Dado** um usuário com `fiscal.config.edit` mas **sem** `fiscal.config.ambiente`
**Quando** abre a aba Ambiente e certificado
**Então** os dois botões estão travados, a barra diz "permissão ausente" e nenhuma requisição de troca é aceita **no servidor** — não só na tela.

- **Por que separado de `edit`:** editar e-mail do contador e trocar o ambiente de emissão não são o mesmo risco.

## UC-FCFG-03 — Trocar ambiente pede o destino escrito à mão `[must]`

**Dado** [W] com o gate concedido, ambiente em Produção
**Quando** pede a troca
**Então** só confirma se digitar `HOMOLOGAÇÃO` **e** escrever um motivo de 15+ caracteres; confirmação que não bate deixa o ambiente inalterado e diz isso.

- **Regressão que defende:** troca por reflexo. Empresa que emite em homologação sem saber passa dias produzindo nota sem valor fiscal.

## UC-FCFG-04 — Toda troca deixa rastro `[must]`

**Dado** uma troca aceita
**Quando** [E] audita depois
**Então** existe evento com autor, horário, o par `antes → depois` e o motivo digitado.

- **Aceite:** o evento aparece na timeline de `/fiscal/eventos` como qualquer outro evento fiscal.

## UC-FCFG-05 — A senha do certificado não volta nunca `[must]`

**Dado** um certificado enviado com senha
**Quando** a tela recarrega, o evento é lido, ou a resposta é inspecionada
**Então** a senha não aparece em payload, log, mensagem de erro ou evento — o campo volta vazio.

- **Âncora:** no vivo a senha está em `$hidden` no Model; a tela não pode desfazer isso.

## UC-FCFG-06 — Séries são lidas, não inventadas `[should]`

**Dado** a aba Séries
**Quando** [E] confere o próximo número
**Então** cada linha corresponde a um modelo real do local, com o próximo número vindo do emissor — nenhuma filial inventada.

- **Regressão que defende:** contadora conferiu numeração contra uma filial que não existe.

---

## Backlog de casos

- **[BACKLOG · ⬜] Aviso de emissão pendente antes da troca** — trocar ambiente com nota em transmissão deveria avisar antes, não depois.
- **[BACKLOG · ⬜] Janela de manutenção** — trocar ambiente em horário comercial merece confirmação extra.
- **[BACKLOG · ⬜ · decisão [W]] Editar e-mail do contador e os dois toggles de envio** nesta tela (hoje só leitura; é `fiscal.config.edit`, risco baixo).
