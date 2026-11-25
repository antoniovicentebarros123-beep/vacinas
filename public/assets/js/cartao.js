document.addEventListener('DOMContentLoaded', function () {
    const botaoImprimir = document.querySelector('[data-acao="imprimir-cartao"]');

    if (botaoImprimir) {
        botaoImprimir.addEventListener('click', function () {
            window.print();
        });
    }
});
