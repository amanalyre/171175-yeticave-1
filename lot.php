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

if (isAuthorized() && $_POST) {
    $resultAddBet = addBet($lot['id'], postQuery()['bet'], $minPrice);

    if ($resultAddBet === true) {
        header('Location: lot.php?id=' . $lot['id']);
        exit;
    } else {
        $errors = $resultAddBet;
    }
}


$templContent = renderTemplate('lot', [
    'lot_info' => $lot_info]);

$categories = getCatList();

$layoutContent = renderTemplate('layout', [
    'pageContent' => $templContent,
    'categories' => $categories,
    'pageName' => $lot_info['lot_name'],
    'isAuth' => empty(getUserSessionData()) ? false : true,
    'userName' => getUserSessionData()['us_name'] ?? null,
    'userAvatar' => getUserSessionData()['us_image'] ?? null]);

print($layoutContent);

