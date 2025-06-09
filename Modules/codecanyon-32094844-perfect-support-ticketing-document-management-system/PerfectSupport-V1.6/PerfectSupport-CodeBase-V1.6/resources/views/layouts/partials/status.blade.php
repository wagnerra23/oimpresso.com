@if(isset(session('status')['success']) && session('status')['success'])
    <div class="alert alert-success alert-dismissible fade show">
        <strong>
            {{ session('status')['msg'] }}
        </strong>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@elseif(isset(session('status')['success']) && !session('status')['success'])
    <div class="alert alert-danger alert-dismissible fade show">
        <strong>
            {{ session('status')['msg'] }}
        </strong>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif