# Módulo NFSe

Emissão de Nota Fiscal de Serviços Eletrônica via **Sistema Nacional NFSe** (LC 214/2025).

- Webservice federal direto (`sefin.nfse.gov.br`) — sem provider terceiro, custo zero
- Município: Tubarão-SC (IBGE `4218707`) — migrou pra SN-NFSe em 01/01/2026
- Auth: Certificado A1 (.pfx)
- Lib: [`nfse-nacional/nfse-php`](https://packagist.org/packages/nfse-nacional/nfse-php) v1.19+

## Configuração

Adicionar ao `.env`:

```env
# NFSe — Sistema Nacional NFSe (LC 214/2025)
NFSE_AMBIENTE=homologacao          # homologacao | producao
NFSE_CERT_PATH=storage/certs/oimpresso.pfx
NFSE_CERT_SENHA=sua_senha_aqui
NFSE_MUNICIPIO_IBGE=4218707        # Tubarão-SC
```

Certificado A1:
```bash
# Copiar .pfx pra pasta storage/certs/ (gitignored)
mkdir -p storage/certs
cp /caminho/para/oimpresso.pfx storage/certs/oimpresso.pfx
```

## Sprints

| Sprint | Tasks | Status |
|--------|-------|--------|
| A — Pesquisa + setup | US-001 ✅ · US-002 🔄 · US-003 | Em progresso |
| B — Backend | US-004 · US-005 · US-006 · US-007 | Pendente |
| C — UI React | US-008 · US-009 · US-010 | Pendente |
| D — Validação + prod | US-011 · US-012 · US-013 · US-014 | Pendente |

Ver SPEC completa: [`memory/requisitos/NFSe/SPEC.md`](../../memory/requisitos/NFSe/SPEC.md)

## Pesquisa fiscal

Resultado US-001: [`memory/requisitos/NFSe/PESQUISA_TUBARAO.md`](../../memory/requisitos/NFSe/PESQUISA_TUBARAO.md)
