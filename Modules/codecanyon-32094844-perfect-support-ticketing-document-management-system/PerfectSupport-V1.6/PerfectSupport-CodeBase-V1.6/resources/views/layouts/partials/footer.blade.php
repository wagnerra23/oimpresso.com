<footer class="float-right text-center text-info">
    <small>
        <b>
            {!!__('messages.application_copyright',
            [
                'name' => config('app.name', 'Laravel'),
                'version' => config('author.app_version'),
                'year' => date('Y')
            ]
        )!!}
        </b>
    </small>
</footer>