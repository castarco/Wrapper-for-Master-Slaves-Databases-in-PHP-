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
* Database optimized class for master/slave configuration
* 
* @version 1.0
* @copyright Ninedots Talent, S.L.
* @author Carles Iborra
*/
class Database
{
    const READ_ONLY     =   1;
    const READ_WRITE    =   2;
    /**
     * @var const String with pdo conector
     */
    const DSN = '%s:dbname=%s;host=%s';
    /**
     * Indicate to PDO the type of
     * database.
     *
     * @var String indicating type
     */
    protected $type = NULL;
	/**
	 * @var Array of masters.
	 */
    protected $m = NULL;
    /**
     * @var Array of slaves.
     */
    protected $s = array();
    /**
     * @var Integer of number of slaves.
     */
    protected $sLength = 0;
    /**
     * @var Array of errors.
     */
    protected $error = array();
    /**
     * @var Boolean to know if we are connected
     */
    protected $connected = FALSE;
    /**
     * @var Array indicating class and method
     */
    protected  $sWitness = NULL;
     /**
     * @var Int indicates active slave
     */
    protected  $token = 0;
    
    /**
     * Constructor of the class who set up
     * type of database and witness callback
     *
     * @author Carles Iborra
     * @param $type Type of database
     */
    public function __construct($type='mysql')
    {
        $this->type = $type;
        $this->sWitness = array($this , 'seed');
    }
    /**
     * Method to setting up database master
     *
     * @author Carles Iborra
     * @param $host string with database host
     * @param $database string with database
     * @param $user string with user of database
     * @param $password string with pasword of database
     * @return object of self class
     */
    public function setMaster($host,$database,$user,$password)
    {
		$data = new stdClass;
        
        $data->host             = $host;
        $data->database         = $database;
        $data->user             = $user;
        $data->password         = $password;

        $this->m = $data;

        return $this;
    }
    /**
     * Method for adding database slaves
     *
     * @author Carles Iborra
     * @param $host string with database host
     * @param $database string with database
     * @param $user string with user of database
     * @param $password string with pasword of database
     * @return object of self class
     */
    public function addSlave($host,$database,$user,$password)
    {
		$data = new stdClass;
        
        $data->host             = $host;
        $data->database         = $database;
        $data->user             = $user;
        $data->password         = $password;

        $this->s[] = $data;

        ++$this->sLength;

        return $this;
    }
    /**
     * Method to setting up witness callback that
     * returns a seed under criteria (ex: load balance)
     *
     * @author Carles Iborra
     * @param $witness array with class and method
     * @return object of self class
     */
    public function setWitness(Array $witness)
    {
        $this->sWitness = $witness;

        return $this;
    }
    /**
     * Method to connect to master and the selected
     * slave which is selected under witness condition
     *
     * @author Carles Iborra
     * @return object of self class
     */
    public function connect()
    {
      if(empty($this->m) OR empty($this->s)) throw new RangeException('Need almost one master and one slave!'); 
        
        try 
        {
            $this->m->db = new PDO(sprintf(self::DSN,$this->type,$this->m->database,$this->m->host),$this->m->user,$this->m->password);   
        } 
        catch (PDOException $e)
        {
            throw new ErrorException('Connection to master failed: '.$e->getMessage());
        }

        $this->token = $i = $this->witness(call_user_func($this->sWitness));
        try 
        {
                $this->s[$i]->db = new PDO(sprintf(self::DSN,$this->type,$this->s[$i]->database,$this->s[$i]->host),$this->s[$i]->user,$this->s[$i]->password);      
        } 
        catch (PDOException $e) 
        {
            throw new ErrorException('Connection to slave number '.($i+1).' failed: '.$e->getMessage());
        }

        $this->connected = TRUE;

        return $this;
    } 
    /**
     * Magic method which executes SQL sentences and return
     * the result. Switch database link under RO (read only) 
     * or RW (read and write) conditions.
     *
     * @author Carles Iborra
     * @param $sql String with SQL sentence without vars
     * @param $array Array with all vars
     * @param $force_mode String force mode to RO or RW
     * @return array or boolean 
     */
    public function prepare($sql,Array $array = array() ,$force_mode = NULL,$debug = FALSE)
    {
        if(!$this->connected) throw new ErrorException('You need to connect to database!');
        
        #Setting local vars.
        $link = NULL;

        #Detect Mode
        preg_match_all('/(SELECT|INSERT|UPDATE|DELETE|CALL|SHOW|USE)/i', $sql, $matches);

        #Select server
        if($force_mode !== NULL)
        {
            if($force_mode === 'ro')
                $link = $this->s[$this->token];
            elseif ($force_mode === 'rw')
                $link = $this->m;
        }
        if($link === NULL)
        {
            switch (strtoupper($matches[1][0]))
            {
                case 'SELECT':
                case 'SHOW':
                    $force_mode = 'ro';
                    $link = $this->s[$this->token];
                    break;
                default:
                    $force_mode = 'rw';
                    $link = $this->m;
                    break;
            }
        }
        if($debug)
        {
            $debug = new stdClass;
            $debug->mode = $force_mode;
            $debug->link = $link;
            $debug->sql = strtr(preg_replace('/(:\w+)/','"$1"',$sql),$array);

            return $debug;
        }
        #Execute sql
        try 
        {
            
            $prepared = $link->db->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
            $prepared->execute($array);
            $prepared->setFetchMode(PDO::FETCH_ASSOC);
            $response = ($force_mode === 'ro') ? $prepared->fetchAll() : TRUE;
        } 
        catch (PDOException $e) 
        {
            $this->error = $e;
            $response = FALSE;
        }

        #Send response.
        return $response;
    }
    /**
     * Alias for prepare in mode debug
     *
     * @author Carles Iborra
     * @param $sql String with SQL sentence without vars
     * @param $array Array with all vars
     * @param $force_mode String force mode to RO or RW
     * @return array or boolean 
     */
    public function debug($sql,Array $array = array() ,$force_mode = NULL,$debug = FALSE)
    {
        return $this->prepare($sql,$array,$force_mode,TRUE);
    }
    /**
     * Transforms witness recieved seed into a rotative
     * index that don't overflow array.
     *
     * @author Carles Iborra
     * @param $seed integer that changes under criteria
     * @return integer index of slaves array
     */
    protected function witness($seed)
    {
        return (int)$seed%$this->sLength;
    }
    /**
     * Generate random seed to supply no witness
     * declaration and try to emulate load balancing.
     *
     * @author Carles Iborra
     * @return integer random big number
     */
    protected function seed()
    {
        return mt_rand();
    }
    /**
     * Error controlling function
     *
     * @author Carles Iborra
     * @return Array of errors
     */
    public function error()
    {
        return $this->error;
    }
}

?>