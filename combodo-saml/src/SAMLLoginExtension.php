<?php
/**
 * @copyright   Copyright (C) 2010-2019 Combodo SARL
 * @license     https://www.combodo.com/documentation/combodo-software-license.html
 *
 */

namespace Combodo\iTop\Extension\Saml;

use AbstractLoginFSMExtension;
use Dict;
use Exception;
use iLoginUIExtension;
use iLogoutExtension;
use IssueLog;
use LoginBlockExtension;
use LoginTwigContext;
use LoginWebPage;
use MetaModel;
use OneLogin\Saml2\Auth;
use utils;

class SAMLLoginExtension extends AbstractLoginFSMExtension implements iLogoutExtension, iLoginUIExtension
{
	private $bErrorOccurred = false;

	/**
	 * Return the list of supported login modes for this plugin
	 *
	 * @return array of supported login modes
	 */
	public function ListSupportedLoginModes()
	{
		try {
			if (!in_array('saml', MetaModel::GetConfig()->GetAllowedLoginTypes())) {
				return [];
			}
			$oConfig = new Config();
			new Auth($oConfig->GetSettings());
		} catch (Exception $e) {
			IssueLog::Error("No valid SAML configuration found");
			return [];
		}
		return ['saml'];
	}

	protected function OnReadCredentials(&$iErrorCode)
	{
		if (!isset($_SESSION['login_mode']) || ($_SESSION['login_mode'] == 'saml'))
		{
			$_SESSION['login_mode'] = 'saml';
			if (empty($_SESSION['auth_user']) && !$this->bErrorOccurred)
			{
				$oConfig = new Config();
				$oAuth = new Auth($oConfig->GetSettings());
				if (!isset($_SESSION['login_will_redirect']))
				{
					$_SESSION['login_will_redirect'] = true;
				}
				else
				{
				    if (empty(utils::ReadParam('login_saml')))
                    {
                        unset($_SESSION['login_will_redirect']);
                        $this->bErrorOccurred = true;
                        $iErrorCode = LoginWebPage::EXIT_CODE_MISSINGLOGIN;
                        return LoginWebPage::LOGIN_FSM_ERROR;
                    }
				}

				if (method_exists('utils', 'GetCurrentAbsoluteUrl')) {
					$sOriginURL = utils::GetCurrentAbsoluteUrl();
				} else {
					$sOriginURL = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
				}
				if (!utils::StartsWith($sOriginURL, utils::GetAbsoluteUrlAppRoot()))
				{
					// If the found URL does not start with the configured AppRoot URL
					$sOriginURL = utils::GetAbsoluteUrlAppRoot().'pages/UI.php';
				}
				Logger::Debug("Login($sOriginURL)");
				$oAuth->login($sOriginURL); // Will redirect and exit
			}
		}
		return LoginWebPage::LOGIN_FSM_CONTINUE;
	}

	protected function OnCheckCredentials(&$iErrorCode)
	{
		if ($_SESSION['login_mode'] == 'saml')
		{
            if (!isset($_SESSION['auth_user']))
            {
            	Logger::Debug("OnCheckCredentials: Wrong credentials!");
                $iErrorCode = LoginWebPage::EXIT_CODE_WRONGCREDENTIALS;
                return LoginWebPage::LOGIN_FSM_ERROR;
            }
		}
		return LoginWebPage::LOGIN_FSM_CONTINUE;
	}

	protected function OnCredentialsOK(&$iErrorCode)
	{
		if ($_SESSION['login_mode'] == 'saml')
		{
			$sAuthUser = $_SESSION['auth_user'];
			if (!LoginWebPage::CheckUser($sAuthUser))
			{
				Logger::Debug("OnCredentialsOK: User ($sAuthUser) Not Authorized!");
				$iErrorCode = LoginWebPage::EXIT_CODE_NOTAUTHORIZED;
				return LoginWebPage::LOGIN_FSM_ERROR;
			}
			Logger::Debug("Successfully logged in (user = '$sAuthUser')");
			// See the note in LogoutAction below
			$_SESSION['saml_auth_user_for_logout'] = $sAuthUser;
			LoginWebPage::OnLoginSuccess($sAuthUser, 'external', $_SESSION['login_mode']);
		}
		return LoginWebPage::LOGIN_FSM_CONTINUE;
	}

	protected function OnError(&$iErrorCode)
	{
		if ($_SESSION['login_mode'] == 'saml')
		{
			if ($iErrorCode != LoginWebPage::EXIT_CODE_MISSINGLOGIN)
			{
				Logger::Debug("OnError: User not allowed!");
				$oLoginWebPage = new LoginWebPage();
				$oLoginWebPage->DisplayLogoutPage(false, Dict::S('SAML:Error:UserNotAllowed'));
                exit();
			}
		}
		return LoginWebPage::LOGIN_FSM_CONTINUE;
	}

	protected function OnConnected(&$iErrorCode)
	{
		if ($_SESSION['login_mode'] == 'saml')
		{
			$bCanLogoff = (bool)MetaModel::GetModuleSetting('combodo-saml', 'can_logoff', false);
			$_SESSION['can_logoff'] = $bCanLogoff;
			return LoginWebPage::CheckLoggedUser($iErrorCode);
		}
		return LoginWebPage::LOGIN_FSM_CONTINUE;
	}

	/**
	 * Execute all actions to log out properly
	 */
	public function LogoutAction()
	{
		$oConfig = new Config();
		$oAuth = new Auth($oConfig->GetSettings());
		// Note: under **some ADFS configurations** we must pass the user to the logout action
		// since at this point in the code the session has been cleaned of the auth_user entry
		// and UserRights::GetUser() returns an empty string as well, we use a custom session key
		// to remember which user was authenticated with SAML...
		$sLogin = $_SESSION['saml_auth_user_for_logout'];
		unset($_SESSION['saml_auth_user_for_logout']);
		Logger::Debug("Logout(".utils::GetAbsoluteUrlAppRoot().'pages/UI.php'.", array(), '$sLogin')");
		$oAuth->logout(utils::GetAbsoluteUrlAppRoot().'pages/UI.php', array(), $sLogin); // Will redirect and exit
	}

    /**
     * @return LoginTwigContext
     * @throws \Exception
     */
    public function GetTwigContext()
    {
        $oLoginContext = new LoginTwigContext();
	    $oLoginContext->SetLoaderPath(utils::GetAbsoluteModulePath('combodo-saml').'view');

	    $sDefaultSamlLogo = utils::GetAbsoluteUrlModulesRoot().'combodo-saml/view/saml.png';
	    $sLogoUrl = MetaModel::GetModuleSetting('combodo-saml', 'saml_logo_url', $sDefaultSamlLogo);
	    
        $aData = array(
        	'sImagePath' => $sLogoUrl,
            'sLoginMode' => 'saml',
            'sLabel' => Dict::S('SAML:Login:SignIn'),
            'sTooltip' => Dict::S('SAML:Login:SignInTooltip'),
        );
        $oBlockExtension = new LoginBlockExtension('saml_sso_button.html.twig', $aData);

        $oLoginContext->AddBlockExtension('login_sso_buttons', $oBlockExtension);

	    return $oLoginContext;
    }
}

