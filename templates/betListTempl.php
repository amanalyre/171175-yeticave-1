<tr class="history__item">
    <td class="history__name"><?=htmlspecialchars($us_name);?></td>
    <td class="history__price"><?php echo price_round($bid_price);?></td>
    <td class="history__time"><?=formatTime($bid_date);?></td>
</tr>