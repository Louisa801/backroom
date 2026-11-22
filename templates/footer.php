<?php
/** @var list<string> $scripts optional, paths under /assets/js/ */
?>
</main>
<?php if (empty($staffNav) && empty($headerNote)): // customer pages only ?>
<footer class="site-footer">
    <a class="site-footer__staff" href="/staff/login.php">Staff</a>
</footer>
<?php endif; ?>
<?php foreach ($scripts ?? [] as $script): ?>
<script src="/assets/js/<?= e($script) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
