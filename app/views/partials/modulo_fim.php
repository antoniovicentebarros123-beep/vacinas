<?php
/**
 * Fecho comum das páginas autenticadas dos módulos.
 */
?>
        </main>
    </div>
</div>

<noscript><p class="mensagem mensagem-erro">Este sistema precisa de JavaScript para o menu e confirmações.</p></noscript>

<form method="POST" action="<?php echo e(url_publica() . 'logout.php'); ?>" id="logoutForm" hidden>
    <?php echo csrf_field(); ?>
</form>

<script src="<?php echo e(url_publica() . 'assets/js/modulos.js'); ?>"></script>
<?php if (!empty($scriptPagina)): ?>
<script src="<?php echo e(url_publica() . 'assets/js/' . $scriptPagina); ?>"></script>
<?php endif; ?>

<?php require __DIR__ . '/foot.php'; ?>
