    <?php

    use Framework\Session;
    ?>
    <?php $sucessMessage = Session::getFlashMessage('success_message'); ?>

    <?php if ($sucessMessage !== null) : ?>
        <div class="message bg-green-100 p-3 my-3">
            <?= $sucessMessage ?>
        </div>
    <?php endif; ?>


    <?php $errorMessage = Session::getFlashMessage('error_message'); ?>

    <?php if ($errorMessage !== null) : ?>
        <div class="message bg-red-100 p-3 my-3">
            <?= $errorMessage ?>
        </div>
    <?php endif; ?>