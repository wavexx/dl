<?php
// database handling functions
require_once("confwrap.php");

require_once(__DIR__."/../../vendor/autoload.php");

final class DBConnection {
    protected static $instance = null;
    protected $conn;
    protected $queries;
    
    /**
     * 
     * @return DBConnection
     */
    public static function getInstance() {
        if (!isset(static::$instance)) {
            static::$instance = new static;
        }
        return static::$instance;
    }
    
    /**
     * protected constructor for Singleton
     */
    protected function __construct() {
        GLOBAL $dsn;
        $this->conn = \Doctrine\DBAL\DriverManager::getConnection([ 'url' => $dsn ], new \Doctrine\DBAL\Configuration());
        
        $this->checkDBVersion();
    }
    
    /**
     * Checks the database version using the config table, and the parameter "version"
     */
    protected function checkDBVersion() {
        GLOBAL $schemaVersion;
        $version = $this->conn->createQueryBuilder()->select('value')
                                                     ->from('config')
                                                     ->where('name = ?')
                                                     ->setParameter(0,'version')
                                                     ->execute()
                                                     ->fetchColumn();
        if(version_compare($version, $schemaVersion, "!=")) {
          die("database requires schema upgrade\n");
        }
    }
    
    
    /**
     * Singletons may not be cloned
     */
    protected function __clone() { }
    
    /**
     * 
     * @param string $id
     * @param integer $user_id
     * @param string $name
     * @param string $path
     * @param integer $size
     * @param string $cmt
     * @param string|null $pass_ph
     * @param boolean $pass_send
     * @param integer $time
     * @param integer $expire
     * @param integer $last_time
     * @param integer $expire_dln
     * @param string|null $notify_email
     * @param string|null $sent_email
     * @param string  $locale
     * @param string|null $from_grant
     * @return boolean
     */
    public function generateTicket($id,$user_id,$name,$path,$size,$cmt,$pass_ph,$pass_send,$time,$expire,$last_time,$expire_dln,$notify_email,$sent_email,$locale,$from_grant = null) {
        return (1===$this->conn->createQueryBuilder()->insert("ticket")
                                                        ->values([ 'id'        => '?',
                                                                    'user_id'   => '?',
                                                                    'name'      => '?',
                                                                    'path'      => '?',
                                                                    'size'      => '?',
                                                                    'cmt'       => '?',
                                                                    'pass_ph'   => '?',
                                                                    'pass_send' => '?',
                                                                    'time'      => '?',
                                                                    'expire'    => '?',
                                                                    'last_time' => '?',
                                                                    'expire_dln'=> '?',
                                                                    'notify_email'=>'?',
                                                                    'sent_email' => '?',
                                                                    'locale'     => '?',
                                                                    'from_grant' => '?'
                                                        ])
                                                        ->setParameter(0,$id)
                                                        ->setParameter(1,$user_id)
                                                        ->setParameter(2,$name)
                                                        ->setParameter(3,$path)
                                                        ->setParameter(4,$size)
                                                        ->setParameter(5,$cmt)
                                                        ->setParameter(6,$pass_ph)
                                                        ->setParameter(7,$pass_send)
                                                        ->setParameter(8,$time)
                                                        ->setParameter(9,$expire)
                                                        ->setParameter(10,$last_time)
                                                        ->setParameter(11,$expire_dln)
                                                        ->setParameter(12,$notify_email)
                                                        ->setParameter(13,$sent_email)
                                                        ->setParameter(14,$locale)
                                                        ->setParameter(15,$from_grant)
                                                        ->execute());
    }
    
    /**
     * 
     * @param string $id
     * @return array|null
     */
    public function getTicketById($id) {
        return $this->conn->createQueryBuilder()->select("*")
                                                    ->from("ticket")
                                                    ->where("id = ?")
                                                    ->setParameter(0,$id)
                                                    ->execute()
                                                    ->fetch();
    }
    
    /**
     * 
     * @param string $id
     * @return array|null
     */
    public function getGrantById($id) {
        return $this->conn->createQueryBuilder()->select("*")
                                                    ->from("grant")
                                                    ->where("id = ?")
                                                    ->setParameter(0,$id)
                                                    ->execute()
                                                    ->fetch();
    }
    
    
    /**
     * 
     * @param string $id
     * @return boolean
     */
    public function purgeTicketById($id) {
        return (1===$this->conn->createQueryBuilder()->delete("ticket")
                                                        ->where("id = ?")
                                                        ->setParameter(0,$id)
                                                        ->execute() );
    }
    
