<?php

namespace Vib94\CasBundle\Security\User;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Doctrine\ORM\EntityManager;
use AppBundle\Entity\User;

class UserCreateFos implements UserCreateInterface
{
    private $em;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    public function createUser($token, UserProviderInterface $userProvider)
    {
        $attributes = $token['attributes'];
        
        $mail = array_key_exists('mail', $attributes) ? trim($attributes['mail']) : null;
        $name = array_key_exists('displayName', $attributes) ? trim($attributes['displayName']) : null;
        $exploded = explode(' ', $name);
        $fname = array_pop($exploded);
        $lname = array_pop($exploded);
                
        $random = random_bytes(10);
        $user = new User();
        $user->setNotification(false);
        $user->setUsername($token['username']);
        $user->setEmail($mail);
        $user->setFname($fname);
        $user->setLname($lname);
        $user->setEnabled(true);
        $user->setPassword($random);
        $user->addRole('ROLE_USER');
        $this->em->persist($user);
        $this->em->flush($user);

        return $user;

    }
}
