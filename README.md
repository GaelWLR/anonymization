### Anonymization script for database

To use if you need to anonymize an existing database without touching non-personal information, which I need to do to generate databases presenting a normal use of an application.

#### How it work ?

First, start by editing settings.php, there is a pre-filled example. The structure must be respected ``tables`` must contain an associative array with the name of the table in key and then an array with the name of the id field (``idFieldName``) and an array of fields to modify associated with the modification method (``['field_name' => 'method']``).

When your settings are correct, run the script, I personally prefer to do it in CLI, but you can do it by browser.
