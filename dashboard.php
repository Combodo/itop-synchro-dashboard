<?php
// Copyright (C) 2010-2012 Combodo SARL
//
//   This file is part of iTop.
//
//   iTop is free software; you can redistribute it and/or modify	
//   it under the terms of the GNU Affero General Public License as published by
//   the Free Software Foundation, either version 3 of the License, or
//   (at your option) any later version.
//
//   iTop is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU Affero General Public License for more details.
//
//   You should have received a copy of the GNU Affero General Public License
//   along with iTop. If not, see <http://www.gnu.org/licenses/>

/**
 * Execute and shows the data quality audit
 *
 * @copyright   Copyright (C) 2010-2024 Combodo SAS
 * @license     http://opensource.org/licenses/AGPL-3.0
 */
/**
 * Adds the context parameters to the audit query
 */

/**
 * Add a condition (restriction) to the current DBObjectSearch on which the display block is based
 * taking into account the hierarchical keys for which the condition is based on the 'below' operator
 *
 * @param DBObjectSearch $oFilter
 * @param $sFilterCode
 * @param $condition
 * @param null $sOpCode
 *
 * @throws Exception
 * @throws CoreException
 * @throws CoreWarning
 * @throws MissingQueryArgument
 */
function AddCondition(DBObjectSearch $oFilter, $sFilterCode, $condition, $sOpCode = null)
{
	static $aConditions = array();
	
	// Workaround to an issue revealed whenever a condition on org_id is applied twice (with a hierarchy of organizations)
	// Moreover, it keeps the query as simple as possible
	if (isset($aConditions[$sFilterCode]) && $condition == $aConditions[$sFilterCode])
	{
		// Skip
		return;
	}
	$aConditions[$sFilterCode] = $condition;

	$sClass = $oFilter->GetClass();
	$bConditionAdded = false;
	
	// If the condition is an external key with a class having a hierarchy, use a "below" criteria
	if (MetaModel::IsValidAttCode($sClass, $sFilterCode))
	{
		$oAttDef = MetaModel::GetAttributeDef($sClass, $sFilterCode);

		if ($oAttDef->IsExternalKey())
		{
			$sHierarchicalKeyCode = MetaModel::IsHierarchicalClass($oAttDef->GetTargetClass());
			
			if ($sHierarchicalKeyCode !== false)
			{
				$oFilter = new DBObjectSearch($oAttDef->GetTargetClass());
				if (($sOpCode == 'IN') && is_array($condition))
				{
					$oFilter->AddConditionExpression(GetConditionIN($oFilter, 'id', $condition));						
				}
				else
				{
					$oFilter->AddCondition('id', $condition);
				}
				$oHKFilter = new DBObjectSearch($oAttDef->GetTargetClass());
				$oHKFilter->AddCondition_PointingTo($oFilter, $sHierarchicalKeyCode, TREE_OPERATOR_BELOW); // Use the 'below' operator by default
				$oFilter->AddCondition_PointingTo($oHKFilter, $sFilterCode);
				$bConditionAdded = true;
			}
			else if (($sOpCode == 'IN') && is_array($condition))
			{
				$oFilter->AddConditionExpression(GetConditionIN($oFilter, $sFilterCode, $condition));
				$bConditionAdded = true;
			}
		}
		else if (($sOpCode == 'IN') && is_array($condition))
		{
			$oFilter->AddConditionExpression(GetConditionIN($oFilter, $sFilterCode, $condition));
			$bConditionAdded = true;
		}
	}
	
	// In all other cases, just add the condition directly
	if (!$bConditionAdded)
	{
		$oFilter->AddCondition($sFilterCode, $condition); // Use the default 'loose' operator
	}
}

/**
 * @param $oFilter
 * @param $sFilterCode
 * @param $condition
 *
 * @return Expression
 *
 * @throws MissingQueryArgument
 */
function GetConditionIN($oFilter, $sFilterCode, $condition)
{
	$oField = new FieldExpression($sFilterCode,  $oFilter->GetClassAlias());
	$sListExpr = '('.implode(', ', CMDBSource::Quote($condition)).')';
	$sOQLCondition = $oField->RenderExpression()." IN $sListExpr";
	$oNewCondition = Expression::FromOQL($sOQLCondition);
	return $oNewCondition;		
}

