<?php
/**
 * The try-on bar (M2), shared by the archive and the item detail page. tryon.js drives it.
 *
 * @var string $csrf csrf_token() of this session
 */
?>
<!-- M2: try-on list, kept in the browser until it is sent (tryon.js). -->
<section class="tryon" id="tryon" aria-label="Your try-on list" hidden
         data-csrf="<?= e($csrf) ?>" data-max="<?= Requests::MAX_ITEMS ?>">
    <div class="tryon__panel" id="tryon-panel" hidden>
        <h2 class="tryon__title">Your try-on list</h2>
        <ul class="tryon__items" id="tryon-items"></ul>
        <p class="tryon__full" id="tryon-full" hidden>Your list is full (<?= Requests::MAX_ITEMS ?> items).</p>

        <fieldset class="tryon__rooms" id="tryon-rooms" hidden>
            <legend>Which fitting room?</legend>
            <div class="radio-group">
                <?php foreach (Requests::FITTING_ROOMS as $room): ?>
                    <label class="radio radio--room" for="room-<?= e($room) ?>">
                        <input type="radio" id="room-<?= e($room) ?>" name="fitting_room" value="<?= e($room) ?>">
                        <span>Room <?= e($room) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button button--large" id="tryon-submit">Send request</button>
        </fieldset>

        <p class="notice notice--error" id="tryon-message" role="alert" hidden></p>
    </div>

    <div class="tryon__bar">
        <button type="button" class="tryon__toggle" id="tryon-toggle" aria-expanded="false" aria-controls="tryon-panel">
            Your try-on list · <span id="tryon-count">0 items</span>
        </button>
        <button type="button" class="button tryon__send" id="tryon-send">Send to fitting room</button>
    </div>
</section>
