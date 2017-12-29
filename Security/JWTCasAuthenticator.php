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
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use AppBundle\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\MissingTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTNotFoundEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\TokenExtractorInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationFailureResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationSuccessResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\PhpBridgeSessionStorage;


class JWTCasAuthenticator extends AbstractGuardAuthenticator
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
    private $jwtManager;
    private $dispatcher;

    public function __construct(JWTTokenManagerInterface $jwtManager, EventDispatcherInterface $dispatcher,Router $router, EntityManager $em, UserCreateInterface $userCreateProvider, $cas_conf)
    {
        $this->em = $em;
        $this->router = $router;
        $this->jwtManager = $jwtManager;
        $this->dispatcher = $dispatcher;
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
           //phpCas::renewAuthentication();
            phpCAS::setServerServiceValidateURL($this->casHost.$this->casValidate);
            if(!$this->casVerif)
                phpCAS::setNoCasServerValidation();

            phpCAS::setNoClearTicketsFromUrl();
            //phpCAS::forceAuthentication();

            //$session = new Session(new PhpBridgeSessionStorage());
            
            $session = $request->getSession();
            //$session->start();
            $session_params = $session->all();
            $session->invalidate();
            //print_r($_SESSION);
            //print_r($session_params);

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
            return false;
        }

        //ob_clean();

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

        $user = $token->getUser();
        $jwt = $this->jwtManager->create($user);

        $response = new JWTAuthenticationSuccessResponse($jwt);
        $event    = new AuthenticationSuccessEvent(['token' => $jwt], $user, $response);

        $this->dispatcher->dispatch(Events::AUTHENTICATION_SUCCESS, $event);
        $response->setData($event->getData());

        return $response;

    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {

        $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);
        ///$url = $this->router->generate($this->targetPathFailure);
        //$response = new RedirectResponse($url);
        return new JsonResponse($exception->getMessageKey(), Response::HTTP_FORBIDDEN);
    }

    /**
     * Called when authentication is needed, but it's not sent
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        $url = 'https://' . $this->casHost;
        if ($this->casPort != 443) {
            $url .= ':'.$this->casPort;
        }
        $url .= $this->casContext;
        $url .= '/login?service=';
        $service_url = $this->router->generate('cas_connect_check', array(), UrlGeneratorInterface::ABSOLUTE_URL);

        $url .= urlencode($service_url);
        $data = array('message' => 'Authentication Required');
        $response = new RedirectResponse($url);

        $exception = new MissingTokenException('JWT Token not found 2', 0, $authException);
        $event     = new JWTNotFoundEvent($exception, new JWTAuthenticationFailureResponse($exception->getMessageKey()));

        $this->dispatcher->dispatch(Events::JWT_NOT_FOUND, $event);

        //if($this->casAutoRedirect)
            //return $response;
        //else
            return $event->getResponse();

        return;
    }

    public function supportsRememberMe()
    {
        return false;
    }
}