<?php
/**
 * @copyright   Copyright (C) 2019-2020 Combodo SARL
 * @license     https://www.combodo.com/documentation/combodo-software-license.html
 *
 */
require_once('../approot.inc.php');
require_once (APPROOT.'bootstrap.inc.php');
require_once (APPROOT.'application/startup.inc.php');

use Combodo\iTop\Application\UI\Base\Component\CollapsibleSection\CollapsibleSectionUIBlockFactory;
use Combodo\iTop\Application\UI\Base\Component\Html\HtmlFactory;
use Combodo\iTop\Extension\Saml\Config;

define('HIDDEN_PRIVATE_KEY', '*** private key no displayed ***');
define('SAML_XML_LEGACY_VERSION', 1.7);

function SamlUseLegacy()
{
	return SAML_XML_LEGACY_VERSION !== '' ? version_compare(ITOP_DESIGN_LATEST_VERSION, SAML_XML_LEGACY_VERSION, '<=') : false;
}


function DisplayInputForm(WebPage $oP, $sUrl, $sRawXml)
{
	$sSafeUrl = htmlentities($sUrl, ENT_QUOTES, 'UTF-8');
	$oP->add(
<<<HTML
	<div class="ibo-is-html-content saml-input-form">
	<h2>Importing the Identity Provider meta data</h2>
	<form method="post">
	<p>Enter the URL of the meta data from the Identity Provider (IdP):</p>
	<p><input type="text" class="ibo-input" size="50" name="url" placeholder="https://my-idp-server/metadata" value="$sSafeUrl"></input></p>
HTML
	);
	$sSafeXml = htmlentities($sRawXml, ENT_QUOTES, 'UTF-8');
	if(SamlUseLegacy()){
		$oP->StartCollapsibleSection('Paste the XML meta data:', false, 'xml_direct_input');
		$oP->add(
			<<<HTML
	    <p><textarea name="xml_meta_data" style="width: 30rem; height:10rem;">$sSafeXml</textarea></p>
HTML
		);
		$oP->EndCollapsibleSection();

	}
	else{
		$oInputCollapsibleBlock = CollapsibleSectionUIBlockFactory::MakeStandard('Paste the XML meta data');
		$oHtml = HtmlFactory::MakeHtmlContent(
			<<<HTML
	    <p><textarea class="ibo-input ibo-is-code" name="xml_meta_data" style="width: 30rem; height:10rem;">$sSafeXml</textarea></p>
HTML
		);
		$oInputCollapsibleBlock->AddSubBlock($oHtml);
		$oP->AddUiBlock($oInputCollapsibleBlock);
	}
	$oP->add(
<<<HTML
		<p><button class="ibo-button ibo-is-regular ibo-is-secondary" type="submit">Check Meta Data</button></p>
		<input type="hidden" name="operation" value="check"/>
	</form>
	</div>
HTML
	);
	
}

