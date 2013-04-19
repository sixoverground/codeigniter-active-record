<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * MY_Model
 * 
 * Extends Codeigniter's CI_Model to emulate Ruby on Rails ActiveRecord class.
 * 
 * @author craigphares
 */
class MY_Model extends CI_Model {
	
	// Schema properties.
	protected $connection; // CI database connection
	protected $table_name; // Inferred database table name
	protected $columns = array(); // Columns to query
	protected $primary_key = 'id'; // Table primary key
	protected $timestamps = FALSE; // created_at and udpated_at
	
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
	protected $before_find = array();
	protected $after_find = array();
	
	// Limits
	protected $limit_value = 0;
	protected $offset_value = 0;
	
	// Validations
	protected $validates = array();
	
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
		
		// Trigger before callbacks.
		$this->run_callback('before_find');
		
		$query = $this->connection->get($this->table_name);
		$this->reset();
		
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
		// Trigger before callbacks.
		$this->run_callback('before_find');
		
		$this->connection->where($this->primary_key, $id);
		$query = $this->connection->get($this->table_name);
		$this->reset();
		
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

		// Trigger before callbacks.
		$this->run_callback('before_find');
		
		$query = $this->connection->get_where($this->table_name, $conditions);
		$this->reset();
		
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
		// Pull properties into data.
		$data = array();
		foreach ($this->columns as $key => $val)
		{
			$data[$key] = $this->{$key};
		}
		
		// Add timestamps.
		if ($this->timestamps)
		{
			$data['created_at'] = date('Y-m-d H:i:s');
			$data['updated_at'] = date('Y-m-d H:i:s');
		}

		// Validate the model.
		$this->run_callback('before_validation', $this);
		$data = $this->validate($data);
		if ($data === FALSE) return FALSE;
		$this->run_callback('after_validation', $this);
		
		// Trigger before callbacks.		
		$this->run_callback('before_save', $this);
		$this->run_callback('around_save', $this);
		$this->run_callback('before_create', $this);
		$this->run_callback('around_create', $this);
		
		// Update data with anything changed from callbacks.
		foreach ($this->columns as $key => $val)
		{
			$data[$key] = $this->{$key};
		}		
			
		$result = $this->connection->insert($this->table_name, $data);
		$this->{$this->primary_key} = $this->connection->insert_id();
		foreach ($this->columns as $key => $val)
		{
			$this->{$key} = $data[$key];
		}
		
		// Set timestamps.
		if ($this->timestamps)
		{
			$this->created_at = $data['created_at'];
			$this->updated_at = $data['updated_at'];
		}
		
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
		
		// Add timestamps.
		if ($this->timestamps) $data['updated_at'] = date('Y-m-d H:i:s');
		
		$this->connection->where($this->primary_key, $this->{$this->primary_key});

		// Validate the model.
		$this->run_callback('before_validation', $this);
		$data = $this->validate($data);
		if ($data === FALSE) return FALSE;
		$this->run_callback('after_validation', $this);
		
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
		
		// Set timestamps.
		if ($this->timestamps) $this->updated_at = $data['updated_at'];
		
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
	 * Prevent CodeIgniter from displaying an error.
	 *
	 * @return string
	 */
	public function __toString(){
		return 'MY_Model';
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
	 * Limit the number of records to return.
	 * 
	 * @param int $limit
	 * @return MY_Model
	 */
	public function limit($limit)
	{
		$this->limit_value = $limit;
		if ( ! in_array('prepare', $this->before_find))
		{
			$this->before_find[] = 'prepare';
		}		
		return $this;	
	}
	
	/**
	 * Skip a number of records.
	 * 
	 * @param int $offset
	 * @return MY_Model
	 */
	public function offset($offset)
	{
		$this->offset_value = $offset;
		if ( ! in_array('prepare', $this->before_find))
		{
			$this->before_find[] = 'prepare';
		}		
		return $this;
	}
	
	/**
	 * Eager load a relationship.
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
	 * Load a relationship.
	 * 
	 * @param string $name
	 * @return MY_Model
	 */
	public function load_association($name)
	{
		$this->includes($name);
		$this->relate($this);
		return $this;	
	}
	
	/**
	 * Count the records in a table.
	 * 
	 * @return int
	 */
	public function count()
	{
		return $this->connection->count_all_results($this->table_name);
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
	 * Prepare the query.
	 */
	protected function prepare()
	{
		if ($this->limit_value > 0)
		{
			$this->connection->limit($this->limit_value, $this->offset_value);
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
      	// TODO: Should check to see if relationship was already loaded.
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
      	// TODO: Should check to see if relationship was already loaded.
				$record->{$relationship} = $this->{$options['model']}->where(array($options['primary_key'] => $record->{$this->primary_key}));
			}
		}
	}
	
	protected function validate($data)
	{
		if ( ! empty($this->validates))
		{
			foreach ($data as $key => $val)
			{
				$_POST[$key] = $val;
			}

			$this->load->library('form_validation');		
		
			if (is_array($this->validates))
			{
				$this->form_validation->set_rules($this->validates);
		
				if ($this->form_validation->run() === TRUE)
				{
					return $data;
				}
				else
				{
					return FALSE;
				}
			}
			else
			{
				if ($this->form_validation->run($this->validates) === TRUE)
				{
					return $data;
				}
				else
				{
					return FALSE;
				}
			}
		}
		return $data;		
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
	
	/**
	 * Reset the model, 
	 * so that limits and orders are not included in the next query.
	 */
	protected function reset()
	{
		$this->limit_value = 0;
		$this->offset_value = 0;
		$this->before_find = array();		
	}
	
	
}

/* End of file MY_Model.php */
/* Location: ./application/core/MY_Model.php */