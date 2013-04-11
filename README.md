codeigniter-active-record
=========================

CodeIgniter base model that emulates the Ruby on Rails ActiveRecord class.

Synopsis
--------

```php
// Extend MY_Model.
class Todo_model extends MY_Model { }

// Load the model.
$this->load->model('todo_model');

// Find all models.
$todos = $this->todo_model->all();

// Find one model.
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

