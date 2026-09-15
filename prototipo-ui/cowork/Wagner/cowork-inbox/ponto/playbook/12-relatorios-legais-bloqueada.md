---
sessao: "12"
titulo: Relatórios legais AFD / AFDT / AEJ — BLOQUEADA (W7)
dono: "[W]"
base: e86130722de1
prefixo: nenhum até W7. Depois: Services/ReportService.php · Tests/Feature/RelatorioLegalContratoTest.php · RelatorioController@gerar (só trocar o 501 pela chamada)
nao_toca: RelatorioController@index · Pages/Ponto/Relatorios/Index.tsx (a tela já mostra o catálogo; o que falta é a geração)
depende: W7 (ordem de implementação)
---
# 12 · Relatórios legais — bloqueada

## Estado (lido no `main`)
`RelatorioController.php:90-102`: *"Só o `espelho` tem destino hoje. Os outros 7 seguem `abort(501)`"* — `abort(501, "Implementar geração de '{$chave}' em ReportService.")`. A tela `Relatorios/Index.tsx` (🔵, `RelatorioCatalogoContratoTest`) lista o catálogo e marca o que não existe — **501 nunca é sucesso**, e o protótipo (thread 09) tem de mostrar o mesmo.

## Por que espera [W]
AFD, AFDT e AEJ são artefatos legais (Portaria MTP 671/2021 Anexos) com formato fixo e ordem de dependência (AFD é insumo do AEJ). A ordem de implementação é decisão de produto/lei (W7), não de tela. Regras que valem para quem abrir: NSR **server-authoritative**; arquivo gerado a partir de `ponto_marcacoes` append-only, nunca de apuração; hash/assinatura conforme o Anexo citado **literalmente**.

## Quando destravar
```
1) [W] responde W7 (ordem, e se AFDT entra ou é legado)
2) [CL] 1 relatório por PR (≤300 ln): ReportService::<chave>() + Pest com arquivo-golden + trocar o 501 daquela chave
PARAR SE : formato exigir campo que ponto_marcacoes não tem → parar; nunca preencher com apuração
```

## Prova (quando destravar)
- `RelatorioController.php` sem `abort(501` para a chave entregue · Pest golden verde · `_saida-12.md`
