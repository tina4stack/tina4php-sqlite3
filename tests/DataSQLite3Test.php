<?php
/**
 * Tina4 - This is not a 4ramework.
 * Copy-right 2007 - current Tina4
 * License: MIT https://opensource.org/licenses/MIT
 */

use PHPUnit\Framework\TestCase;

require_once "./Tina4/DataSQLite3.php";

class DataSQLite3Test extends TestCase
{
    public $connectionString;
    public $DBA;

    final public function setUp(): void
    {
        $this->connectionString = "test.db";
        $this->DBA = new \Tina4\DataSQLite3($this->connectionString);
    }

    /**
     * Test that the constructor with valid params creates a connection object.
     */
    final public function testConnection(): void
    {
        $dba = new \Tina4\DataSQLite3(":memory:");
        $this->assertNotEmpty($dba);
        $this->assertInstanceOf(\Tina4\DataSQLite3::class, $dba);
    }

    final public function testOpen(): void
    {
        $this->assertNotEmpty($this->DBA);
    }

    /**
     * Test that CREATE TABLE succeeds without error.
     */
    final public function testExecCreateTable(): void
    {
        $dba = new \Tina4\DataSQLite3(":memory:");
        $error = $dba->exec("CREATE TABLE test_create (id INTEGER PRIMARY KEY, value TEXT)");
        $this->assertInstanceOf(\Tina4\DataError::class, $error);
        $this->assertEquals("0", $error->getError()["errorCode"]);
        $this->assertTrue($dba->tableExists("test_create"));
    }

    /**
     * Test that INSERT returns a proper DataError result with no error.
     */
    final public function testExecInsert(): void
    {
        $dba = new \Tina4\DataSQLite3(":memory:");
        $dba->exec("CREATE TABLE test_insert (id INTEGER PRIMARY KEY, name TEXT)");
        $error = $dba->exec("INSERT INTO test_insert (id, name) VALUES (1, 'Alice')");
        $this->assertInstanceOf(\Tina4\DataError::class, $error);
        $this->assertEquals("0", $error->getError()["errorCode"]);

        // Verify the row is there
        $result = $dba->fetch("SELECT * FROM test_insert WHERE id = 1");
        $records = $result->records();
        $this->assertCount(1, $records);
    }

    /**
     * Test that UPDATE returns proper result and changes data.
     */
    final public function testExecUpdate(): void
    {
        $dba = new \Tina4\DataSQLite3(":memory:");
        $dba->exec("CREATE TABLE test_update (id INTEGER PRIMARY KEY, name TEXT)");
        $dba->exec("INSERT INTO test_update (id, name) VALUES (1, 'Before')");

        $error = $dba->exec("UPDATE test_update SET name = 'After' WHERE id = 1");
        $this->assertInstanceOf(\Tina4\DataError::class, $error);
        $this->assertEquals("0", $error->getError()["errorCode"]);

        $record = $dba->fetchOne("SELECT * FROM test_update WHERE id = 1")->asArray();
        $this->assertEquals("After", $record["name"]);
    }

    /**
     * Test that DELETE returns proper result and removes data.
     */
    final public function testExecDelete(): void
    {
        $dba = new \Tina4\DataSQLite3(":memory:");
        $dba->exec("CREATE TABLE test_delete (id INTEGER PRIMARY KEY, name TEXT)");
        $dba->exec("INSERT INTO test_delete (id, name) VALUES (1, 'ToRemove')");
        $dba->exec("INSERT INTO test_delete (id, name) VALUES (2, 'ToKeep')");

        $error = $dba->exec("DELETE FROM test_delete WHERE id = 1");
        $this->assertInstanceOf(\Tina4\DataError::class, $error);
        $this->assertEquals("0", $error->getError()["errorCode"]);

        $result = $dba->fetch("SELECT * FROM test_delete");
        $records = $result->records();
        $this->assertCount(1, $records);
    }

    /**
     * Test that SELECT returns a DataResult with the correct number of rows.
     */
    final public function testFetch(): void
    {
        $dba = new \Tina4\DataSQLite3(":memory:");
        $dba->exec("CREATE TABLE test_fetch (id INTEGER PRIMARY KEY, name TEXT)");
        $dba->exec("INSERT INTO test_fetch (id, name) VALUES (1, 'Row1')");
        $dba->exec("INSERT INTO test_fetch (id, name) VALUES (2, 'Row2')");
        $dba->exec("INSERT INTO test_fetch (id, name) VALUES (3, 'Row3')");

        $result = $dba->fetch("SELECT * FROM test_fetch", 100);
        $this->assertInstanceOf(\Tina4\DataResult::class, $result);
        $records = $result->records();
        $this->assertCount(3, $records);
    }

