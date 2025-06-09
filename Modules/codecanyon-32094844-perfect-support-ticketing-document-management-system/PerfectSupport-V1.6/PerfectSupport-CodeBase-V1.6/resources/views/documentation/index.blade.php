@extends('layouts.app')
@section('css')
<style type="text/css">
	.pcoded-content {
		padding: 0px !important;
	}
	.row{
		padding: 30px !important;
	}
</style>
@includeIf('documentation.partials.doc_view_header_css')
@endsection
@section('content')
	@includeIf('layouts.partials.doc_search')
    <div class="row">
    	@if(count($documentations) > 0)
        	@foreach($documentations as $documentation)
        		<div class="col-md-4">
        			<div class="card">
						<div class="card-header">
							<h4>
								<a
									href="{{route('view.documentation', ['slug' => $documentation->doc_slug, 'documentation' => $documentation->id])}}">
									{{ucfirst($documentation->title)}}
								</a>
							</h4>
						</div>
						<div class="card-body pr-3 pl-3">
							<div class="accordion" id="documentation">
								@foreach($documentation->sections as $section)
									<div class="card">
										<div class="card-header">
											<div class="d-flex align-items-center">
												<h5 class="mb-0">
									            	<a href="#!" data-toggle="collapse" data-target="#collapse_{{$section->id}}"
									            		aria-expanded="false" aria-controls="collapse_{{$section->id}}" class="collapsed">
									            		{{ucfirst($section->title)}}
									            	</a>
									            </h5>
									            <a href="{{route('view.documentation.section', ['slug' => $section->doc_slug, 'documentation' => $section->id])}}">
									            	<i class="fas fa-external-link-square-alt"></i>
									            </a>
									        </div>
										</div>
										@if(count($section->articles) > 0)
											<div id="collapse_{{$section->id}}" class="collapse" aria-labelledby="heading_{{$section->id}}" data-parent="#documentation">
												<div class="card-body pr-3 pl-3">
				            						<ul class="list-group">
														@foreach($section->articles as $article)
															<li class="list-group-item text-dark d-flex justify-content-between align-items-center">
																<a href="{{route('view.section.article', ['slug' => $article->doc_slug, 'documentation' => $article->id])}}">
													            	{{ucfirst($article->title)}}
													            </a>
													            <span class="badge">
													            	<a href="{{route('view.section.article', ['slug' => $article->doc_slug, 'documentation' => $article->id])}}">
													            		<i class="fas fa-external-link-square-alt"></i>
													            	</a>
																</span>
															</li>
														@endforeach
													</ul>
												</div>
											</div>
										@endif
									</div>
								@endforeach
							</div>
						</div>
					</div>
        		</div>
        	@endforeach
        @endif
    </div>
@stop