try
{
	//require_once('../approot.inc.php'); // Not needed since the page is called via exec.php which performs this for us
	require_once(APPROOT.'/application/application.inc.php');
	//remove require itopdesignformat at the same time as version_compare(ITOP_DESIGN_LATEST_VERSION , '3.0') < 0
	if (! defined("ITOP_DESIGN_LATEST_VERSION")) {
		require_once APPROOT.'setup/itopdesignformat.class.inc.php';
	}
	if (version_compare(ITOP_DESIGN_LATEST_VERSION, '3.0') < 0) {
		require_once(APPROOT.'/application/itopwebpage.class.inc.php');
	}
	require_once(APPROOT.'/application/startup.inc.php');
	if (version_compare(ITOP_DESIGN_LATEST_VERSION , '3.0') < 0) {
		require_once(APPROOT.'/application/csvpage.class.inc.php');
	}
	
	$operation = utils::ReadParam('operation', '');
	$oAppContext = new ApplicationContext();
	
	require_once(APPROOT.'/application/loginwebpage.class.inc.php');
	LoginWebPage::DoLogin(); // Check user rights and prompt if needed
	
	$oP = new iTopWebPage(Dict::S('UI:SynchroDashboard:Title'));
	$sOperation = utils::ReadParam('operation', '');
	$bDoSearch = ($sOperation == 'search_form');

	$oFilter = new DBObjectSearch('SynchroDataSource');

	$aExtraParams = array('open' => $bDoSearch, 'action' => utils::GetAbsoluteUrlAppRoot().'pages/exec.php', 'exec_module' => 'itop-synchro-dashboard', 'exec_page' => 'dashboard.php');
	$oSearchBlock = new LegacySearchBlock($oFilter, $aExtraParams);
	$oSearchBlock->Display($oP, 'sds_filter');

	// Apply the context filtering and the search criteria, if any

	$oAppContext = new ApplicationContext();
	$sClass = $oFilter->GetClass();
	$aFilterCodes = MetaModel::GetFiltersList($sClass);
	$aCallSpec = array($sClass, 'MapContextParam');
	if (is_callable($aCallSpec)) {
		foreach ($oAppContext->GetNames() as $sContextParam) {
			$sParamCode = call_user_func($aCallSpec, $sContextParam); //Map context parameter to the value/filter code depending on the class
			if (!is_null($sParamCode)) {
				$sParamValue = $oAppContext->GetCurrentValue($sContextParam, null);
				if (!is_null($sParamValue))
				{
					$aExtraParams[$sParamCode] = $sParamValue;
				}
			}
		}
	}
	foreach($aFilterCodes as $sFilterCode)
	{
		$externalFilterValue = utils::ReadParam($sFilterCode, '', false, 'raw_data');
		$condition = null;
		if ($bDoSearch && $externalFilterValue != "")
		{
			// Search takes precedence over context params...
			if (!is_array($externalFilterValue))
			{
				$condition = trim($externalFilterValue);
			}
			else if (count($externalFilterValue) == 1)
			{
				$condition = trim($externalFilterValue[0]);
			}
			else
			{
				$condition = $externalFilterValue;
			}
		}

		if (!is_null($condition))
		{
			$sOpCode = null; // default operator
			if (is_array($condition))
			{
				// Multiple values, add them as AND X IN (v1, v2, v3...)
				$sOpCode = 'IN';
			}

			AddCondition($oFilter, $sFilterCode, $condition, $sOpCode);
		}
	}
	
	$oSDSSet = new DBObjectSet($oFilter);
	$aData = array();
	$iTotalReplicas = 0;
	$iTotalErrors = 0;
	$iTotalWarnings = 0;
	$iPeakMemory = 0;
	$iTotalDuration = 0;
	$iTotalDataSources = 0;
	
	while($oSDS = $oSDSSet->Fetch())
	{
		$sSelectSynchroLog = 'SELECT SynchroLog WHERE sync_source_id = :source_id';
		$oSetSynchroLog = new CMDBObjectSet(DBObjectSearch::FromOQL($sSelectSynchroLog), array('start_date' => false) /* order by*/, array('source_id' => $oSDS->GetKey()));
		$oSetSynchroLog->SetLimit(1); // Stats are based on the latest run
		$iDSid = $oSDS->GetKey();
		
		$sReplicaURl = utils::GetAbsoluteUrlAppRoot().'synchro/replica.php';
		
		$aRow = array('name' => $oSDS->GetHyperlink());
		if ($oSetSynchroLog->Count() > 0)
		{
			$oLastLog = $oSetSynchroLog->Fetch();
			$sStartDate = $oLastLog->Get('start_date');
			$sEndDate = $oLastLog->Get('end_date');
			
			$aRow['start_date'] = $sStartDate;
			$oStartDate = new DateTime($sStartDate);
			$oEndDate = new DateTime($sEndDate);
			/** @var integer $iDuration */
			$iDuration = $oEndDate->format('U') - $oStartDate->format('U');
			$iTotalDuration += $iDuration;
			$aRow['duration'] = AttributeDuration::FormatDuration($iDuration);
			
			$sOQL = "SELECT SynchroReplica WHERE sync_source_id=$iDSid";
			$oSet = new DBObjectSet(DBObjectSearch::FromOQL($sOQL));
			$iCountAllReplicas = $oSet->Count();
			$aRow['replicas'] = "<a href=\"{$sReplicaURl}?operation=oql&datasource=$iDSid&oql=$sOQL\">$iCountAllReplicas</a>";
			$sOQL = "SELECT SynchroReplica WHERE sync_source_id=$iDSid AND status_last_error !=''";
			$oSet = new DBObjectSet(DBObjectSearch::FromOQL($sOQL));
			$iCountAllErrors = $oSet->Count();
			$aRow['errors'] = "<a href=\"{$sReplicaURl}?operation=oql&datasource=$iDSid&oql=$sOQL\">$iCountAllErrors</a>";
			$sOQL = "SELECT SynchroReplica WHERE sync_source_id=$iDSid AND status_last_warning !=''";
			$oSet = new DBObjectSet(DBObjectSearch::FromOQL($sOQL));
			$iCountAllWarnings = $oSet->Count();
			$aRow['warnings'] = "<a href=\"{$sReplicaURl}?operation=oql&datasource=$iDSid&oql=$sOQL\">$iCountAllWarnings</a>";
			
			$aRow['peak_memory'] = sprintf('%.2f Mo', $oLastLog->Get('memory_usage_peak') / (1024*1024));
			
			$iTotalReplicas += $iCountAllReplicas;
			$iTotalErrors += $iCountAllErrors;
			$iTotalWarnings += $iCountAllWarnings;
			$iPeakMemory = max($iPeakMemory, $oLastLog->Get('memory_usage_peak'));
			
			if ($iCountAllErrors > 0)
			{
				$aRow['@class'] = 'ibo-is-'.HILIGHT_CLASS_CRITICAL;
			}
			else if ($iCountAllWarnings > 0) {
				$aRow['@class'] = 'ibo-is-'.HILIGHT_CLASS_WARNING;
			}
		}
		else
		{
			$aRow['start_date'] = Dict::S('Core:Synchro:NeverRun');
			$aRow['duration'] = '&nbsp;';
			$aRow['replicas'] = '&nbsp;';
			$aRow['errors'] = '&nbsp;';
			$aRow['warnings'] = '&nbsp;';
			$aRow['peak_memory'] = '&nbsp;';
		}
		$aData[] = $aRow;
		$iTotalDataSources++;
	}
	$aConfig = array(
		'name' => array('label' => MetaModel::GetName('SynchroDataSource'), 'description' => MetaModel::GetClassDescription('SynchroDataSource')),
		'start_date' => array('label' => Dict::S('UI:SynchroDashboard:StartDate'), 'description' => Dict::S('UI:SynchroDashboard:StartDate+')),
		'duration' => array('label' => Dict::S('UI:SynchroDashboard:Duration'), 'description' => Dict::S('UI:SynchroDashboard:Duration+')),
		'replicas' => array('label' => Dict::S('UI:SynchroDashboard:NbOfReplicas'), 'description' => Dict::S('UI:SynchroDashboard:NbOfReplicas+')),
		'errors' => array('label' => Dict::S('UI:SynchroDashboard:NbOfErrors'), 'description' => Dict::S('UI:SynchroDashboard:NbOfErrors+')),
		'warnings' => array('label' => Dict::S('UI:SynchroDashboard:NbOfWarnings'), 'description' => Dict::S('UI:SynchroDashboard:NbOfWarnings+')),
		'peak_memory' => array('label' => Dict::S('UI:SynchroDashboard:PeakMemoryUsage'), 'description' => Dict::S('UI:SynchroDashboard:PeakMemoryUsage+')),
	);
	
	$oP->add_style('.stats-container { margin-left: auto; margin-right: auto; width: 900px; vertical-align: bottom; }');
	$oP->add_style('.stats_bagde { padding: 0.5em; display: inline-block; vertical-align: middle; height: 90px; min-width: 90px; font-size: 1.3em; font-weight: bold; margin-left: 0.5em; margin-right: 0.5em; text-align: center; margin-top: 1em; margin-bottom: 1em; }');
	$oP->add_style('.badge_label { font-size: 0.6em; color: #999; margin-bottom: 20px; margin-top: -10px; }');
	$oP->add_style('.badge_number { position: relative; top: 50%; transform: translateY(-50%); }');
	$oP->add_linked_script(utils::GetAbsoluteUrlAppRoot().'js/raphael-min.js');
	$oP->add_linked_script(utils::GetAbsoluteUrlModulesRoot().'itop-synchro-dashboard/js/justgage.1.0.1.min.js');

	$oP->AddTabContainer('main');
	$oP->SetCurrentTabContainer('main');
	$oP->SetCurrentTab(Dict::S('UI:SynchroDashboard:Overview:LatestRun'));

	$oP->add('<div class="stats-container">');
	$oP->add("<div class=\"stats_bagde ui-widget-content ui-corner-top  ui-corner-bottom\"><div class=\"badge_number\"><div class=\"badge_label\">".Dict::S('UI:SynchroDashboard:Overview:DataSources')."</div>$iTotalDataSources</div></div>");
	$oP->add("<div class=\"stats_bagde ui-widget-content ui-corner-top  ui-corner-bottom\"><div class=\"badge_number\"><div class=\"badge_label\">".Dict::S('UI:SynchroDashboard:Overview:RunTime')."</div>".AttributeDuration::FormatDuration($iTotalDuration)."</div></div>");
	$oP->add("<div class=\"stats_bagde ui-widget-content ui-corner-top  ui-corner-bottom\"><div class=\"badge_number\"><div class=\"badge_label\">".Dict::S('UI:SynchroDashboard:Overview:Replicas')."</div>$iTotalReplicas</div></div>");
	$oP->add("<div class=\"stats_bagde ui-widget-content ui-corner-top  ui-corner-bottom\"><div id=\"gauge_errors\" style=\"width:100px; height: 80px;\"></div></div>");
	$sGaugeTitle = addslashes(Dict::S('UI:SynchroDashboard:Overview:Errors'));
	$oP->add_ready_script(
<<< EOF
var g1 = new JustGage({
id: "gauge_errors",
value: $iTotalErrors,
min: 0,
max: $iTotalReplicas,
showMinMax: false,
title: '$sGaugeTitle'
});
EOF
	);
	$oP->add("<div class=\"stats_bagde ui-widget-content ui-corner-top  ui-corner-bottom\"><div id=\"gauge_warnings\" style=\"width:100px; height: 80px;\"></div></div>");
	$sGaugeTitle = addslashes(Dict::S('UI:SynchroDashboard:Overview:Warnings'));
	$oP->add_ready_script(
<<< EOF
var g2 = new JustGage({
id: "gauge_warnings",
value: $iTotalWarnings,
min: 0,
max: $iTotalReplicas,
showMinMax: false,
title: '$sGaugeTitle'
});
EOF
	);
	$oP->add("<div class=\"stats_bagde ui-widget-content ui-corner-top  ui-corner-bottom\"><div id=\"gauge_memory\" style=\"width:100px; height: 80px;\"></div></div>");
	$iMaxPHPMemoryMB = utils::ConvertToBytes(trim(ini_get('memory_limit'))) / (1024*1024);
	if ($iMaxPHPMemoryMB == 0)
	{
		$iMaxPHPMemoryMB = PHP_INT_MAX;
	}
	$iPeakMemoryMB = sprintf('%.2f', $iPeakMemory / (1024*1024));
	$sGaugeTitle = addslashes(Dict::S('UI:SynchroDashboard:Overview:PeakMemory'));
	$oP->add_ready_script(
<<< EOF
var g3 = new JustGage({
id: "gauge_memory",
value: $iPeakMemoryMB,
min: 0,
max: $iMaxPHPMemoryMB,
showMinMax: false,
title: '$sGaugeTitle',
label: 'MB'
});
EOF
	);
	$oP->add('</div>');
	$oP->table($aConfig, $aData);

	$oP->SetCurrentTab(Dict::S('UI:SynchroDashboard:Overview:DataSources'));
	$oFilter = new DBObjectSearch('SynchroDataSource');
	
	$oBlock = new DisplayBlock($oFilter, 'list', false);
	$oBlock->Display($oP, 'sds_list');
	
	$oP->output();
	
}
catch(Exception $e)
{
	require_once(APPROOT.'/setup/setuppage.class.inc.php');
	$oP = new SetupPage(Dict::S('UI:PageTitle:FatalError'));
	$oP->add("<h1>".Dict::S('UI:FatalErrorMessage')."</h1>\n");	
	$oP->error(Dict::Format('UI:Error_Details', $e->getMessage()));	
	$oP->output();

	if (MetaModel::IsLogEnabledIssue())
	{
		if (MetaModel::IsValidClass('EventIssue'))
		{
			$oLog = new EventIssue();

			$oLog->Set('message', $e->getMessage());
			$oLog->Set('userinfo', '');
			$oLog->Set('issue', 'PHP Exception');
			$oLog->Set('impact', 'Page could not be displayed');
			$oLog->Set('callstack', $e->getTrace());
			$oLog->Set('data', array());
			$oLog->DBInsertNoReload();
		}

		IssueLog::Error($e->getMessage());
	}
}