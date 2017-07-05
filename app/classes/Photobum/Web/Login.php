<?php

namespace Photobum\Web;

use Photobum\Utilities\General;

class Login extends FrontController
{
    public function __construct()
    {
        parent::__construct();

        $this->page = [
            'title' => 'Login',
            'section' => 'login'
        ];
    }

    public function view()
    {
        if ($this->f3->get('VERB') == 'POST') {
            $data = $this->f3->get('POST');
            
            $user = $this->initOrm('users', true);
            $user->load(['username=?', $data['username']]);
            
            if ($user->dry()) {
                General::flushJsonResponse(['ack'=>'error'], 403);
            }
            
            if (password_verify($data['password'], $user->password)) {
                $user->last_login = date('Y-m-d H:i:s');
                $user->save();
                $user->copyTo('SESSION.album_web');
                $this->f3->clear('SESSION.album_web.password');

                General::flushJsonResponse(['ack'=>'ok', 'msg'=>$data]);
            }
        }
        $template = $this->twig->loadTemplate('Web/login.html');
        echo $template->render(array(
            'page' => $this->page
        ));

        die();
    }
}
