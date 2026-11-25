document.addEventListener('DOMContentLoaded', function () {
    const contentor = document.getElementById('filaDados');
    if (!contentor) return;
    const estado = document.getElementById('filaEstado');
    const url = contentor.dataset.url;
    const baseDetalhe = contentor.dataset.detalhe;
    let idsConhecidos = null;

    function elemento(tag, texto, classe) {
        const no = document.createElement(tag);
        if (texto !== undefined) no.textContent = texto;
        if (classe) no.className = classe;
        return no;
    }

    function desenhar(itens) {
        const idsAtuais = itens.map(function (item) { return String(item.id); });
        const novos = idsConhecidos === null ? [] : idsAtuais.filter(function (id) {
            return idsConhecidos.indexOf(id) === -1;
        });
        contentor.replaceChildren();
        if (itens.length === 0) {
            contentor.appendChild(elemento('p', 'Não há atendimentos aguardando.', 'vazio'));
        }
        itens.forEach(function (item) {
            const cartao = elemento('article', '', 'fila-item' + (novos.indexOf(String(item.id)) !== -1 ? ' novo' : ''));
            const identidade = elemento('div');
            identidade.appendChild(elemento('strong', item.primeiro_nome));
            identidade.appendChild(document.createElement('br'));
            identidade.appendChild(elemento('small', item.idade));
            cartao.appendChild(identidade);
            cartao.appendChild(elemento('div', item.vacinas.join(', ')));
            cartao.appendChild(elemento('time', item.chegada));
            const ligacao = elemento('a', 'Abrir ficha', 'botao botao-primario botao-pequeno');
            ligacao.href = baseDetalhe + encodeURIComponent(item.id);
            cartao.appendChild(ligacao);
            contentor.appendChild(cartao);
        });
        idsConhecidos = idsAtuais;
        if (estado) {
            estado.textContent = novos.length > 0
                ? 'Novo atendimento na fila: ' + novos.length + '.'
                : 'Fila atualizada agora.';
        }
    }

    async function atualizar() {
        try {
            const resposta = await fetch(url, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'Accept': 'application/json' }
            });
            if (!resposta.ok) {
                throw new Error('HTTP ' + resposta.status);
            }
            const dados = await resposta.json();
            if (!dados || !Array.isArray(dados.itens)) {
                throw new Error('Resposta inválida');
            }
            desenhar(dados.itens);
        } catch (erro) {
            if (estado) {
                estado.textContent = 'Não foi possível atualizar a fila. Nova tentativa dentro de 12 segundos.';
                estado.setAttribute('role', 'alert');
            }
        }
    }

    atualizar();
    window.setInterval(atualizar, 12000);
});
