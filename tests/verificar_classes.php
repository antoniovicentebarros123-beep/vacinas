<?php
/**
 * Verifica as classes do HTML gerado, os ícones e os caminhos dos CSS.
 * Executar: php tests/verificar_classes.php
 */

$raiz = dirname(__DIR__);
$falhas = 0;

function verificar($condicao, $mensagem) {
    global $falhas;

    echo ($condicao ? 'OK' : 'FALHOU') . ' - ' . $mensagem . PHP_EOL;
    if (!$condicao) {
        $falhas++;
    }
}

function normalizarClasses($conteudo) {
    $classes = [];
    foreach (preg_split('/\s+/', trim($conteudo)) as $classe) {
        if ($classe !== '' && strpos($classe, 'icone-') !== 0) {
            $classes[] = $classe;
        }
    }
    return $classes;
}

function extrairClassesPhp($conteudo) {
    preg_match_all('/class\s*=\s*["\']([^"\']+)["\']/i', $conteudo, $encontradas);
    $classes = [];
    foreach ($encontradas[1] as $lista) {
        $classes = array_merge($classes, normalizarClasses($lista));
    }
    return array_unique($classes);
}

function extrairClassesJs($conteudo) {
    $classes = [];
    $padroes = [
        '/(?:classe|className)\s*:\s*["\']([^"\']+)["\']/',
        '/className\s*=\s*["\']([^"\']+)["\']/',
        '/class\s*=\s*["\']([^"\']+)["\']/'
    ];

    foreach ($padroes as $padrao) {
        preg_match_all($padrao, $conteudo, $encontradas);
        foreach ($encontradas[1] as $lista) {
            $classes = array_merge($classes, normalizarClasses($lista));
        }
    }

    return array_unique($classes);
}

function extrairIcones($conteudos) {
    $icones = [];
    foreach ($conteudos as $conteudo) {
        preg_match_all('/icone\s*\(\s*["\']([^"\']+)["\']\s*\)/', $conteudo, $encontradas);
        foreach ($encontradas[1] as $icone) {
            $icones[] = $icone;
        }
        preg_match_all('/icone-([a-z0-9-]+)/i', $conteudo, $literais);
        foreach ($literais[1] as $icone) {
            $icones[] = $icone;
        }
    }
    return array_unique($icones);
}

function classeTemRegra($classe, $css) {
    return preg_match('/\.' . preg_quote($classe, '/') . '(?![a-zA-Z0-9_-])/', $css) === 1;
}

$cssOrdem = [
    'variaveis.css',
    'base.css',
    'icones.css',
    'componentes.css',
    'layout.css',
    'paginas/login.css',
    'paginas/painel.css',
    'impressao.css'
];

$cssCarregados = [];
$cssCompleto = '';
foreach ($cssOrdem as $css) {
    $caminho = $raiz . '/public/assets/css/' . $css;
    verificar(is_file($caminho), 'CSS existe: ' . $css);
    if (is_file($caminho)) {
        $conteudo = file_get_contents($caminho);
        $cssCarregados[$css] = $conteudo;
        $cssCompleto .= $conteudo . PHP_EOL;
    }
}

$cssModulos = $raiz . '/public/assets/css/modulos.css';
verificar(is_file($cssModulos), 'CSS dos módulos existe');
if (is_file($cssModulos)) {
    $conteudoModulos = file_get_contents($cssModulos);
    $cssCarregados['modulos.css'] = $conteudoModulos;
    $cssCompleto .= $conteudoModulos . PHP_EOL;
}

$cssPrevia = $raiz . '/public/assets/css/previa.css';
verificar(is_file($cssPrevia), 'CSS de pré-visualização existe');
if (is_file($cssPrevia)) {
    $cssCompleto .= file_get_contents($cssPrevia) . PHP_EOL;
}

// Confirma que o partial usa a mesma ordem do index.html original arquivado.
$head = file_get_contents($raiz . '/app/views/partials/head.php');
$posicaoAnterior = -1;
foreach ($cssOrdem as $css) {
    $posicao = strpos($head, 'assets/css/' . $css);
    verificar($posicao !== false && $posicao > $posicaoAnterior, 'CSS ligado no head pela ordem original: ' . $css);
    if ($posicao !== false) {
        $posicaoAnterior = $posicao;
    }
}

