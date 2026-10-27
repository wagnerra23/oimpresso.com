# `ci-runner-leve/` — runners GitHub Actions dos gates leves no CT 100

Tira do teto de concorrência da conta do GitHub os gates que são **scripts Node de segundos**
(sem `services:`, sem PHP/Pest/PHPStan, sem browser, sem segredo). Pest, PHPStan e
visual-regression continuam nos runners do GitHub.

Medido em 2026-10-27 19:20Z: **1.471 runs na fila** contra **26 rodando**. O gargalo é a fila
(teto de concorrência), não a duração de cada gate.

## Como funciona

- `ci-runner-leve@N.service` (systemd, uma instância por vaga) roda `supervisor.sh N`. Começa com **8 vagas** (CT 100: 16 núcleos, 32 GB; [W] 2026-10-27 liberou aumentar). Para mais vagas, basta `systemctl enable --now ci-runner-leve@9`, e assim por diante.
- A cada volta o supervisor pede ao GitHub um **JIT config** (vale para **um** job), sobe um
  container descartável com ele e, quando o job acaba, o container some e a volta recomeça.
- O **PAT fica só no host** (`/root/.config/ci-runner-leve/pat`, chmod 600). O container recebe
  apenas o JIT config de uso único.
- Conexão **de saída** para o GitHub: não precisa de porta aberta, IP fixo nem Tailscale.

## Isolamento (medido 2026-10-27 no CT 100)

| Defesa | Como | Prova |
|---|---|---|
| sem segredo do host | nenhum `-v`/mount; PAT fica fora do container | `docker run` sem volume |
| não-root, sem sudo | usuário `runner` (uid 1001), `--cap-drop ALL`, `no-new-privileges` | `sudo` → *"no new privileges flag is set"* |
| sem LAN/Tailscale/host | rede `ci-leve` (172.31.250.0/24) + `firewall.sh` (`DOCKER-USER` e `INPUT`) | 192.168.0.50, 192.168.0.1, 172.31.250.1, 172.17.0.1, 100.99.207.66 → timeout; api.github.com e registry.npmjs.org → 200 |
| teto de recurso | `--cpus 2 --memory 3g --pids-limit 2048` por vaga | — |
| fork não usa | `runs-on` cai em `ubuntu-latest` quando `head.repo.fork` | expressão nos workflows |

⚠️ **O repo é público.** Um PR de fork pode editar o próprio YAML e apontar `runs-on` para este
runner. A expressão nos workflows não impede isso; o que impede é a política de aprovação de
workflows de fork estar em **`all_external_contributors`** (Settings → Actions → *Require approval
for all outside collaborators*). Não ligue as vagas com a política mais frouxa.

## Instalar / atualizar (a partir do git, nunca cópia à mão)

```bash
# no CT 100, com o repo clonado em /opt/oimpresso-src (git pull do main)
install -d -m 700 /root/.config/ci-runner-leve
# PAT fine-grained: só este repo, permissão "Administration: Read and write" (registro de runner)
install -m 600 /dev/stdin /root/.config/ci-runner-leve/pat   # cole o PAT e Ctrl-D
ln -sfn /opt/oimpresso-src/docker/ci-runner-leve /opt/oimpresso/ci-runner-leve
docker build -t oimpresso/ci-runner-leve:2.338.0 /opt/oimpresso/ci-runner-leve
cp /opt/oimpresso/ci-runner-leve/ci-runner-leve@.service /etc/systemd/system/
systemctl daemon-reload && systemctl enable --now ci-runner-leve@{1..8}
```

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
