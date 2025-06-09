<!-- resources/views/partials/dify.blade.php -->
<div id="dify-chat-sidebar"
     class="fixed top-0 right-0 h-full w-full sm:w-96 bg-base-100 shadow-xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out overflow-hidden flex flex-col"
     style="max-width: 100vw;"> {{-- Ensure it doesn't overflow viewport width --}}

    {{-- Header with Title and Close Button --}}
    <div class="p-4 border-b border-base-300 flex justify-between items-center bg-base-200 flex-shrink-0">
        <h2 class="text-lg font-semibold">Assistente IA</h2>
        <button id="close-dify-chat" class="btn btn-sm btn-ghost">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    {{-- Iframe Container - flex-grow makes it fill remaining space --}}
    <div class="flex-grow relative">
        <iframe id="dify-chat-iframe"
                src="URL_DA_SUA_WEBAPP_DIFY_AQUI" {{-- Substitua pela URL real da sua WebApp Dify --}}
                width="100%"
                height="100%"
                frameborder="0"
                allowfullscreen
                style="border: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%;" {{-- Use absolute positioning to fill parent --}}
                title="Chat Dify">
            Seu navegador não suporta iframes.
        </iframe>
    </div>
</div>

{{-- Overlay for background dimming --}}
<div id="dify-chat-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden transition-opacity duration-300 ease-in-out"></div>

{{-- JavaScript for toggling the sidebar --}}
{{-- Idealmente, mova este script para seu arquivo JS principal (ex: app.js) e compile --}}
@push('scripts') {{-- Use @push para adicionar ao stack de scripts do layout principal --}}
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const sidebar = document.getElementById('dify-chat-sidebar');
        const overlay = document.getElementById('dify-chat-overlay');
        const closeButton = document.getElementById('close-dify-chat');
        // O botão no header.blade.php precisará ter o ID 'open-dify-chat'
        const openButton = document.getElementById('open-dify-chat'); 

        function openChat() {
            if (!sidebar || !overlay) return;
            sidebar.classList.remove('translate-x-full');
            overlay.classList.remove('hidden');
            // Forçar reflow para garantir que a transição de opacidade funcione após remover 'hidden'
            void overlay.offsetWidth; 
            overlay.classList.add('opacity-100');
        }

        function closeChat() {
            if (!sidebar || !overlay) return;
            sidebar.classList.add('translate-x-full');
            overlay.classList.remove('opacity-100');
            // Esperar a transição terminar antes de esconder o overlay
            setTimeout(() => {
                 overlay.classList.add('hidden');
            }, 300); // Deve corresponder à duração da transição (duration-300)
        }

        // Adiciona listener ao botão de abrir (que será adicionado no header)
        if (openButton) {
            openButton.addEventListener('click', openChat);
        } else {
            console.warn("Botão com ID 'open-dify-chat' não encontrado no header. Adicione-o para que o chat possa ser aberto.");
        }

        // Adiciona listener ao botão de fechar dentro do painel
        if (closeButton) {
            closeButton.addEventListener('click', closeChat);
        }

        // Adiciona listener ao clique no overlay para fechar
        if (overlay) {
            overlay.addEventListener('click', closeChat);
        }
    });
</script>
@endpush

