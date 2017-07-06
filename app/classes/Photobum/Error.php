<?php

namespace Photobum;

class Error extends Web\FrontController
{
    public function view()
    {
        $this->auth();
        $this->page['body_class'] = 'error';
        $template = $this->twig->loadTemplate('404.html');
        echo $template->render([
          'page' => $this->page
        ]);
    }
}
