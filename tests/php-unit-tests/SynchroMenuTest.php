<?php

namespace Combodo\iTop\Test\SynchroDashboard;

use ApplicationMenu;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use MetaModel;
use SynchroDataSource;
use UserRights;


/**
 * @runTestsInSeparateProcesses
 * @backupGlobals disabled
 */
class SynchroMenuTest extends ItopDataTestCase {
	protected function setUp(): void {
		parent::setUp();
	}

	public function MenuAccessProvider(){
		return [
			'Administrator' => [ 'sProfileName' => 'Administrator' ],
			'Configuration Manager' => [ 'sProfileName' => 'Configuration Manager' ],
			'Support Agent' => [ 'sProfileName' => 'Support Agent' ],
			/*'ProfileWithSynchroData' => [ 'sProfileName' => 'ProfileWithSynchroData' ],
			'ProfileWithAdminTools' => [ 'sProfileName' => 'ProfileWithAdminTools' ],
			'ProfileWithoutSynchro' => [ 'sProfileName' => 'ProfileWithoutSynchro' ],*/
		];
	}

	/*private static function CleanupEnv(string $sEnv){
		$aFilesToCleanup = [
			\utils::GetConfigFilePath($sEnv),
			APPROOT . "/conf/$sEnv",
			APPROOT . "/data/cache-$sEnv",
			APPROOT . "/data/env-$sEnv",
			APPROOT . "/data/datamodel-$sEnv-with-delta.xml",
			APPROOT . "/data/datamodel-$sEnv.xml",
			APPROOT . "/data/$sEnv.delta.xml",
		];

		foreach ($aFilesToCleanup as $sPath){
			if (is_file($sPath)){
				@unlink($sPath);
			}

			if (is_dir($sPath)){
				self::CleanupDir($sPath);
			}
		}
	}

	private static function CleanupDir(string $sDir){
		if (! is_dir($sDir)){
			return;
		}

		foreach (glob("$sDir" . DIRECTORY_SEPARATOR . '*') as $sPath){
			if (is_dir($sPath)){
				self::CleanupDir($sPath);
			}

			if (is_file($sPath)){
				@unlink($sPath);
			}
		}

		@rmdir($sDir);
	}

	private function CompileDeltaIfRequired(string $sEnv){
		$sConfigFilePath = \utils::GetConfigFilePath($sEnv);

		if (! is_file($sConfigFilePath)) {
			copy(__DIR__. "/production.delta.xml", APPROOT . "/data/$sEnv.delta.xml");

			//copy conf from production to phpunit context
			$sDirPath = dirname($sConfigFilePath);
			if (!is_dir($sDirPath)) {
				mkdir($sDirPath);
			}
			$oConfig = new Config(\utils::GetConfigFilePath());
			$oConfig->WriteToFile($sConfigFilePath);

			$oConfig = new Config($sConfigFilePath);
			$oConfig->WriteToFile();
			$oRunTimeEnvironment = new RunTimeEnvironment($sEnv);
			$oRunTimeEnvironment->CompileFrom(\utils::GetCurrentEnvironment());
			$oConfig->WriteToFile();
		}

		//$sConfigFile = APPCONF.\utils::GetCurrentEnvironment().'/'.ITOP_CONFIG_FILE;
		MetaModel::Startup($sConfigFilePath, false, true, false, $sEnv);
	//}*/

	/**
	 * @dataProvider MenuAccessProvider
	 */
	public function testMenuAccess($sProfileName){
		//$this->CompileDeltaIfRequired("superuser");

		$oUser = $this->CreateContactlessUserViaProfile($sProfileName);
		if (is_null($oUser)){
			$this->markTestSkipped("$sProfileName unknown profile: test skipped");
			return;
		}

		$_SESSION = [];
		$sLogin = $oUser->Get('login');
		if (! \UserRights::Login($sLogin)){
			throw new \Exception("Login via $sLogin failed!");
		}
		echo "User connected: " . \UserRights::GetUser() . "\n";

		$bExpectedMenuVisible = UserRights::IsActionAllowed(SynchroDataSource::class, UR_ACTION_READ)===UR_ALLOWED_YES;
		$sMessage = "User connected: " . \UserRights::GetUser();
		ApplicationMenu::ReflectionMenuNodes();
		$oMenuNode = ApplicationMenu::GetMenuNode(ApplicationMenu::GetMenuIndexById('DataSourcesDashboard'));
		$bMenuVisible = (false === is_null($oMenuNode)) && $oMenuNode->IsEnabled();
		$this->assertEquals($bExpectedMenuVisible, $bMenuVisible, $sMessage);
	}

	private function CreateContactlessUserViaProfile($sProfileName){
		$oProfile = MetaModel::GetObjectFromOQL("SELECT URP_Profiles WHERE name = :name", array('name' => $sProfileName), true);

		if (!is_object($oProfile)){
			return null;
		}

		$sUid = uniqid();
		return $this->CreateContactlessUser("$sProfileName-$sUid", $oProfile->GetKey(),  "Iuytrez9876543ç_è-(");
	}
}
