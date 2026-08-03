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
