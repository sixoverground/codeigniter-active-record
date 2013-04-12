<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * MY_Model
 * 
 * Extends Codeigniter's CI_Model to emulate Ruby on Rails ActiveRecord class.
 * 
 * @author craigphares
 */
class MY_Model extends CI_Model {
	
	protected $connection; // CI database connection
	protected $table_name; // Inferred database table name
	protected $columns = array(); // Columns to query
	protected $primary_key = 'id'; // Table primary key
	
	// Relationship arrays.
	protected $belongs_to = array(); // Has one model that it belongs to.
	protected $has_many = array(); // Has many models.
	protected $includes_values = array(); // Relationships to be included
	
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
		$this->establish_connection();
		
		// Determine the database table name.
		$this->compute_table_name();
	}
	
	/**
	 * Find all rows in the model table.
	 * 
	 * @return array
	 */
	public function all()
	{
		$result = array();
		$query = $this->connection->get($this->table_name);
		foreach ($query->result() as $row)
		{
			$record = $this->parse_row($row);
			
			// Trigger after callbacks.
			$record->run_callback('after_initialize', $record);
			$record->run_callback('after_find', $record);
			
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
		$this->connection->where($this->primary_key, $id);
		$query = $this->connection->get($this->table_name);
		foreach ($query->result() as $row)
		{
			$record = $this->parse_row($row);
			
			// Trigger after callbacks.
			$record->run_callback('after_initialize', $record);
			$record->run_callback('after_find', $record);
			
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
		$query = $this->connection->get_where($this->table_name, $conditions);
		foreach ($query->result() as $row)
		{
			$record = $this->parse_row($row);
			
			// Trigger after callbacks.
			$record->run_callback('after_initialize', $record);
			$record->run_callback('after_find', $record);
			
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
		$data = $this->filter_attributes($data);
		$class_name = get_class($this);
		$record = new $class_name();	
		foreach ($this->columns as $key => $val)
		{
			$record->{$key} = $data[$key];
		}
				
		// Trigger after callbacks.
		$record->run_callback('after_initialize', $record);
		
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
		foreach ($this->columns as $key => $val)
		{
			$data[$key] = $this->{$key};
		}
		$data['created_at'] = date('Y-m-d H:i:s');
		$data['updated_at'] = date('Y-m-d H:i:s');	

		// Trigger before callbacks.		
		$this->run_callback('before_save', $this);
		$this->run_callback('around_save', $this);
		$this->run_callback('before_create', $this);
		$this->run_callback('around_create', $this);
			
		$result = $this->connection->insert($this->table_name, $data);
		$this->{$this->primary_key} = $this->connection->insert_id();
		foreach ($this->columns as $key => $val)
		{
			$this->{$key} = $data[$key];
		}
		$this->created_at = $data['created_at'];
		$this->updated_at = $data['updated_at'];
		
		// Trigger after callbacks.
		$this->run_callback('after_create', $this);		
		$this->run_callback('after_save', $this);		
		
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
		$data = $this->filter_attributes($data);
		$data['updated_at'] = date('Y-m-d H:i:s');
		$this->connection->where($this->primary_key, $this->{$this->primary_key});
		
		// Trigger before callbacks.
		$this->run_callback('before_save', $this);
		$this->run_callback('around_save', $this);
		$this->run_callback('before_update', $this);
		$this->run_callback('around_update', $this);
		
		$result = $this->connection->update($this->table_name, $data);
		foreach ($this->columns as $key => $val)
		{
			$this->{$key} = $data[$key];
		}
		$this->updated_at = $data['updated_at'];
		
		// Trigger after callbacks.
		$this->run_callback('after_update', $this);
		$this->run_callback('after_save', $this);
		
		if ($result) return $this;
		return FALSE;
	}
	
	/**
	 * Delete a row from the table.
	 */
	public function destroy()
	{
		$this->connection->where($this->primary_key, $this->{$this->primary_key});

		// Trigger before callbacks.
		$this->run_callback('before_destroy', $this);
		$this->run_callback('around_destroy', $this);
		
		$this->connection->delete($this->table_name);

		// Trigger after callbacks.
		$this->run_callback('after_destroy', $this);
	}
	
	/**
	 * Set the order for the query.
	 * 
	 * @param array|string $criteria
	 * @param string $order_by
	 * @return MY_Model
	 */
	public function order($criteria, $order_by = 'ASC')
	{
		if (is_array($criteria))
		{
			foreach ($criteria as $key => $value)
			{
				$this->connection->order_by($key, $value);
			}
		}
		else
		{
			$this->connection->order_by($criteria, $order_by);
		}
		return $this;
	}
	
	/**
	 * Include a relationship.
	 * 
	 * @param string $relationship
	 * @return MY_Model
	 */
	public function includes($relationship)
	{
		$this->includes_values[] = $relationship;
		if ( ! in_array('relate', $this->after_find))
		{
			$this->after_find[] = 'relate';
		}		
		return $this;		
	}

	/**
	 * Trigger a callback and call its observers.
	 *
	 * @param array $callback
	 * @param MY_Model|boolean $record
	 * @param boolean $last
	 */
	protected function run_callback($callback, $record = FALSE, $last = TRUE)
	{
		if (isset($this->$callback) && is_array($this->$callback))
		{
			foreach ($this->$callback as $method)
			{
				call_user_func_array(array($this, $method), array($record, $last));
			}
		}
	}
	
	/**
	 * Setup desired associations for the model.
	 * 
	 * @param MY_Model $record
	 */
	protected function relate($record)
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
      if (in_array($relationship, $this->includes_values))
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
			if (in_array($relationship, $this->includes_values))
			{
				$this->load->model($options['model']);
				$record->{$relationship} = $this->{$options['model']}->where(array($options['primary_key'] => $record->{$this->primary_key}));
			}
		}
	}
	
	/**
	 * Load the database library and keep the connection.
	 */
	protected function establish_connection()
	{
		if ( ! $this->connection)
		{
			$this->connection = $this->load->database('default', TRUE);
		}
		else
		{
			$this->connection = $this->load->database($this->connection, TRUE);
		}
	}
	
	/**
	 * Calculate the table name based on the model class name.
	 */
	protected function compute_table_name()
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
	protected function filter_attributes($data = array())
	{
		$default_attributes = array();
		foreach ($this->columns as $key => $val)
		{
			$default_attributes[$key] = $val;
		}
		$filtered_attributes = array();
		foreach ($data as $key => $val)
		{
			if (array_key_exists($key, $this->columns))
			{
				$filtered_attributes[$key] = $val;
			}
		}
		return array_merge($default_attributes, $filtered_attributes);
	}
	
	/**
	 * Convert a query row into a model object.
	 * 
	 * @param array $row
	 * @return MY_Model
	 */
	protected function parse_row($row)
	{
		$class_name = get_class($this);
		$record = new $class_name();	
		foreach ($row as $key => $val)
		{
			$record->{$key} = $val;
		}
		
		// Clone scoped includes.
		$record->includes_values = $this->includes_values;
		
		// Clone callbacks.
		$record->after_find = $this->after_find;
		
		return $record;
	}
	
	
}

/* End of file MY_Model.php */
/* Location: ./application/core/MY_Model.php */