<?php

namespace Photobum\Web;

class Home extends FrontController
{
    public function view()
    {
        $this->auth();
        $template = $this->twig->loadTemplate('Web/home.html');
        echo $template->render([
      'page' => $this->page
    ]);
    }
}
