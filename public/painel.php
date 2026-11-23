<?php
/**
 * Página do painel principal
 */

// Incluir helpers
require __DIR__ . '/../app/core/helpers.php';

// Verificar modo pré-visualização
$modoPrevia = is_preview_mode();

if (!$modoPrevia) {
    // Se não for pré-visualização, redirecionar para login
    header('Location: ' . url_base() . 'login.php');
    exit;
}

// Dados fictícios para pré-visualização
$dadosSessao = [
    'utilizador' => 'preview_user',
    'nome' => 'Utilizador de pré-visualização',
    'perfil' => 'administrador',
    'postoNome' => config('nome_posto')
];

// Definir dados na sessão
$_SESSION['usuario'] = $dadosSessao;

// Obter dados para exibição
$nomePosto = config('nome_posto');
$nomeUsuario = $dadosSessao['nome'];
$hoje = formatar_data_pt('now');
$saudacao = saudacao($nomeUsuario);
?>
<?php $pagina_css = 'painel'; require __DIR__ . '/../app/views/partials/head.php'; ?>

<div class="aplicacao" data-menu="fechado">
    <!-- Barra lateral -->
    <aside class="barra-lateral" id="barra" aria-label="Menu principal">
        <div class="marca">
            <div class="marca-selo" aria-hidden="true">
                <!-- Ícone de seringa SVG -->
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div>
                <div class="marca-nome">Vacinação infantil</div>
                <div class="marca-posto"><?php echo e($nomePosto); ?></div>
            </div>
        </div>
        
        <nav aria-label="Secções">
            <ul class="menu">
                <li>
                    <a href="#" aria-current="page">
                        <!-- Ícone de dashboard SVG -->
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="3" y="3" width="7" height="7" stroke="currentColor" stroke-width="2"/>
                            <rect x="14" y="3" width="7" height="7" stroke="currentColor" stroke-width="2"/>
                            <rect x="14" y="14" width="7" height="7" stroke="currentColor" stroke-width="2"/>
                            <rect x="3" y="14" width="7" height="7" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        Painel
                    </a>
                </li>
                <li>
                    <span class="item-menu em-breve" aria-disabled="true">
                        <!-- Ícone de bebê SVG -->
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z" stroke="currentColor" stroke-width="2"/>
                            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        Crianças
                        <small>Fase 2</small>
                    </span>
                </li>
                <li>
                    <span class="item-menu em-breve" aria-disabled="true">
                        <!-- Ícone de seringa SVG -->
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Vacinação
                        <small>Fase 3</small>
                    </span>
                </li>
                <li>
                    <span class="item-menu em-breve" aria-disabled="true">
                        <!-- Ícone de telefone SVG -->
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Faltosos
                        <small>Fase 4</small>
                    </span>
                </li>
                <li>
                    <span class="item-menu em-breve" aria-disabled="true">
                        <!-- Ícone de pacote SVG -->
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Stock
                        <small>Fase 4</small>
                    </span>
                </li>
                <li>
                    <span class="item-menu em-breve" aria-disabled="true">
                        <!-- Ícone de gráfico de colunas SVG -->
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <line x1="18" y1="20" x2="18" y2="10" stroke="currentColor" stroke-width="2"/>
                            <line x1="12" y1="20" x2="12" y2="4" stroke="currentColor" stroke-width="2"/>
                            <line x1="6" y1="20" x2="6" y2="14" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        Relatórios
                        <small>Fase 6</small>
                    </span>
                </li>
                <li>
                    <span class="item-menu em-breve" aria-disabled="true">
                        <!-- Ícone de configurações SVG -->
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
                            <path d="M12 1v6m0 6v6m-9-9h6m6 0h6M4.22 4.22l4.24 4.24m6.24 6.24l4.24 4.24M4.22 19.78l4.24-4.24m6.24-6.24l4.24-4.24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Administração
                        <small>Fase 7</small>
                    </span>
                </li>
            </ul>
        </nav>
    </aside>

    <!-- Coluna principal -->
    <div class="coluna-principal">
        <!-- Topo -->
        <header class="topo">
            <button type="button" class="botao botao-secundario botao-icone botao-menu" aria-label="Abrir menu" aria-controls="barra">
                <!-- Ícone de menu SVG -->
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <line x1="3" y1="12" x2="21" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <line x1="3" y1="6" x2="21" y2="6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <line x1="3" y1="18" x2="21" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
            <div class="topo-titulo">Painel</div>
            <div class="topo-indicador">Servidor local</div>
            <div class="topo-utilizador">
                <div>
                    <?php echo e($nomeUsuario); ?>
                    <small>Administrador</small>
                </div>
                <button type="button" class="botao botao-secundario botao-icone" aria-label="Terminar sessão" title="Terminar sessão">
                    <!-- Ícone de logout SVG -->
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <polyline points="16 17 21 12 16 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <line x1="21" y1="12" x2="9" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
        </header>

        <!-- Conteúdo -->
        <main class="conteudo" id="conteudo" tabindex="-1">
            <!-- Saudação -->
            <section class="painel-saudacao">
                <h1><?php echo e($saudacao); ?></h1>
                <p><?php echo e($hoje); ?></p>
            </section>

            <!-- "Hoje no posto" -->
            <section class="faixa-hoje" aria-labelledby="titulo-hoje">
                <h2 id="titulo-hoje">Hoje no posto</h2>
                <div class="indicador-grande">
                    <strong>—</strong>
                    <span>Crianças esperadas hoje</span>
                    <em>Cálculo ainda não disponível</em>
                </div>
                <div class="indicador-grande">
                    <strong>—</strong>
                    <span>Em atraso</span>
                    <em>Cálculo ainda não disponível</em>
                </div>
                <div class="indicador-grande">
                    <strong>—</strong>
                    <span>Faltosas</span>
                    <em>Cálculo ainda não disponível</em>
                </div>
            </section>

            <!-- Resumo -->
            <section class="grelha-resumo" aria-label="Resumo">
                <article class="cartao resumo">
                    <div class="resumo-icone">
                        <!-- Ícone de usuários SVG -->
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
                            <path d="M23 21v-2a4 4 0 00-3-3.87" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M16 3.13a4 4 0 010 7.75" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div>
                        <strong>0</strong>
                        <p>Crianças registadas</p>
                    </div>
                </article>
                <article class="cartao resumo">
                    <div class="resumo-icone">
                        <!-- Ícone de seringa SVG -->
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div>
                        <strong>0</strong>
                        <p>Vacinações registadas</p>
                    </div>
                </article>
                <article class="cartao resumo">
                    <div class="resumo-icone">
                        <!-- Ícone de cadeado SVG -->
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
                            <path d="M7 11V7a5 5 0 0110 0v4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div>
                        <strong>PRÉ-VISUALIZAÇÃO</strong>
                        <p>Sem autenticação e sem dados reais</p>
                    </div>
                </article>
            </section>

            <!-- Cartão de informações -->
            <section class="cartao painel-aviso-fase">
                <h2>O que já está pronto</h2>
                <p>Base de dados local, fila de envio e perfis. As crianças, a vacinação e o cálculo das doses chegam nas próximas fases.</p>
            </section>
        </main>
    </div>
