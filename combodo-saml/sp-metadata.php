<?php
/**
 * Copyright (C) 2019-2020 Combodo SARL
 *
 * This file is part of iTop.
 *
 * iTop is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * iTop is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 */

/**
 * UNAUTHENTICATED page to export the SP Meta Data of iTop for configuring your SAML IdP
 */
require_once('../approot.inc.php');
require_once (APPROOT.'bootstrap.inc.php');
require_once (APPROOT.'application/startup.inc.php');
require_once (APPROOT.'application/ajaxwebpage.class.inc.php');

use Combodo\iTop\Extension\Saml\Config;
use OneLogin\Saml2\Metadata;
use OneLogin\Saml2\Settings;

$oP = new ajax_page('');
$oP->SetContentType('application/xml;charset=UTF-8');

$oConfig = new Config();
$aSettings = $oConfig->GetSettings();
// Automatically fill-in the URLs
Config::FillSPSettings($aSettings['sp']);

$aAttributes = isset($aSettings['sp']['attributeConsumingService']) ? $aSettings['sp']['attributeConsumingService'] : array();
$aContactPerson = isset($aSettings['contactPerson']) ? $aSettings['contactPerson'] : array();
$aOrganization = isset($aSettings['organization']) ? $aSettings['organization'] : array();

$bWantMessagesSigned = isset($aSettings['security']['wantMessagesSigned']) ? $aSettings['security']['wantMessagesSigned'] :  true;
$bWantAssertionsSigned = isset($aSettings['security']['wantAssertionsSigned']) ? $aSettings['security']['wantAssertionsSigned'] :  true;

$validUntil = MetaModel::GetModuleSetting('combodo-saml', 'validUntil', null);
if ($validUntil !== null)
{
	// Convert from days (relative to the current time) to seconds
	$validUntil = (int)(time()+(24*3600)*$validUntil);
}
$cacheDuration = MetaModel::GetModuleSetting('combodo-saml', 'cacheDuration', null);
if ($cacheDuration !== null)
{
	// Convert from days to seconds
	$cacheDuration = (int)((24*3600)*$cacheDuration);
}

$oSettings = new Settings($aSettings); // Will fill default values for missing fields
$aSP = $oSettings->getSPData();

$sXml = MetaData::builder($aSP, $bWantMessagesSigned, $bWantAssertionsSigned, $validUntil, $cacheDuration, $aContactPerson, $aOrganization, $aAttributes);
if (isset($aSettings['sp']['x509cert']) && isset($aSettings['sp']['privateKey']) && ($aSettings['sp']['x509cert'] != '') && ($aSettings['sp']['privateKey'] != ''))
{
	// If a private key and a certificate are specified, add them to the metadata and sign the metadata
	$sXml = MetaData::addX509KeyDescriptors($sXml, $aSettings['sp']['x509cert']);
	$sXml = MetaData::signMetadata($sXml, $aSettings['sp']['privateKey'], $aSettings['sp']['x509cert']);
}

$oP->add($sXml);
$oP->output();
