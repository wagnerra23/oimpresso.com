---
id: cowork-inbox-fiscal-config-divergencia
tela: /fiscal/config
autor: "[CC]"
data: 2026-08-24
status: superado por Config.charter.md (proposta [CC] desta sessão, ratificação [W] pendente)
---

# Config fiscal — o registro do impasse (histórico)

> **Superado.** [W] autorizou a decisão em 2026-08-24; o trio existe em `Config.charter.md` + `Config.casos.md` + `prototipo-ui/contrato/fiscal-config.contract.json`, marcado como proposta até virar ADR. Este arquivo fica como registro de por que o impasse existiu — e de que ele foi resolvido por decisão, não por esquecimento.

## Por que este pacote começou sem charter

O `Config.casos.md` do `main` registra a disputa e se recusa a escrever caso de uso:

> **[BACKLOG · sem contrato · decisão [W]] Trocar o ambiente SEFAZ e enviar certificado a partir desta tela** — os dois formulários existem, mas o charter diz que a tela é read-only. Sem contrato até [W] resolver a divergência. Escrever UC aqui seria escolher o vencedor de uma disputa de intenção.

Faço o mesmo. Escrever charter aqui seria eu decidir por você.

## As três perguntas — e as respostas dadas nesta sessão

1. **Read-only ou editável?** → **editável.** Os formulários existem, foram pagos, e read-only empurra o operador pra UI legada do emissor pra fazer a coisa mais arriscada do módulo. Esconder a ação não a torna segura; cercá-la, sim.
2. **Gate próprio?** → **sim, `fiscal.config.ambiente`**, separado de `fiscal.config.edit`. Editar e-mail do contador e trocar ambiente de emissão não são o mesmo risco.
3. **Séries?** → **leitura real**, filial inventada removida.

## O que o F1 fez

- As quatro abas do vivo existem: **Certificado e regime · Séries · Ambiente e certificado · SPED**.
- Na aba *Ambiente e certificado*, a barra de permissão diz se `fiscal.config.ambiente` está concedida; sem ela os dois botões ficam travados **com o motivo dito**. Com ela, trocar ambiente pede o destino digitado à mão (`PRODUÇÃO`/`HOMOLOGAÇÃO`) mais motivo de 15+ caracteres, e a troca vira evento na timeline fiscal.
- A senha do certificado nunca é exibida — no vivo ela está em `$hidden` no Model, e o F1 preserva a regra.
- A aba *Séries* traz selo `leitura real`: a filial de Guarulhos saiu.

## O que ainda falta

Virar ADR. Até lá, `Config.charter.md` é proposta — se você reverter, `UC-FCFG-02..06` caem inteiros.
