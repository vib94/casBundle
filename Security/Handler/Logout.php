<?php
namespace Vib94\CasBundle\Security\Handler;

use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Http\Logout\DefaultLogoutSuccessHandler;
use Symfony\Component\Security\Http\Logout\LogoutHandlerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use phpCAS;

class Logout implements LogoutHandlerInterface
{

    private $casHost;
    private $casPort;
    private $context;
    private $validate;
    private $debug;
    private $verif;


    public function __construct($cas_conf)
    {
        $this->casHost = $cas_conf['host'];
        $this->casPort = array_key_exists('port', $cas_conf) ? $cas_conf['port'] : '';
        $this->casPath = array_key_exists('cas_connect_check', $cas_conf) ? $cas_conf['cas_connect_check'] : 'cas_connect_check';
        $this->targetPath = array_key_exists('target_path', $cas_conf) ? $cas_conf['target_path'] : 'homepage';
        $this->targetPathFailure = array_key_exists('target_path_fail', $cas_conf) ? $cas_conf['target_path_fail'] : 'homepage';
        $this->casContext = $cas_conf['context'];
        $this->casValidate = $cas_conf['validate'];
        $this->casDebug = $cas_conf['debug'];
        $this->casVerif = $cas_conf['verif'];
        $this->casVersion = $cas_conf['version'];
    }    

    public function logout(Request $request, Response $response, TokenInterface $token)
    {

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
            $logout_service = $request->getScheme().'://'.$request->getHttpHost();
            $paramSeparator = '?';
            $cas_url = phpCAS::getServerLogoutURL();
            if (isset($logout_url)) {
                $cas_url = $cas_url . $paramSeparator . "url="
                    . urlencode($logout_url);
                $paramSeparator = '&';
            }
            if (isset($logout_service)) 
            {
                $cas_url = $cas_url . $paramSeparator . "service="
                    . urlencode($logout_service);
            }
            phpCAS::trace("Prepare redirect to : ".$cas_url);
            $url_redirect = $cas_url;
            $contents = ob_get_contents();
        } 
        catch (CAS_AuthenticationException $e) 
        {
            $contents = ob_get_contents();
            ob_clean();
            return false;
        }

        ob_clean();

        //phpCAS::logout();
        //$logout_url = '';

            
        //$this->app['session']->invalidate();

        /*try {
            $response = $this->httpUtils->createRedirectResponse($request, $url_redirect);
        } catch (\Exception $e) {
            print_r($e);   
        }*/

        $response = new RedirectResponse($url_redirect);
        $response->send();
        return new RedirectResponse($url_redirect);       
        return $response;

        //return $this->httpUtils->createRedirectResponse($request, $this->targetUrl);
    }
}