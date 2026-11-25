# `ci-runner-leve/` — runners GitHub Actions dos gates leves no CT 100

Tira do teto de concorrência da conta do GitHub os gates que são **scripts Node de segundos**
(sem `services:`, sem PHP/Pest/PHPStan, sem browser, sem segredo). Pest, PHPStan e
visual-regression continuam nos runners do GitHub.

Medido em 2026-10-27 19:20Z: **1.471 runs na fila** contra **26 rodando**. O gargalo é a fila
(teto de concorrência), não a duração de cada gate.

## Como funciona

- **8 vagas** (`ci-leve-1..8`), cada uma um container gerenciado por `ci-runner-leve@N.service`.
  ⚠️ **O teto é o DISCO, não a CPU.** Medido 2026-10-27 21:11Z com 16 vagas, logo depois de o
  roteamento entrar no `main`: carga 27 em 24 núcleos, mas CPU 43% ociosa e **43% em iowait**
  (`/proc/pressure/io` some avg60=58). O health do MCP (`/api/mcp/health`), que responde em 0,03 s,
  levou **5,2 s e 9,7 s**. Com 8 vagas voltou a 0,04–0,13 s, com iowait 6%. Os runners dividem o
  disco com os serviços de produção do CT 100: **não suba vagas sem medir `/proc/pressure/io` e o
  health do MCP com a fila cheia.**
  **Espaço:** cada vaga guarda dentro do container um checkout do repo e o cache do Node, reaproveitados
  entre jobs: ~1,6 a 2,4 GB por vaga (medido 2026-10-27). Com 16 vagas o disco do CT 100 foi de 67% a
  80%. Antes de subir vagas, confira também `df -h /`.
- **Runner persistente:** registra uma vez com um token de **registro** (vale 1 hora) e passa a
  usar a credencial própria que o `config.sh` grava dentro do container. **Nenhum PAT nem token
  fica guardado no host.**
- **Workspace esvaziado antes de cada job** pelo hook `ACTIONS_RUNNER_HOOK_JOB_STARTED`
  (`limpa-workspace.sh`). O runner não é ephemeral, e em 2026-10-27 esta linha afirmava que o
  `actions/checkout` limpava o workspace sozinho. Não limpava: um job com sparse-checkout deixava
  o índice marcado, e o job seguinte na mesma vaga caía com "Cannot find module" em arquivo
  versionado (medido 2026-10-28: 14 de 25 falhas nos `ct100-leve-*`). Resíduo em `_temp`/`_tool`
  ainda passa entre jobs; isso é aceitável porque só roda código de quem tem push ou de fork
  aprovado (ver ⚠️ abaixo).
