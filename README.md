codeigniter-active-record
=========================

CodeIgniter base model that emulates the Ruby on Rails ActiveRecord class.

Get Started
-----------

```php
// Extend MY_Model.
class Todo_model extends MY_Model { 
	// Define the schema defaults.
	public $columns = array('title' => '');
}

// Load the model.
$this->load->model('todo_model');

// Find all models.
$todos = $this->todo_model->all();

// Find a single model.
$todo = $this->todo_model->find(1);

// Find models with conditions.
$todos = $this->todo_model->where('title', 'Take out the trash');

// Create a model.
$todo = $this->todo_model->new_model(array('title' => 'My new todo'));
$todo->save();

// Update a model.
$todo->update_attributes(array('title' => 'This is different'));

// Destroy a model.
$todo->destroy();
```

Installation
------------

Download and copy the MY\_Model.php file into your _application/core_ folder. CodeIgniter will load and initialize this class automatically for you.

Extend your model classes from `MY_Model`.

Ordering
--------

To retrieve records in a specific order, use the order method.

```php
// Order the models by their title.
$todos = $this->todo_model->order('title')->all();
```

Limit and Offset
----------------

Use limit to specify the number of records to be retrieved, and use offset to specify the number or records to skip.

```php
// Retrieve 5 models.
$todos = $this->todo_model->limit(5);

// Retrieve 5 models, starting at the 31st.
$todos = $this->todo_model->limit(5)->offset(30)->all();
```

Relationships
-------------

**MY\_Model** has support for basic _belongs\_to_ and has\_many relationships. These relationships are easy to define:

```php
class Todo_model extends MY_Model {
	public $belongs_to = array('project');
}

class Project_model extends MY_Model {
	public $has_many = array('todos');
}
```

You can then access your related data using the `includes()` method:

```php
$todo = $this->todo_model->includes('project')->find(1);
$project = $this->project_model->includes('todos')->find(1);
```

The related data will be embedded in the returned value from `find`:

```php
echo $todo->project->name;

foreach ($project->todos as $project_todo)
{
	echo $project_todo->title;
}
```

Calculations
------------

See how many records are in your model's table.

```php
$this->todo_model->count();
```

Validations
-----------

MY_Model uses CodeIgniter's built in form validation to validate data on insert.

You can enable validation by setting the `$validates` instance to the usual form validation library rules array:

```php
class Todo_model extends MY_Model {
	public $validates = array(
		array( 
			'field' => 'title', 
			'label' => 'title',
			'rules' => 'required' 
		),
	);
}
```

Anything valid in the form validation library can be used here. To find out more about the rules array, please [view the library's documentation](http://codeigniter.com/user_guide/libraries/form_validation.html#validationrulesasarray).

With this array set, each call to `save()` or `update_attributes()` will validate the data before allowing  the query to be run. 

**Unlike the CodeIgniter validation library, this won't validate the POST data, rather, it validates the data passed directly through.**

Callbacks
---------

Hook into the life cycle of your Active Record objects. Callbacks are methods that get called at certain moments of an objectÕs life cycle. With callbacks it is possible to write code that will run whenever an Active Record object is created, saved, updated, deleted, validated, or loaded from the database.

Available Callbacks:

* before_validation
* after_validation
* before_save
* around_save
* before_create
* around_create
* after_create
* before_update
* around_update
* after_update
* after_save
* before_destroy
* around_destroy
* after_destroy
* after_initialize
* after_find

These are instance variables defined at the class level. They are arrays of methods on this class to be called at certain points. An example:

```php
class Todo_model extends MY_Model {
 	public $before_create = array('check_with_wife');
 	
 	public $columns = array('okay_with_wife' => FALSE);
  
	protected function check_with_wife($todo)
	{
		$todo->okay_with_wife = TRUE;
	}
}
```

