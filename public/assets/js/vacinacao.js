/**
 * Seleção de vacina e dose sem eventos inline.
 */
(function () {
    'use strict';
    var vacina = document.querySelector('[data-vacina-select]');
    if (vacina) {
        vacina.addEventListener('change', function () {
            var url = new URL(window.location.href);
            url.searchParams.set('vacina_id', vacina.value);
            url.searchParams.set(vacina.dataset.parametro, vacina.dataset.valor);
            window.location.assign(url.toString());
        });
    }
    var calendario = document.getElementById('calendario_id');
    var numero = document.querySelector('input[name="numero_dose"]');
    if (calendario && numero) {
        var atualizarDose = function () {
            var opcao = calendario.options[calendario.selectedIndex];
            numero.value = opcao ? opcao.dataset.dose : '';
        };
        calendario.addEventListener('change', atualizarDose);
        atualizarDose();
    }
}());
