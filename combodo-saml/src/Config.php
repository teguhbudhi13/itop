<?php
/**
 * @copyright   Copyright (C) 2010-2019 Combodo SARL
 * @license     https://www.combodo.com/documentation/combodo-software-license.html
 *
 */

namespace Combodo\iTop\Extension\Saml;

require_once(APPROOT.'/application/utils.inc.php');
require_once(APPROOT.'/core/metamodel.class.php');

use MetaModel;
use utils;
use DOMDocument;


class Config
{
	// XML namespaces used for the meta data
	const META_DATA_NS = 'urn:oasis:names:tc:SAML:2.0:metadata';
	const DIGITAL_SIGNATURE_NS = 'http://www.w3.org/2000/09/xmldsig#';
	// SAML / OASIS binding code for HTTP-Redirect, HTTP-POST
	const BINDING_HTTP_REDIRECT = 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect';
	const BINDING_HTTP_POST = 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST';

	public function GetSettings()
	{
		$aSP = MetaModel::GetModuleSetting('combodo-saml', 'sp', array());
		$aIDP = MetaModel::GetModuleSetting('combodo-saml', 'idp', array());
		$aSecurity = MetaModel::GetModuleSetting('combodo-saml', 'security', array());
		$sEntityId = utils::GetAbsoluteUrlModulesRoot().'combodo-saml';
		
		$aSettings = array (
			// If 'strict' is True, then the PHP Toolkit will reject unsigned
			// or unencrypted messages if it expects them to be signed or encrypted.
			// Also it will reject the messages if the SAML standard is not strictly
			// followed: Destination, NameId, Conditions ... are validated too.
			'strict' => MetaModel::GetModuleSetting('combodo-saml', 'strict', true),
			
			// Enable debug mode (to print errors).
			'debug' => MetaModel::GetModuleSetting('combodo-saml', 'debug', true),
			
			// Set a BaseURL to be used instead of try to guess
			// the BaseURL of the view that process the SAML Message.
			// Ex http://sp.example.com/
			//    http://example.com/sp/
			'baseurl' => MetaModel::GetModuleSetting('combodo-saml', 'baseurl', $sEntityId),
			
			// Which attribute do we use to get the login of the person identified by the IdP ?
			'nameid' => MetaModel::GetModuleSetting('combodo-saml', 'nameid', ''),
			
			// Duration information for the validity of the meta data... (used for creating the XML meta data
			'validUntil' => MetaModel::GetModuleSetting('combodo-saml', 'validUntil', null),
			'cacheDuration' => MetaModel::GetModuleSetting('combodo-saml', 'cacheDuration', null),
			
			// Hack for processing some Logout reponses which differ (uppercase vs lowercase ?) in the encoding/signature
			// Set to true for compatibility with ADFS / Azure
			'retrieveParametersFromServer' => MetaModel::GetModuleSetting('combodo-saml', 'retrieveParametersFromServer', true),
			
			// Service Provider Data that we are deploying.
			'sp' => $aSP,
			
			// Identity Provider Data that we want connected with our SP.
			'idp' => $aIDP,
		    
			// Extra security configuration
			'security' => $aSecurity,
		);

		if (!isset($aSettings['sp']['entityId']))
		{
			$aSettings['sp']['entityId'] = $sEntityId;
		}
		if (!isset($aSettings['sp']['assertionConsumerService']['url']))
		{
			$sACSUrl = utils::GetAbsoluteUrlModulesRoot().'combodo-saml/acs.php';
			$aSettings['sp']['assertionConsumerService']['url'] = $sACSUrl;
		}
		if (!isset($aSettings['sp']['singleLogoutService']['url']))
		{
			$sSLSUrl = utils::GetAbsoluteUrlModulesRoot().'combodo-saml/sls.php';
			$aSettings['sp']['singleLogoutService']['url'] = $sSLSUrl;
		}

		return $aSettings;
	}

