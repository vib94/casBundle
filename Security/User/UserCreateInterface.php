<?php

namespace Vib94\CasBundle\Security\User;

use Symfony\Component\Security\Core\User\UserProviderInterface;

interface UserCreateInterface
{
    public function createUser($token, UserProviderInterface $userProvider);
}
