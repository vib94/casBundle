<?php
namespace Vib94\CasBundle\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Guard\Token\PostAuthenticationGuardToken;
use Symfony\Component\Security\Core\Role\SwitchUserRole;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use phpCAS;

//use Cas\phpCAS;
//use AppBundle\Entity\User;

class CasAuthenticator extends AbstractGuardAuthenticator
{

    private $em;
    private $casHost;
    private $casPort;
    private $casContext;
    private $casValidate;
    private $casDebug;
    private $casVerif;

    private $router;

    public function __construct($token_storage, Router $router, EntityManager $em, $cas_conf)
    {
        $this->em = $em;
        $this->router = $router;

        $this->casHost = $cas_conf['host'];
        $this->casPort = array_key_exists('port', $cas_conf) ? $cas_conf['port'] : '';
        $this->casContext = $cas_conf['context'];
        $this->casValidate = $cas_conf['validate'];
        $this->casDebug = $cas_conf['debug'];
        $this->casVerif = $cas_conf['verif'];
        $this->casVersion = $cas_conf['version'];
        //3.0
    }

    /**
     * Called on every request to decide if this authenticator should be
     * used for the request. Returning false will cause this authenticator
     * to be skipped.
     */
    public function supports(Request $request)
    {
       if($request->get('_route') != 'cas_connect_check')
       {
           return false;
       }

       return true;

    }    
    /**
     * Called on every request. Return whatever credentials you want to
     * be passed to getUser(). Returning null will cause this authenticator
     * to be skipped.
     */
    public function getCredentials(Request $request)
    {

        //phpCAS::forceAuthentication();
        $token = array();
        phpCas::setDebug('/home/web/sites/symfonytest/debugcas.log');
        try 
        {
            phpCAS::client($this->casVersion, $this->casHost, $this->casPort, $this->casContext, false, false);
            phpCAS::setServerServiceValidateURL($this->casHost.$this->casValidate);
            if(!$this->casVerif)
                phpCAS::setNoCasServerValidation();

            phpCAS::setNoClearTicketsFromUrl();

            phpCAS::forceAuthentication();
            if(phpCAS::isAuthenticated())
            {
                $user_login = phpCAS::getUser();
                $attributes = phpCAS::getAttributes();
    
                
                $token['username'] = $user_login;
                $token['attributes']  = $attributes;
                $token['created']  = date('Y-m-d H:i:s');
            }
        } 
        catch (MissingAuthorizationCodeException $e) 
        {
            throw new NoAuthCodeAuthenticationException();
        }

        // What you return here will be passed to getUser() as $credentials
        return array(
            'token' => $token,
        );
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        $token = $credentials['token'];

        // if null, authentication will fail
        if (!isset($token['username']) || is_null($token['username'])) {
            return;
        }
        
        $attributes = $token['attributes'];

        $mail = array_key_exists('mail', $attributes) ? trim($attributes['mail']) : null;
        $name = array_key_exists('displayName', $attributes) ? trim($attributes['displayName']) : null;
        $exploded = explode(' ', $name);
        $fname = array_pop($exploded);
        $lname = array_pop($exploded);

        try 
        {
            $return = $userProvider->loadUserByUsername($token['username']);
        } 
        catch (UsernameNotFoundException $e)
        {/*
            $random = random_bytes(10);
            $entity = new User();
            $entity->setNotification(false);
            $entity->setUsername($token['username']);
            $entity->setEmail($mail);
            $entity->setFname($fname);
            $entity->setLname($lname);
            $entity->setEnabled(true);
            $entity->setPassword($random);
            $entity->addRole('ROLE_USER');

            $this->em->persist($entity);
            $this->em->flush($entity);
            
            $return = $entity;*/
        }

        // if a User object, checkCredentials() is called
        return $return;
    }

    public function checkCredentials($credentials, UserInterface $user)
    {
        // check credentials - e.g. make sure the password is valid
        // no credential check is needed in this case

        // return true to cause authentication success
        return true;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        /*
        $usernametoswitch = $request->query->get('_switch_user');
        if (!empty($usernametoswitch)) {

            $roles[] = new SwitchUserRole('ROLE_PREVIOUS_ADMIN', $token);

            $token = array();
            $token['username'] = $usernametoswitch;
            $token['attributes']  = array();
            $token['created']  = date('Y-m-d H:i:s');

            return array(
                'token' => $token,
            );           
        }*/
        // on success, let the request continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        $data = array('message' => strtr($exception->getMessageKey(), $exception->getMessageData())

            // or to translate this message
            // $this->translator->trans($exception->getMessageKey(), $exception->getMessageData())
        );

        return new JsonResponse($data, Response::HTTP_FORBIDDEN);
    }

    /**
     * Called when authentication is needed, but it's not sent
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        $url = $this->router->generate('cas_connect');
        $data = array('message' => 'Authentication Required');
        $response = new RedirectResponse($url);
        return $response;
        //return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }

    public function supportsRememberMe()
    {
        return false;
    }
    
}