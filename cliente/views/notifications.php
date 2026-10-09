<div class="wrap page">
    <h1>Avisos</h1>
    <div class="list">
        <?php foreach ($items as $item): ?>
            <div class="row"><div><strong><?= e($item['title']) ?></strong><div class="meta"><?= e($item['body']) ?></div></div><span class="mono"><?= e(substr((string) $item['created_at'], 0, 16)) ?></span></div>
        <?php endforeach; ?>
    </div>
</div>
