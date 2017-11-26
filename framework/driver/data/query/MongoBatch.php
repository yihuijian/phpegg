<?php
namespace framework\driver\data\query;

use MongoDB\Driver\BulkWrite;

class MongoBatch
{
    protected $ns;
    protected $bulk;
    protected $manager;
    
    public function __construct($manager, $db, $collection, $options = null)
    {
        $this->manager = $manager;
        $this->ns = "$db.$collection";
        $this->bulk = new BulkWrite($options);
    }
    
    public function set($id, $data)
    {
        $this->bulk->update(['_id' => $id], $data, ['upsert' => true]);
        return $this;
    }
    
    public function insert($data)
    {
        $this->bulk->insert($data);
        return $this;
    }
    
    public function update($filter, $data, $options = null)
    {
        if (!is_array($filter)) {
            $filter = ['_id' => $filter];
        }
        $this->bulk->update($filter, $data, $options);
        return $this;
    }
    
    public function delete($filter, $options = null)
    {
        if (!is_array($filter)) {
            $filter = ['_id' => $filter];
        }
        $this->bulk->delete($filter, $options);
        return $this;
    }

    public function call()
    {
        return $this->manager->executeBulkWrite($this->ns, $this->bulk);
    }
}
