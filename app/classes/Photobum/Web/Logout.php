<?php

namespace Photobum\Web;

class Logout extends FrontController
{
    public function view()
    {
        $this->f3->clear('SESSION.album_web');
        $this->f3->reroute('/');
    }
}
