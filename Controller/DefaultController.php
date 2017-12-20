<?php

namespace Vib94\CasBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;

class DefaultController extends Controller
{
    public function connectAction()
    {

        return new RedirectResponse('/app_dev.php');

        return $this->get('oauth2.registry')
            ->getClient('facebook_main') // key used in config.yml
            ->redirect();
    }

    public function connectCheckAction(Request $request)
    {
        // ** if you want to *authenticate* the user, then
        // leave this method blank and create a Guard authenticator
        // (read below)

        /** @var \KnpU\OAuth2ClientBundle\Client\Provider\FacebookClient $client */
        $client = $this->get('oauth2.registry')
            ->getClient('facebook_main');

        try {
            // the exact class depends on which provider you're using
            /** @var \League\OAuth2\Client\Provider\FacebookUser $user */
            $user = $client->fetchUser();

            // do something with all this new power!
            $user->getFirstName();
            // ...
        } catch (IdentityProviderException $e) {
            // something went wrong!
            // probably you should return the reason to the user
            var_dump($e->getMessage());die;
        }
    }
}
