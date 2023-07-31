<?php
/**
 * @copyright   Copyright (C) 2010-2019 Combodo SARL
 * @license     https://www.combodo.com/documentation/combodo-software-license.html
 *
 */
/**
 *  SP Single Logout Service Endpoint
 */
use Combodo\iTop\Extension\Saml\Logger;

require_once('../../approot.inc.php');
require_once (APPROOT.'bootstrap.inc.php');
require_once (APPROOT.'application/startup.inc.php');

$oConfig = new Combodo\iTop\Extension\Saml\Config();
$oAuth = new OneLogin\Saml2\Auth($oConfig->GetSettings());

unset($_SESSION['auth_user']);
unset($_SESSION['login_mode']);

$bRetrieveParametersFromServer = MetaModel::GetModuleSetting('combodo-saml', 'retrieveParametersFromServer', true);
Logger::Debug("Processing Logout Response (bRetrieveParametersFromServer = $bRetrieveParametersFromServer)");

if (isset($_GET['SAMLResponse'])) {
    $sSAMLResponse = base64_decode($_GET['SAMLResponse']) ?: $_GET['SAMLResponse'];
    $sSAMLResponse = @gzinflate($sSAMLResponse) ?: $sSAMLResponse;
    Logger::Debug(sprintf("GET SAMLResponse is:\n%s", $sSAMLResponse));
} else {
    Logger::Debug(sprintf("GET SAMLResponse is empty"));
}

$oAuth->processSLO(false, null, $bRetrieveParametersFromServer);

$aErrors = $oAuth->getErrors();

if (empty($aErrors))
{
	Logger::Debug('Logout Response Ok.');
	$oPage = LoginWebPage::NewLoginWebPage();
	$oPage->DisplayLogoutPage(false);
	exit;
}
else
{
	echo '<p>'.Dict::S('SAML:Error:ErrorOccurred').'</p>';
	echo '<p>'.Dict::S('SAML:Error:CheckTheLogFileForMoreInformation').'</p>';
	Logger::Error("An error occured while processing the logout message: ".implode("\n", $aErrors));
	Logger::Error('Last error reason: '.$oAuth->getLastErrorReason());
}
