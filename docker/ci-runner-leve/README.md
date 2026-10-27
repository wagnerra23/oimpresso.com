# `ci-runner-leve/` — runners GitHub Actions dos gates leves no CT 100

Tira do teto de concorrência da conta do GitHub os gates que são **scripts Node de segundos**
(sem `services:`, sem PHP/Pest/PHPStan, sem browser, sem segredo). Pest, PHPStan e
visual-regression continuam nos runners do GitHub.

Medido em 2026-10-27 19:20Z: **1.471 runs na fila** contra **26 rodando**. O gargalo é a fila
(teto de concorrência), não a duração de cada gate.

## Como funciona

- **16 vagas** (`ci-leve-1..16`), cada uma um container gerenciado por `ci-runner-leve@N.service`.
  Para mais: `VAGAS=N install.sh`. Histórico: 8 → 12 (CT 100 medido ~75% ocioso) → 16, depois de
  ampliar o CT 100 (abaixo), por decisão [W] 2026-10-27 de usar mais a máquina.
- **Runner persistente:** registra uma vez com um token de **registro** (vale 1 hora) e passa a
  usar a credencial própria que o `config.sh` grava dentro do container. **Nenhum PAT nem token
  fica guardado no host.**
- O `actions/checkout` limpa o workspace a cada job. Como o runner não é ephemeral, um job pode
  deixar resíduo para o seguinte na mesma vaga. Isso é aceitável porque só roda código de quem
  tem push ou de fork aprovado (ver ⚠️ abaixo).
- A conexão é **de saída** para o GitHub: não precisa de porta aberta, IP fixo nem Tailscale.

## Capacidade do CT 100 (Proxmox `sistema`, 192.168.0.2)

Medido 2026-10-27 pela API do Proxmox: o servidor físico tem **28 threads** (Xeon E5-2680 v4) e
**125,7 GB** de RAM, com 11 GB em uso. Os convidados são só o CT 100 (`docker-host`) e o CT 101
(`ponte-tor`, 4 núcleos, 8 GB).

Em 2026-10-27, com autorização [W], o CT 100 passou de **16 núcleos / 32 GB** para **24 núcleos /
96 GB** (`PUT /nodes/sistema/lxc/100/config cores=24 memory=98304`). A mudança vale na hora, sem
reiniciar. Sobram 4 threads e ~22 GB para o Proxmox e o CT 101. Para voltar:
`cores=16 memory=32768`.

## Isolamento (medido 2026-10-27 no CT 100)

| Defesa | Como | Prova |
|---|---|---|
| sem segredo do host | nenhum `-v`/mount | `docker create` sem volume |
| não-root, sem sudo | usuário `runner` (uid 1001), `--cap-drop ALL`, `no-new-privileges` | `sudo` → *"no new privileges flag is set"* |
| sem LAN/Tailscale/host | rede `ci-leve` (172.31.250.0/24) + `firewall.sh` (`DOCKER-USER` e `INPUT`) | 192.168.0.50, 192.168.0.1, 172.31.250.1, 172.17.0.1, 100.99.207.66 → timeout; api.github.com e registry.npmjs.org → 200 |
| teto de recurso | `--cpus 2 --memory 3g --pids-limit 2048` por vaga | — |
| fork não usa sem aprovação | política `all_external_contributors` (aplicada 2026-10-27) + `runs-on` cai em `ubuntu-latest` quando `head.repo.fork` | `gh api …/fork-pr-contributor-approval` |

⚠️ **O repo é público.** Um PR de fork pode editar o próprio YAML e apontar `runs-on` para este
runner. A expressão nos workflows não impede isso; o que impede é a política de aprovação de
workflows de fork estar em **`all_external_contributors`**, aplicada em 2026-10-27 por decisão [W]
(Settings → Actions → *Require approval for all outside collaborators*). Não afrouxe essa política
com as vagas ligadas.

## Instalar / completar vagas (a partir do git, nunca cópia à mão)

Do computador de quem tem admin no repo (o `gh` gera o token de registro, que vale 1 hora):

```bash
gh api -X POST repos/wagnerra23/oimpresso.com/actions/runners/registration-token --jq .token   | ssh root@192.168.0.50 'cd /opt/oimpresso-ci-src && git pull -q && VAGAS=16 docker/ci-runner-leve/install.sh'
```

`/opt/oimpresso-ci-src` é um clone *sparse* do repo, só `docker/ci-runner-leve/`. O `install.sh`
é idempotente: vaga que já existe fica como está.

Depois liga o roteamento no GitHub (sem PR):

```bash
gh variable set CI_RUNNER_LEVE --body ct100 --repo wagnerra23/oimpresso.com
```

## Desligar (se o CT 100 cair, os jobs voltam pro GitHub)

```bash
gh variable delete CI_RUNNER_LEVE --repo wagnerra23/oimpresso.com
```

Sem a variável, todo `runs-on` desses workflows cai em `ubuntu-latest`. **21 dos 47 required**
estão neste lote: com a variável ligada e as vagas fora do ar, eles ficam esperando runner e o
merge trava. Desligar a variável é o conserto imediato.
