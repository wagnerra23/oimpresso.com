@extends('documentacao.layout')

@section('titulo', 'Documentação do sistema')

@section('conteudo')
  <div class="col">
    <div class="stamp">Documentação do sistema</div>

    <div class="colophon">
      <span><b>Fonte</b> {{ $fonte }}</span>
      @if ($atualizadoEm)
        <span><b>Atualizado em</b> {{ $atualizadoEm }}</span>
      @endif
      <span><b>Renderizado</b> a cada acesso</span>
      @unless ($buscaDisponivel)
        <span><b>Busca</b> índice indisponível neste ambiente</span>
      @endunless
    </div>

    <div class="doc">{!! $html !!}</div>

    <footer>
      Esta página <strong>é</strong> o documento <code>{{ $fonte }}</code>, renderizado —
      não uma cópia dele. Alterou a fonte por PR? A página muda no próximo acesso.
      Não existe versão intermediária para ficar desatualizada.
      @if ($buscaDisponivel)
        Para o resto do acervo, use a busca acima — ela cobre decisões, referências,
        specs e runbooks.
      @endif
    </footer>
  </div>
@endsection

@push('script')
<script>
  // Tabela larga rola sozinha — o corpo da página nunca rola de lado.
  document.querySelectorAll('.doc table').forEach(function (t) {
    if (t.parentElement.classList.contains('tabela-scroll')) return;
    var box = document.createElement('div');
    box.className = 'tabela-scroll';
    t.parentNode.insertBefore(box, t); box.appendChild(t);
  });
</script>
@endpush
