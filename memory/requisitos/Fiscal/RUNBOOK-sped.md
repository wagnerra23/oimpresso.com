# RUNBOOK — Fiscal/Sped (sub-página 7)

> **Tela:** `/fiscal/sped` · **Permissão:** `fiscal.sped.export` · **PR origem:** PR #3 Wave final (placeholder)

## 1. Objetivo

**Placeholder consciente** — gerador SPED Fiscal real (EFD ICMS/IPI + EFD-Contribuições) é integração complexa pra PR dedicado. Esta tela mostra **panorama** dos 5 últimos meses com contagem agregada de `NfeEmissao` autorizadas como referência cru.

## 2. Estrutura

```
FxShell route="sped"
└── Body
    ├── Notice warning gradient "em desenvolvimento"
    ├── Tabela períodos (5 últimos meses)
    │   colunas: Competência · Status · Notas auth · Valor auth · Prazo · Export (disabled)
    └── Empty Livros (apuração ICMS/ISS — placeholder)
```

## 3. Dados

- Agregação `NfeEmissao` autorizadas por mês (5 últimos)
- Status heurístico: M = aberto, M-1 = pronto, M-2+ = entregue
- Prazo entrega = M.startOfMonth()->addMonth()->day(15)
- Sem geração de TXT SPED real (anti-hook)

## 4. Permissão

`fiscal.sped.export` — pré-existente PR #1.

## 5. Riscos

- **R1**: NÃO emitir SPED TXT real até implementação canônica (formato CONFAZ é crítico — gerar TXT inválido pode causar penalidade fiscal)
- **R2**: Heurística de status (aberto/pronto/entregue) é APROXIMAÇÃO — quem decide entrega final é contador via portal SEFIN

## 6. Smoke biz=1

```bash
curl -sv "https://oimpresso.com/fiscal/sped" -H "Cookie: ..." | grep '^< HTTP'
# Esperado: < HTTP/2 200 (ou 403 sem fiscal.sped.export)
```

## 7. Próximo PR

- Gerador EFD ICMS/IPI: parser TXT layout CONFAZ + queries reais (livros entrada/saída/apuração)
- Gerador EFD-Contribuições: PIS/COFINS cumulativo/não-cumulativo
- Workflow validação contador → entrega SEFIN