function CheckMetaData(WebPage $oP, $sUrl, $sRawXml)
{
	DisplayInputForm($oP, $sUrl, $sRawXml);
	$aErrors = array();
	$sMetaData = GetMetaData($sUrl, $sRawXml);
	if ($sMetaData === false)
	{
		$aErrors[] = 'Failed to read the XML data from the supplied URL';
		$aIdP = array();
	}
	else if (($sUrl == '') && ($sRawXml == ''))
	{
	    $aErrors[] = 'Please either supply a valid URL or paste the XML meta data';
	    $aIdP = array();
	}
	else
	{
		$aIdP = Config::ParseIdPMetaData($sMetaData, $aErrors);
	}
	
	if (count($aErrors) > 0)
	{
		$oP->add('<div class="header_message message_error">');
		foreach($aErrors as $sError)
		{
			$oP->p(htmlentities($sError, ENT_QUOTES, 'UTF-8'));
		}
		$oP->add('</div>');
	}
	else
	{
		$sSafeURL = htmlentities($sUrl, ENT_QUOTES, 'UTF-8');
		$sSafeXml = htmlentities($sRawXml, ENT_QUOTES, 'UTF-8');
		$oP->add(
<<<HTML
		<div class="header_message message_ok ibo-alert ibo-is-success ibo-is-opened">Ok, the meta data look correct.</div>
		<form method="post">
		<input type="hidden" name="operation" value="update"/>
		<input type="hidden" name="url" value="$sSafeURL"/>
		<input type="hidden" name="xml_meta_data" value="$sSafeXml"/>
		<button class="ibo-button ibo-is-regular ibo-is-primary" type="submit">Update iTop Configuration</button>
		</form>
HTML
		);
		if(SamlUseLegacy()) {
			$oP->StartCollapsibleSection('PHP configuration:', false, 'saml_conf');
			$oP->add('<pre>'.var_export($aIdP, true).'</pre>');
			$oP->EndCollapsibleSection();
		}
		else{
			$oPHPConfCollapsibleBlock = CollapsibleSectionUIBlockFactory::MakeStandard('PHP configuration');
			$oHtml = HtmlFactory::MakeHtmlContent('<pre>'.var_export($aIdP, true).'</pre>');
			$oPHPConfCollapsibleBlock->AddSubBlock($oHtml);
			$oP->AddUiBlock($oPHPConfCollapsibleBlock);
		}
	}
	if(SamlUseLegacy()) {
		$oP->StartCollapsibleSection('Raw Meta Data:', false, 'saml_metadata');
		$oP->add('<pre>'.htmlentities($sMetaData, ENT_QUOTES, 'UTF-8').'</pre>');
		$oP->EndCollapsibleSection();
	}
	else{
		$oRawMetaCollapsibleBlock = CollapsibleSectionUIBlockFactory::MakeStandard('Raw Meta Data');
		$oHtml = HtmlFactory::MakeHtmlContent('<pre>'.htmlentities($sMetaData, ENT_QUOTES, 'UTF-8').'</pre>');
		$oRawMetaCollapsibleBlock->AddSubBlock($oHtml);
		$oP->AddUiBlock($oRawMetaCollapsibleBlock);
	}
}

function UpdateIdPConfiguration(WebPage $oP, $sUrl, $sRawXml)
{
	$sMetaData = GetMetaData($sUrl, $sRawXml);

	$aErrors = array();
	$aIdP = Config::ParseIdPMetaData($sMetaData, $aErrors);
	if (count($aErrors) == 0)
	{
		$oConf = Metamodel::GetConfig();

		// Make sure that SAML is enabled
		$aAllowedLoginTypes = $oConf->GetAllowedLoginTypes();
		if (!in_array('saml', $aAllowedLoginTypes))
		{
			// Add 'saml' after 'form'
			$aModifiedLoginTypes = array();
			foreach($aAllowedLoginTypes as $sType)
			{
				$aModifiedLoginTypes[] = $sType;
				if ($sType == 'form')
				{
					$aModifiedLoginTypes[] = 'saml';
				}
			}
			$oConf->SetAllowedLoginTypes($aModifiedLoginTypes);
		}

		if ($sUrl != '')
		{
			$oConf->SetModuleSetting('combodo-saml', 'idp_metadata_url', $sUrl);
		}
		$oConf->SetModuleSetting('combodo-saml', 'idp', $aIdP);
		@chmod($oConf->GetLoadedFile(), 0770); // Allow overwriting the file
		$oConf->WriteToFile();
		@chmod($oConf->GetLoadedFile(), 0444); // Read-only
		$oP->add('<div class="header_message message_ok">iTop Configuration updated!!</div>');
	}
	else 
	{
		$oP->add('<div class="header_message message_error">');
		foreach($aErrors as $sError)
		{
			$oP->p(htmlentities($sError, ENT_QUOTES, 'UTF-8'));
		}
		$oP->add('</div>');
		$oP->add('<div class="header_message message_info">The iTop Configuration <b>was not updated</b>!!</div>');
	}
}

/**
 * Get the meta data from the URL or directly from the form when
 * no URL is supplied.
 *
 * @param string $sUrl
 * @param string $sXmlMetaData
 * @return string|false Returns false when the URL cannot be read
 */
function GetMetaData($sUrl, $sXmlMetaData)
{
    if (empty($sUrl))
    {
        return $sXmlMetaData;
    }
    else
    {
        return @file_get_contents($sUrl);
    }
}

