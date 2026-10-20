{{-- Página pública da prova (UC-FLX-02 · US-COMVIS-NEW-004). Sem login, sem dado do cliente:
     só a prova, o nome da etapa e os dois botões. @see AprovacaoArteController --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Aprovação da arte</title>
    <style>
        body { font-family: system-ui, -apple-system, 'Segoe UI', Arial, sans-serif; color: #111; background: #f5f5f5; margin: 0; }
        main { max-width: 720px; margin: 0 auto; padding: 16px; }
        .card { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 16px; }
        h1 { font-size: 20px; margin: 0 0 12px; }
        img { max-width: 100%; height: auto; border: 1px solid #ddd; }
        .acoes { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 16px; }
        button { font-size: 16px; padding: 12px 18px; border-radius: 6px; border: 1px solid #111; cursor: pointer; }
        .aprovar { background: #116611; color: #fff; border-color: #116611; }
        textarea { width: 100%; box-sizing: border-box; min-height: 90px; font-size: 15px; margin-top: 8px; }
        .erro { color: #a00; }
    </style>
</head>
<body>
<main>
    <div class="card">
        @if($pedido)
            <h1>Aprovação da arte{{ $etapa ? ' — '.$etapa : '' }}</h1>
            @if(preg_match('/\.(png|jpe?g|gif|webp)(\?.*)?$/i', $pedido->arquivo))
                <img src="{{ $pedido->arquivo }}" alt="Prova da arte">
            @endif
            <p><a href="{{ $pedido->arquivo }}" target="_blank" rel="noopener noreferrer">Abrir a prova</a></p>
            <p>Válido até {{ $pedido->expira_em->format('d/m/Y H:i') }}.</p>
            @if($errors->any())<p class="erro">{{ $errors->first() }}</p>@endif
            <div class="acoes">
                <form method="POST" action="{{ route('producao.arte.aprovar', $token) }}">
                    @csrf
                    <button type="submit" class="aprovar">Aprovar a arte</button>
                </form>
            </div>
            <form method="POST" action="{{ route('producao.arte.recusar', $token) }}" style="margin-top: 16px;">
                @csrf
                <label for="comentario">Precisa mudar algo? Diga o quê:</label>
                <textarea id="comentario" name="comentario" maxlength="2000" required></textarea>
                <div class="acoes"><button type="submit">Pedir alteração</button></div>
            </form>
        @else
            <h1>Aprovação da arte</h1>
            <p>{{ $mensagem }}</p>
        @endif
    </div>
</main>
</body>
</html>
