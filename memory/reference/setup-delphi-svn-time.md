---
id: reference-setup-delphi-svn-time
name: Setup Delphi/SVN READ-ONLY pra time remoto (Felipe etc)
description: Runbook de provisionar máquina de dev remoto pra trabalhar com Delphi WR Comercial em modo READ-ONLY conforme feedback-commits-delphi-svn.md. Atualmente provisionado pra Felipe (Wagner 2026-05-27); outros devs sob demanda. URL canon SVN wr2.com.br:8777 (split-DNS — LAN resolve direto, remoto precisa Tailscale/VPN/presencial OU hosts file override). Cobre install SlikSvn + checkout completo D:\Programas + validação + credenciais SVN + troubleshoot. Aplica regra Wagner READ-ONLY (princípio 4 Constituição v2 + ADR 0113 + PEGADINHAS:182).
type: reference
---

# Setup Delphi/SVN READ-ONLY pra time remoto

> **Provisionamento atual:** Felipe (Wagner aprovou 2026-05-27).
> **Outros devs** (Maiara/Eliana/Luiz): sob demanda — pedir Wagner antes.
> **Regra de uso:** READ-ONLY. Ver [feedback-commits-delphi-svn.md](feedback-commits-delphi-svn.md). Claude NÃO comita SVN nesta working copy.

## Pré-requisitos

- Windows 10/11 (admin shell pode ser necessário se for usar hosts file override no Passo 2c)
- ~50 GB livres em disco (working copy completa estimada)
- Conexão internet com banda de download tolerável (~horas pra checkout inicial)
- Credenciais SVN (pegar com Wagner via Vaultwarden — item `svn-sistema-wr2-readonly` OU usuário/senha do servidor SVN dedicado em `192.168.0.55:8777`)

## Estrutura de rede (contexto)

- **Servidor SVN dedicado:** `192.168.0.55:8777` (LAN escritório — [infra-rede-empresa.md:47](infra-rede-empresa.md))
- **NAT TP-Link regra #5:** porta `8777` exposta no IP público `177.74.67.30:8777` ([infra-rede-empresa.md:87](infra-rede-empresa.md))
- **URL canônica SVN:** `http://wr2.com.br:8777/svn/Programas/Trunk`
  - **Split-DNS:** na LAN do escritório `wr2.com.br` resolve pro IP interno do servidor SVN (.55); externamente resolve pro IP do site institucional WR2 (`177.12.170.3` per nslookup 2026-05-27) que **não expõe 8777**
  - **Hostname legacy `servidor-crm`:** working copy original do Wagner usa esse hostname; também só resolve LAN. Novas máquinas devem usar `wr2.com.br` direto

## Passo 1 — Instalar SVN CLI (SlikSvn)

```powershell
# Windows admin shell (Win+X → "Terminal (Admin)")
winget install --id Slik.Subversion --silent --accept-source-agreements --accept-package-agreements

# Validar (precisa abrir shell novo pra PATH atualizar)
& 'C:\Program Files\SlikSvn\bin\svn.exe' --version --quiet
# esperado: 1.14.2 (ou superior)
```

Alternativa GUI: TortoiseSVN também instala svn.exe se marcar "command line client tools" no installer — mas SlikSvn é mais leve (~10MB) e suficiente.

## Passo 2 — Resolver acesso à rede (3 caminhos — escolher 1)

A URL canônica `http://wr2.com.br:8777/svn/Programas/Trunk` resolve direto na LAN do escritório (split-DNS interno). Dev remoto precisa um destes caminhos:

### 2a — Vai à empresa pra primeira vez (mais simples)

Felipe vai presencialmente no escritório, conecta no Wi-Fi LAN, e o `wr2.com.br` resolve automaticamente. Faz checkout inicial overnight lá (vai puxar dezenas de GB rápido pela LAN). Depois leva a working copy pra casa (já populada) e pode trabalhar OFFLINE — `svn info`/`log`/`status`/`diff`/`blame` funcionam sem rede, e `svn update` periódico requer voltar à empresa OU usar 2b/2c.

**Sem ação no hosts file.** Working copy fica consistente com Wagner.

### 2b — Tailscale futuro (mais seguro, infra nova)

Reabrir decisão da regra (atualmente vetada — ver "Reabertura desta decisão" em [feedback-commits-delphi-svn.md](feedback-commits-delphi-svn.md)). Subir Tailscale no servidor `192.168.0.55` + máquina Felipe → Magic DNS resolve `wr2.com.br` corretamente via mesh privado, tráfego sempre criptografado.

