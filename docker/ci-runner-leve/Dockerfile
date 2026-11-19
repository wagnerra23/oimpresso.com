# Runner GitHub Actions dos gates LEVES (scripts Node de segundos).
# Base oficial do GitHub, versão fixada. Acrescenta só o que os jobs leves usam:
# git (actions/checkout com histórico) e certificados. Node vem do actions/setup-node.
# Versão vem do atualiza.sh (última release do actions/runner). O default só vale
# para build à mão. O runner roda com --disableupdate (entrypoint.sh): quem o
# mantém atualizado é o timer, reconstruindo a imagem, nunca o próprio runner.
ARG RUNNER_VERSION=2.338.2
FROM ghcr.io/actions/actions-runner:${RUNNER_VERSION}

USER root
RUN apt-get update \
 && apt-get install -y --no-install-recommends git ca-certificates curl unzip xz-utils \
 && rm -rf /var/lib/apt/lists/*
COPY --chmod=755 entrypoint.sh /usr/local/bin/ci-leve-entrypoint
COPY --chmod=755 limpa-workspace.sh /usr/local/bin/ci-leve-limpa-workspace.sh
# Esvazia o workspace antes de cada job (runner persistente; ver limpa-workspace.sh).
ENV ACTIONS_RUNNER_HOOK_JOB_STARTED=/usr/local/bin/ci-leve-limpa-workspace.sh
USER runner
WORKDIR /home/runner
ENTRYPOINT ["/usr/local/bin/ci-leve-entrypoint"]