    /**
     * 
     * @param string $id
     * @return boolean
     */
    public function purgeGrantById($id) {
        return (1===$this->conn->createQueryBuilder()->delete("grant")
                                                        ->where("id = ?")
                                                        ->setParameter(0,$id)
                                                        ->execute() );
    }
    
    /**
     * 
     * @param integer $now
     * @param integer $limit
     * @return array
     */
    public function getTicketsToPurge($now,$limit) {
        $queryBuilder = $this->conn->createQueryBuilder();
        return $queryBuilder->select("*")->from("ticket")
                                                        ->where($queryBuilder->expr()->orX(
                                                                $queryBuilder->expr()->lt('(expire + time)','?'),
                                                                $queryBuilder->expr()->lt('(last_stamp + last_time)','?'),
                                                                $queryBuilder->expr()->lte('expire_dln','downloads')))
                                                        ->setMaxResults($limit)
                                                        ->setParameter(0,$now)
                                                        ->setParameter(1,$now)
                                                        ->execute()->fetchAll();
    }
    
    /**
     * 
     * @param integer $now
     * @param integer $limit
     * @return array
     */
    public function getGrantsToPurge($now,$limit) {
        $queryBuilder = $this->conn->createQueryBuilder();
        return $queryBuilder->select("*")->from("grant")
                                                        ->where(
                                                            $queryBuilder->expr()->orX(
                                                                $queryBuilder->expr()->lt('(grant_expire + time)','?'),
                                                                $queryBuilder->expr()->lt('(last_stamp + grant_last_time)','?'),
                                                                $queryBuilder->expr()->lte('grant_expire_uln','uploads')))
                                                        ->setMaxResults($limit)
                                                        ->setParameter(0,$now)
                                                        ->setParameter(1,$now)
                                                        ->execute()
                                                        ->fetchAll();
    }
    
    /**
     * 
     * @param string $name
     * @return array|null
     */
    public function getRoleByName($name) {
        return $this->conn->createQueryBuilder()->select("*")
                                                    ->from("role")
                                                    ->where("name = ?")
                                                    ->setParameter(0,$name)
                                                    ->execute()
                                                    ->fetch();
    }
    
    /**
     * 
     * @param string $user
     * @param string $password
     * @param integer $role_id
     * @param string $email
     * @return boolean
     */
    public function createUser($user,$password,$role_id,$email) {
        return (1===$this->conn->createQueryBuilder()->insert("user")
                                                        ->values(['name' => '?', 
                                                                'pass_ph' => '?', 
                                                                'role_id' => '?',
                                                                'email' => '?']  )
                                                        ->setParameter(0,$user)
                                                        ->setParameter(1,$password)
                                                        ->setParameter(2,$role_id)
                                                        ->setParameter(3,$email)
                                                        ->execute());
    }
    
    /**
     * 
     * @param string $user
     * @return boolean
     */
    public function deleteUser($user) {
        return (1===$this->conn->createQueryBuilder()->delete("user")
                                                        ->where("name = ?")
                                                        ->setParameter(0,$user)
                                                        ->execute() );
    }

    /**
     * 
     * @param string $user
     * @return NULL|boolean
     */
    public function userIsAdmin($user) {
        $result = $this->conn->createQueryBuilder()->select("u.name","r.admin")
                                                   ->from('user', 'u')
                                                   ->innerJoin('u','role','r','u.role_id = r.id')
                                                   ->where('u.name = ?')
                                                   ->setParameter(0,$user)
                                                   ->execute()
                                                   ->fetch();
       if (!$result) {
           return null;
       }
       return $result[0]['admin'];
    }
    
    /**
     * 
     * @param string $user
     * @return array|null
     */
    public function getUserByName($user) {
        return $this->conn->createQueryBuilder()->select("u.id","u.name","u.pass_ph","admin","r.admin", "u.email")
                                                    ->from('user', 'u')
                                                    ->innerJoin('u','role','r','u.role_id = r.id')
                                                    ->where('u.name = ?')
                                                    ->setParameter(0,$user)
                                                    ->execute()
                                                    ->fetch();
    }
    
