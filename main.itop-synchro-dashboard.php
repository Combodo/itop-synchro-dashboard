<?php
class ItopSynchroDashboardMenus extends ModuleHandlerAPI
{
	/**
	 * @inheritDoc
	 * @throws \Exception
	 */
	public static function OnMenuCreation()
	{
		// Add the admin menus
		if (UserRights::IsAdministrator())
		{
			// From iTop 2.7, the "ConfigurationTools" menu group exists
			// Before, only "AdminTools" was available for that kind of entry
			$sParentMenuId = ApplicationMenu::GetMenuIndexById('ConfigurationTools') > -1 ? 'ConfigurationTools' : 'AdminTools';
			$sParentMenuIndex = ApplicationMenu::GetMenuIndexById($sParentMenuId);

			new WebPageMenuNode('DataSourcesDashboard', utils::GetAbsoluteUrlAppRoot().'pages/exec.php?exec_module=itop-synchro-dashboard&exec_page=dashboard.php', $sParentMenuIndex, 25 /* fRank */);
		}
	}
}
