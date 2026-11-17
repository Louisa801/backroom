<?php
/** @var list<string> $scripts optional, paths under /assets/js/ */
?>
</main>
<?php foreach ($scripts ?? [] as $script): ?>
<script src="/assets/js/<?= e($script) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