    /**
     *
     * @param integer $user
     * @return array|null
     */
    public function getUserById($id) {
        return $this->conn->createQueryBuilder()->select("u.id","u.name","u.pass_ph","admin","r.admin", "u.email")
                                                            ->from('user', 'u')
                                                            ->innerJoin('u','role','r','u.role_id = r.id')
                                                            ->where('u.id = ?')
                                                            ->setParameter(0,$id)
                                                            ->execute()
                                                            ->fetch();
    }
    
    /**
     * 
     * @return array
     */
    public function getAllUsers() {
        return $this->conn->createQueryBuilder()->select("u.id","u.name","u.pass_ph","r.admin", "u.email")
                                                    ->from('user', 'u')
                                                    ->innerJoin('u','role','r','u.role_id = r.id')
                                                    ->execute()
                                                    ->fetchAll();
    }
    
    /**
     * 
     * @return array
     */
    public function getAllUsersIncludingStats() {
        $sql = <<<EOF
        SELECT u.name, admin, t.count as tickets, g.count as grants, t.size
        FROM "user" u
        LEFT JOIN role r ON r.id = u.role_id
        LEFT JOIN (
        SELECT u.id AS id, count(t.id) as count, sum(t.size) as size
        FROM "user" u
        LEFT JOIN ticket t ON t.user_id = u.id
        GROUP BY u.id
        ) t ON t.id = u.id
        LEFT JOIN (
        SELECT u.id AS id, count(g.id) as count
        FROM "user" u
        LEFT JOIN "grant" g ON g.user_id = u.id
        GROUP BY u.id
        ) g ON g.id = u.id
        ORDER BY u.name
EOF;
        return $this->conn->executeQuery($sql);
    }
    

