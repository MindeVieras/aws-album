<?php

namespace Photobum\Web;

class Home extends FrontController
{
    public function view()
    {
        $this->auth();
        $this->page['body_class'] = 'front';
        $template = $this->twig->loadTemplate('home.html');
        echo $template->render([
          'page' => $this->page
        ]);
    }
}
