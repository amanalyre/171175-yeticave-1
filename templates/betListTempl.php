<tr class="history__item">
    <td class="history__name"><?=$us_name;?></td>
    <td class="history__price"><?php echo price_round($bid_price);?></td>
    <td class="history__time"><?=formatTime($bid_date);?></td>
    <!-- #TODO добавить форматирование даты "19.03.17 в 08:21" и "Час/20 минут назад" -->
</tr>