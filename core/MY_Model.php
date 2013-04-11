<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * MY_Model
 * 
 * Extends Codeigniter's CI_Model to emulate Ruby on Rails ActiveRecord class.
 * 
 * @author craigphares
 */
class MY_Model extends CI_Model {
	
	protected $db_conn; // CI database connection
	protected $table_name; // Inferred database table name
	protected $columns = array(); // Columns to query
	protected $primary_key = 'id'; // Table primary key
	
	// Relationship arrays.
	protected $belongs_to = array(); // Has one model that it belongs to.
	protected $has_many = array(); // Has many models.
	protected $relationships = array(); // Relationships to be included
	
	// Callbacks for creation.
	protected $before_validation = array();
	protected $after_validation = array();
	protected $before_save = array();
	protected $around_save = array();
	protected $before_create = array();
	protected $around_create = array();
	protected $after_create = array();
	protected $after_save = array();
	
	// Callbacks for updating.
	protected $before_update = array();
	protected $around_update = array();
	protected $after_update = array();
	
	// Callbacks for destoying.
	protected $before_destroy = array();
	protected $around_destroy = array();
	protected $after_destroy = array();
	
	// Callbacks for initializing and finding.
	protected $after_initialize = array();
	protected $after_find = array();
	
	
	
	public function __construct()
	{
		parent::__construct();
		
		// Load the inflector class for pluralization.
		$this->load->helper('inflector');
		
		// Save the database connection.
		$this->_load_db_conn();
		
		// Determine the database table name.
		$this->_compute_table_name();
	}
	
	/**
	 * Find all rows in the model table.
	 * 
	 * @return array
	 */
	public function all()
	{
		$result = array();
		$query = $this->db_conn->get($this->table_name);
		foreach ($query->result() as $row)
		{
			$record = $this->_parse_row($row);
			
			// Trigger after callbacks.
			$record->trigger('after_initialize', $record);
			$record->trigger('after_find', $record);
			
			array_push($result, $record);
		}
		return $result;
	}
	
	/**
	 * Get a single row from the table from the primary key.
	 * 
	 * @param integer $id
	 * @return MY_Model|boolean
	 */
	public function find($id)
	{
		$this->db_conn->where($this->primary_key, $id);
		$query = $this->db_conn->get($this->table_name);
		foreach ($query->result() as $row)
		{
			$record = $this->_parse_row($row);
			
			// Trigger after callbacks.
			$record->trigger('after_initialize', $record);
			$record->trigger('after_find', $record);
			
			return $record;
		}
		return FALSE;
	}
	
	/**
	 * Find all rows where conditions are met.
	 * 
	 * @param array $conditions
	 * @return array
	 */
	public function where($conditions)
	{
		$result = array();
		$query = $this->db_conn->get_where($this->table_name, $conditions);
		foreach ($query->result() as $row)
		{
			$record = $this->_parse_row($row);
			
			// Trigger after callbacks.
			$record->trigger('after_initialize', $record);
			$record->trigger('after_find', $record);
			
			array_push($result, $record);
		}
		return $result;
	}
	
	/**
	 * Create a new model with default attributes.
	 * 
	 * @param array $data
	 * @return MY_Model
	 */
	public function new_model($data = array())
	{
		$data = $this->_filter_attributes($data);
		$class_name = get_class($this);
		$record = new $class_name();	
		foreach ($this->columns as $column)
		{
			$record->{$column} = $data[$column];
		}
		
		// Trigger after callbacks.
		$record->trigger('after_initialize', $record);
		
		return $record;
	}
	
	/**
	 * Insert a record in the table.
	 * 
	 * @return MY_Model|boolean
	 */
	public function save()
	{
		$data = array();
		foreach ($this->columns as $column)
		{
			$data[$column] = $this->{$column};
		}
		$data['created_at'] = date('Y-m-d H:i:s');
		$data['updated_at'] = date('Y-m-d H:i:s');	

		// Trigger before callbacks.
		
		$this->trigger('before_save', $this);
		$this->trigger('around_save', $this);
		$this->trigger('before_create', $this);
		$this->trigger('around_create', $this);
		
		
		$result = $this->db_conn->insert($this->table_name, $data);
		$this->{$this->primary_key} = $this->db_conn->insert_id();
		foreach ($this->columns as $column)
		{
			$this->{$column} = $data[$column];
		}
		$this->created_at = $data['created_at'];
		$this->updated_at = $data['updated_at'];
		
		// Trigger after callbacks.
		$this->trigger('after_create', $this);		
		$this->trigger('after_save', $this);		
		
		if ($result) return $this;
		return FALSE;
	}
	
