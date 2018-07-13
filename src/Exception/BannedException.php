<?php
namespace App\Exception;

use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class BannedException extends BadCredentialsException
{
    public function getMessageKey()
    {
        return 'You have been banned.';
    }
}
