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
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Guard\Token\PostAuthenticationGuardToken;
use Symfony\Component\Security\Core\Role\SwitchUserRole;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\InsufficientAuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Vib94\CasBundle\Security\User\UserCreateInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use phpCAS;
use CAS_AuthenticationException;
use Doctrine\ORM\EntityManager;
use AppBundle\Entity\User;

class CasAuthenticator extends AbstractGuardAuthenticator
{

    private $em;
    private $casHost;
    private $casPort;
    private $casContext;
    private $casValidate;
    private $casDebug;
    private $casCheckPath;
    private $targetPath;
    private $targetPathFailure;
    private $casVerif;
    private $userCreateProvider;
    private $casAutoRedirect;

    private $router;

    public function __construct(Router $router, EntityManager $em, UserCreateInterface $userCreateProvider, $cas_conf)
    {
        $this->em = $em;
        $this->router = $router;
        $this->userCreateProvider = $userCreateProvider;
        $this->casHost = $cas_conf['host'];
        $this->casPort = array_key_exists('port', $cas_conf) ? $cas_conf['port'] : '';
        $this->casCheckPath = array_key_exists('cas_connect_check', $cas_conf) ? $cas_conf['cas_connect_check'] : 'cas_connect_check';
        $this->targetPath = array_key_exists('target_path', $cas_conf) ? $cas_conf['target_path'] : 'homepage';
        $this->targetPathFailure = array_key_exists('target_path_fail', $cas_conf) ? $cas_conf['target_path_fail'] : 'homepage';
        $this->casContext = $cas_conf['context'];
        $this->casValidate = $cas_conf['validate'];
        $this->casDebug = $cas_conf['debug'];
        $this->casVerif = $cas_conf['verif'];
        $this->casVersion = $cas_conf['version'];
        $this->casAutoRedirect = array_key_exists('auto_redirect', $cas_conf) ? $cas_conf['auto_redirect'] : true;
    }

    /**
     * Called on every request to decide if this authenticator should be
     * used for the request. Returning false will cause this authenticator
     * to be skipped.
     */
    public function supports(Request $request)
    {
       if($request->get('_route') != $this->casCheckPath)
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
        ob_start();
        //phpCas::setDebug('/home/web/sites/symfonytest/debugcas.log');
        phpCas::setVerbose(false);
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
            $contents = ob_get_contents();
        } 
        catch (CAS_AuthenticationException $e) 
        {
            $contents = ob_get_contents();
            ob_clean();
            echo 'bolosserie';
            return false;
        }

        ob_clean();

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

        try 
        {
            $user = $userProvider->loadUserByUsername($token['username']);                  
        } 
        catch (UsernameNotFoundException $e)
        {
            if (!$user = $this->userCreateProvider->createUser($token, $userProvider)) {
                throw new UsernameNotFoundException();
            }

        }

        // if a User object, checkCredentials() is called
        return $user;
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
        if (null === $jwt) {
            $jwt = $this->jwtManager->create($user);
        }

        $response = new JWTAuthenticationSuccessResponse($jwt);
        $event    = new AuthenticationSuccessEvent(['token' => $jwt], $user, $response);

        $this->dispatcher->dispatch(Events::AUTHENTICATION_SUCCESS, $event);
        $response->setData($event->getData());

        return $response;
*/
        $url = $this->router->generate($this->targetPath);
        $response = new RedirectResponse($url);
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
        return $response;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {

        $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);
        $url = $this->router->generate($this->targetPathFailure);
        $response = new RedirectResponse($url);
        
        //return new JsonResponse($data, Response::HTTP_FORBIDDEN);
    }

    /**
     * Called when authentication is needed, but it's not sent
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        $url = 'https://' . $this->casHost;
        if ($this->casPort!=443) {
            $url .= ':'.$this->casPort;
        }
        $url .= $this->casContext;
        $url .= '/login?service=';
        $service_url = $this->router->generate('cas_connect_check', array(), UrlGeneratorInterface::ABSOLUTE_URL);

        $url .= urlencode($service_url);
        $data = array('message' => 'Authentication Required');
        $response = new RedirectResponse($url);
        if($this->casAutoRedirect)
            return $response;
        
        return;
    }

    public function supportsRememberMe()
    {
        return false;
    }
}