</div>

<?php require __DIR__ . '/../app/views/partials/foot.php'; ?>

<script>
// REMOVER NA ETAPA 4: login real - lógica de pré-visualização
document.addEventListener('DOMContentLoaded', function() {
    const aplicacao = document.querySelector('.aplicacao');
    const menuBtn = document.querySelector('.botao-menu');
    
    // Alternar menu
    menuBtn.addEventListener('click', function() {
        const aberto = aplicacao.dataset.menu === 'aberto';
        aplicacao.dataset.menu = aberto ? 'fechado' : 'aberto';
        menuBtn.setAttribute('aria-label', aberto ? 'Abrir menu' : 'Fechar menu');
    });
    
    // Fechar menu ao clicar fora ou nos itens do menu
    aplicacao.addEventListener('click', function(ev) {
        if (ev.target === aplicacao || ev.target.closest?.('.menu a')) {
            aplicacao.dataset.menu = 'fechado';
            menuBtn.setAttribute('aria-label', 'Abrir menu');
        }
    });
    
    // Fechar menu com Escape
    aplicacao.addEventListener('keydown', function(ev) {
        if (ev.key === 'Escape') {
            aplicacao.dataset.menu = 'fechado';
            menuBtn.setAttribute('aria-label', 'Abrir menu');
        }
    });
});
</script>