**Não disponível hoje — só se Wagner aprovar setup novo.**

### 2c — Hosts file override (de casa, sem Tailscale)

⚠️ **Tradeoff:** sobrescreve DNS público de `wr2.com.br` na máquina do Felipe — qualquer outro serviço que use esse hostname externamente (provavelmente app web em HTTPS via AWS) deixa de funcionar nessa máquina.

```powershell
# Windows admin shell — editar hosts file
notepad C:\Windows\System32\drivers\etc\hosts
```

Adicionar linha no final:
```
177.74.67.30    wr2.com.br
```

Salvar (Notepad pode pedir Save As → mesmo nome, sobrescrever).

Validar resolução:
```powershell
nslookup wr2.com.br
# esperado: Address 177.74.67.30 (não 177.12.170.3 que é o site institucional WR2)

Test-NetConnection wr2.com.br -Port 8777
# esperado: TcpTestSucceeded = True
```

**Confirmar com Wagner antes** se esse override quebra algum app web/serviço que Felipe usa diariamente.

## Passo 3 — Checkout working copy completo

⚠️ **Demorado** — checkout completo da pasta `Programas` é dezenas de GB. Estimativa: várias horas dependendo da banda. Rodar em momento que possa deixar máquina ligada (overnight é seguro). Bandwidth na LAN é muito maior que via internet — preferir Passo 2a se possível.

```powershell
# Definir destino (D: se tiver, senão ajustar path)
# Recomendação: usar D:\Programas pra ficar consistente com working copy do Wagner
# (paths absolutos em docs e RUNBOOKs apontam pra D:\Programas\...)

$svn = 'C:\Program Files\SlikSvn\bin\svn.exe'
$dest = 'D:\Programas'

# Se não tiver drive D:, alternativa C:\Programas (mas perde consistência com docs)
# $dest = 'C:\Programas'

# Checkout completo do Trunk (Wagner aprovou pasta inteira 2026-05-27)
& $svn checkout http://wr2.com.br:8777/svn/Programas/Trunk $dest
# Vai pedir user/password — usar credencial pegada do Wagner/Vaultwarden
# Marcar "save credentials" se confiável na máquina
```

Se conexão cair no meio: rodar `svn cleanup` + `svn update` pra retomar:
```powershell
& $svn cleanup $dest
& $svn update $dest
```

## Passo 4 — Validar

```powershell
$svn = 'C:\Program Files\SlikSvn\bin\svn.exe'

& $svn info 'D:\Programas' | Select-String 'URL|Revisão|Working|UUID'
# esperado:
#   Working Copy Root Path: D:\Programas
#   URL: http://wr2.com.br:8777/svn/Programas/Trunk
#   Revisão: >= 10815
# (working copy original do Wagner usa URL http://servidor-crm:8777/... — mesmo repo
#  via hostname legacy LAN. Felipe novo deve usar wr2.com.br canônico)

& $svn log 'D:\Programas' -l 5
# esperado: últimos 5 commits visíveis

Test-Path 'D:\Programas\WR Comercial\Principal.pas'
# esperado: True (arquivo core do Delphi)
```

## Passo 5 — Validar regra READ-ONLY no Claude Code

