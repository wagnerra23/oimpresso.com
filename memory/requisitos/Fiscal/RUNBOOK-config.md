# RUNBOOK — Fiscal/Config (sub-página 6)

> **Tela:** `/fiscal/config` · **Permissão:** `fiscal.config.edit` · **PR origem:** PR #3 Wave final

## 1. Objetivo

Visão **read-only** consolidada de cert A1 + regime tributário + tributação default. Edição vive em `Modules/NfeBrasil/.../Configuracao/Certificado.tsx` canon (link no header).

## 2. Estrutura

```
FxShell route="fiscal_config"
└── Body
    ├── Section Cert A1 (`NfeCertificado::ativos()->first()`)
    │   └── cnpj_titular / valido_ate / dias / status / uuid
    ├── Section Regime + Emissão (`NfeBusinessConfig::first()`)
    │   └── regime / autoEmissionEnabled / tributacao_default JSON
    └── Notice rodapé link pra NfeBrasil edição
```

## 3. Dados

- `NfeCertificado` (HasBusinessScope) — `$hidden = ['encrypted_password']` 🔒
- `NfeBusinessConfig` (1 por business) — regime/tributacao default cascata
- Tone cert: bad ≤7d, warn ≤60d, ok >60d

## 4. Permissão

`fiscal.config.edit` — pré-existente PR #1.

## 5. Riscos

- **R1**: `encrypted_password` NUNCA pode vazar — `$hidden` no Model garante
- **R2**: Edits via UI desta tela NÃO existem (read-only by design — anti-hook charter)
- **R3**: Se cert expirar, todas emissões falham — alerta visual obrigatório (já implementado pelo tone bad)

## 6. Smoke biz=1

```bash
curl -sv "https://oimpresso.com/fiscal/config" -H "Cookie: ..." | grep '^< HTTP'
# Esperado: < HTTP/2 200 (ou 403 sem fiscal.config.edit)
```
