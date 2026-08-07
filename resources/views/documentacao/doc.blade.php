@extends('documentacao.layout')

@section('titulo', $doc->title)

@section('conteudo')
  <div class="col">
    <div class="stamp">{{ $doc->type }}@if ($doc->module) · {{ $doc->module }}@endif</div>

    <div class="colophon">
      <span><b>Origem</b> {{ $doc->git_path }}</span>
      @if ($doc->git_sha)
        <span><b>Commit</b> {{ substr($doc->git_sha, 0, 8) }}</span>
      @endif
      @if ($doc->indexed_at)
        {{-- cast 'datetime' no model: sem format() sairia o timestamp inteiro --}}
        <span><b>Indexado em</b> {{ $doc->indexed_at->format('Y-m-d') }}</span>
      @endif
    </div>

    <div class="doc">{!! $html !!}</div>

    <footer>
      Conteúdo servido do acervo sincronizado do git
      (<code>{{ $doc->git_path }}</code>). O arquivo no repositório é a fonte —
      onde esta página divergir dele, <strong>o repositório manda</strong>.
      @if ($doc->pii_redactions_count > 0)
        <br>Este documento passou pelo redator de PII na indexação
        ({{ $doc->pii_redactions_count }} campo(s) redigido(s)).
      @endif
    </footer>

    {{-- Anterior/próximo na ORDEM DO RAIL, já filtrada pela lente ativa: o rodapé nunca
         oferece um destino que o menu ao lado não está mostrando. Só aparece quando este
         documento está no rail — doc fora dele (sem nav_group) não tem vizinho. --}}
    @php
      $pos = collect($nav['linear'] ?? [])->search(fn ($d) => $d['id'] === ($atual ?? null));
      $anterior = $pos === false ? null : ($nav['linear'][$pos - 1] ?? null);
      $proximo = $pos === false ? null : ($nav['linear'][$pos + 1] ?? null);
    @endphp

    @if ($anterior || $proximo)
      <nav class="vizinhos" aria-label="Documento anterior e próximo">
        @if ($anterior)
          <a href="{{ route('documentacao.documento', $anterior['id']) }}">
            <span class="rot">← anterior</span>{{ $anterior['rotulo'] }}
          </a>
        @endif
        @if ($proximo)
          <a class="dir" href="{{ route('documentacao.documento', $proximo['id']) }}">
            <span class="rot">próximo →</span>{{ $proximo['rotulo'] }}
          </a>
        @endif
      </nav>
    @endif
  </div>
@endsection

@push('script')
<script>
  document.querySelectorAll('.doc table').forEach(function (t) {
    if (t.parentElement.classList.contains('tabela-scroll')) return;
    var box = document.createElement('div');
    box.className = 'tabela-scroll';
    t.parentNode.insertBefore(box, t); box.appendChild(t);
  });
</script>
@endpush
