<?php 

/**
* @license Licence.txt
*
* Test for database.php class in phpunit.
*
* @author Carles Iborra
* @version 1.0
*/
include 'database.php';

class DbTest extends PHPUnit_Framework_TestCase
{
    /**
     * @expectedException RangeException
     */
    public function testNoSlave()
    {
        $db = new Database();
        $db
        ->setMaster('localhost','test','root','root')
        ->connect();
    }
    public function testNoConnection()
    {
        $db = new Database();
        $db
        ->setMaster('localhost','test','root','root')
        ->addSlave('localhost','test','root','root')
        ->connect();
    }

    /**
     * @expectedException PHPUnit_Framework_Error
     */
    public function testSettingWitness()
    {
        $db = new Database();
        $db
        ->setMaster('localhost','test','root','root')
        ->addSlave('localhost','test','root','root')
        ->setWitness(3)
        ->connect();
    }
    public function testPrepareSelect()
    {
        $db = new Database();
        $db
        ->setMaster('localhost','test','root','root')
        ->addSlave('localhost','test','root','root')
        ->addSlave('localhost','test','root','root')
        ->connect();

        $result = $db->prepare('SELECT * FROM user WHERE login = :name',array(':name' => 'test'));
        $this->assertInternalType('array', $result);
        $this->assertGreaterThan(0, count($result));
    }
    public function testPrepareInsert()
    {
        $db = new Database();
        $db
        ->setMaster('localhost','test','root','root')
        ->addSlave('localhost','test','root','root')
        ->addSlave('localhost','test','root','root')
        ->connect();

        $result = $db->prepare('INSERT INTO user (login) VALUES (:name)',array(':name' => 'test'));
        $this->assertInternalType('bool', $result);
        $this->assertTrue($result);
    }
    public function testPrepareDebug()
    {
        $db = new Database();
        $db
        ->setMaster('localhost','test','root','root')
        ->addSlave('localhost','test','root','root')
        ->addSlave('localhost','test','root','root')
        ->connect();

        $result = $db->debug('SELECT * FROM test WHERE login = :name',array(':name' => 'test'));
        
        $this->assertInstanceOf('stdClass', $result);
        $this->assertEquals($result->mode, 'ro');
    }

}

?>