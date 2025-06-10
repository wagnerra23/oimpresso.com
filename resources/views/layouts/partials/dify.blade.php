<!-- Chat Dify Sidebar -->
<div id="difyChatSidebar" class="offcanvas offcanvas-end bg-light shadow hidden" tabindex="-1" aria-labelledby="difyChatLabel">
    <!-- Manipulador de Redimensionamento -->
    <div class="resize-handle"></div>

    <!-- Header -->
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="difyChatLabel">Assistente IA</h5>
        <button type="button" id="close-dify-chat" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <!-- Iframe Container -->
    <div class="offcanvas-body p-0">
        <iframe id="dify-chat-iframe"
                src="https://jana.wr2.com.br/chat/eBR7KXDlBoTp4QhM"
                width="100%"
                height="100%"
                frameborder="0"
                allowfullscreen
                class="border-0 w-100 h-100"
                title="Chat Dify">
            Seu navegador não suporta iframes.
        </iframe>
    </div>
</div>

<!-- Overlay for background dimming -->
<div id="dify-chat-overlay" class="offcanvas-backdrop fade hidden"></div>

<style>
.resize-handle {
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 5px;
    background: #ccc;
    cursor: col-resize;
    z-index: 1050;
}

.resize-handle:hover {
    background: #999;
}

.offcanvas {
    width: 600px !important;
    min-width: 300px;
    max-width: 800px;
    transition: transform 0.3s ease;
}

.offcanvas:not(.showing) {
    transform: translateX(100%);
}

.offcanvas-backdrop.show {
    opacity: 0.5;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('difyChatSidebar');
    const overlay = document.getElementById('dify-chat-overlay');
    const closeButton = document.getElementById('close-dify-chat');
    const openButton = document.getElementById('open-dify-chat');
    const resizeHandle = document.querySelector('#difyChatSidebar .resize-handle');
    const bootstrapOffcanvas = new bootstrap.Offcanvas(sidebar);

    function openChat() {
        if (!sidebar || !overlay) return;
        bootstrapOffcanvas.show();
        overlay.classList.add('show');
    }

    function closeChat() {
        if (!sidebar || !overlay) return;
        bootstrapOffcanvas.hide();
        overlay.classList.remove('show');
    }

    if (openButton) {
        openButton.addEventListener('click', openChat);
    } else {
        console.warn("Botão com ID 'open-dify-chat' não encontrado no header.");
    }

    if (closeButton) {
        closeButton.addEventListener('click', closeChat);
    }

    if (overlay) {
        overlay.addEventListener('click', closeChat);
    }

    // Redimensionamento
    let isResizing = false;
    if (resizeHandle) {
        resizeHandle.addEventListener('mousedown', function(e) {
            isResizing = true;
            e.preventDefault();
        });

        document.addEventListener('mousemove', function(e) {
            if (isResizing) {
                const newWidth = window.innerWidth - e.clientX;
                if (newWidth >= 300 && newWidth <= 800) {
                    sidebar.style.width = `${newWidth}px`;
                }
            }
        });

        document.addEventListener('mouseup', function() {
            isResizing = false;
        });
    }
});
</script>