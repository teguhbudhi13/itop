<?php
/**
 * @copyright   Copyright (C) 2010-2019 Combodo SARL
 * @license     https://www.combodo.com/documentation/combodo-software-license.html
 *
 */
require_once __DIR__.'/src/Config.php';
require_once __DIR__.'/src/Logger.php';
require_once __DIR__.'/src/SAMLLoginExtension.php';
require_once __DIR__.'/vendor/autoload.php';

//
// Menus
//
class CombodoSamlMenuHandler extends ModuleHandlerAPI
{
	/**
	 * Create the menu to manage the configuration of the extension, but only for
	 * users allowed to manage the configuration
	 */
	public static function OnMenuCreation()
	{
        $bConfigMenuEnabled = UserRights::IsAdministrator();
		if ($bConfigMenuEnabled)
		{
			new WebPageMenuNode(
				'SAMLConfiguration',
				utils::GetAbsoluteUrlModulePage('combodo-saml', "configuration.php"),
				ApplicationMenu::GetMenuIndexById('ConfigEditor'),
				50 ,
				'ResourceAdminMenu',
				UR_ACTION_MODIFY,
				UR_ALLOWED_YES,
				null);}
	}
}
