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
$newBet = $_POST['bet'];

if (isAuthorized() && $_POST) {
    $minPrice = minBet($lot_info, true);
    //var_dump($minPrice);
    $betAdded = betAdd($lot_info['id'], $newBet, $minPrice);

    if ($betAdded === true) {
        header('Location: lot.php?id=' . $lot_info['id']);
        exit;
    } else {
        $errors = $betAdded;
        var_dump($errors);
    }
}


$templContent = renderTemplate('lot', [
    'lot_info' => $lot_info,
    'errors'   => $errors ?? []]);

$categories = getCatList();

$layoutContent = renderTemplate('layout', [
    'pageContent' => $templContent,
    'categories'  => $categories,
    'pageName'    => $lot_info['lot_name'],
    'isAuth'      => empty(getUserSessionData()) ? false : true,
    'userName'    => getUserSessionData()['us_name'] ?? null,
    'userAvatar'  => getUserSessionData()['us_image'] ?? null]);

print($layoutContent);

