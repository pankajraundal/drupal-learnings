Modules create users and and store in custom table.

How custom table has been created
--------------------------------

Add hook_schema in the .install file of this module
Add all fields in the schema custom_user as table name

How table get created
---------------------------

Whenever module get installed hook_schema hook runs and create a table

What if uninstall the module
---------------------------
custom_user table with all data will get deleted.


Extra module needs to eanable
------------------------------------
We have used jquery datepicker for Date of birth, following module needs to install with this module
- jquery_ui_datepicker

Where to access form to add user
-----------------------
<your_domain>/add-custom-user