<?php
class ItopSynchroDashboardMenus extends ModuleHandlerAPI
{
	public static function OnMenuCreation()
	{
		// Add the admin menus
		if (UserRights::IsAdministrator())
		{
			$oAdminMenu = new MenuGroup('AdminTools', 80 /* fRank */, 'SynchroDataSource', UR_ACTION_MODIFY, UR_ALLOWED_YES);
			new WebPageMenuNode('DataSourcesDashboard', utils::GetAbsoluteUrlAppRoot().'pages/exec.php?exec_module=itop-synchro-dashboard&exec_page=dashboard.php', $oAdminMenu->GetIndex(), 13 /* fRank */);
		}
	}
}