# App — Fiscal (tela 14) — só leitura

> Parte do contrato da API do app ([API-CONTRATO-v1.md](../API-CONTRATO-v1.md): regras gerais §0, Início §6).

`GET /api/app/fiscal?status=todos|rascunho|processando|autorizado|cancelado|rejeitado&pagina=N` →
`{ itens:[{id, tipo, numero, referencia, valor, status, chave, erro, emitido_em}],
contadores:{todos, rascunho, processando, autorizado, cancelado, rejeitado}, pagina, tem_mais }`,
20 por página, mais recente primeiro.

- Fontes: `nfe_emissoes` (modelo 55 → `NFe`, 65 → `NFCe`) e `nfse_emissoes` (`NFSe`), numa lista só.
  `id` é o id da tabela de origem — único só junto com `tipo` (uma NF-e e uma NFS-e podem ter o mesmo).
- Acesso = o da web: módulo Fiscal no plano e, por tipo, a permissão da tela dele (`fiscal.nfe.view`
  para NF-e/NFC-e, `fiscal.nfse.view` para NFS-e). Quem vê só um tipo recebe só aquele (lista e
  contadores). Sem nenhum → `403 sem_permissao`. A área `fiscal` entra em `areas` (§6) com essa regra.
- Status: NF-e/NFC-e `pendente`/`enviando` → `processando` (a web conta "pendente" como processando),
  `autorizada` → `autorizado`, `cancelada`/`inutilizada` → `cancelado`, `rejeitada`/`denegada`/`erro_envio`
  → `rejeitado`. NFS-e `rascunho`, `processando`, `emitida` → `autorizado`, `cancelada`, `erro` → `rejeitado`.
- `referencia` = "Pedido #<nº da venda> · <cliente>" quando a nota tem venda; sem venda, o destinatário
  (NF-e) ou o tomador (NFS-e). `valor` = total da nota (NFS-e: valor dos serviços).
- `chave` só em `autorizado` (NF-e/NFC-e: chave de 44 dígitos; NFS-e: código de verificação da
  prefeitura). `erro` só em `rejeitado`: "Rejeição <cStat>: <motivo da SEFAZ>" (NFS-e: a mensagem do provedor).
- `emitido_em` = data de emissão (sem ela, a de criação), ISO com fuso.
- Tier 0: as duas tabelas e os joins filtrados pelo business do token.
