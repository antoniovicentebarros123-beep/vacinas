<?php
/**
 * Fecho exclusivo da gestão de funcionários.
 */
?>
        </main>
    </div>
</div>
<form method="POST" action="<?php echo e(url_gestao() . 'logout.php'); ?>" id="logoutForm" hidden>
    <?php echo csrf_field(); ?>
</form>
<script src="<?php echo e(url_publica() . 'assets/js/modulos.js'); ?>"></script>
<?php require __DIR__ . '/foot.php'; ?>
