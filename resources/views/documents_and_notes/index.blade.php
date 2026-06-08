<div class="table-responsive">
    @if(in_array('create', $permissions))
        <div class="pull-right">
            <button type="button" class="tw-dw-btn tw-dw-btn-primary tw-text-white tw-dw-btn-sm docs_and_notes_btn pull-right" data-href="{{action([\App\Http\Controllers\DocumentAndNoteController::class, 'create'], ['notable_id' => $notable_id, 'notable_type' => $notable_type])}}">
                @lang('messages.add')&nbsp;
                <i class="fa fa-plus"></i>
            </button> 
        </div> <br><br>
    @endif
    <table class="table table-bordered table-striped" style="width: 100%;" id="documents_and_notes_table">
        <thead>
            <tr>
                <th>@lang('messages.action')</th>
                <th>@lang('lang_v1.heading')</th>
                <th>@lang('lang_v1.added_by')</th>
                <th>@lang('lang_v1.created_at')</th>
                <th>@lang('lang_v1.updated_at')</th>
            </tr>
        </thead>
    </table>
</div>
<div class="modal fade docus_note_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>


<div id="page-content" class="page-wrapper clearfix grid-button notes-list-view">

    <ul class="nav nav-tabs bg-white title scrollable-tabs" role="tablist">
        <li class="title-tab">
            <h4 class="pl15 pt10 pr15">{{ __('lang_v1.documents_and_notes') }}</h4>  {{-- Adjusted title --}}
        </li>

        {{--  Remove or adapt tabs as needed --}}
        @include('documents_and_notes.tabs', ['active_tab' => 'list'])  {{-- Assuming you create a tabs view --}}

        <div class="tab-title clearfix no-border">
            <div class="title-button-group">
                @can('create', \App\Models\DocumentAndNote::class) {{-- Permissions check --}}
                 <button type="button" class="btn btn-default docs_and_notes_btn" data-href="{{action([\App\Http\Controllers\DocumentAndNoteController::class, 'create'], ['notable_id' => $notable_id, 'notable_type' => $notable_type])}}">
                    <i data-feather="plus-circle" class="icon-16"></i> {{ __('messages.add') }}
                </button> 
                @endcan {{-- End permissions check --}}
            </div>
        </div>
    </ul>

    <div class="card border-top-0 rounded-top-0">
        <div class="table-responsive pb50">
            <table id="documents_and_notes_table" class="display" cellspacing="0" width="100%">
            </table>
        </div>
    </div>

</div>

<script type="text/javascript">
    $(document).ready(function() {
        $("#documents_and_notes_table").appTable({  {{-- Updated table ID --}}
            source: '{{ action([\App\Http\Controllers\DocumentAndNoteController::class, 'indexData'], ['notable_id' => $notable_id, 'notable_type' => $notable_type]) }}', // Updated route
            order: [[0, 'desc']],
            columns: [
                {title: '<i data-feather="menu" class="icon-16"></i>', "class": "text-center option w100"},
                {title: '{{ __("lang_v1.heading") }}', "class": "all"},
                {title: '{{ __("lang_v1.added_by") }}'},
                {title: '{{ __("lang_v1.created_at") }}', "class": "w200"},
                {title: '{{ __("lang_v1.updated_at") }}', "class": "w200"}
            ]
        });
    });
</script>
