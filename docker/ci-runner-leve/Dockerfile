# Runner GitHub Actions dos gates LEVES (scripts Node de segundos).
# Base oficial do GitHub, versão fixada. Acrescenta só o que os jobs leves usam:
# git, gh, node/npm (o que o ubuntu-latest traz e os jobs usam sem instalar) e certificados.
# Versão vem do atualiza.sh (última release do actions/runner). O default só vale
# para build à mão. O runner roda com --disableupdate (entrypoint.sh): quem o
# mantém atualizado é o timer, reconstruindo a imagem, nunca o próprio runner.
ARG RUNNER_VERSION=2.338.2
FROM ghcr.io/actions/actions-runner:${RUNNER_VERSION}

USER root
RUN apt-get update \
 && apt-get install -y --no-install-recommends git ca-certificates curl unzip xz-utils \
 && rm -rf /var/lib/apt/lists/*
# GitHub CLI, como no ubuntu-latest. Medido 2026-11-19: o cron-watchdog.mjs (chamado
# pelo memory-health.yml) pergunta à API pelo `gh` e, sem ele, sai "NÃO MEDIDO" (exit 3).
# Repositório apt oficial (cli.github.com).
RUN curl -fsSL https://cli.github.com/packages/githubcli-archive-keyring.gpg \
      -o /usr/share/keyrings/githubcli-archive-keyring.gpg \
 && chmod go+r /usr/share/keyrings/githubcli-archive-keyring.gpg \
 && echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/githubcli-archive-keyring.gpg] https://cli.github.com/packages stable main" \
      > /etc/apt/sources.list.d/github-cli.list \
 && apt-get update && apt-get install -y --no-install-recommends gh \
 && rm -rf /var/lib/apt/lists/* && gh --version | head -1
# Node no PATH, como no ubuntu-latest (que traz Node 22). Medido 2026-11-19: o job
# cron-liveness do memory-health.yml chama `node` sem setup-node ("o runner já traz
# Node 20+") e caiu com exit 127 numa vaga. O runner embute node20 e node24; usa o 24.
RUN d=/home/runner/externals/node24/bin \
 && for t in node npm npx; do ln -sf "$d/$t" "/usr/local/bin/$t"; done \
 && node --version && npm --version
COPY --chmod=755 entrypoint.sh /usr/local/bin/ci-leve-entrypoint
COPY --chmod=755 limpa-workspace.sh /usr/local/bin/ci-leve-limpa-workspace.sh
# Esvazia o workspace antes de cada job (runner persistente; ver limpa-workspace.sh).
ENV ACTIONS_RUNNER_HOOK_JOB_STARTED=/usr/local/bin/ci-leve-limpa-workspace.sh
USER runner
WORKDIR /home/runner
ENTRYPOINT ["/usr/local/bin/ci-leve-entrypoint"]
