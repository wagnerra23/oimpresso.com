---
title: "RUNBOOK — Fiscal/Config (sub-página 6)"
owner: W
status: ativo
last_validated: "2026-11-06"
---

# RUNBOOK — Fiscal/Config (sub-página 6)

> **Tela:** `/fiscal/config` · **Permissão:** `fiscal.config.edit` · **PR origem:** PR #3 Wave final
> **Acesso (2026-11-05, #10404):** além da permissão da tela, a rota exige `CheckUserLogin` e o pacote `fiscal_module` da empresa (middleware `FiscalLiberado`) — sem o pacote, a URL direta também é recusada.

## 1. Objetivo

Configuração fiscal da empresa num lugar só: cert A1, ambiente SEFAZ, contingência, contador, séries e SPED. Cert, ambiente, contingência e contador são **editados nesta tela**; regime e tributação seguem em `/nfe-brasil/tributacao` (link).

> ⚠️ **Corrigido em 2026-11-06.** Até aqui este RUNBOOK dizia *"visão read-only … edição vive em
> `Modules/NfeBrasil/.../Configuracao/Certificado.tsx`"*. O arquivo não existe (a Page foi removida e o
> GET legado redireciona para `/fiscal/config`, *"UNIFICADA — Wagner 2026-05-27"*), e a tela posta.
> O charter foi reconciliado em 2026-09-02 ([`Config.charter.md`](../../../resources/js/Pages/Fiscal/Config.charter.md)).

| Formulário em `Config.tsx` | POST para | Permissão no controller |
|---|---|---|
| upload do certificado A1 | `/nfe-brasil/configuracao/certificado` | do NfeBrasil |
| ambiente Homologação × Produção (motivo + confirmação) | `/nfe-brasil/configuracao/certificado/ambiente` | do NfeBrasil |
| ativar/desativar contingência | `/nfe-brasil/contingencia/ativar` (e desativar) | do NfeBrasil |
| contador (nome, e-mail, CRC) | `/fiscal/config/contador` (throttle 20/min) | `fiscal.config.edit` |

O ambiente gravado aqui é o que a manifestação, a distribuição DF-e e a inutilização passaram a usar (`business.ambiente`, `Modules/NfeBrasil/Support/AmbienteDoEmitente.php`, #10405 em 2026-11-05), em vez da variável de ambiente do servidor.

## 2. Estrutura

```
FxShell route="fiscal_config"
└── Abas (whitelist server-side): cert | series | ambiente | sped
    ├── cert     — cert A1 ativo (validade, dias, tom) + upload + contador
    ├── ambiente — Homologação × Produção + contingência
    ├── series   — séries fiscais (parte ainda mockada: `mockSeriesFiscais`, ver SDD §5.4.1)
    └── sped     — dados do SPED
```

## 3. Dados

- `NfeCertificado` (HasBusinessScope) — `$hidden = ['encrypted_password']` 🔒
- `NfeBusinessConfig` (1 por business) — regime/tributacao default cascata
- Tone cert: bad ≤7d, warn ≤60d, ok >60d

## 4. Permissão

`fiscal.config.edit` — pré-existente PR #1.

## 5. Riscos

- **R1**: `encrypted_password` NUNCA pode vazar — `$hidden` no Model garante
- **R2**: A tela edita (tabela da §1). Trocar o ambiente para Produção muda o `tpAmb` de tudo o que a empresa manda à SEFAZ — o formulário exige motivo e confirmação. *(Até 2026-11-06 este item dizia "edits via UI desta tela NÃO existem"; estava errado desde a unificação de 2026-05-27.)*
- **R3**: Se cert expirar, todas emissões falham — alerta visual obrigatório (já implementado pelo tone bad)

## 6. Smoke biz=1

```bash
curl -sv "https://oimpresso.com/fiscal/config" -H "Cookie: ..." | grep '^< HTTP'
# Esperado: < HTTP/2 200 (ou 403 sem fiscal.config.edit)
```