	/**
	 * Update a record in the table.
	 * 
	 * @param array $data
	 * @return MY_Model|boolean
	 */
	public function update_attributes($data = array())
	{
		$data = $this->_filter_attributes($data);
		$data['updated_at'] = date('Y-m-d H:i:s');
		$this->db_conn->where($this->primary_key, $this->{$this->primary_key});
		
		// Trigger before callbacks.
		$this->trigger('before_save', $this);
		$this->trigger('around_save', $this);
		$this->trigger('before_update', $this);
		$this->trigger('around_update', $this);
		
		$result = $this->db_conn->update($this->table_name, $data);
		foreach ($this->columns as $column)
		{
			$this->{$column} = $data[$column];
		}
		$this->updated_at = $data['updated_at'];
		
		// Trigger after callbacks.
		$this->trigger('after_update', $this);
		$this->trigger('after_save', $this);
		
		if ($result) return $this;
		return FALSE;
	}
	
	/**
	 * Delete a row from the table.
	 */
	public function destroy()
	{
		$this->db_conn->where($this->primary_key, $this->{$this->primary_key});

		// Trigger before callbacks.
		$this->trigger('before_destroy', $this);
		$this->trigger('around_destroy', $this);
		
		$this->db_conn->delete($this->table_name);

		// Trigger after callbacks.
		$this->trigger('after_destroy', $this);
	}
	
	/**
	 * Include a relationship
	 */
	public function includes($relationship)
	{
		$this->relationships[] = $relationship;
		if ( ! in_array('_relate', $this->after_find))
		{
			$this->after_find[] = '_relate';
		}		
		return $this;		
	}
	
	private function _relate($record)
	{		
		// Connect all belongs to relationships.
		foreach ($this->belongs_to as $key => $value)
		{
			if (is_string($value))
      {
      	$relationship = $value;
      	// Assume the primary key is modelname_id.
        $options = array('primary_key' => $value . '_id', 'model' => $value . '_model');
      }
      else
      {
      	$relationship = $key;
        $options = $value;
      }

      // Only associate relationships that were included.
      if (in_array($relationship, $this->relationships))
      {
      	$this->load->model($options['model']);
      	$record->{$relationship} = $this->{$options['model']}->find($record->{$options['primary_key']});
    	}
		}
		
		// Connect all has many relationships.
		foreach ($this->has_many as $key => $value)
		{
			if (is_string($value))
			{
				$relationship = $value;
				// Assume the primary key is modelname_id.
				$options = array('primary_key' => singular($this->table_name) . '_id', 'model' => singular($value) . '_model');
			}
			else
			{
				$relationship = $key;
				$options = $value;
			}
		
			// Only associate relationships that were included.
			if (in_array($relationship, $this->relationships))
			{
				$this->load->model($options['model']);
				$record->{$relationship} = $this->{$options['model']}->where(array($options['primary_key'] => $record->{$this->primary_key}));
			}
		}
	}
	
	/**
	 * Load the database library.
	 */
	private function _load_db_conn()
	{
		if ( ! $this->db_conn)
		{
			$this->db_conn = $this->load->database('default', TRUE);
		}
		else
		{
			$this->db_conn = $this->load->database($this->db_conn, TRUE);
		}
	}
	
	/**
	 * Calculate the table name based on the model class name.
	 */
	private function _compute_table_name()
	{
		if ( ! $this->table_name)
		{
			$this->table_name = plural(preg_replace('/(_m|_model)?$/', '', strtolower(get_class($this))));
		}
	}
	
	/**
	 * Filter all unnecessary fields from the data, based on the model columns.
	 * 
	 * @param array $data
	 * @return array
	 */
	private function _filter_attributes($data = array())
	{
		$default_attributes = array();
		foreach ($this->columns as $column)
		{
			$default_attributes[$column] = '';
		}
		$filtered_attributes = array();
		foreach ($data as $key => $val)
		{
			if (in_array($key, $this->columns)) $filtered_attributes[$key] = $val;
		}
		return array_merge($default_attributes, $filtered_attributes);
	}
	
	/**
	 * Convert a query row into a model object.
	 * 
	 * @param array $row
	 * @return MY_Model
	 */
	private function _parse_row($row)
	{
		$class_name = get_class($this);
		$record = new $class_name();	
		$record->{$this->primary_key} = $row->{$this->primary_key};
		foreach ($this->columns as $column)
		{
			$record->{$column} = $row->{$column};
		}			
		$record->created_at = $row->created_at;
		$record->updated_at = $row->updated_at;
		
		// Add callbacks.
		$record->relationships = $this->relationships;
		$record->after_find = $this->after_find;
		
		return $record;
	}
	
	/**
	 * Trigger a callback and call its observers.
	 * 
	 * @param array $callback
	 * @param MY_Model|boolean $record
	 * @param boolean $last
	 */
	public function trigger($callback, $record = FALSE, $last = TRUE)
	{
		if (isset($this->$callback) && is_array($this->$callback))
		{
			foreach ($this->$callback as $method)
			{
				call_user_func_array(array($this, $method), array($record, $last));
			}
		}
	}
	
}

/* End of file MY_Model.php */
/* Location: ./application/core/MY_Model.php */