    /**
     * 
     * @return boolean
     */
    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }
    
    /**
     *
     * @return boolean
     */
    public function commit() {
        return $this->conn->commit();
    }
    
    /**
     *
     * @return boolean
     */
    public function rollBack() {
        return $this->conn->rollBack();
    }
    
    /**
     *
     * @param string $id
     * @param integer $now
     * @param integer $downloadCount
     * @return boolean
     */
    public function updateGrantUsage($id,$now,$updateCount) {
        return (1===$this->conn->createQueryBuilder()->update("grant")
                                                    ->set('last_stamp','?')
                                                    ->set('uploads', '(uploads + ?)')
                                                    ->where('id = ?')
                                                    ->setParameter(0,$now)
                                                    ->setParameter(1,$updateCount)
                                                    ->setParameter(2,$id)
                                                    ->execute());
    }
    
    /**
     * 
     * @param string $id
     * @param integer $now
     * @param integer $downloadCount
     * @return boolean
     */
    public function updateTicketUsage($id,$now,$downloadCount) {
        return (1===$this->conn->createQueryBuilder()->update("ticket")
                                                    ->set('last_stamp','?')
                                                    ->set('downloads', '(downloads + ?)')
                                                    ->where('id = ?')
                                                    ->setParameter(0,$now)
                                                    ->setParameter(1,$downloadCount)
                                                    ->setParameter(2,$id)
                                                    ->execute());
        
    }
    
    /**
     * 
     * @param string $id
     * @param array $values
     * @return boolean
     */
    public function updateGrant($id,$values) {
        $q = $this->conn->createQueryBuilder()->update("grant");
        
        $fields = array_keys($values);
        
        foreach($fields as $f) {
            $q = $q->set($f,'?');
        }
        for($i=0;$i < count($fields);$i++) {
            $q = $q->setParameter($i,$values[$fields[$i]]);
        }
        return (1===$q->where('id = ?')
                    ->setParameter(count($values),$id)
                    ->execute());
    }

    /**
     * Updates user Information based on array $values
     * @param string $user
     * @param array $values
     * @return boolean
     */
    public function updateUser($user,$values) {
        $q = $this->conn->createQueryBuilder()->update("user");
        
        $fields = array_keys($values);
        
        //TODO: Sanity check here - check if all passed columns are valid
        
        foreach($fields as $f) {
            $q = $q->set($f,'?');
        }
        for($i=0;$i < count($fields);$i++) {
            $q = $q->setParameter($i,$values[$fields[$i]]);
        }
        return (1===$q->where('name = ?')
            ->setParameter(count($values),$user)
            ->execute());
    }
    
    /**
     * Updates user Information based on array $values
     * @param string $user
     * @param array $values
     * @return boolean
     */
    public function updateTicket($id,$values) {
        $q = $this->conn->createQueryBuilder()->update("ticket");
        
        $fields = array_keys($values);
        foreach($fields as $f) {
            $q = $q->set($f,'?');
        }
        for($i=0;$i < count($fields);$i++) {
            $q = $q->setParameter($i,$values[$fields[$i]]);
        }
        return (1===$q->where('id = ?')
            ->setParameter(count($values),$id)
            ->execute());
    }
    
    
    public function createGrant($id,$user_id,$grant_expire,$grant_last_time,$grant_expire_dln,$cmt,$pass_ph,$pass_send,$time,$expire,$last_time,$expire_dln, $notify_email, $sent_email, $locale) {
        return (1===$this->conn->createQueryBuilder()->insert("grant")
                                                        ->values(['id'               => '?',
                                                                  'user_id'          => '?',
                                                                  'grant_expire'     => '?',
                                                                  'grant_last_time'  => '?',
                                                                  'grant_expire_uln' => '?',
                                                                  'cmt'              => '?',
                                                                  'pass_ph'          => '?',
                                                                  'pass_send'        => '?',
                                                                  'time'             => '?',
                                                                  'expire'           => '?',
                                                                  'last_time'        => '?',
                                                                  'expire_dln'       => '?',
                                                                  'notify_email'     => '?',
                                                                  'sent_email'       => '?',
                                                                  'locale'           => '?'
                                                            ])
                                                        ->setParameter(0,$id)
                                                        ->setParameter(1,$user_id)
                                                        ->setParameter(2,$grant_expire)
                                                        ->setParameter(3,$grant_last_time)
                                                        ->setParameter(4,$grant_expire_uln)
                                                        ->setParameter(5,$cmt)
                                                        ->setParameter(6,$pass_ph)
                                                        ->setParameter(7,$pass_send)
                                                        ->setParameter(8,$time)
                                                        ->setParameter(9,$expire)
                                                        ->setParameter(10,$last_time)
                                                        ->setParameter(11,$expire_dln)
                                                        ->setParameter(12,$notify_email)
                                                        ->setParameter(13,$sent_email)
                                                        ->setParameter(14,$locale)
                                                        ->execute());
    }
    
    /**
     * 
     * @param string $user_id
     * @return array
     */
    public function getActiveGrantsByUser($user_id) {
        return $this->conn->createQueryBuilder()->select("*")
                                                ->from("grant")
                                                ->where("user_id = ?")
                                                ->orderBy('time','DESC')
                                                ->setParameter(0,$user_id)
                                                ->execute()
                                                ->fetchAll();
    }
    
    public function getAllActiveGrants() {
        $sql = 'SELECT g.*, u.name AS "user" FROM "grant" g'
            . ' LEFT JOIN "user" u ON u.id = g.user_id'
            . ' ORDER BY time DESC';
        return $this->conn->executeQuery($sql);
    }
    
    public function getActiveTicketsForUser($user_id) {
        $queryBuilder = $this->conn->createQueryBuilder();
        return $queryBuilder->select("*")
                            ->from("ticket")
                            ->where(
                                $queryBuilder->expr()->andX($queryBuilder->expr()->eq('user_id','?'),
                                                            $queryBuilder->expr()->isNull('from_grant') ) )
                            ->orderBy('time','DESC')
                            ->setParameter(0,$user_id)
                            ->execute()
                            ->fetchAll();
    }
    
    public function getReceivedFilesForUser($user_id) {
        $sql = 'SELECT t.*, g.cmt AS grant_cmt FROM ticket t'
             . ' LEFT JOIN "grant" g ON g.id = t.from_grant'
             . ' WHERE t.user_id = ' . $user_id
             . ' AND t.from_grant IS NOT NULL'
             . ' ORDER BY t.time DESC';
        return $this->conn->executeQuery($sql);
    }
    
    public function getAllActiveTickets() {
        $sql = 'SELECT t.*, u.name AS "user", t.from_grant FROM ticket t'
             . ' LEFT JOIN "user" u ON u.id = t.user_id'
             . ' ORDER BY time DESC';
        return $this->conn->executeQuery($sql);
    }
    
    
}
