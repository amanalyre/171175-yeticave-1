<?php
require_once("functions.php");
require_once ('connection.php');
require_once ("configure.php");


$categories = getCatList();

$user = [];
if ($_POST) {
    $user = $_POST['user'];

    $result = login($user);
    if ($result['result'] == true) {
        $_SESSION['user'] = $result['user'];
        setcookie('sessWasStarted', 'hi', time()+60*60*24*30);
        header('Location: index.php');

        exit;
    } else {
        $errors = $result['errors'];
    }
}


try {
    $templContent = renderTemplate('login', [
        'categories'  => $categories,
        'errors'      => $errors ?? [],
        'email'       => $user['email'] ?? ''
    ]);
} catch (Exception $e)
{
    echo 'Поймано исключение: ',  $e->getMessage(), "\n";
};

$layoutContent = renderTemplate('layout', [
    'pageContent' => $templContent,
    'categories'  => $categories,
    'pageName'    => 'Вход - Yeticave',
    'userName' => getUserSessionData()['us_name'] ?? null,
    'userAvatar' => getUserSessionData()['us_image'] ?? null]);

print($layoutContent);