# Casos de uso — /product-catalogue/catalogue-qr (contrato de teste)

> `UC-CQR-01`…`UC-CQR-10`.

| ID | Cenário | Esperado |
| --- | --- | --- |
| UC-CQR-01 | Abrir a tela | nenhum QR gerado; vazio explica o que fazer; botão "Gerar QR code" desabilitado |
| UC-CQR-02 | Clicar em gerar sem local | ação bloqueada + "Escolha o local comercial primeiro." (o blade vivo dava `alert()`) |
| UC-CQR-03 | Escolher local e gerar | QR renderizado + link `/catalogue/{biz}/{local}` visível + selo "256 × 256 px · PNG" |
| UC-CQR-04 | Trocar título/subtítulo | o card do QR reflete a copy; nada é gerado até clicar de novo |
| UC-CQR-05 | Trocar a cor do QR | swatch ativo muda; aviso de contraste permanece visível |
| UC-CQR-06 | Negócio sem logo cadastrado | switch do logo desabilitado + sublabel "sem logo cadastrado no negócio: o QR sai limpo" |
| UC-CQR-07 | Copiar link | botão vira "Link copiado" por ~1,6 s; o link no clipboard é o mesmo do QR |
| UC-CQR-08 | Nenhum local comercial cadastrado | vazio de primeira vez + ação que leva a Configurações › Locais comerciais |
| UC-CQR-09 | Falha ao carregar locais | vazio de erro dizendo que nada foi publicado |
| UC-CQR-10 | Papel sem acesso | sem-permissão explicando que publicar preço não é do balcão |

## Anti-regressão

- O link nunca é montado sem `location_id`.
- A tela nunca some com o aviso de catálogo público.
- Nenhuma escrita: a tela é geradora, não publicadora.