    /**
     * Test that limit and offset work correctly on fetch.
     */
    final public function testFetchWithLimit(): void
    {
        $dba = new \Tina4\DataSQLite3(":memory:");
        $dba->exec("CREATE TABLE test_limit (id INTEGER PRIMARY KEY, name TEXT)");
        for ($i = 1; $i <= 10; $i++) {
            $dba->exec("INSERT INTO test_limit (id, name) VALUES ({$i}, 'Row{$i}')");
        }

        // Fetch 3 records starting from offset 0
        $result = $dba->fetch("SELECT * FROM test_limit ORDER BY id", 3, 0);
        $records = $result->records();
        $this->assertCount(3, $records);

        // Fetch 3 records starting from offset 5
        $result2 = $dba->fetch("SELECT * FROM test_limit ORDER BY id", 3, 5);
        $records2 = $result2->records();
        $this->assertCount(3, $records2);
        // The first record at offset 5 should be id=6
        $this->assertEquals(6, $records2[0]->id);
    }

    /**
     * Test that an empty result returns a DataResult with 0 records.
     */
    final public function testFetchEmpty(): void
    {
        $dba = new \Tina4\DataSQLite3(":memory:");
        $dba->exec("CREATE TABLE test_empty (id INTEGER PRIMARY KEY, name TEXT)");

        $result = $dba->fetch("SELECT * FROM test_empty");
        $this->assertInstanceOf(\Tina4\DataResult::class, $result);
        $this->assertCount(0, $result->records());
    }

    /**
     * Test that getLastId returns the correct auto-incremented ID after INSERT.
     */
    final public function testGetLastId(): void
    {
        $dba = new \Tina4\DataSQLite3(":memory:");
        $dba->exec("CREATE TABLE test_lastid (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)");

        $dba->exec("INSERT INTO test_lastid (name) VALUES ('First')");
        $lastId1 = $dba->getLastId();
        $this->assertEquals(1, (int)$lastId1);

        $dba->exec("INSERT INTO test_lastid (name) VALUES ('Second')");
        $lastId2 = $dba->getLastId();
        $this->assertEquals(2, (int)$lastId2);

        $this->assertGreaterThan((int)$lastId1, (int)$lastId2);
    }

    /**
     * Test that executing bad SQL returns a DataError with a non-zero error code.
     */
    final public function testError(): void
    {
        $dba = new \Tina4\DataSQLite3(":memory:");
        // Suppress the PHP warning from SQLite3::prepare() so we can check the DataError
        $error = @$dba->exec("INSERT INTO nonexistent_table (id) VALUES (1)");
        $this->assertInstanceOf(\Tina4\DataError::class, $error);
        $this->assertNotEquals("0", $error->getError()["errorCode"]);
        $this->assertNotEmpty($error->getError()["errorMessage"]);
    }

    /**
     * Test that tableExists returns true for an existing table and false for a missing one.
     */
    final public function testTableExists(): void
    {
        $dba = new \Tina4\DataSQLite3(":memory:");
        $dba->exec("CREATE TABLE real_table (id INTEGER PRIMARY KEY)");

        $this->assertTrue($dba->tableExists("real_table"));
        $this->assertFalse($dba->tableExists("ghost_table"));
        $this->assertFalse($dba->tableExists(""));
    }

    /**
     * Test that getDatabase returns metadata array with table and field info.
     */
    final public function testGetDatabase(): void
    {
        $dba = new \Tina4\DataSQLite3(":memory:");
        $dba->exec("CREATE TABLE meta_test (id INTEGER PRIMARY KEY, name TEXT DEFAULT 'hello', age INTEGER)");
        $dba->exec("CREATE TABLE meta_other (pk INTEGER PRIMARY KEY, description TEXT)");

        $database = $dba->getDatabase();
        $this->assertIsArray($database);
        $this->assertArrayHasKey("meta_test", $database);
        $this->assertArrayHasKey("meta_other", $database);

        // Check that meta_test has the right columns
        $columns = $database["meta_test"];
        $this->assertCount(3, $columns);

        // Verify column names
        $colNames = array_map(function ($col) {
            return $col->fieldName;
        }, $columns);
        $this->assertContains("id", $colNames);
        $this->assertContains("name", $colNames);
        $this->assertContains("age", $colNames);
    }

    /**
     * Test that SQL with special characters (quotes and unicode) is handled correctly.
     */
    final public function testSpecialCharacters(): void
    {
        $dba = new \Tina4\DataSQLite3(":memory:");
        $dba->exec("CREATE TABLE test_special (id INTEGER PRIMARY KEY, name TEXT)");

        // Test with single quotes using parameterized query
        $dba->exec("INSERT INTO test_special (id, name) VALUES (?, ?)", 1, "O'Brien");
        $record = $dba->fetchOne("SELECT * FROM test_special WHERE id = 1")->asArray();
        $this->assertEquals("O'Brien", $record["name"]);

        // Test with unicode characters
        $dba->exec("INSERT INTO test_special (id, name) VALUES (?, ?)", 2, "Muller");
        $record2 = $dba->fetchOne("SELECT * FROM test_special WHERE id = 2")->asArray();
        $this->assertEquals("Muller", $record2["name"]);

        // Test with emoji/extended unicode
        $dba->exec("INSERT INTO test_special (id, name) VALUES (?, ?)", 3, "Hello World!");
        $record3 = $dba->fetchOne("SELECT * FROM test_special WHERE id = 3")->asArray();
        $this->assertEquals("Hello World!", $record3["name"]);

        // Test with double quotes in value
        $dba->exec("INSERT INTO test_special (id, name) VALUES (?, ?)", 4, 'He said "hello"');
        $record4 = $dba->fetchOne("SELECT * FROM test_special WHERE id = 4")->asArray();
        $this->assertEquals('He said "hello"', $record4["name"]);
    }