- **Packs do `.git` compactados acima de 50** pelo mesmo hook (`git repack -a -d -l`). O
  `actions/checkout` grava `gc.auto=0`, e cada fetch deixava um pack novo no `_work`, que é um
  tmpfs de 4 GB. Em 2026-11-24 as vagas ligadas havia 5 dias estavam com o `_work` entre 79% e
  89% (vaga 2: 657 packs, 2,5 GB de `.git`, objetos 24× repetidos), e jobs caíam com "No space
  left on device" no setup-node. Para conferir uma vaga:
  `docker exec ci-leve-N df -h /home/runner/_work` e
  `docker exec ci-leve-N git -C /home/runner/_work/oimpresso.com/oimpresso.com count-objects -v`.
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
gh api -X POST repos/wagnerra23/oimpresso.com/actions/runners/registration-token --jq .token   | ssh root@192.168.0.50 'cd /opt/oimpresso-ci-src && git pull -q && VAGAS=8 docker/ci-runner-leve/install.sh'
```

`/opt/oimpresso-ci-src` é um clone *sparse* do repo, só `docker/ci-runner-leve/`, que acompanha
o `main`. O `install.sh` é idempotente: vaga que já existe fica como está. Ele só é necessário para
**criar** vagas; para **atualizar**, existe a rotina abaixo.

## Atualização automática

O `ci-runner-leve-atualiza.timer` roda o `atualiza.sh` a cada 15 minutos. Ele busca o `main` e
compara a árvore de `docker/ci-runner-leve/` com a última implantada
(`/var/lib/ci-runner-leve/arvore-implantada`). Se mudou, reconstrói a imagem e troca as vagas uma
por uma:

- vaga com job rodando espera ficar livre (até 30 min; se não liberar, fica para a próxima volta);
- a credencial do runner (`.runner`, `.credentials`, `.credentials_rsaparams`) passa do container
  antigo para o novo, então **não precisa de token de registro nem de SSH de ninguém**;
- se a vaga não estiver rodando 60 s depois, o container antigo volta.

**Versão do runner.** As vagas rodam com `--disableupdate`: o runner não se atualiza sozinho
dentro do container. Em 2026-11-19 as 8 vagas (imagem 2.338.0) foram achadas sem a pasta
`/home/runner/bin`, em laço `Runner.Listener: No such file or directory`, depois de uma
auto-atualização que não terminou. Agora o `atualiza.sh` consulta a última release do
`actions/runner` a cada volta e, se mudou, reconstrói a imagem com `--build-arg RUNNER_VERSION`
e troca as vagas. O carimbo guarda `<árvore> <versão>`; registro antigo ganha
`"disableUpdate": true` na credencial copiada.

O carimbo só é gravado quando todas as vagas foram trocadas. Ver o que aconteceu:
`journalctl -u ci-runner-leve-atualiza --since today`. Forçar agora:
`systemctl start ci-runner-leve-atualiza`.

Origem: em 2026-10-28 o conserto do workspace (#10093) ficou parado no `main` porque a troca das
vagas dependia de alguém entrar no CT 100. O clone ainda apontava para o branch do PR, que já
tinha sido apagado.

Depois liga o roteamento no GitHub (sem PR):

```bash
gh variable set CI_RUNNER_LEVE --body ct100 --repo wagnerra23/oimpresso.com
```

## Reiniciar uma vaga sem derrubar um job

Não reinicie vaga com `systemctl restart ci-runner-leve@N` "quando ela estiver livre". Com a fila
cheia, a vaga fica livre só ~2 s entre um job e o seguinte, e o GitHub entrega o próximo nesse
intervalo. O job morto não mostra erro na hora: fica **10 minutos em andamento sem nenhum passo**,
sem log, e cai com *"The self-hosted runner lost communication with the server"*.

Medido em 2026-11-24: um laço manual que esperava `pgrep Runner.Worker` dar vazio e então reiniciava
as vagas 2, 4 e 7 (para esvaziar o tmpfs do `_work`) derrubou dois jobs: vaga 4, job entregue
20:11:52Z, restart 20:11:53Z; vaga 7, job anterior terminou 20:22:44Z, o seguinte chegou 20:22:46Z,
restart 20:22:46Z. Nos 9.174 runs do dia, esses foram os únicos jobs de runner que terminaram sem
nenhum passo por causa de runner (outros 3 sem passos eram cancelamento por `concurrency`).

- Para esvaziar o `_work`, não reinicie: o hook `limpa-workspace.sh` limpa antes de cada job.
- Se precisar parar uma vaga mesmo assim, olhe logo depois se ela pegou job no instante da parada:
  `docker logs --since <hora-da-checagem> ci-leve-N 2>&1 | grep 'Running job:'`. Se pegou, re-rode
  o job (`gh run rerun <run> --failed`) em vez de esperar os 10 minutos.
- O `atualiza.sh` faz a cópia da credencial antes da última checagem (a janela vira só o
  `systemctl stop`) e imprime `AVISO vaga N: o job '…' chegou no instante da parada` no journal
  quando mesmo assim pega um. Ver: `journalctl -u ci-runner-leve-atualiza | grep AVISO`.
- Para achar jobs presos assim: `steps` vazio na API (`gh api repos/<repo>/actions/jobs/<id> --jq
  '.steps|length'` = 0) com `runner_name` `ct100-leve-*` e anotação "lost communication".

## Desligar (se o CT 100 cair, os jobs voltam pro GitHub)

```bash
gh variable delete CI_RUNNER_LEVE --repo wagnerra23/oimpresso.com
```

Sem a variável, todo `runs-on` desses workflows cai em `ubuntu-latest`. **21 dos 47 required**
estão neste lote: com a variável ligada e as vagas fora do ar, eles ficam esperando runner e o
merge trava. Desligar a variável é o conserto imediato.
