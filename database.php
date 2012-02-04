<?php 

/**
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

    public function __construct($type='mysql')
    {
        $this->type = $type;
        $this->sWitness = array($this , 'seed');
    }
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }
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
    public function setWitness(Array $witness)
    {
        $this->sWitness = $witness;

        return $this;
    }
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
    public function prepare($sql,Array $array = array() ,$force_mode = NULL)
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
    protected function witness($seed)
    {
        return (int)$seed%$this->sLength;
    }
    protected function seed()
    {
        return mt_rand();
    }
    public function error()
    {
        return $this->error;
    }
}

?>