<?php

namespace Core;

use App\Config\App;

class Request
{

    public $url,             // url appellée par l'utilisateur
           $action,          // Action demandée
           $controller,      // Controller demandé
           $referer = null,  // Page précédante
           $params,          // Les paramettres requis
           $prefixe = false, // Le prefixe requis si besoin
           $isPost = false,  // La methode est de type post ?
           $data = false,    // Les données postées
           $page = 1;        // Pour la pagination

    public function __construct(App $app)
    {
        $default_controller = ucfirst($app->getAppSettings("default_controller"));

        if (isset($_SERVER['PATH_INFO'])) {
            $this->url = $_SERVER['PATH_INFO'];
        } else {
            $this->url = (!is_null($default_controller) ? $default_controller : "Controller") . "/" . $app->getAppSettings("default_action");
        }

        if (isset($_SERVER['HTTP_REFERER'])) {
            $root = addcslashes(ROOT, '/');
            $parts = preg_split('/' . $root . '/', $_SERVER['HTTP_REFERER']);
            $this->referer = isset($parts[1]) ? $parts[1] : $parts[0];
        }

        if (!empty($_POST)) {
            $this->isPost = true;
            $this->data = new \stdClass();
            foreach ($_POST as $k => $v) {
                $this->data->$k = $v;
            }
            unset($_POST);
        }

        if (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0) {
            $this->page = round($_GET['page']);
        }
    }


}