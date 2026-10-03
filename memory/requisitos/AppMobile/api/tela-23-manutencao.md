# App — Manutenção (tela 23) — derivada da 07, sem rota própria

> Parte do contrato da API do app ([API-CONTRATO-v1.md](../API-CONTRATO-v1.md): regras gerais §0, Início §6).

Os 3 números saem de `GET /api/app/os` sem filtro: no pátio = `total`; aguardando peças =
`etapas[chave=aguardando_pecas].total`; prontos = `etapas[chave=pronto_retirada].total`. Sem
preventiva (o ERP não tem plano de preventiva). Telas 24/31/32 (Equipamentos) e 33 (Locais) ficam
fora até o ERP ter cadastro de equipamento de cliente e de box (decisão [W] 2026-10-02).
