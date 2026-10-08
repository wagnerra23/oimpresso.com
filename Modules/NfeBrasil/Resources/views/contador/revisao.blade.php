{{--
  Revisão do contador pelo link (playbook Fiscal thread 15b). Página pública, sem o shell do ERP:
  quem abre não tem conta. Só regras fiscais — nada de cliente, venda ou valor de nota.
--}}
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Revisão de regras fiscais — {{ $empresa }}</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; padding: 24px 16px; color: #1f2937; background: #f9fafb; }
        main { max-width: 960px; margin: 0 auto; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .sub { color: #6b7280; margin: 0 0 24px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 12px; }
        .erro { color: #b91c1c; }
        table { border-collapse: collapse; width: 100%; font-size: 14px; }
        td, th { text-align: left; padding: 4px 8px; border-bottom: 1px solid #f3f4f6; }
        button { min-height: 44px; padding: 0 16px; border-radius: 6px; border: 1px solid #d1d5db; background: #fff; cursor: pointer; }
        button.primario { background: #4f46e5; border-color: #4f46e5; color: #fff; }
        input[type=text], textarea { font: inherit; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; }
        .acoes { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; align-items: flex-start; }
    </style>
</head>
<body>
<main>
    <h1>Revisão de regras fiscais</h1>
    <p class="sub">{{ $empresa }} · para {{ $link->nome }}@if($link->crc) (CRC {{ $link->crc }})@endif</p>

    @if ($modo === 'codigo')
        <div class="card">
            <p>Enviamos um código de 6 dígitos para o seu e-mail. Ele vale 15 minutos.</p>
            @if (! empty($erro))<p class="erro" role="alert">{{ $erro }}</p>@endif
            <form method="post" action="{{ request()->fullUrl() }}" class="acoes">
                @csrf
                <label for="codigo">Código</label>
                <input type="text" id="codigo" name="codigo" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required>
                <button type="submit" class="primario">Entrar</button>
            </form>
        </div>
    @else
        <p><a href="{{ route('nfe-brasil.contador.csv', ['link' => $link->id]) }}">Baixar regras (CSV)</a>
            — devolva o arquivo à empresa; ela importa e as mudanças voltam para você aceitar.</p>

        @forelse ($revisoes as $v)
            <div class="card">
                <strong>NCM {{ $v->ncm }}</strong> · {{ $v->uf_origem }} → {{ $v->uf_destino ?: 'todas as UFs' }}
                <div class="sub">{{ $v->autor }} · {{ \Illuminate\Support\Carbon::parse($v->created_at)->format('d/m/Y H:i') }} · origem: {{ $v->origem }}</div>
                <table>
                    <thead><tr><th>Campo</th><th>De</th><th>Para</th></tr></thead>
                    <tbody>
                    @foreach ($v->diff as $campo => $par)
                        <tr><td>{{ $campo }}</td><td>{{ $par[0] ?? '—' }}</td><td>{{ $par[1] ?? '—' }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
                @if ($v->status === 'ajuste_pedido')
                    <p>Ajuste pedido: {{ $v->comentario }}</p>
                @else
                    <div class="acoes">
                        <form method="post" action="{{ route('nfe-brasil.contador.aceitar', ['link' => $link->id, 'id' => $v->id]) }}">
                            @csrf<button type="submit" class="primario">Aceitar</button>
                        </form>
                        <form method="post" action="{{ route('nfe-brasil.contador.ajuste', ['link' => $link->id, 'id' => $v->id]) }}" class="acoes">
                            @csrf
                            <textarea name="comentario" rows="2" aria-label="O que precisa ser ajustado" required></textarea>
                            <button type="submit">Pedir ajuste</button>
                        </form>
                    </div>
                @endif
            </div>
        @empty
            <div class="card">Nada para revisar agora.</div>
        @endforelse
    @endif
</main>
</body>
</html>