function DisplayWelcomePage(WebPage $oP)
{
	$sModuleURL = utils::GetAbsoluteUrlModulesRoot().'/combodo-saml';
	if(SamlUseLegacy()) {
		$oP->add(
			<<<HTML
		<h1>Single Sign-On configuration using SAML</h1>
		<p><img src="$sModuleURL/asset/img/SAML-configuration.svg"></p>
		<p>To enable the Single Sign On (SSO) based on SAML in iTop, you have to configure both:</p>
		<ul>
		<li>iTop as a SAML <b>Service Provider</b> (SP) connected to your SAML server</li>
		<li>Your SAML server as a SAML <b>Identity Provider</b> (IdP) accepting this instance of iTop</li>
		</ul>
		<p>This configuration is done by echanging XML meta data between both systems.
		You must export the meta data describing iTop as a Service provider to your SAML server in order to allow iTop to use the Identity Provider.
		Similarly you must configure the Identity Provider to be used by iTop. This is achieved by importing the XML meta data published by your SAML server into iTop.</p>
		<hr/>
HTML
		);
	}
	else{
		$oHeaderCollapsibleBlock = CollapsibleSectionUIBlockFactory::MakeStandard('Single Sign-On configuration using SAML');
		$sHtmlContent = 
			<<<HTML
		<div class="saml-welcome-content">
		<div>
			<p>To enable the Single Sign On (SSO) based on SAML in iTop, you have to configure both:</p>
			<ul>
			<li>iTop as a SAML <b>Service Provider</b> (SP) connected to your SAML server</li>
			<li>Your SAML server as a SAML <b>Identity Provider</b> (IdP) accepting this instance of iTop</li>
			</ul>
			<p>This configuration is done by echanging XML meta data between both systems.
			You must export the meta data describing iTop as a Service provider to your SAML server in order to allow iTop to use the Identity Provider.
			Similarly you must configure the Identity Provider to be used by iTop. This is achieved by importing the XML meta data published by your SAML server into iTop.</p>
		</div>
				<div><img src="$sModuleURL/asset/img/SAML-configuration.svg"></div>
		</div>
HTML;
		$oHtml = HtmlFactory::MakeHtmlContent($sHtmlContent);
		$oHeaderCollapsibleBlock->AddSubBlock($oHtml);	
		$oHeaderCollapsibleBlock->SetOpenedByDefault(true);	
		$oP->AddUiBlock($oHeaderCollapsibleBlock);
	}

	$sUrl = MetaModel::GetModuleSetting('combodo-saml', 'idp_metadata_url','');
	DisplayInputForm($oP, $sUrl, '');

	$oConfig = new Config();
	$aSettings = $oConfig->GetSettings();
	$sSafePrivateKey = (isset($aSettings['sp']['privateKey']) && ($aSettings['sp']['privateKey'] != '')) ? HIDDEN_PRIVATE_KEY : '';
	$sSafeX509Cert = isset($aSettings['sp']['x509cert']) ? htmlentities($aSettings['sp']['x509cert'], ENT_QUOTES, 'UTF-8') : '';

	$bDebug = isset($aSettings['debug']) ? (bool)$aSettings['debug'] : false;
	
	$sSafeNameID = MetaModel::GetModuleSetting('combodo-saml', 'nameid', '');
	$sMetaDataURI = utils::GetAbsoluteUrlModulePage('combodo-saml', "sp-metadata.php");
	$sDebugChecked = $bDebug ? 'checked' : '';
	$oP->add(
<<<HTML
<hr/>
<div class="ibo-is-html-content">
<form id="certificate_form" method="post">
<input type="hidden" name="operation" value="update_certificate"/>
<h2>Configuring the iTop Service Provider</h2>
<p><input type="checkbox" name="debug" $sDebugChecked value="1" id="debug_checkbox"><label for="debug_checkbox"> Debug mode (more debug information logged to the file <i>log/saml.log</i>, <b>not recommended</b> in production.)</label</p>
<p>NameID or attribute <i>in the IdP response</i> holding the login/identifier:</p>
<p><input class="ibo-input saml-uid-input"  type="text" name="name_id" style="width: 10em" placeholder="uid" value="$sSafeNameID"/> (For example: NameID, uid, email...)</p>
<p>Enter the X509 certificate and the private key to use for signing iTop's SAML requests. You can use <a href="https://www.samltool.com/self_signed_certs.php" target="_blank">this online tool</a> to generate a self-signed certificate.</p>
<p>If you skip this configuration the requests will NOT be signed.</p>
<p>Private Key:</p>
<p><textarea class="ibo-input ibo-is-code" style="width:35rem;height:10rem;" name="private_key" placeholder="-----BEGIN PRIVATE KEY-----
...
...
...
-----END PRIVATE KEY-----">$sSafePrivateKey</textarea></p>
<p>X509 certificate:</p>
<p><textarea class="ibo-input ibo-is-code" style="width:35rem;height:10rem;" name="x509cert" placeholder="-----BEGIN CERTIFICATE-----
...
...
...
-----END CERTIFICATE-----">$sSafeX509Cert</textarea></p>
<p><button class="ibo-button ibo-is-regular ibo-is-primary" type="submit">Save SP configuration</button></p>
</form>

<hr/>

<h2>Exporting iTop's Service Provider meta data</h2>
<p>Use the following link to export iTop's meta data: <a target="_blank" href="$sMetaDataURI">Meta Data Export</a></p>
</div>
HTML
	);
}

