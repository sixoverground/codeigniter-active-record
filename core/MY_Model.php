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
	protected $column_types = array(); // Columns that should be treated as boolean

	// Relationship arrays.
	protected $belongs_to = array(); // Has one model that it belongs to.
	protected $has_many = array(); // Has many models.
	protected $has_and_belongs_to_many = array(); // Has many models and belongs to many models.
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

	// Logging
	protected $logger = FALSE; // Log all database queries.

	public function __construct()
	{
		parent::__construct();

		$args = func_get_args();



		// Save the database connection.
		switch (func_num_args())
		{
		case 1:
			$this->connection = $args[0];
			break;
			case 2:
			$this->connection = $args[0];
			$this->table_name = $args[1];
			break;
		default:
			$this->establish_connection();
		}

		// Determine the database table name.
		if ( ! $this->table_name)
		{
			// Load the inflector class for pluralization.
			$this->load->helper('inflector');
			$this->compute_table_name();
		}

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
		if ($this->logger) log_message('debug', $this->connection->last_query());
		$this->reset();

		foreach ($query->result() as $row)
		{
			$record = $this->parse_row($row);
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
		return $this->find_by($this->primary_key, $id);
	}

	/**
	 * Get a single row from the table based on the column value.
	 *
	 * @param string $column
	 * @param mixed $value
	 * @return MY_Model|boolean
	 */
	public function find_by($column, $value)
	{
		// Trigger before callbacks.
		$this->run_callback('before_find');

		$this->connection->where($column, $value);
		$query = $this->connection->get($this->table_name);
		if ($this->logger) log_message('debug', $this->connection->last_query());
		$this->reset();

		foreach ($query->result() as $row)
		{
			$record = $this->parse_row($row);
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
		if ($this->logger) log_message('debug', $this->connection->last_query());
		$this->reset();

		foreach ($query->result() as $row)
		{
			$record = $this->parse_row($row);
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
		$data = $this->initialize_attributes($data);
		$class_name = get_class($this);
		$record = new $class_name($this->connection, $this->table_name);
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
	 * @property array $options
	 * @return MY_Model|boolean
	 */
	public function save($options = array('validate' => TRUE))
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
			$data['updated_at'] = date('Y-m-d H:i:s');
		}

		// Validate the model.
		if ($options['validate']) {
			$this->run_callback('before_validation', $this);
			$data = $this->validate($data);
			if ($data === FALSE) return FALSE;
			$this->run_callback('after_validation', $this);
		}

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

		if (isset($this->{$this->primary_key}))
		{
			// Save an existing record.
			$this->connection->where($this->primary_key, $this->{$this->primary_key});
			$result = $this->connection->update($this->table_name, $data);
			if ($this->logger) log_message('debug', $this->connection->last_query());
		}
		else
		{
			// Insert a new record.
			if ($this->timestamps) $data['created_at'] = date('Y-m-d H:i:s');
			$result = $this->connection->insert($this->table_name, $data);
			if ($this->logger) log_message('debug', $this->connection->last_query());
			$this->{$this->primary_key} = $this->connection->insert_id();
			if ($this->timestamps) $this->created_at = $data['created_at'];
		}
		foreach ($this->columns as $key => $val)
		{
			$this->{$key} = $data[$key];
		}

		// Set timestamps.
		if ($this->timestamps)
		{
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

		// Pull data into properties.
		foreach ($data as $key => $val)
		{
			$this->{$key} = $data[$key];
		}

		// Add timestamps.
		if ($this->timestamps) $data['updated_at'] = date('Y-m-d H:i:s');

		$this->connection->where($this->primary_key, $this->{$this->primary_key});

		// Validate the model.
		$this->run_callback('before_validation', $this);
		// Update data with anything changed from callbacks.
		foreach ($this->columns as $key => $val)
		{
			$data[$key] = $this->{$key};
		}
		$data = $this->validate($data);
		if ($data === FALSE) return FALSE;
		$this->run_callback('after_validation', $this);

		// Trigger before callbacks.
		$this->run_callback('before_save', $this);
		$this->run_callback('around_save', $this);
		$this->run_callback('before_update', $this);
		$this->run_callback('around_update', $this);

		// Update data with anything changed from callbacks.
		foreach ($this->columns as $key => $val)
		{
			$data[$key] = $this->{$key};
		}

		$result = $this->connection->update($this->table_name, $data);
		if ($this->logger) log_message('debug', $this->connection->last_query());
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
		if ($this->logger) log_message('debug', $this->connection->last_query());

		// Trigger after callbacks.
		$this->run_callback('after_destroy', $this);
	}

	/**
	 * Prevent CodeIgniter parser from displaying an error.
	 *
	 * @return string
	 */
	public function __toString(){
		return 'MY_Model';
	}

	/**
	 * Get the first model in the table.
	 *
	 * @return MY_Model
	 */
	public function first()
	{
		// Trigger before callbacks.
		$this->run_callback('before_find');

		$this->connection->limit(1);
		$query = $this->connection->get($this->table_name);
		if ($this->logger) log_message('debug', $this->connection->last_query());
		$this->reset();

		foreach ($query->result() as $row)
		{
			$record = $this->parse_row($row);
			return $record;
		}
		return FALSE;
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

	public function group($group)
	{
		$this->connection->group_by($group);
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
	 * Perform a join.
	 *
	 * @param string $table
	 * @param string $cond
	 * @param string $type
	 * @return MY_Model
	 */
	public function joins($table, $cond, $type = '')
	{
		$this->connection->join($table, $cond, $type);
		return $this;
	}

	/**
	 * Add an associated model to this model.
	 *
	 * @param string $association
	 * @param MY_Model $object
	 */
	public function add($association, $object)
	{
		// Determine table and key names.
		$table1 = $this->table_name;
		$table2 = $association;
		$key1 = singular($table1) . '_id';
		$key2 = singular($table2) . '_id';
		if (strcmp($table1, $table2) < 0) $join_table = $table1 . '_' . $table2;
		else $join_table = $table2 . '_' . $table1;

		// Insert the join table record.
		$this->connection->insert($join_table, array(
			$key1 => $this->{$this->primary_key},
			$key2 => $object->{$object->primary_key}
		));
		if ($this->logger) log_message('debug', $this->connection->last_query());

		// Add the object to the model association.
		if ( ! isset($this->{$association}))
		{
			$this->{$association} = array();
		}
		array_push($this->{$association}, $object);

		return TRUE;
	}

	public function remove($association, $object)
	{
		// Determine table and key names.
		$table1 = $this->table_name;
		$table2 = $association;
		$key1 = singular($table1) . '_id';
		$key2 = singular($table2) . '_id';
		if (strcmp($table1, $table2) < 0) $join_table = $table1 . '_' . $table2;
		else $join_table = $table2 . '_' . $table1;

		// Delete the join table record.
		$this->connection->delete($join_table, array(
			$key1 => $this->{$this->primary_key},
			$key2 => $object->{$object->primary_key}
		));
		if ($this->logger) log_message('debug', $this->connection->last_query());

		// TODO: Remove object from association array.

		return TRUE;
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
		$num_results = $this->connection->count_all_results($this->table_name);
		if ($this->logger) log_message('debug', $this->connection->last_query());
		return $num_results;
	}

	/**
	 * Get a single child object based on the primary key.
	 *
	 * @param string $relationship
	 * @param mixed $id
	 * @return MY_Model|boolean
	 */
	public function find_child($relationship, $id)
	{
		return $this->find_child_by($relationship, $this->primary_key, $id);
	}

	/**
	 * Get a single child object based on the key provided.
	 *
	 * @param string $relationship
	 * @param string $column
	 * @param mixed $value
	 * @return MY_Model|boolean
	 */
	public function find_child_by($relationship, $column, $value)
	{
		foreach ($this->{$relationship} as $relation)
		{
			if ($relation->{$column} == $value)
			{
				return $relation;
			}
		}
		return FALSE;
	}

	/**
	 * Convert the model to a friendly JSON format.
	 *
	 * @return string
	 */
	public function to_json($options = array())
	{
		// Add public columns to array.
		$output = array();
		$output[$this->primary_key] = $this->{$this->primary_key};
		foreach ($this->columns as $key => $val)
		{
			$output[$key] = $this->{$key};
		}
		if ($this->timestamps)
		{
			$output['created_at'] = $this->created_at;
			$output['updated_at'] = $this->updated_at;
		}

		// Include passed options.
		if (isset($options['include']))
		{
			foreach ($options['include'] as $extra)
			{
				$output[$extra] = $this->{$extra};
			}
		}

		// Exclude passed options.
		if (isset($options['exclude']))
		{
			foreach ($options['exclude'] as $extra)
			{
				if (isset($output[$extra])) unset($output[$extra]);
			}
		}

		// Add belongs to relationships if they exist.
		foreach ($this->belongs_to as $relationship)
		{

			// Exclude passed options.
			$exclude_relationship = FALSE;
			if (isset($options['exclude']))
			{
				if (in_array($relationship, $options['exclude']))
				{
					$exclude_relationship = TRUE;
				}
			}


			if (isset($this->{$relationship}) && $this->{$relationship} != '' && ! $exclude_relationship)
			{

				// Add passed included options
				$option = array();
				foreach ($options as $key => $val)
				{
					// echo 'compare ' . $key . ' to ' . $relationship;
					if ($key == $relationship)
					{
						$option = $val;
					}
				}

				$output[$relationship] = $this->{$relationship}->to_json($option);
			}
		}

		// Add has many relationships if they exist.
		foreach ($this->has_many as $relationship)
		{

			// Exclude passed options.
			$exclude_relationship = FALSE;
			if (isset($options['exclude']))
			{
				if (in_array($relationship, $options['exclude']))
				{
					$exclude_relationship = TRUE;
				}
			}

			if (isset($this->{$relationship}) && ! $exclude_relationship)
			{

				// Add passed included options
				$option = array();
				foreach ($options as $key => $val)
				{
					if ($key == $relationship)
					{
						$option = $options[$key];
					}
				}

				$json_relationship = array();
				foreach ($this->{$relationship} as $child)
				{
					array_push($json_relationship, $child->to_json($option));
				}
				$output[$relationship] = $json_relationship;
			}
		}

		return $output;
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

		// Connect all has and belongs to many relationships.
		foreach ($this->has_and_belongs_to_many as $key => $value)
		{
			if (is_string($value))
			{
				$relationship = $value;
				$options = array('primary_key' => singular($this->table_name) . '_id', 'model' => singular($value) . '_model');
			}
			else
			{
				$relationship = $key;
				$options = $value;
			}

			if (in_array($relationship, $this->includes_values))
			{
				$this->load->model($options['model']);
				$table1 = $this->table_name;
				$table2 = $this->{$options['model']}->table_name;
				$key1 = $options['primary_key'];
				$key2 = singular($table2) . '_id';
				if (strcmp($table1, $table2) < 0) $join_table = $table1 . '_' . $table2;
				else $join_table = $table2 . '_' . $table1;
				$join_model = $this->{$options['model']};
				$join1_str = $join_table . '.' . $key1 . ' = ' . $table1 . '.' . $this->primary_key;
				$join2_str = $join_table . '.' . $key2 . ' = ' . $table2 . '.' . $join_model->primary_key;
				$where_str = $table1 . '.' . $this->primary_key . ' = ' . $record->{$this->primary_key};
				$where_key = $record->{$this->primary_key};

				//$sql = "SELECT $table2.* FROM $table2 JOIN $join_table ON $join2_str JOIN $this->table_name ON $join1_str WHERE $where_str";
				$sql = "SELECT $table2.*, t0.$key1 AS ar_association_key_name FROM $table2
						INNER JOIN $join_table t0 ON $table2.$join_model->primary_key = t0.$key2
						WHERE t0.$key1 = $where_key";

				$record->{$relationship} = array();
				$query = $this->connection->query($sql);

				if ($this->logger) log_message('debug', $this->connection->last_query());

				foreach ($query->result() as $row)
				{
					$association = $join_model->parse_row($row);
					array_push($record->{$relationship}, $association);
				}

			}
		}

		// Empty all includes calls to prevent recursion.
		$this->includes_values = array();
		$this->after_find = array();

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
	 * Set default attributes.
	 *
	 * @param array $data
	 * @return array
	 */
	protected function initialize_attributes($data = array())
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
	 * Filter all unnecessary fields from the data, based on the model columns.
	 *
	 * @param array $data
	 * @return array
	 */
	protected function filter_attributes($data = array())
	{
		$filtered_attributes = array();
		foreach ($data as $key => $val)
		{
			if (array_key_exists($key, $this->columns))
			{
				$filtered_attributes[$key] = $val;
			}
		}
		return $filtered_attributes;
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

		$record = new $class_name($this->connection, $this->table_name);

		foreach ($row as $key => $val)
		{
			$record->{$key} = $val;
		}

		// Force types.
		$record->id = (int) ($record->id);
		foreach ($this->column_types as $type_key => $type_val)
		{
			if (isset($record->{$type_key}))
			{
				if ($type_val == 'boolean')
				{
					$record->{$type_key} = (bool) ($record->{$type_key});
				}
				else if ($type_val == 'integer')
				{
					$record->{$type_key} = (int) ($record->{$type_key});
				}
				else if ($type_val == 'float')
				{
					$record->{$type_key} = (float) ($record->{$type_key});
				}
			}
		}

		// Clone scoped includes.
		$record->includes_values = $this->includes_values;

		// Clone callbacks.
		$record->after_find = $this->after_find;

		// Trigger after callbacks.
		$record->run_callback('after_initialize', $record);
		$record->run_callback('after_find', $record);

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
