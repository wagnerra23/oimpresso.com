@extends('layouts.app')
@section('css')
<style type="text/css">
	.pcoded-content {
		padding-top:0px !important;
		padding-left: 15px !important;
		padding-right:15px !important;
	}
</style>
@includeIf('documentation.partials.doc_view_header_css')
@endsection
@section('content')
	@includeIf('layouts.partials.doc_search')
	<div class="row">
		<div class="col-md-12">
			<div class="card">
				<div class="card-body">
					<blockquote class="blockquote mb-5">
                        <h2>
                        	{{ucfirst($article->title)}}
                        <h2>
                    </blockquote>
					{!!$article->content!!}
				</div>
			</div>
		</div>
		@includeIf('documentation.partials.feedback_form', ['doc' => $article])
	</div>
@stop