verificar(strpos($head, 'assets/css/modulos.css') > $posicaoAnterior, 'CSS dos módulos é ligado depois dos CSS originais');

$indexOriginal = file_get_contents($raiz . '/arquivo_cloudflare/public/index.html');
preg_match_all('/href=["\']\/assets\/css\/([^"\']+)["\']/i', $indexOriginal, $cssIndexOriginal);
verificar($cssIndexOriginal[1] === $cssOrdem, 'Ordem dos CSS igual à do index.html original');

// Os ficheiros ui/ são cópias não alteradas da tag cloudflare-fase1a.
$ficheirosReferencia = [
    $raiz . '/arquivo_cloudflare/public/index.html',
    $raiz . '/public/assets/js/ui/login.js',
    $raiz . '/public/assets/js/ui/painel.js'
];
$ficheirosAtuais = [
    $raiz . '/public/login.php',
    $raiz . '/public/painel.php',
    $raiz . '/app/views/partials/head.php',
    $raiz . '/app/views/partials/foot.php',
    $raiz . '/public/assets/js/login.js',
    $raiz . '/public/assets/js/painel.js'
];

$classesReferencia = [];
$classesAtuais = [];
$conteudosAtuais = [];
foreach ($ficheirosReferencia as $ficheiro) {
    $conteudo = file_get_contents($ficheiro);
    $classesReferencia = array_merge($classesReferencia, extrairClassesPhp($conteudo));
    $classesReferencia = array_merge($classesReferencia, extrairClassesJs($conteudo));
}
foreach ($ficheirosAtuais as $ficheiro) {
    $conteudo = file_get_contents($ficheiro);
    $conteudosAtuais[] = $conteudo;
    if (substr($ficheiro, -4) === '.php') {
        $classesAtuais = array_merge($classesAtuais, extrairClassesPhp($conteudo));
    } else {
        $classesAtuais = array_merge($classesAtuais, extrairClassesJs($conteudo));
    }
}

// A classe em-breve pertencia aos módulos pendentes e não é mais exigida.
$classesReferencia = array_values(array_diff(array_unique($classesReferencia), ['em-breve']));
$classesAtuais = array_values(array_unique($classesAtuais));
sort($classesReferencia);
sort($classesAtuais);

$faltando = array_diff($classesReferencia, $classesAtuais);
verificar(empty($faltando), 'Todas as classes estruturais da referência existem no HTML gerado');
foreach ($faltando as $classe) {
    echo '  Classe em falta: ' . $classe . PHP_EOL;
}
verificar(strpos(file_get_contents($raiz . '/public/painel.php'), 'Em desenvolvimento') === false, 'Painel não contém etiquetas de módulos em desenvolvimento');

foreach ($classesAtuais as $classe) {
    verificar(classeTemRegra($classe, $cssCompleto), 'Classe tem regra CSS: .' . $classe);
}

foreach (extrairIcones($conteudosAtuais) as $icone) {
    verificar(classeTemRegra('icone-' . $icone, $cssCompleto), 'Ícone tem máscara CSS: .icone-' . $icone);
    verificar(is_file($raiz . '/public/assets/icons/' . $icone . '.svg'), 'Ícone SVG existe: ' . $icone . '.svg');
}

// Todos os url() dos CSS devem ser relativos e apontar para ficheiros existentes.
foreach ($cssCarregados as $css => $conteudo) {
    preg_match_all('/url\(\s*(["\']?)(.*?)\1\s*\)/i', $conteudo, $urls);
    foreach ($urls[2] as $url) {
        if ($url === '' || strpos($url, 'data:') === 0 || strpos($url, 'http:') === 0 || strpos($url, 'https:') === 0 || strpos($url, '#') === 0) {
            continue;
        }
        verificar(strpos($url, '/') !== 0, 'Caminho CSS relativo: ' . $css . ' -> ' . $url);
        $destino = realpath(dirname($raiz . '/public/assets/css/' . $css) . '/' . $url);
        verificar($destino !== false && is_file($destino), 'Recurso CSS existe: ' . $css . ' -> ' . $url);
    }
}

echo PHP_EOL . ($falhas === 0 ? 'Verificação concluída sem falhas.' : 'Verificação concluída com ' . $falhas . ' falha(s).') . PHP_EOL;
exit($falhas === 0 ? 0 : 1);
