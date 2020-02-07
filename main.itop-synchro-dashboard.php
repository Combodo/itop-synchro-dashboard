<?php
class ItopSynchroDashboardMenus extends ModuleHandlerAPI
{
	/**
	 * @inheritDocc
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

			$oAdminMenu = new MenuGroup($sParentMenuId, 90 /* fRank */, 'SynchroDataSource', UR_ACTION_MODIFY, UR_ALLOWED_YES);
			new WebPageMenuNode('DataSourcesDashboard', utils::GetAbsoluteUrlAppRoot().'pages/exec.php?exec_module=itop-synchro-dashboard&exec_page=dashboard.php', $oAdminMenu->GetIndex(), 25 /* fRank */);
		}
	}
}
