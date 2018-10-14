<?php

require_once("functions.php");
require_once ('connection.php');
require_once ("configure.php");

$lot_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

if ($lot_id) {
    $lot_info = getLot($lot_id);
}

if ($lot_id == false || $lot_info == false) {
    http_response_code(404);
    $templContent = renderTemplate('404', []);
    $categories = getCatList();
    $layoutContent = renderTemplate('layout', [
        'pageContent' => $templContent,
        'categories' => $categories,
        'pageName' => '404 Not Found']);
    print($layoutContent);
    exit;
}

$lot_info =  getLot($lot_id);
$lot_info = lotPrice($lot_info);

$betList = getLotBets($lot_id);
$betsCount = getLotBetsCount($lot_id);

if (isset($_POST['bet'])) {
    $newBet = $_POST['bet'];
}

$betListContent = ''; // содержит все ставки на лот
if ($betsCount['betsCount'] > 0) {
    if ($betsCount['betsCount'] === 1) {
        $betListContent = renderTemplate('betListTempl', $betList);
    } else {
        foreach ($betList as $bet) {
            $betListContent .= renderTemplate('betListTempl', $bet);
        }
    }
} elseif (strtotime($lot_info['finish_date']) < time()) {
    $betListContent = "Торги окончены";
} else {$betListContent = "На этот лот нет ставок";}

//switch ($betsCount['betsCount']) {
//    case $betsCount['betsCount'] === 1 && biddingIsOver($lot_info) === false:
//        $betListContent = renderTemplate('betListTempl', $betList);
//        echo "Here";
//        break;
//    case $betsCount['betsCount'] > 1 && biddingIsOver($lot_info) === false:
//        $betListContent .= renderTemplate('betListTempl', $bet);
//        break;
//    case $betsCount['betsCount'] < 1 && biddingIsOver($lot_info) === false:
//        $betListContent = "На этот лот нет ставок";
//        break;
//
//    case $betsCount['betsCount'] === 1 && biddingIsOver($lot_info) === true:
//        $betListContent = renderTemplate('betListTempl', $betList);
//        $biddingIsOver = "Торги окончены";
//        break;
//    case $betsCount['betsCount'] > 1 && biddingIsOver($lot_info) === true:
//        $betListContent .= renderTemplate('betListTempl', $bet);
//        $biddingIsOverVar = "Торги окончены";
//        break;
//    case $betsCount['betsCount'] < 1 && biddingIsOver($lot_info) === true:
//        $betListContent = "Торги окончены";
//        break;
//}

$minPrice = null;
$betAdded = null;

if (isAuthorized() && $_POST) {
    $minPrice = minBet($lot_info, true);
    $betAdded = betAdd($lot_info['id'], $newBet, $minPrice);

    if ($betAdded['result'] === true) {
        header('Location: lot.php?id=' . $lot_info['id']);
        exit;
    } else {
        $errors = $betAdded['errors'];
    }
}


$templContent = renderTemplate('lot', [
    'lot_info'       => $lot_info,
    'errors'         => $errors ?? [],
    '$betList'       => $betList,
    'betsCount'      => $betsCount,
    //'biddingIsOver'  => $biddingIsOverVar,
    'betListContent' => $betListContent]);

$categories = getCatList();

$layoutContent = renderTemplate('layout', [
    'pageContent' => $templContent,
    'categories'  => $categories,
    'pageName'    => $lot_info['lot_name'],
    'userName'    => getUserSessionData()['us_name'] ?? null,
    'userAvatar'  => getUserSessionData()['us_image'] ?? null]);

print($layoutContent);

