<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>NF-e {{ $emissao->numero }} autorizada</title>
<style>
  body { margin:0; padding:0; background:#f5f7fa; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; color:#1f2937; }
  .wrap { max-width:600px; margin:0 auto; padding:24px 16px; }
  .card { background:#fff; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; }
  .header { background:#0f172a; color:#fff; padding:24px; }
  .header h1 { margin:0 0 4px; font-size:20px; font-weight:600; }
  .header p { margin:0; font-size:13px; opacity:.8; }
  .body { padding:24px; }
  .body p { margin:0 0 12px; font-size:14px; line-height:1.5; }
  table.fields { width:100%; border-collapse:collapse; margin:16px 0; font-size:13px; }
  table.fields td { padding:8px 0; border-bottom:1px solid #f1f5f9; vertical-align:top; }
  table.fields td:first-child { color:#64748b; width:130px; font-weight:500; }
  table.fields td:last-child { font-family:'SF Mono',Menlo,Consolas,monospace; color:#0f172a; }
  .anexos { background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:12px 16px; font-size:13px; }
  .anexos strong { display:block; margin-bottom:4px; color:#475569; }
  .anexos ul { margin:0; padding-left:20px; }
  .anexos li { margin:2px 0; }
  .footer { padding:16px 24px; background:#f8fafc; border-top:1px solid #e5e7eb; font-size:12px; color:#64748b; }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="header">
      <h1>NF-e autorizada</h1>
      <p>Sua nota fiscal eletrônica foi emitida com sucesso.</p>
    </div>
    <div class="body">
      <p>Olá,</p>
      <p>Segue em anexo a Nota Fiscal Eletrônica autorizada referente ao seu pagamento.</p>

      <table class="fields">
        <tr><td>Número</td><td>{{ $emissao->numero }}</td></tr>
        <tr><td>Série</td><td>{{ $emissao->serie }}</td></tr>
        <tr><td>Chave de acesso</td><td style="word-break:break-all">{{ $emissao->chave_44 }}</td></tr>
        <tr><td>Valor total</td><td>R$ {{ number_format((float) $emissao->valor_total, 2, ',', '.') }}</td></tr>
        <tr><td>Data de emissão</td><td>{{ optional($emissao->emitido_em)->format('d/m/Y H:i') }}</td></tr>
      </table>

      <div class="anexos">
        <strong>Anexos</strong>
        <ul>
          <li>DANFE em PDF (impressão e visualização)</li>
          <li>XML autorizado (uso fiscal e contábil)</li>
        </ul>
      </div>
    </div>
    <div class="footer">
      Esta é uma notificação automática enviada por {{ $razaoEmissora ?? 'oimpresso' }}.
      Em caso de dúvidas, responda este e-mail.
    </div>
  </div>
</div>
</body>
</html>
