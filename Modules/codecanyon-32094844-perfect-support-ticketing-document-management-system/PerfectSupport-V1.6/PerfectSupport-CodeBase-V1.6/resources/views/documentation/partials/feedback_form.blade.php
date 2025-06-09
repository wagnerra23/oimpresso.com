<div class="col-md-12">
	<form method="POST" action="{{route('doc.feedback')}}">
		@csrf
		<input type="hidden" name="doc_id" value="{{$doc->id}}">
		<input type="hidden" name="doc_type" value="{{$doc->doc_type}}">
		<p class="float-right">
			@lang('messages.was_this_article_helpful')
			<button type="submit" class="btn btn-success btn-sm" name="feedback" value="yes" @if(Cookie::get($doc->id)) disabled @endif>
			  @lang('messages.yes') <span class="badge badge-light">{{$doc->yes}}</span>
			</button>
			<button type="submit" class="btn btn-danger btn-sm" name="feedback" value="no" @if(Cookie::get($doc->id)) disabled @endif>
			  @lang('messages.no') <span class="badge badge-light">{{$doc->no}}</span>
			</button>
		</p>
	</form>
	@includeIf('layouts.partials.status')
</div>