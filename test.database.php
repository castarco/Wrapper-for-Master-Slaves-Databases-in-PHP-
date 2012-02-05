<?php 

/**
* This license is a legal agreement between you and the Ninedots Talent,S.L. for the use of Database class (the "Software"). By obtaining the Software you agree to comply with the terms and conditions of this license.
*
* Copyright © 2008–2012 Ninedots Talent,S.L. All rights reserved.
*
* Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
*
*   Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
*   Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
*   Neither the name of the Ninedots Talent,S.L. nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
*
* THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE. 
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