	public static function ParseIdPMetaData($sXMLMetaData, &$aError)
	{
		$aIdP = array();
		$oDom = new DOMDocument('1.0', 'UTF-8');
		if ($oDom->loadXML($sXMLMetaData) === false)
		{
			$aError[] = 'Failed to parse the XML';
		}

		// Check and retrieve the EntityID
		$oEntities = $oDom->getElementsByTagNameNS(static::META_DATA_NS, 'EntityDescriptor');
		if ($oEntities->length == 0)
		{
			$aErrors[] = 'Missing the Entity ID! (No <EntityDescriptor> tag found)';
		}
		else
		{
			$oEntityDesc = $oEntities->item(0);
			$aIdP['entityId'] = $oEntityDesc->getAttribute('entityID');
			if($oEntities->length > 1)
			{
				$aErrors[] = 'Several Entity IDs, will use the first one! (Multiple <EntityDescriptor> tags found)';
			}
		}

		// Check the x509 certificates
		$oKeys = $oDom->getElementsByTagNameNS(static::META_DATA_NS, 'KeyDescriptor');
		if ($oKeys->length == 0)
		{
			// Hmm, missing x509 certificate !
			$aErrors[] = 'Missing x509 signing certificate! (No <KeyDescriptor> tag found)';
		}
		if ($oKeys->length == 1)
		{
			// Just one certificate, use the simple 'x509cert' entry of the conf
			$oKey = $oKeys->item(0);
			$oCerts = $oKey->getElementsByTagNameNS(static::DIGITAL_SIGNATURE_NS, 'X509Certificate');

			$oCert = $oCerts->item(0);
			$aIdP['x509cert'] = $oCert->textContent;
		}
		else
		{
			// Several keys are declared, use the more complex 'x509certMulti' structure
			foreach($oKeys as $oKey)
			{
				$sUse = $oKey->getAttribute('use');
				$oCerts = $oKey->getElementsByTagNameNS(static::DIGITAL_SIGNATURE_NS, 'X509Certificate');

				foreach($oCerts as $oCert)
				{
					$aIdP['x509certMulti'][$sUse][] = $oCert->textContent;
				}
			}
		}

		// Check the SSO endpoints
		$oSignOnServices = $oDom->getElementsByTagNameNS(static::META_DATA_NS, 'SingleSignOnService');
		$aIdP['singleSignOnService'] = array();
		$iBindingsCount = 0;
		$bFound = false;
		foreach($oSignOnServices as $oNode)
		{
			$iBindingsCount++;
			if($oNode->getAttribute('Binding') == static::BINDING_HTTP_REDIRECT)
			{
				$bFound = true;
				$aIdP['singleSignOnService'] = array(
					'url' => $oNode->getAttribute('Location'),
					'binding' => $oNode->getAttribute('Binding'),
				);
			}
		}
		if ($iBindingsCount == 0)
		{
			$aErrors[] = 'Missing SingleSignOnService end point! (No <SingleSignOnService> tag found)';
		}
		else if (!$bFound)
		{
			$aErrors[] = 'Missing HTTP-Redirect SSO end point - The OneLogin library supports only this binding! (No <SingleSignOnService> tag with Binding="'.static::BINDING_HTTP_REDIRECT.'" found)';
		}

		// Check the SLO endpoints
		$oLogoutServices = $oDom->getElementsByTagNameNS(static::META_DATA_NS, 'SingleLogoutService');
		$aIdP['singleLogoutService'] = array();
		$iBindingsCount = 0;
		$bFound = false;
		foreach($oLogoutServices as $oNode)
		{
			$iBindingsCount++;
			if($oNode->getAttribute('Binding') == static::BINDING_HTTP_REDIRECT)
			{
				$bFound = true;
				$aIdP['singleLogoutService'] = array(
				'url' => $oNode->getAttribute('Location'),
				'binding' => $oNode->getAttribute('Binding'),
				'responseUrl' => $oNode->getAttribute('ResponseLocation'),
				);
			}
		}
		if ($iBindingsCount == 0)
		{
			$aErrors[] = 'Missing SingleLogoutService end point! (No <SingleLogoutService> tag found)';
		}
		else if (!$bFound)
		{
			$aErrors[] = 'Missing HTTP-Redirect SLO end point - The OneLogin library supports only this binding! (No <SingleLogoutService> tag with Binding="'.static::BINDING_HTTP_REDIRECT.'" found)';
		}

		return $aIdP;
	}
	
	public static function GetSPSettings()
	{
		$oConf = new static();
		$aConfSettings = $oConf->GetSettings();
		$sPath = utils::GetAbsoluteUrlModulesRoot().'combodo-saml';
		$aSP = array(
			'entityId' => utils::GetAbsoluteUrlModulesRoot().'combodo-saml',
			'SingleLogoutService' => array(
				'Binding' => static::BINDING_HTTP_REDIRECT,
				'Location' => $sPath.'/sls.php',
			),
			'AssertionConsumerService' => array(
				'Binding' => static::BINDING_HTTP_POST,
				'Location' => $sPath.'/acs.php',
			),
		);
		if (isset($aConfSettings['sp']['x509cert']))
		{
		    $aSP['key'] = str_replace(array('-----END CERTIFICATE-----', '-----BEGIN CERTIFICATE-----', "\n", "\r"), '', $aConfSettings['sp']['x509cert']);
		}
		return $aSP;
	}
	
	public static function FillSPSettings(&$aSP)
	{
		$oConf = new static();
		$aConfSettings = $oConf->GetSettings();
		$sPath = utils::GetAbsoluteUrlModulesRoot().'combodo-saml';
		$aSP['entityId'] = utils::GetAbsoluteUrlModulesRoot().'combodo-saml';
		$aSP['singleLogoutService'] = array(
				'binding' => static::BINDING_HTTP_REDIRECT,
				'url' => $sPath.'/sls.php',
			);
		$aSP['assertionConsumerService'] = array(
				'binding' => static::BINDING_HTTP_POST,
				'url' => $sPath.'/acs.php',
			);
		if (isset($aConfSettings['sp']['x509cert']))
		{
			//$aSP['key'] = str_replace(array('-----END CERTIFICATE-----', '-----BEGIN CERTIFICATE-----', "\n", "\r"), '', $aConfSettings['sp']['x509cert']);
		}
		return $aSP;
	}
}
