<?php
/**
 * @copyright   Copyright (C) 2010-2019 Combodo SARL
 * @license     https://www.combodo.com/documentation/combodo-software-license.html
 *
 */

/**
 *  SP Assertion Consumer Service Endpoint
 */

use Combodo\iTop\Application\Helper\Session;
use Combodo\iTop\Extension\Saml\Logger;

require_once('../../approot.inc.php');
require_once (APPROOT.'bootstrap.inc.php');
require_once (APPROOT.'application/startup.inc.php');

if (class_exists('Combodo\iTop\Application\Helper\Session')) {
	Session::Start();
}

$oConfig = new Combodo\iTop\Extension\Saml\Config();
$oAuth = new OneLogin\Saml2\Auth($oConfig->GetSettings());

Logger::Debug('Processing Login Response');
if (isset($_POST['SAMLResponse'])) {
    $sSAMLResponse = base64_decode($_POST['SAMLResponse']) ?: $_POST['SAMLResponse'];
    Logger::Debug(sprintf("POST SAMLResponse is:\n%s", $sSAMLResponse));
} else {
    Logger::Debug(sprintf("POST SAMLResponse is empty"));
}

$oAuth->processResponse();

$aErrors = $oAuth->getErrors();

if (!empty($aErrors))
{
	echo '<p><b>'.Dict::S('SAML:Error:ErrorOccurred').'</b></p>';
	echo '<p>'.Dict::S('SAML:Error:CheckTheLogFileForMoreInformation').'</p>';
	Logger::Debug('Processing of Login Response failed: '.implode("\n", $aErrors));
	Logger::Error('Last error reason: '.$oAuth->getLastErrorReason());
	exit();
}
Logger::Debug('Login Response Ok.');

if (!$oAuth->isAuthenticated())
{
	echo "<p>".Dict::S('SAML:Error:NotAuthenticated')."</p>";
	Logger::Error('isAuthenticated() returned false!');
	Logger::Error('Last error reason: '.$oAuth->getLastErrorReason());
	exit();
}

$aUserAttributes = $oAuth->getAttributes();
$sNameId = MetaModel::GetModuleSetting('combodo-saml', 'nameid', 'uid');
if ($sNameId == '')
{
	// Enforce a default / non-empty value in case the conf gets garbled
	$sNameId = 'uid';
}

if (strcasecmp($sNameId, 'nameid') == 0)
{
	$sLogin = $oAuth->getNameId();
	Logger::Debug("Using nameId as the 'login', the value is '$sLogin'");
}
else
{
	if (!array_key_exists($sNameId, $aUserAttributes))
	{
		echo "<p>".Dict::Format('SAML:Error:Invalid_Attribute', $sNameId)."</p>";
		echo '<p>'.Dict::S('SAML:Error:CheckTheLogFileForMoreInformation').'</p>';
		Logger::Error("SAML authentication failed because the expected attribute '$sNameId' was not found in the IdP response.");
		Logger::Error('Adjust the parameter "nameid" of the module "combodo-saml" in the iTop configuration file to specify a valid attribute or specify "NameID" to use the "subject" of the SAML response as the login.');
		Logger::Error('IdP reponse subject contains '.$oAuth->getNameId());
		Logger::Error('Available attributes in the IdP response: '.print_r($aUserAttributes, true));
		unset($_SESSION['login_mode']);
		unset($_SESSION['login_will_redirect']);
		exit;
	}
	$sLogin = $aUserAttributes[$sNameId][0];
	Logger::Debug("Using attribute '$sNameId' as the 'login', the value is '$sLogin'");
}

$_SESSION['auth_user'] = $sLogin;
$_SESSION['login_mode'] = 'saml';
unset($_SESSION['login_will_redirect']);

if (isset($_POST['RelayState']) && OneLogin\Saml2\Utils::getSelfURL() != $_POST['RelayState'] && utils::StartsWith($_POST['RelayState'], utils::GetAbsoluteUrlAppRoot()))
{
	Logger::Debug('Redirecting to: '.$_POST['RelayState']);
	$oAuth->redirectTo($_POST['RelayState'], array('login_saml' => 'connected'));
} else {
	$oAuth->redirectTo(utils::GetAbsoluteUrlAppRoot().'pages/UI.php', array('login_saml' => 'connected'));
}
