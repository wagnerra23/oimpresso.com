---
name: curl --resolve bypassa api.github.com bloqueado lado Wagner
description: Quando gh CLI falha em api.github.com (4.228.31.149 backbone Azure bloqueado), executar REST direto via curl --resolve apontando pra IP GitHub edge 140.82.*. Bypassa o bloqueio e executa qualquer ação gh teria feito.
type: reference
---
Workaround testado e validado pra rede do Wagner que bloqueia o backbone Azure do GitHub (`api.github.com → 4.228.31.149`).

**Quando aplicar:**
- `gh pr merge/create/view/api` retorna `dial tcp 4.228.31.149:443: connectex` timeout
- `curl https://api.github.com` (sem `--resolve`) timeout
- `curl https://github.com` (porta 443) **responde 200** — confirma que GitHub está no ar, só rota IP bloqueada

**Diagnóstico:**
```bash
nslookup api.github.com 1.1.1.1
# Address: 4.228.31.149   ← este IP bloqueado lado Wagner

for ip in 140.82.112.6 140.82.113.6 140.82.114.6 140.82.121.6; do
  curl -sS -m 5 --resolve api.github.com:443:$ip \
    -o /dev/null -w "via $ip: %{http_code}\n" \
    https://api.github.com
done
# via 140.82.112.6: 200 — funciona!
```

**Uso prático — mergear PR:**
```bash
TOKEN=$(gh auth token)
curl -sS -m 15 --resolve api.github.com:443:140.82.112.6 \
  -X PUT \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/vnd.github+json" \
  -d '{"merge_method":"squash"}' \
  https://api.github.com/repos/wagnerra23/oimpresso.com/pulls/<N>/merge
```

**Criar PR:**
```bash
curl -sS -m 20 --resolve api.github.com:443:140.82.112.6 \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/vnd.github+json" \
  -X POST "https://api.github.com/repos/wagnerra23/oimpresso.com/pulls" \
  --data-binary @- <<'JSON'
{"title":"...","head":"branch-name","base":"main","body":"..."}
JSON
```

**Ver status PR:**
```bash
curl -sS --resolve api.github.com:443:140.82.112.6 \
  -H "Authorization: Bearer $TOKEN" \
  https://api.github.com/repos/wagnerra23/oimpresso.com/pulls/<N> \
  | grep -E '"state"|"mergeable"|"mergeable_state"|"merged"'
```

**Por que funciona:**
- GitHub serve a mesma API em vários IPs edge (round-robin DNS GeoDNS)
- Wagner ISP/firewall bloqueia subnet `4.228.*` (Azure) mas permite `140.82.*` (GitHub próprio)
- `--resolve host:port:ip` força curl a usar IP específico mantendo SNI/Host correto

**IPs GitHub edge alternativos testados (2026-05-15):**
- `140.82.112.6` ✅
- `140.82.113.6` ✅
- `140.82.114.6` ✅
- `140.82.121.6` ✅

Se `140.82.*` também cair futuramente, tentar outras subnets GitHub: `192.30.252.*`, `185.199.108.*` (Pages edge — testar).

**Não usa pra:**
- Push/clone — `git push origin <branch>` em `github.com:443` (não `api.github.com`) tipicamente funciona sem workaround
- Token JWT/SSO redirect (usa fluxo de browser de qualquer jeito)

**Refs:**
- [feedback-gh-cli-vs-git-push-rotas-rede.md](feedback-gh-cli-vs-git-push-rotas-rede.md) — diagnóstico inicial
- sessão 2026-05-15 merge #853 #859 (commits `bcdcf2cec` + `17a226fc6` mergeados via essa técnica em ~3min após 7 falhas de `gh`)
