<?php
//
// iTop module definition file
//

SetupWebPage::AddModule(
	__FILE__, // Path to the current file, all other file names are relative to the directory containing this file
	'itop-synchro-dashboard/1.0.0',
	array(
		// Identification
		//
		'label' => 'Data Synchronization Dashboard',
		'category' => 'business',

		// Setup
		//
		'dependencies' => array(
			'itop-welcome-itil/2.0.0', // For the loading order of the menus
		),
		'mandatory' => false,
		'visible' => true,

		// Components
		//
		'datamodel' => array(
			'main.itop-synchro-dashboard.php',
			'model.itop-synchro-dashboard.php',
		),
		'webservice' => array(
			
		),
		'data.struct' => array(
			// add your 'structure' definition XML files here,
		),
		'data.sample' => array(
			// add your sample data XML files here,
		),
		
		// Documentation
		//
		'doc.manual_setup' => '', // hyperlink to manual setup documentation, if any
		'doc.more_information' => '', // hyperlink to more information, if any 

		// Default settings
		//
		'settings' => array(
			// Module specific settings go here, if any
		),
	)
);


?>
