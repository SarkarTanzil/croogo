<?php

App::uses('MigrationVersion', 'Migrations.Lib');
App::uses('CroogoPlugin', 'Extensions.Lib');

class CroogoPluginTest extends CakeTestCase {

/**
 * CroogoPlugin class
 * @var CroogoPlugin 
 */	
	public $CroogoPlugin;

	public function setUp() {
		parent::setUp();
		App::build(array(
			'Plugin' => array(CakePlugin::path('Extensions') . 'Test' . DS . 'test_app' . DS . 'Plugin' . DS),
				), App::PREPEND);

		$this->CroogoPlugin = new CroogoPlugin();

		$this->_mapping = array(
			1346748762 => array(
				'version' => 1346748762,
				'name' => '1346748762_first',
				'class' => 'First',
				'type' => 'app',
				'migrated' => '2012-09-04 10:52:42'
			),
			1346748933 => array(
				'version' => 1346748933,
				'name' => '1346748933_addstatus',
				'class' => 'AddStatus',
				'type' => 'app',
				'migrated' => '2012-09-04 10:55:33'
			)
		);
	}

	public function tearDown() {
		parent::tearDown();
		unset($this->CroogoPlugin);
		$this->CroogoPlugin = new CroogoPlugin();
	}

	public function testGetDataPluginNotActive() {
		$actives = Configure::read('Hook.bootstraps');
		Configure::write('Hook.bootstraps', '');

		$suppliers = $this->CroogoPlugin->getData('Suppliers');

		$needed = array(
			'name' => 'Suppliers',
			'description' => 'Suppliers plugin',
			'active' => false,
			'needMigration' => false
		);
		$this->assertEquals($needed, $suppliers);

		Configure::write('Hook.bootstraps', $actives);
	}

	public function testGetDataPluginActive() {
		$actives = Configure::read('Hook.bootstraps');
		Configure::write('Hook.bootstraps', 'suppliers');
		
		$migrationVersion = $this->getMock('MigrationVersion');
		$croogoPlugin = new CroogoPlugin($migrationVersion);

		$suppliers = $croogoPlugin->getData('Suppliers');
		
		$needed = array(
			'name' => 'Suppliers',
			'description' => 'Suppliers plugin',
			'active' => true,
			'needMigration' => false
		);
		$this->assertEquals($needed, $suppliers);

		Configure::write('Hook.bootstraps', $actives);
	}

	public function testGetDataPluginNotExists() {
		$data = $this->CroogoPlugin->getData('NotARealPlugin');
		$this->assertEquals(false, $data);
	}

	public function testGetDataWithEmptyJson() {
		$data = $this->CroogoPlugin->getData('EmptyJson');
		$this->assertEquals(array(), $data);
	}
	
	
	public function testNeedMigrationPluginNotExists() {
		$migrationVersion = $this->getMock('MigrationVersion');
		$migrationVersion->expects($this->any())
				->method('getMapping')
				->will($this->returnValue(false));
		$croogoPlugin = new CroogoPlugin($migrationVersion);
		$this->assertEquals(false, $croogoPlugin->needMigration('Anything', true));
	}

	public function testNeedMigrationPluginNotActive() {
		$croogoPlugin = new CroogoPlugin();
		$this->assertEquals(false, $croogoPlugin->needMigration('Anything', false));
	}
	
	public function testNeedMigrationPluginNoMigration() {
		$migrationVersion = $this->getMock('MigrationVersion');
		$migrationVersion->expects($this->any())
				->method('getMapping')
				->will($this->returnValue($this->_mapping));
		$migrationVersion->expects($this->any())
				->method('getVersion')
				->will($this->returnValue(1346748933));
		$croogoPlugin = new CroogoPlugin($migrationVersion);
		$this->assertEquals(false, $croogoPlugin->needMigration('app', true));
	}

	public function testNeedMigrationPluginWithMigration() {
		$migrationVersion = $this->getMock('MigrationVersion');
		$migrationVersion->expects($this->any())
				->method('getMapping')
				->will($this->returnValue($this->_mapping));
		$migrationVersion->expects($this->any())
				->method('getVersion')
				->will($this->returnValue(1346748762));
		$croogoPlugin = new CroogoPlugin($migrationVersion);
		$this->assertEquals(true, $croogoPlugin->needMigration('app', true));
	}
	
	public function testMigratePluginNotNeedMigration() {
		$actives = Configure::read('Hook.bootstraps');
		Configure::write('Hook.bootstraps', 'Suppliers');

		$migrationVersion = $this->getMock('MigrationVersion');
		$migrationVersion->expects($this->any())
				->method('getMapping')
				->will($this->returnValue($this->_mapping));
		$migrationVersion->expects($this->any())
				->method('getVersion')
				->will($this->returnValue(1346748933));
		$croogoPlugin = new CroogoPlugin($migrationVersion);
		
		$this->assertEquals(false, $croogoPlugin->migrate('Suppliers'));
		
		Configure::read('Hook.bootstraps', $actives);
	}
	
	public function testMigratePluginWithMigration() {
		$actives = Configure::read('Hook.bootstraps');
		Configure::write('Hook.bootstraps', 'Suppliers');

		$migrationVersion = $this->getMock('MigrationVersion');
		$migrationVersion->expects($this->any())
				->method('getMapping')
				->will($this->returnValue($this->_mapping));
		$migrationVersion->expects($this->any())
				->method('getVersion')
				->will($this->returnValue(1346748762));
		$migrationVersion->expects($this->any())
				->method('run')
				->with($this->logicalAnd($this->arrayHasKey('version'), $this->arrayHasKey('type')))
				->will($this->returnValue(true));
		
		$croogoPlugin = new CroogoPlugin($migrationVersion);
		
		$this->assertEquals(true, $croogoPlugin->migrate('Suppliers'));
		
		Configure::read('Hook.bootstraps', $actives);
	}
	
	public function testMigratePluginWithMigrationError() {
		$actives = Configure::read('Hook.bootstraps');
		Configure::write('Hook.bootstraps', 'Suppliers');

		$migrationVersion = $this->getMock('MigrationVersion');
		$migrationVersion->expects($this->any())
				->method('getMapping')
				->will($this->returnValue($this->_mapping));
		$migrationVersion->expects($this->any())
				->method('getVersion')
				->will($this->returnValue(1346748762));
		$migrationVersion->expects($this->any())
				->method('run')
				->will($this->returnValue(false));
		
		$croogoPlugin = new CroogoPlugin($migrationVersion);
		
		$this->assertEquals(false, $croogoPlugin->migrate('Suppliers'));
		
		Configure::read('Hook.bootstraps', $actives);
	}

}
