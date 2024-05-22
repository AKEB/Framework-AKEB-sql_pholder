<?php

error_reporting(E_ALL);

class SQLTest extends PHPUnit\Framework\TestCase {
	function test_function() {
		$this->assertTrue(function_exists('sql_pholder'));
		$this->assertTrue(function_exists('sql'));

		$this->assertEquals(
			sql_pholder("SELECT * FROM `table` WHERE `id` = ? AND `name` = ?", 1, (int)2),
			"SELECT * FROM `table` WHERE `id` = '1' AND `name` = '2'"
		);

		$this->assertEquals(
			sql("SELECT * FROM `table` WHERE `id` = ? AND `name` = ?", 1, 2),
			"SELECT * FROM `table` WHERE `id` = '1' AND `name` = '2'"
		);

		$value1 = rand(0, 1000000);
		$value2 = rand(0, 1000000);
		$this->assertEquals(
			sql_pholder("SELECT * FROM `table` WHERE `key1` = ? AND `key2` = ?", $value1, $value2),
			sql("SELECT * FROM `table` WHERE `key1` = ? AND `key2` = ?", $value1, $value2)
		);
	}

	function test_scalar() {
		$this->assertEquals(
			sql("?", "test_value"),
			"'test_value'"
		);

		$this->assertEquals(
			sql("?", "test'hello"),
			"'test\'hello'"
		);

		$this->assertEquals(
			sql("`k` = ?", "test'hel;lo"),
			"`k` = 'test\'hel;lo'"
		);
	}

	function test_constant() {
		define('TEST_CONSTANT', rand(0, 1000000));
		$this->assertEquals(
			sql("key = ?#TEST_CONSTANT"),
			"key = ".constant('TEST_CONSTANT')
		);

		$this->assertEquals(
			sql("key = ?", constant('TEST_CONSTANT')),
			"key = '".constant('TEST_CONSTANT')."'"
		);

		define('TEST_CONSTANT2', 'text_'.rand(0, 1000000));
		$this->assertEquals(
			sql("key = ?#TEST_CONSTANT2"),
			"key = ".constant('TEST_CONSTANT2')
		);

		$this->assertEquals(
			sql("key = ?", constant('TEST_CONSTANT2')),
			"key = '".constant('TEST_CONSTANT2')."'"
		);
	}

	function test_array() {
		$testArray = [
			rand(0, 1000000),
			rand(0, 1000000),
			rand(0, 1000000),
			rand(0, 1000000),
		];
		$this->assertEquals(
			sql("SELECT `id`, `title` FROM `test_table` WHERE id IN ( ?@ )", $testArray),
			"SELECT `id`, `title` FROM `test_table` WHERE id IN ( '".implode("','", $testArray)."' )"
		);
	}

	function test_dict() {
		$testDict = [
			'key1' => 'value1',
			'key2' => 'value2',
			'key3' => 'value3',
		];
		$this->assertEquals(
			sql("UPDATE `test_table` SET ?%", $testDict),
			"UPDATE `test_table` SET `key1`='value1', `key2`='value2', `key3`='value3'"
		);
	}
}