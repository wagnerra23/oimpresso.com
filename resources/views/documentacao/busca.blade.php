@extends('documentacao.layout')

@section('titulo', $termo !== '' ? 'Busca: ' . $termo : 'Busca')

@section('conteudo')
  <div class="col">
    <div class="stamp">Busca na documentação</div>

    @if ($indisponivel)
      {{-- Não finge "nenhum resultado": distingue ausência de acervo de ausência de match. --}}
      <div class="vazio">
        <p><strong>O índice não está acessível neste ambiente.</strong></p>
        <p>O acervo vive na tabela <code>mcp_memory_documents</code>, sincronizada do git
           por webhook. Sem ela, a busca não tem o que consultar — e mostrar
           “nenhum resultado” aqui seria mentira.</p>
      </div>
    @elseif ($termo === '')
      <div class="vazio">
        <p>Digite um termo acima para buscar em <strong>{{ $escopoProsa }}</strong>.</p>
        <p>Diário de bordo — sessões e handoffs — fica de fora de propósito: são
           registros datados, não documentação, e misturá-los enterraria o que você procura.</p>
      </div>
    @elseif ($resultados->isEmpty())
      <div class="vazio">
        <p>Nada encontrado para <strong>{{ $termo }}</strong>.</p>
        <p>Vale tentar um termo mais específico, ou o nome do módulo.</p>
      </div>
    @else
      <div class="colophon">
        <span><b>{{ $resultados->count() }}</b> resultado(s) para “{{ $termo }}”</span>
        <span><b>Escopo</b> {{ implode(' · ', $escopoTipos) }}</span>
      </div>

      @foreach ($resultados as $r)
        <div class="achado">
          <div class="meta">
            <span class="tag">{{ $r['type'] }}</span>
            @if ($r['module'])<span>{{ $r['module'] }}</span>@endif
            <span>{{ $r['git_path'] }}</span>
          </div>
          <a class="tit" href="{{ route('documentacao.documento', $r['slug']) }}">{{ $r['title'] }}</a>
          @if ($r['trecho'])<p>{{ $r['trecho'] }}</p>@endif
        </div>
      @endforeach
    @endif

    <footer>
      A busca usa o índice <strong>full-text que já existe</strong> no acervo
      (<code>title</code> + <code>content_md</code>) — o mesmo que a Jana consulta.
      Nenhum índice novo foi criado para esta página.
    </footer>
  </div>
@endsection
