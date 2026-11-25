document.addEventListener('DOMContentLoaded', function () {
    const aplicacao = document.querySelector('.aplicacao');
    const botaoMenu = document.querySelector('.botao-menu');
    const primeiroItemMenu = document.querySelector('.barra-lateral .menu a');
    const formularioLogout = document.getElementById('logoutForm');
    const botaoSair = document.querySelector('[data-acao="sair"]');
    const menuMobile = window.matchMedia('(max-width: 62rem)');

    function alternarMenu(aberto, restaurarFoco = true) {
        if (!aplicacao || !botaoMenu) return;
        if (aberto && !menuMobile.matches) return;
        aplicacao.dataset.menu = aberto ? 'aberto' : 'fechado';
        botaoMenu.setAttribute('aria-expanded', String(aberto));
        botaoMenu.setAttribute('aria-label', aberto ? 'Fechar navegação' : 'Abrir menu');

        if (aberto && primeiroItemMenu) {
            primeiroItemMenu.focus();
        } else if (!aberto && restaurarFoco) {
            window.setTimeout(function () {
                if (menuMobile.matches && aplicacao.dataset.menu === 'fechado') {
                    botaoMenu.focus();
                }
            }, 0);
        }
    }

    if (botaoMenu && aplicacao) {
        botaoMenu.addEventListener('click', function () {
            alternarMenu(aplicacao.dataset.menu !== 'aberto');
        });
    }
    if (aplicacao) {
        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape' && menuMobile.matches && aplicacao.dataset.menu === 'aberto') {
                alternarMenu(false);
            }
        });
        document.addEventListener('click', function (evento) {
            if (aplicacao.dataset.menu !== 'aberto' || !(evento.target instanceof Element)) return;
            if (evento.target.closest('.botao-menu')) return;

            if (evento.target.closest('.menu a, .menu .item-menu') || !evento.target.closest('.barra-lateral')) {
                alternarMenu(false);
            }
        });
    }
    if (typeof menuMobile.addEventListener === 'function') {
        menuMobile.addEventListener('change', function (evento) {
            if (!evento.matches && aplicacao && aplicacao.dataset.menu === 'aberto') {
                alternarMenu(false, false);
            }
        });
    }
    if (botaoSair && formularioLogout) {
        botaoSair.addEventListener('click', function () {
            formularioLogout.submit();
        });
    }

    document.querySelectorAll('form[data-confirm]').forEach(function (formulario) {
        formulario.addEventListener('submit', function (evento) {
            if (!window.confirm(formulario.dataset.confirm)) {
                evento.preventDefault();
            }
        });
    });

    document.querySelectorAll('[data-apenas-digitos]').forEach(function (campo) {
        campo.addEventListener('input', function () {
            campo.value = campo.value.replace(/\D/g, '');
        });
    });
});