/**
 * Update iTop configuration to set/unset the certificate and the security flags
 *
 * @param WebPage $oP
 */
function UpdateCertificate(WebPage $oP)
{
	$oConf = Metamodel::GetConfig();
	$bDebug = (bool)utils::ReadPostedParam('debug', '', false, 'raw_data');
	$sNameID = utils::ReadPostedParam('name_id', 0, false, 'raw_data');
	$sX509Cert = utils::ReadPostedParam('x509cert', '', false, 'raw_data');
	$sPrivateKey = utils::ReadPostedParam('private_key', '', false, 'raw_data');
	
	$oConf->SetModuleSetting('combodo-saml', 'nameid', $sNameID);
	$oConf->SetModuleSetting('combodo-saml', 'debug', $bDebug);
	
	$aSP = $oConf->GetModuleSetting('combodo-saml', 'sp', array());
	$aSP['entityId'] = utils::GetAbsoluteUrlModulesRoot() . 'combodo-saml';
	$aSP['x509cert'] = $sX509Cert;
	if ($sPrivateKey !== HIDDEN_PRIVATE_KEY)
	{
		$aSP['privateKey'] = $sPrivateKey;
	}
	$oConf->SetModuleSetting('combodo-saml', 'sp', $aSP);

	$aSecurity = $oConf->GetModuleSetting('combodo-saml', 'security', array());
	if ($sX509Cert != '')
	{
		// When a certificate is configured, request that the assertions be signed
		$aSecurity['wantMessagesSigned'] = false; // Forcing this to true seems to cause the logoff to fail with SimpleSAML since the client expects ALL messages to be signed
		$aSecurity['wantAssertionsSigned'] = true;
		$aSecurity['authnRequestsSigned'] = true;
		$aSecurity['logoutRequestSigned'] = true;
		$aSecurity['logoutResponseSigned'] = true;
	}
	else
	{
		// No certificate, don't try to sign the messages !
		$aSecurity['wantMessagesSigned'] = false;
		$aSecurity['wantAssertionsSigned'] = false;
		$aSecurity['authnRequestsSigned'] = false;
		$aSecurity['logoutRequestSigned'] = false;
		$aSecurity['logoutResponseSigned'] = false;
	}
	$oConf->SetModuleSetting('combodo-saml', 'security', $aSecurity);

	@chmod($oConf->GetLoadedFile(), 0770); // Allow overwriting the file
	$oConf->WriteToFile();
	@chmod($oConf->GetLoadedFile(), 0444); // Read-only

	$oP->add('<div class="header_message message_ok">iTop (Service Provider) Configuration updated!!</div>');
	DisplayWelcomePage($oP);
}

/////////////////////////////////////////////////////////////////////
// Main program
//
LoginWebPage::DoLogin(); // Check user rights and prompt if needed
ApplicationMenu::CheckMenuIdEnabled('SAMLConfiguration');

$oP = new iTopWebPage('SAML Configuration');
if(!SamlUseLegacy()){
	$oP->add_saas('env-'.utils::GetCurrentEnvironment().'/combodo-saml/css/configuration.scss');
}
try
{
	$sOperation = utils::ReadParam('operation', '');
	$sUrl = utils::ReadParam('url', '', false, 'raw_data');
	$sRawXml = utils::ReadParam('xml_meta_data', '', false, 'raw_data');

	switch($sOperation)
	{
		case 'update':
			UpdateIdPConfiguration($oP, $sUrl, $sRawXml);
			break;

		case 'check':
			CheckMetaData($oP, $sUrl, $sRawXml);
			break;

		case 'idp':
			DisplayInputForm($oP, $sUrl, $sRawXml);
			break;
			
		case 'update_certificate':
			UpdateCertificate($oP);
			break;
		    
		default:
			DisplayWelcomePage($oP);
	}
}
catch (Exception $e)
{
	$oP->p('ERROR: '.$e->getMessage());
}
$oP->output();