Quando Felipe abrir Claude Code numa sessão tocando `D:\Programas\`:

1. Skill [`brief-first`](../../.claude/skills/brief-first/) carrega brief MCP (inclui feedback-commits-delphi-svn.md indexado)
2. Tentativa de `svn commit` deve ser BLOQUEADA pelo Claude (regra na feedback-commits-delphi-svn.md)
3. Se Felipe quiser comitar mesmo assim: faz manual via TortoiseSVN GUI (Wagner aprova caso a caso — ver [ADR 0113](../decisions/0113-integracao-delphi-laravel-ads-3-caminhos.md) "não recompila Delphi")

## Credenciais SVN — pegar com Wagner

Opções (Wagner escolhe):

| Opção | Como | Pra quem |
|---|---|---|
| **A — usuário Administrador compartilhado** | Wagner passa user/pass do `Administrador` (vi `Autor da última Mudança: Administrador` no log SVN) via Vaultwarden | Simples mas todos commits aparecem como "Administrador" |
| **B — usuário pessoal Felipe** | Wagner cria usuário SVN próprio pro Felipe no servidor SVN (`192.168.0.55:8777`) com permissão READ-ONLY pelo menos | Mais auditável; precisa Wagner acessar admin SVN |
| **C — auth anônima** | Wagner libera leitura anônima no SVN config | Mais aberto; só se trabalho não tem dado sensível |

Recomendação: **B se for fácil**, senão A com nota "Felipe usa Administrador read-only por enquanto" e migrar pra B quando viável.

## Bandwidth e tempo estimado

| Tamanho working copy | Banda 50 Mbps | Banda 200 Mbps |
|---|---|---|
| ~5 GB (só WR Comercial) | ~15 min | ~3 min |
| ~50 GB (pasta inteira) | ~3 horas | ~45 min |
| ~150 GB (se inclui binários/assets pesados) | ~9 horas | ~2 horas |

Variável principal: upload do escritório Wagner (TP-Link ateky.net.br — banda exata desconhecida). Felipe pode rodar `svn checkout` overnight pra primeira vez.

## Quando outros devs (Maiara/Eliana/Luiz) precisarem

Pedir Wagner antes (per regra deste runbook). Wagner avalia:
- Maiara: faz UI Inertia — raramente toca Delphi. Provavelmente não precisa.
- Eliana: menos técnica. Provavelmente não precisa.
- Luiz: avaliar escopo.

Quando aprovado: roda mesmo passo-a-passo, atualiza este doc adicionando linha em "Provisionamento atual".

## Troubleshoot

| Sintoma | Causa | Fix |
|---|---|---|
| `nslookup wr2.com.br` retorna `177.12.170.3` (site institucional) e não `192.168.0.55` | Felipe está fora da LAN — split-DNS interno não alcança | Escolher Passo 2a (ir à empresa) OU 2c (hosts file override pra 177.74.67.30) OU 2b (Tailscale futuro) |
| `svn checkout` retorna "Connection timed out" | Porta 8777 bloqueada pelo firewall do ISP do Felipe OU resolveu pra IP AWS errado | Confirmar resolução com `Test-NetConnection wr2.com.br -Port 8777`; aplicar Passo 2c |
| `svn checkout` pede senha repetida | credencial não foi salva ou inválida | rodar `svn auth --remove` e tentar de novo |
| `svn cleanup` falha com "database is locked" | sessão SVN paralela ainda rodando | matar processo svn.exe em Task Manager e retry |
| Checkout traz pasta mas Test-Path em arquivo específico falha | path tem espaço/caracter especial — verificar literal | Usar nome exato ex `'D:\Programas\WR Comercial\Principal.pas'` com aspas |
| Working copy do Wagner mostra URL `servidor-crm` mas Felipe usa `wr2.com.br` | Hostnames diferentes pro MESMO repo SVN (UUID match) | Funciona normal — não precisa `svn relocate` se UUID bate. Se SVN reclamar de "canonical URL mismatch": rodar `svn relocate http://wr2.com.br:8777/svn/Programas/Trunk D:\Programas` |

## Ver também

- [feedback-commits-delphi-svn.md](feedback-commits-delphi-svn.md) — regra READ-ONLY (princípio 4 Constituição v2)
- [legacy-delphi-firebird.md](legacy-delphi-firebird.md) — código fonte Delphi + 50 bancos Firebird + creds
- [contrato-delphi-inviolavel.md](contrato-delphi-inviolavel.md) — Tier 0 wire IRREVOGÁVEL
- [infra-rede-empresa.md](infra-rede-empresa.md) — NAT rules + DHCP + SVN dedicado `.55:8777`
- [oimpresso-team-onboarding skill](../../.claude/skills/oimpresso-team-onboarding/SKILL.md) — setup MCP base do time (seção 11 adicionada 2026-05-27 aponta pra este runbook)
- [PEGADINHAS.md:182](../legacy-delphi/PEGADINHAS.md) — hard rule READ-ONLY em `D:\Programas\WR Comercial\app\`

## Histórico provisionamento

| Data | Dev | Status | Notas |
|---|---|---|---|
| 2026-05-27 | Wagner | ✅ já tinha (working copy original) | máquina principal — TortoiseSVN + SlikSvn instalados |
| 2026-05-27 | Felipe | 🔲 a provisionar | Wagner aprovou — Felipe roda passo-a-passo deste runbook |
| — | Maiara | ❌ sem provisionamento | aguarda demanda |
| — | Eliana | ❌ sem provisionamento | aguarda demanda |
| — | Luiz | ❌ sem provisionamento | aguarda demanda |
