#!/usr/bin/env bash
# Cria a rede dos runners e corta o acesso dela à rede interna.
# O runner precisa só da internet (GitHub, npm). Nada de LAN da empresa,
# Tailscale, Proxmox, MySQL, Vaultwarden ou outros containers.
# Idempotente: pode rodar de novo a cada boot (chamado pelo ExecStartPre do serviço).
set -euo pipefail

NETWORK="${CI_LEVE_NETWORK:-ci-leve}"
SUBNET="${CI_LEVE_SUBNET:-172.31.250.0/24}"

docker network inspect "$NETWORK" >/dev/null 2>&1 \
  || docker network create --driver bridge --subnet "$SUBNET" \
       -o com.docker.network.bridge.name=br-ci-leve "$NETWORK"

for DST in 10.0.0.0/8 172.16.0.0/12 192.168.0.0/16 100.64.0.0/10 169.254.0.0/16; do
  iptables -C DOCKER-USER -s "$SUBNET" -d "$DST" -j DROP 2>/dev/null \
    || iptables -I DOCKER-USER -s "$SUBNET" -d "$DST" -j DROP
done
# Respostas de conexões já abertas continuam passando (regra no topo).
iptables -C DOCKER-USER -s "$SUBNET" -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT 2>/dev/null \
  || iptables -I DOCKER-USER -s "$SUBNET" -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT
# DOCKER-USER só vê tráfego ENCAMINHADO. O container falando com o próprio host
# (gateway da bridge, ex. 172.31.250.1:3306) entra pela cadeia INPUT, então
# fecha também. O DNS do container não depende disso: o resolver embutido do
# Docker encaminha a consulta a partir do namespace do host.
iptables -C INPUT -i br-ci-leve -j DROP 2>/dev/null \
  || iptables -I INPUT -i br-ci-leve -j DROP
iptables -C INPUT -i br-ci-leve -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT 2>/dev/null \
  || iptables -I INPUT -i br-ci-leve -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT
