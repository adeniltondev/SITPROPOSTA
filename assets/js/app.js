/**
 * FORMA4 Imobiliária – app.js
 * Scripts globais do painel administrativo
 */

document.addEventListener('DOMContentLoaded', function () {

    // =========================================================
    // SIDEBAR TOGGLE (mobile)
    // =========================================================
    const sidebar    = document.getElementById('sidebar');
    const toggleBtn  = document.getElementById('sidebarToggle');

    if (sidebar && toggleBtn) {
        // Cria backdrop
        const backdrop = document.createElement('div');
        backdrop.className = 'sidebar-backdrop';
        document.body.appendChild(backdrop);

        function openSidebar() {
            sidebar.classList.add('open');
            backdrop.classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            backdrop.classList.remove('open');
            document.body.style.overflow = '';
        }

        toggleBtn.addEventListener('click', function () {
            sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
        });

        backdrop.addEventListener('click', closeSidebar);
    }

    // =========================================================
    // AUTO-DISMISS ALERTS (após 5 segundos)
    // =========================================================
    document.querySelectorAll('.alert-dismissible').forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity .4s';
            alert.style.opacity = '0';
            setTimeout(function () { alert.remove(); }, 400);
        }, 5000);
    });

    // =========================================================
    // CONFIRMAÇÃO DE EXCLUSÃO
    // =========================================================
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            const msg = el.getAttribute('data-confirm') || 'Tem certeza que deseja excluir?';
            if (!confirm(msg)) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    });

    // =========================================================
    // SUBMIT FORMS COM data-confirm (forms de exclusão)
    // =========================================================
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            const msg = form.getAttribute('data-confirm') || 'Confirmar ação?';
            if (!confirm(msg)) {
                e.preventDefault();
            }
        });
    });

    // =========================================================
    // PREVIEW DE IMAGEM NO UPLOAD (logo)
    // =========================================================
    const logoInput   = document.getElementById('logo_upload');
    const logoPreview = document.getElementById('logo_preview_img');

    if (logoInput && logoPreview) {
        logoInput.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;

            const allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowed.includes(file.type)) {
                showToast('Tipo de arquivo não permitido. Use JPG, PNG, GIF ou WEBP.', 'error');
                this.value = '';
                return;
            }

            if (file.size > 2 * 1024 * 1024) {
                showToast('Arquivo muito grande. Tamanho máximo: 2 MB.', 'error');
                this.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function (ev) {
                logoPreview.src = ev.target.result;
                logoPreview.style.display = 'block';
                const noLogo = logoPreview.parentElement.querySelector('.no-logo');
                if (noLogo) noLogo.style.display = 'none';
            };
            reader.readAsDataURL(file);
        });
    }

    // =========================================================
    // TOAST NOTIFICATION (pequena notificação temporária)
    // =========================================================
    window.showToast = function (message, type) {
        type = type || 'info';

        const colors = {
            success: { bg: '#ecfdf5', color: '#065f46', border: '#6ee7b7' },
            error:   { bg: '#fef2f2', color: '#991b1b', border: '#fca5a5' },
            warning: { bg: '#fffbeb', color: '#92400e', border: '#fcd34d' },
            info:    { bg: '#eff6ff', color: '#1e40af', border: '#bfdbfe' },
        };

        const c = colors[type] || colors.info;

        const toast = document.createElement('div');
        toast.style.cssText = [
            'position:fixed', 'bottom:24px', 'right:24px',
            'z-index:9999', 'padding:12px 18px',
            'border-radius:8px', 'border:1px solid ' + c.border,
            'background:' + c.bg, 'color:' + c.color,
            'font-size:13.5px', 'font-weight:600',
            'box-shadow:0 4px 12px rgba(0,0,0,.12)',
            'max-width:340px', 'line-height:1.4',
            'transition:opacity .3s, transform .3s',
            'opacity:0', 'transform:translateY(8px)',
        ].join(';');

        toast.textContent = message;
        document.body.appendChild(toast);

        requestAnimationFrame(function () {
            toast.style.opacity = '1';
            toast.style.transform = 'translateY(0)';
        });

        setTimeout(function () {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(8px)';
            setTimeout(function () { toast.remove(); }, 300);
        }, 4000);
    };

    // =========================================================
    // COPY LINK (botão copiar URL do formulário)
    // =========================================================
    document.querySelectorAll('[data-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const text = btn.getAttribute('data-copy');
            if (!text) return;

            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function () {
                    showToast('Link copiado para a área de transferência!', 'success');
                });
            } else {
                // Fallback para navegadores sem clipboard API
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px;';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                ta.remove();
                showToast('Link copiado!', 'success');
            }
        });
    });

    // =========================================================
    // MODAL GENÉRICO
    // =========================================================
    // Abre modal pelo ID
    window.openModal = function (id) {
        const overlay = document.getElementById(id);
        if (overlay) overlay.classList.add('open');
    };

    // Fecha modal
    window.closeModal = function (id) {
        const overlay = document.getElementById(id);
        if (overlay) overlay.classList.remove('open');
    };

    // Fecha ao clicar fora do modal
    document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) overlay.classList.remove('open');
        });
    });

    // Fecha ao pressionar ESC
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.open').forEach(function (overlay) {
                overlay.classList.remove('open');
            });
        }
    });

    // =========================================================
    // INPUTS: MÁSCARA CPF (simples, sem biblioteca)
    // =========================================================
    document.querySelectorAll('input[data-mask="cpf"]').forEach(function (input) {
        input.addEventListener('input', function () {
            let v = this.value.replace(/\D/g, '').substring(0, 11);
            v = v.replace(/(\d{3})(\d)/, '$1.$2');
            v = v.replace(/(\d{3})(\d)/, '$1.$2');
            v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            this.value = v;
        });
    });

    // Máscara de telefone
    document.querySelectorAll('input[data-mask="phone"]').forEach(function (input) {
        input.addEventListener('input', function () {
            let v = this.value.replace(/\D/g, '').substring(0, 11);
            if (v.length <= 10) {
                v = v.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
            } else {
                v = v.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
            }
            this.value = v.trim();
        });
    });

    // Máscara de CEP
    document.querySelectorAll('input[data-mask="cep"]').forEach(function (input) {
        input.addEventListener('input', function () {
            let v = this.value.replace(/\D/g, '').substring(0, 8);
            v = v.replace(/(\d{5})(\d{1,3})/, '$1-$2');
            this.value = v;
        });
    });

});
