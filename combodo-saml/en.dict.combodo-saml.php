<?php
/**
 * @copyright   Copyright (C) 2010-2019 Combodo SARL
 * @license     https://www.combodo.com/documentation/combodo-software-license.html
 *
 */
/**
 * Localized data
 */

Dict::Add('EN US', 'English', 'English', array(
	'SAML:Error:UserNotAllowed' => 'User not allowed',
	'SAML:Error:ErrorOccurred' => 'An error occurred',
	'SAML:Error:CheckTheLogFileForMoreInformation' => 'Check the log file "log/saml.log" for more information.',
	'SAML:Error:NotAuthenticated' => 'Not authenticated',
	'SAML:SimpleSaml:GenerateSimpleSamlConf' => 'Generate configuration for SimpleSaml',
	'SAML:SimpleSaml:Instructions' => 'Append this conf to: simplesamlphp/metadata/saml20-sp-remote.php',
    'SAML:Login:SignIn' => 'Sign in with SAML',
    'SAML:Login:SignInTooltip' => 'Click here to authenticate yourself with the SAML server',
	'Menu:SAMLConfiguration' => 'SAML Configuration',
	'SAML:Error:Invalid_Attribute' => 'SAML authentication failed because the expected attribute \'%1$s\' was not found in the response from the Identity Provider (IdP). Check the error.log file for more information.',
));
