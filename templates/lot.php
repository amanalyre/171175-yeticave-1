<section class="lot-item container">
    <h2><?=$lot_info['lot_name'] ?></h2>
    <div class="lot-item__content">
        <div class="lot-item__left">
            <div class="lot-item__image">
                <img src=<?=htmlspecialchars($lot_info['img_url']) ?> width="730" height="548" alt="Сноуборд">
            </div>
            <p class="lot-item__category">Категория: <span><?=htmlspecialchars($lot_info['cat_name']) ?></span></p>
            <p class="lot-item__category">Владелец: <?=htmlspecialchars($lot_info['author_id']) ?></p>
            <p class="lot-item__description"><?=htmlspecialchars($lot_info['lot_description']) ?></p>
        </div>
        <div class="lot-item__right">
            <div class="lot-item__state">
                <div class="lot-item__timer timer <?php echo lotFinishTime($lot_info['finish_date']) == '00:00' ? 'timer--finishing' : null ?>">
                    <?=lotFinishTime($lot_info['finish_date'], true);?>
                    <!--#TODO Добавить корректный подсчет оставшегося времени-->
                </div>
                <div class="lot-item__cost-state">
                    <div class="lot-item__rate">
                        <span class="lot-item__amount">Текущая цена</span>
                        <span class="lot-item__cost"><?=htmlspecialchars(price_round($lot_info['cur_price'])) ?></span>
                    </div>
                    <div class="lot-item__min-cost">
                        Мин. ставка <span><?= htmlspecialchars(minBet($lot_info)) ?></span>
                    </div>
                </div>
                <?php if (showBetForm($lot_info)):?>
                <form class="lot-item__form" action="lot.php?id=<?= $lot_info['id'] ?>" method="post">
                    <p class="lot-item__<?= empty($errors['cost']) ? "form-item" : "form-item form__item--invalid" ?>">
                        <label for="cost">Ваша ставка</label>
                        <input id="cost" type="number" name="bet[cost]" placeholder="<?=htmlspecialchars(minBet($lot_info)) ?>">
                    </p>
                    <button type="submit" class="button">Сделать ставку</button>
                </form>
                    <div class="form__item form__item <?php echo empty($errors['cost']) ? : 'form__item--invalid' ?>">
                        <span class="form__error"><?= $errors['cost'] ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="history">
                <h3>История ставок (<span><?=$betsCount['betsCount']; ?></span>)</h3>
                <table class="history__list">
                    <?= $betListContent; ?>
                </table>
            </div>
        </div>
    </div>
</section>