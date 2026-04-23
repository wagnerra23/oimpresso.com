---
name: Hostinger always connect via IPv4
description: When connecting to oimpresso.com hosted on Hostinger, always use IPv4 — never switch protocols
type: feedback
originSessionId: 3f332cf1-9ebd-4bb2-8b41-a6a1fd23c222
---
Sempre conectar ao servidor Hostinger (oimpresso.com) usando IPv4. Nunca trocar de protocolo durante a conexão.

**Why:** Hostinger proíbe troca de protocolo durante a sessão — qualquer alternância entre IPv4/IPv6 derruba a conexão.

**How to apply:** Em qualquer comando SSH/SFTP/FTP/curl/wget/rsync que conecte a oimpresso.com ou servidores Hostinger, forçar IPv4 explicitamente (`-4`, `--ipv4`, ou equivalente). Não misturar hosts IPv4/IPv6 na mesma operação.
