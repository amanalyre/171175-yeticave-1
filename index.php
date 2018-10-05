<?php
require_once("functions.php");
//require_once("data.php");
require_once ('connection.php');
require_once ("configure.php");

date_default_timezone_set("Europe/Moscow");

$lotsList = getLotsList(6);
$lotListContent = ''; // содержит все мои лоты
foreach ($lotsList as $lot) {
    $lotListContent .= renderTemplate('lot-oneItem', $lot);
}


$categories = getCatList();


$templContent = renderTemplate('index', [
    'lotListContent' => $lotListContent]);

$layoutContent = renderTemplate('layout', [
    'pageContent' => $templContent,
    'categories' => $categories,
    'isAuth' => empty(getUserSessionData()) ? false : true,
    'userName' => getUserSessionData()['us_name'] ?? null,
    'userAvatar' => getUserSessionData()['us_image'] ?? null,
    'pageName' => 'Main - YetiCave']);


print($layoutContent);
