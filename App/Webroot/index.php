<?php

    /**
     * Définition des contantes de base
     */
    define('DS', DIRECTORY_SEPARATOR);
    define('BASE', dirname(dirname(__DIR__)));
    $root = dirname(dirname(str_replace('index.php', '', $_SERVER['SCRIPT_NAME'])));
    define('ROOT', $root . ($root == '/') ? '' : DS);
    define('CORE', ROOT . 'Core' . DS);
    define('APP', ROOT . 'App' . DS);
    define('WEBROOT', APP . "Webroot" . DS);

    /**
     * On charge l'autoloader de composer
     */
    require_once BASE . DS . "vendor" . DS . "autoload.php";
    require_once BASE . DS . "Core" . DS . 'Lib' . DS . 'functions.php';

    use Core\Dispatcher;

    $dispatcher = new Dispatcher();
