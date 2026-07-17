# Devcontainer do agente — chip C7 (grade de réguas 2026-07-17, seguranca-do-agente 5,0/10).
# Objetivo: dar ao agente um ambiente onde a EXFIL é cortada pela rede (default-deny +
# allowlist), não adivinhada por regex. Ver .devcontainer/README.md pro trade-off.
FROM mcr.microsoft.com/devcontainers/javascript-node:20

# iptables/ipset = o firewall (init-firewall.sh). dnsutils = dig (resolve a allowlist).
# openssh-client = Hostinger/CT100. gh = o canal de PR (R10 é humano, mas o gh é meu).
RUN apt-get update && apt-get install -y --no-install-recommends \
      iptables ipset dnsutils openssh-client jq ca-certificates gnupg \
  && mkdir -p -m 755 /etc/apt/keyrings \
  && curl -fsSL https://cli.github.com/packages/githubcli-archive-keyring.gpg \
       | gpg --dearmor -o /etc/apt/keyrings/githubcli-archive-keyring.gpg \
  && chmod go+r /etc/apt/keyrings/githubcli-archive-keyring.gpg \
  && echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/githubcli-archive-keyring.gpg] https://cli.github.com/packages stable main" \
       > /etc/apt/sources.list.d/github-cli.list \
  && apt-get update && apt-get install -y --no-install-recommends gh \
  && rm -rf /var/lib/apt/lists/*

# O firewall precisa de root; o agente NÃO. `node` roda o firewall via sudo e só ele —
# senão o próprio agente poderia derrubar a regra que o contém (NOPASSWD escopado).
COPY init-firewall.sh /usr/local/bin/init-firewall.sh
RUN chmod +x /usr/local/bin/init-firewall.sh \
  && echo 'node ALL=(root) NOPASSWD: /usr/local/bin/init-firewall.sh' > /etc/sudoers.d/init-firewall \
  && chmod 0440 /etc/sudoers.d/init-firewall

USER node