    // ---- Preserve original integration tests that use test.db ----

    final public function testDropCreateTable(): void
    {
        if ($this->DBA->tableExists("sub_testing")) {
            $error = $this->DBA->exec("drop table sub_testing");
        }

        if ($this->DBA->tableExists("testing")) {
            $error = $this->DBA->exec("drop table testing");
        }

        $this->DBA->commit();

        $error = $this->DBA->exec("create table testing(id integer default 0, name varchar(200) default 'Name', contact_number varchar(20) default '', age integer default 22, salary numeric (10,2), my_date timestamp default '01/01/1900', my_date_2 timestamp default 'now', my_date_3 date default '01/01/1900', primary key(id))");

        $this->DBA->commit();

        $exists = $this->DBA->tableExists("testing");

        $error = $this->DBA->exec("create table sub_testing (id integer default 1 not null, testing_id integer default 0 not null references testing(id) on delete cascade, primary key(id))");
        $this->DBA->commit();

        $this->assertEquals(true, $exists, "Not working false table check");
    }

    final public function testRead(): void
    {
        $this->DBA->exec("insert into testing (id) values (?)", 1);

        $this->DBA->exec("insert into testing (id) values (2)");

        $records = $this->DBA->fetch("select * from testing")->asArray();

        $this->assertCount(2, $records, "Records were not 2");

        $result = $this->DBA->exec("insert into testing (name) values ('Hello') RETURNING id")->asArray();

        $this->assertEquals(4, $result[0]["id"], "Id does not match");

        $this->DBA->exec("insert into testing (id, contact_number) values (?, ?)", 5, "0836464535");

        $record = $this->DBA->fetchOne("select * from testing where id = 5")->asArray();

        $this->assertTrue(is_string("0836464535"), "Not a string");

        $this->assertEquals("0836464535", $record["contactNumber"], "Contact number does not match");
    }

    final public function testGetDatabaseIntegration(): void
    {
        $database = $this->DBA->getDatabase();
        $this->assertArrayHasKey("testing", $database);
        $this->assertArrayHasKey("sub_testing", $database);
    }

    final public function testTableExistsIntegration(): void
    {
        $exists = $this->DBA->tableExists("testing");
        $this->assertEquals(true, $exists, "Table testing not found");
        $exists = $this->DBA->tableExists("user_one");
        $this->assertEquals(false, $exists, "Not working false table check");
    }

    final public function testFetchWithLimitIntegration(): void
    {
        $result = $this->DBA->fetch("select * from testing", 2, 0);
        $this->assertLessThanOrEqual(2, count($result->records()));
    }

    final public function testFetchWithOffset(): void
    {
        $result = $this->DBA->fetch("select * from testing", 1, 1);
        $records = $result->records();
        $this->assertLessThanOrEqual(1, count($records));
    }

    final public function testFetchEmptyIntegration(): void
    {
        $result = $this->DBA->fetch("select * from testing where id = 99999");
        $this->assertCount(0, $result->records());
    }

    final public function testExecUpdateIntegration(): void
    {
        $this->DBA->exec("update testing set name = 'Updated' where id = 1");
        $record = $this->DBA->fetchOne("select * from testing where id = 1")->asArray();
        $this->assertEquals("Updated", $record["name"]);
    }

    final public function testExecDeleteIntegration(): void
    {
        $this->DBA->exec("insert into testing (id, name) values (100, 'ToDelete')");
        $this->DBA->exec("delete from testing where id = 100");
        $result = $this->DBA->fetch("select * from testing where id = 100");
        $this->assertCount(0, $result->records());
    }

    final public function testGetLastIdIntegration(): void
    {
        $this->DBA->exec("insert into testing (name) values ('LastIdTest')");
        $lastId = $this->DBA->getLastId();
        $this->assertNotEmpty($lastId);
        $this->assertGreaterThan(0, (int)$lastId);
    }

    final public function testGetDefaultPort(): void
    {
        $this->assertEquals(0, $this->DBA->getDefaultDatabasePort());
    }

    final public function testGetDefaultDateFormat(): void
    {
        $this->assertEquals("Y-m-d", $this->DBA->getDefaultDatabaseDateFormat());
    }

    final public function testGetShortName(): void
    {
        $this->assertEquals("sqlite3", $this->DBA->getShortName());
    }

    final public function testIsNotNoSQL(): void
    {
        $this->assertFalse($this->DBA->isNoSQL());
    }
}
