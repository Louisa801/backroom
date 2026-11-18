<?php
declare(strict_types=1);

// M3: status of a try-on request. Updates itself every 5 seconds (request-status.js).

require_once __DIR__ . '/../src/bootstrap.php';

start_session();

$id = $_GET['id'] ?? '';
$request = (is_string($id) && ctype_digit($id) && strlen($id) <= 9)
    ? (new Requests(db()))->status((int) $id)
    : null;

$pageTitle = 'Your try-on request';

if ($request === null) {
    http_response_code(404);
    require __DIR__ . '/../templates/header.php';
    ?>
    <h1 class="page__title">We couldn't find this request</h1>
    <p class="page__intro">It may have been finished already. You can start a new try-on list in the archive.</p>
    <a class="button button--large" href="/">Back to the archive</a>
    <?php
    require __DIR__ . '/../templates/footer.php';
    exit;
}

// Items taken by someone else while this request was sent (shown once).
$takenNotice = $_SESSION['request_taken'][$request['id']] ?? null;
unset($_SESSION['request_taken'][$request['id']]);

$steps = ['new' => 'Sent', 'fetching' => 'Being fetched', 'ready' => 'Ready'];
$current = array_search($request['status'], array_keys($steps), true);
$current = $current === false ? count($steps) : $current; // closed: every step done

$scripts = ['request-status.js'];
require __DIR__ . '/../templates/header.php';
?>
<section class="request" id="request" data-request-id="<?= e($request['id']) ?>" data-status="<?= e($request['status']) ?>">
    <h1 class="page__title">Your try-on request</h1>

    <?php if ($takenNotice !== null): ?>
        <p class="notice notice--error" role="alert">
            Sorry, someone else just took <?= e($takenNotice) ?>. The rest of your request was sent.
        </p>
    <?php endif; ?>

    <ol class="steps" aria-label="Request progress">
        <?php $i = 0; foreach ($steps as $key => $label): ?>
            <li class="steps__step<?= $i < $current ? ' steps__step--done' : '' ?><?= $i === $current ? ' steps__step--current' : '' ?>"
                data-step="<?= e($key) ?>"<?= $i === $current ? ' aria-current="step"' : '' ?>>
                <span class="steps__dot" aria-hidden="true"><?= $i + 1 ?></span>
                <span class="steps__label"><?= e($label) ?></span>
            </li>
        <?php $i++; endforeach; ?>
    </ol>

    <div class="request__status">
        <p class="request__label" id="request-label" aria-live="polite"><?= e($request['label']) ?></p>
        <p class="request__room">Fitting room <strong id="request-room"><?= e($request['fitting_room']) ?></strong></p>
    </div>

    <h2 class="request__items-title">Your items</h2>
    <ul class="request__items">
        <?php foreach ($request['items'] as $item): ?>
            <li class="request__item">
                <img src="<?= e($item['photo_url']) ?>" alt="<?= e($item['title']) ?>" width="48" height="60">
                <span><strong><?= e($item['title']) ?></strong> · Size <?= e($item['size']) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>

    <p class="request__countdown" id="request-countdown" aria-live="polite" hidden></p>
    <a class="button button--large" href="/">Back to the archive</a>
</section>
<?php require __DIR__ . '/../templates/footer.php'; ?>
