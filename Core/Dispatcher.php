<?php

namespace Core;

use App\Config\App;
use Core\Lib\Debug;
use Core\Lib\SwithException;

/**
 * Se charge de charger le bon controller et la bonne action
 */
class Dispatcher
{

    /**
     * Objet Request qui contient toutes les variables utiles (controller, action, parmas, url...)
     * @var Request
     */
    public $request;

    /**
     * Vérifie que les information de base on bien été configurées
     * @var bool
     */
    private $isVerified = false;

    public function __construct()
    {

        $app = App::getInstance();

        // On initialise l'objet Request
        if($_ENV['USE_ROUTES']) {
            require('../Config/routes.php');
            $this->request = Router::run(new Request($app));
        } else {
            $this->request = new Request($app);
        }

        // On parse l'url (définition du controller, action,...)
        Router::parse($this->request);

        // On affiche les erreurs ?
        Debug::set();

        // On charge le bon controller
        try {
            $controller = $this->loadController();
        } catch (SwithException $e) {
            $controller = new Controllers\Errors($this->request, "ErrorsController");
            $controller->error('controllerNotFound', $this->request->controller);
        }

        if (method_exists($controller, "beforeRender")) {
            $controller->beforeRender();
        }

        // On appelle la fonction
        $action = $this->request->getAction();

        $availablesActions = array_diff(get_class_methods($controller),get_class_methods(get_parent_class($controller)));
        if (in_array($action, $availablesActions)) {
            call_user_func_array([$controller, $action], $this->request->params);
        } else {
            $controller->error('methodeNotFound', $this->request->controller, $action);
        }

        $controller->render($controller->view);
    }

    /**
     * Permet d'initialiser le controller demandé par l'url
     * @throws SwithException
     * @return instance retourne une instance du controller.
     */
    private function loadController()
    {
        $controllerName = 'App\\Controllers\\' . ucfirst($this->request->controller) . 'Controller';
        if (!class_exists($controllerName)) {
            $controllerName = 'Core\\Controllers\\' . ucfirst($this->request->controller);
            if (!class_exists($controllerName)) {
                throw new SwithException("Controller introuvable", 404);
                return false;
            }
        }
        return new $controllerName($this->request, $this->request->controller);
    }
}