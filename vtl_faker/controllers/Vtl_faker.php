<?php

require_once __DIR__ . '/../assets/vendor/autoload.php';
include_once(__DIR__ . '/../assets/vendor/ifsnop/mysqldump-php/src/Ifsnop/Mysqldump/Mysqldump.php');

use Ifsnop\Mysqldump as IMysqldump;
class Vtl_faker extends Trongate
{
    //protected mixed $settings;

    protected mixed $applicationModules;

    private string $host = HOST;

    private string $dbname = DATABASE;

    private string $user = USER;

    private string $pass = PASSWORD;

    /**
     * Constructor for the Vtl_faker class.
     *
     * @param string|null $module_name The name of the module. Default is null.
     */
    public function __construct(?string $module_name = null)
    {
        // Call the constructor of the parent class Trongate
        parent::__construct($module_name);

        // Set the parent and child module names
        $this->parent_module = 'vtl_gen';
        $this->child_module = 'vtl_faker';

        // Initialize the Faker instance
        $faker = null;
        $this->$faker = \Faker\Factory::create('en_GB');

        //Get a list of all modules in the application and whether or not they have an api.
        $this->applicationModules = $this -> list_all_modules();


    }

    /**
     * This function was create by Simon Field aka Dafa.
     * I am indebted to him for it.
     *
     * Retrieves information about all modules in the application.
     *
     * This function scans the modules directory and gathers information about each module,
     * including whether it has associated database tables and whether it has an API defined.
     * It returns an array containing information about each module and its submodules.
     *
     * @return array An array containing information about all modules in the application.
     */
    private function list_all_modules()
    {
        // Define the path to the modules directory
        $modules_dir = APPPATH . 'modules';

        // Query the database to retrieve a list of all tables
        $tables = $this->model->query("SHOW TABLES", 'array');

        // Extract table names from the query result
        $table_names = [];
        foreach ($tables as $table) {
            $table_names[] = $table[array_key_first($table)];
        }

        // Initialize an array to store module information
        $module_info = [];

        // Iterate through each directory in the modules directory
        foreach (new DirectoryIterator($modules_dir) as $module_dir) {
            if ($module_dir->isDir() && !$module_dir->isDot() && $module_dir->getFilename() !== 'modules') {
                // Get the name of the module
                $module_name = $module_dir->getFilename();

                // Check if the module has associated database tables
                if (in_array($module_name, $table_names)) {
                    $parent_has_table = true;
                    unset($table_names[array_search($module_name, $table_names)]);
                } else {
                    $parent_has_table = false;
                }

                // Define the path to the controllers directory within the module
                $controllers_dir = $module_dir->getPathname() . '/controllers';

                // Check if the controllers directory exists
                if (is_dir($controllers_dir)) {
                    // Define the path to the assets directory within the module
                    $assets_dir = $module_dir->getPathname() . '/assets';

                    // Check if the assets directory exists
                    if (is_dir($assets_dir)) {
                        // Check if the module has an API defined by looking for an api.json file in the assets directory
                        $api_json_exists = file_exists($assets_dir . '/api.json');
                    } else {
                        $api_json_exists = false;
                    }

                    // Initialize an array to store information about submodules
                    $submodules = [];

                    // Iterate through each directory within the module directory
                    foreach (new DirectoryIterator($module_dir->getPathname()) as $submodule_dir) {
                        if ($submodule_dir->isDir() && !$submodule_dir->isDot() && $submodule_dir->getFilename() !== 'controllers') {
                            // Get the name of the submodule
                            $submodule_name = $submodule_dir->getFilename();

                            // Check if the submodule has associated database tables
                            if (in_array($submodule_name, $table_names)) {
                                $child_has_table = true;
                                unset($table_names[array_search($submodule_name, $table_names)]);
                            } else {
                                $child_has_table = false;
                            }

                            // Define the path to the controllers directory within the submodule
                            $submodule_controllers_dir = $submodule_dir->getPathname() . '/controllers';

                            // Check if the controllers directory exists within the submodule
                            $controllers_exist = is_dir($submodule_controllers_dir);

                            // Define the path to the assets directory within the submodule
                            $submodule_assets_dir = $submodule_dir->getPathname() . '/assets';

                            // Check if the assets directory exists within the submodule and if an api.json file exists
                            $submodule_api_json_exists = is_dir($submodule_assets_dir) && file_exists($submodule_assets_dir . '/api.json');

                            // If controllers exist within the submodule, add submodule information to the submodules array
                            if ($controllers_exist) {
                                $submodules[] = [
                                    'module_name' => $submodule_name,
                                    'is_child_module_of' => $module_name,
                                    'has_table' => $child_has_table,
                                    'api_json_exists' => $submodule_api_json_exists
                                ];
                            }
                        }
                    }

                    // If submodules exist, add module information to the module_info array including submodule details
                    if (!empty($submodules)) {
                        $module_info[] = [
                            'module_name' => $module_name,
                            'has_table' => $parent_has_table,
                            'api_json_exists' => $api_json_exists,
                            'submodules' => $submodules
                        ];
                    } else {
                        // If no submodules exist, add module information to the module_info array
                        $module_info[] = [
                            'module_name' => $module_name,
                            'has_table' => $parent_has_table,
                            'api_json_exists' => $api_json_exists
                        ];
                    }
                }
            }
        }

        // If there are any table names remaining in the table_names array, add them as orphaned_tables in module_info
        if (!empty($table_names)) {
            $module_info[] = [
                'orphaned_tables' => $table_names
            ];
        }

        // Return the module_info array containing information about all modules in the application
        return $module_info;
    }


    /**
     * Generates fake data based on user input and inserts it into the database via API.
     *
     * This function processes the submitted form data to generate fake data based on the selected table
     * and the specified number of rows. It then inserts the generated data into the database via API.
     *
     * @return void
     */
    public function createFakes(): void
    {
        // Initialize Faker instance
        $faker = null;
        $faker = $this->$faker;

        // Retrieve raw POST data from the request body
        $rawPostData = file_get_contents('php://input');

        // Decode the JSON data into an associative array
        $postData = json_decode($rawPostData, true);

        // Extract relevant data from the decoded JSON
        $selectedTable = $postData['selectedTable'];
        $selectedRows = $postData['selectedRows'];
        $numRows = $postData['numRows'];

        // Check if API JSON exists for the selected table
        $apiJsonExists = $this->findApiJsonExists($selectedTable);

        // Process fake data generation and insertion based on user input
        if ($selectedRows != null && $apiJsonExists == true) {
            if ($numRows == 1) {
                // Generate and insert a single row of fake data
                $newRecordId = $this->generateSingleRowAndInsertViaApi($faker, $selectedRows, $selectedTable);
                echo 'New Record Id = ' . $newRecordId;
            } else {
                // Generate and insert multiple rows of fake data
                $count = $this->generateMultipleRowsAndInsertViaApi($faker, $selectedRows, $selectedTable, $numRows);
                echo 'Number of records inserted =  ', $count;
            }
        } else {
            // Inform the user if no API exists for the selected table
            echo 'No API Exists';
        }
    }


    /**
     * Checks if API JSON configuration exists for the specified table.
     *
     * This function iterates over the list of application modules to find the specified table.
     * If the table is found and associated with an API JSON configuration, it returns true,
     * indicating that the API JSON exists for the table. Otherwise, it returns false.
     *
     * @param string $selectedTable The name of the table to check for API JSON configuration.
     * @return bool True if API JSON exists for the specified table, false otherwise.
     */
    public function findApiJsonExists($selectedTable)
    {
        // Iterate over application modules to find the specified table
        foreach ($this->applicationModules as $module) {
            if ($module['module_name'] === $selectedTable) {
                // Return true if API JSON exists for the specified table
                return $module['api_json_exists'];
            }
        }
        // Return false if the module name is not found or no API JSON exists for the table
        return false;
    }


    /**
     * Generates fake data for a single row and inserts it into the specified table via API.
     *
     * This function constructs a JSON object containing fake data for the selected rows,
     * based on the provided Faker instance and field specifications. It then decodes the JSON
     * object into an associative array and inserts the data into the specified table using
     * the model's insert method.
     *
     * @param Faker\Generator $faker The Faker instance used to generate fake data.
     * @param array $selectedRows An array of selected rows (fields) for which fake data is generated.
     * @param string $selectedTable The name of the table into which the fake data will be inserted.
     * @return bool|string Returns the ID of the newly inserted record if successful, or false if insertion fails.
     */
    private function generateSingleRowAndInsertViaApi($faker, $selectedRows, $selectedTable): bool|string
    {
        // Initialize an empty string to store the values as a JSON object
        $values = '{';
        // Iterate over selected rows to generate fake data for each field
        foreach ($selectedRows as $key => $selectedRow) {
            $originalFieldName = $selectedRow['field'];
            $values .= '"' . $originalFieldName . '":';
            // Process field name and generate fake value based on field specifications
            $field = $this->processFieldName($selectedRow['field']);
            $fieldFakerStatement = $this->generateValueFromFieldName($faker, $field);
            //This is where you should add code to generate custom field data
            //it needs to be in the form of:
            //  if($field === '<add your field name here') {
            //      $fieldFakerStatement = $faker -> rgbColor();
            //  }


            // If no specific Faker statement is available, generate value based on field type
            if ($fieldFakerStatement == "nothing") {
                $typeWithBrackets = $selectedRow['type'];
                $valueInBrackets = 0;
                $type = $this->extractType($typeWithBrackets, $valueInBrackets);
                $typeFakerStatement = $this->generateValueFromType($faker, $type, $valueInBrackets);
                $values .= $typeFakerStatement;
            } else {
                $values .= $fieldFakerStatement;
            }
            // Check if the current element is the last one in the array
            if ($key === array_key_last($selectedRows)) {
                $values .= '}';
            } else {
                $values .= ',';
            }
        }

        // Decode the JSON object into an associative array
        $newValuesArray = json_decode($values, true);
        // Insert the generated data into the specified table using the model's insert method
        return $this->model->insert($newValuesArray, $selectedTable);
    }


    /**
     * Processes the input string to prepare it as a field name.
     *
     * This function takes an input string and performs the following operations:
     * - Trims leading and trailing whitespace.
     * - Removes spaces, underscores, and dashes from the string.
     * - Converts the string to lowercase.
     *
     * @param string $inputString The input string to be processed.
     * @return string Returns the processed field name string.
     */
    private function processFieldName($inputString): string
    {
        // Trim leading and trailing whitespace
        $trimmedString = trim($inputString);

        // Remove spaces, underscores, and dashes from the string
        $filteredString = preg_replace('/[\s_\-]+/', '', $trimmedString);

        // Convert the string to lowercase
        return strtolower($filteredString);
    }

    /**
     * Generates a value based on the given field name using Faker.
     *
     * This function generates a value based on the provided field name using the Faker library.
     * It maps field names to Faker methods to generate appropriate fake data.
     *
     * @param \Faker\Generator $faker The Faker instance used to generate fake data.
     * @param string $fieldName The name of the field for which a value needs to be generated.
     * @return mixed|string|null Returns the generated value as a string, or 'nothing' if no suitable method is found.
     */
    private function generateValueFromFieldName($faker, $fieldName)
    {
        $statement = null;
        $value = null;
        switch ($fieldName)
        {
            case 'firstname':
                $value = $faker -> firstName();
                $statement = '"'.$value.'"';
                break;

            case 'lastname':
                $value = $faker -> lastName();
                $statement = '"'.$value.'"';
                break;

            case 'customername':
            case 'name':
                $value = $faker -> name();
                $statement = '"'.$value.'"';
                break;

            case 'username':
                $value = $faker -> userName();
                $statement = '"'.$value.'"';
                break;

            case 'customeremail':
            case 'emailaddress':
            case 'email':
                $value = $faker -> email();
                $statement = '"'.$value.'"';
                break;

            case 'password':
                $value = $faker -> password();
                $statement = '"'.$value.'"';
                break;

            case 'age':
                $value = $faker -> numberBetween($min = 18, $max = 99);
                $statement = $value;
                break;

            case 'customeraddress':
            case 'companyaddress':
            case 'address':
                $value = $faker -> address();
                $statement = '"'.$value.'"';
                break;

            case 'city':
                $value = $faker -> city();
                $statement = '"'.$value.'"';
                break;

            case 'town':
                $value = $faker -> town();
                $statement = '"'.$value.'"';
                break;

            case 'streetaddress':
                $value = $faker -> streetAddress();
                $statement = '"'.$value.'"';
                break;

            case 'state';
                $value = $faker -> state();
                $statement = '"'.$value.'"';
                break;

            case 'county':
                $value = $faker -> county();
                $statement = '"'.$value.'"';
                break;

            case 'country':
                $value = $faker -> country();
                $statement = '"'.$value.'"';
                break;

            case 'zipcode':
            case 'postcode':
                $value = $faker -> postcode();
                $statement = '"'.$value.'"';
                break;

            case 'phone':
                $value = $faker -> phoneNumber();
                $statement = '"'.$value.'"';
                break;

            case 'company':
                $value = $faker -> company();
                $statement = '"'.$value.'"';
                break;

            case 'job':
                $value = $faker -> jobTitle();
                $statement = '"'.$value.'"';
                break;

            case 'title':
                $value = $faker -> title();
                $statement = '"'.$value.'"';
                break;

            case 'deliverydate':
            case 'orderdate':
            case 'lastupdateddate':
            case 'datemodified':
            case 'dateadded':
            case 'date':
            case 'dateofbirth':
            case 'dob':
                $value = $faker -> date($format = 'Y-m-d', $max = 'now');
                $statement = '"'.$value.'"';
                break;

            case 'gender':
                $value = $faker -> randomElement(['Male','Female' ]);
                $statement = '"'.$value.'"';
                break;

            case 'website':
                $value = $faker -> url();
                $statement = '"'.$value.'"';
                break;

            case 'comment':
            case 'productdescription':
            case 'description':
                $value = $faker -> text();
                $statement = '"'.$value.'"';
                break;

            case 'lastupdated':
            case 'datecreated':
                $value = $faker -> unixTime(new dateTime('-3 days'));
                $statement = $value;
                break;

            case 'active':
            case 'isactive':
                $value = $faker -> boolean();
                $statement = $value;
                break;

            case 'productname':
                $value = $faker -> productName();
                $statement = '"'.$value.'"';
                break;

            case 'totalamount':
            case 'total':
            case 'ordernumber':
            case 'quantity':
            case 'price':
            case 'productprice':
                $value = $faker -> numberBetween($min = 0, $max = 1000000);
                $statement = $value;
                break;

            case 'orderstatus':
                $value = $faker -> randomElement(['Processed', 'Out for Delivery', 'Fulfilled']);
                $statement = '"'.$value.'"';
                break;

            case 'deliverystatus':
                $value = $faker -> randomElement(['Delivered', 'Returned']);
                $statement = '"'.$value.'"';
                break;

            case 'paymentmethod':
                $value = $faker -> randomElement(['Cash', 'Credit Card', 'PayPal']);
                $statement = '"'.$value.'"';
                break;

            case 'paymentstatus':
                $value = $faker -> randomElement(['Paid', 'Unpaid']);
                $statement = '"'.$value.'"';
                break;

            case 'paymenttype':
                $value = $faker -> randomElement(['Credit Card', 'Cash', 'PayPal']);
                $statement = '"'.$value.'"';
                break;

            case 'transactionid':
                $value = $faker -> uuid();
                $statement = $value;
                break;

            case 'discount':
            case 'discountpercentage':
                $value = $faker -> numberBetween($min = 0, $max = 100);
                $statement = $value;
                break;

            case 'taxamount':
                $value = $faker -> randomFloat(2, 0, 50);
                $statement = $value;
                break;

            default:
                $statement = 'nothing';
        }
        return $statement;
    }

    /**
     * Extracts the type from a string containing the type with optional brackets.
     *
     * This function extracts the type from a string containing the type with optional brackets.
     * It returns the type without brackets if present, otherwise, it returns the original type.
     *
     * @param string $typeWithBrackets The type string possibly containing brackets (e.g., 'varchar(255)').
     * @param mixed|null $valueInBrackets The value inside the brackets (if present).
     * @return string Returns the extracted type without brackets, or the original type if no brackets are found.
     */
    private function extractType($typeWithBrackets, $valueInBrackets = null): string
    {
        // Strip parentheses and get the value inside them
        if (preg_match('/^([a-z]+)(?:\(([^)]+)\))?$/i', $typeWithBrackets, $matches)) {
            // $matches[1] will contain the type without brackets
            // $matches[2] will contain the value inside brackets (if present)
            $valueInBrackets = isset($matches[2]) ? $matches[2] : null;
            return $matches[1];
        }

        // Default to the original type if the pattern doesn't match
        return $typeWithBrackets;
    }

    /**
     * Generates a value based on the given database field type.
     *
     * This function generates a value based on the provided database field type and returns it as a string.
     *
     * @param \Faker\Generator $faker The Faker generator instance.
     * @param string $type The database field type.
     * @param mixed $valueInBrackets The value inside the brackets associated with the type (if present).
     * @return string The generated value as a string.
     */
    private function generateValueFromType($faker, $type, $valueInBrackets)
    {
        $statement = null;
        $value = null;
        switch ($type)
        {

            case 'int':
            case 'bigint':
                $value = $faker -> randomNumber();
                $statement =$value;
                break;

            case 'smallint':
                $value = $faker -> numberBetween(1, 32767);
                $statement = $value;
                break;


            case 'varchar':
            case 'blob':
            case 'text':
                $value = $faker -> text();
                if ($valueInBrackets == 0) {
                    $statement = '"' . $value . '"';
                }else
                {$statement = '"' . substr($value, 0, $valueInBrackets) . '"';}
                break;

            case 'char':
            case 'binary':
            case 'varbinary':
                $value = $faker -> word();
                if ($valueInBrackets == 0) {
                    $statement = '"' . $value . '"';
                }else
                {$statement = '"' . substr($value, 0, $valueInBrackets) . '"';}
                break;

            case 'float':
            case 'double':
                $value = $faker -> randomFloat();
                $statement = $value;
                break;

            case 'decimal':
                $value = $faker -> randomFloat(NULL, 0, 999999.99);
                $statement = $value;
                break;

            case 'date':
                $value = $faker -> date();
                $statement = '"'.$value.'"';
                break;

            case 'timestamp':
            case 'datetime':
                $value = $faker -> dateTime()->format('Y-m-d H:i:s');
                $statement = '"'.$value.'"';
                break;

            case 'time':
                $value = $faker -> time();
                $statement = '"'.$value.'"';
                break;

            case 'tinyint':
                $value = $faker -> boolean();
                $statement = $value;
                break;

            case 'bit':
                $value = $faker -> randomElement(['0', '1']);
                $statement = $value;
                break;

            case 'enum':
                $value = $faker -> randomElement(['value1', 'value2', 'value3']);
                $statement = $value;
                break;

            case 'set':
                $value = $faker -> randomElements(['value1', 'value2', 'value3'], 2);
                $statement = $value;
                break;

            default:
                $statement = '';
        }
        return $statement;
    }

    /**
     * Generates multiple rows of fake data and inserts them into the database via API.
     *
     * This function generates multiple rows of fake data based on the selected fields and their types,
     * and then inserts these rows into the specified table using the model's insert_batch method.
     *
     * @param \Faker\Generator $faker The Faker generator instance.
     * @param array $selectedRows An array containing the selected fields and their types.
     * @param string $selectedTable The name of the table where the data will be inserted.
     * @param int|string $numRows The number of rows to generate and insert.
     * @return int The number of records successfully inserted into the database.
     */
    private function generateMultipleRowsAndInsertViaApi($faker, $selectedRows, $selectedTable, $numRows)
    {
        if (!is_int($numRows)) {
            $numRows = intval($numRows);
        }
        $records = [];

        for ($i = 0; $i < $numRows; $i++) {
            $record = [];
            foreach ($selectedRows as $selectedRow) {
                $originalFieldName = $selectedRow['field'];
                $field = $this->processFieldName($selectedRow['field']);
                $fieldFakerStatement = $this->generateValueFromFieldName($faker, $field);
                //This is where you should add code to generate custom field data
                //it needs to be in the form of:
                //  if($field === '<add your field name here') {
                //      $fieldFakerStatement = $faker -> rgbColor();
                //  }

                if ($fieldFakerStatement == "nothing") {
                    $typeWithBrackets = $selectedRow['type'];
                    $valueInBrackets = 0;
                    $type = $this->extractType($typeWithBrackets, $valueInBrackets);
                    $typeFakerStatement = $this->generateValueFromType($faker, $type, $valueInBrackets);
                    $record[$originalFieldName] = $typeFakerStatement;
                } else {
                    $record[$originalFieldName] = $fieldFakerStatement;
                }
            }
            $records[] = $record;
        }
        // Remove the double quotes from date values
        foreach ($records as &$record) {
            foreach ($record as &$value) {
                if (is_string($value) && substr($value, 0, 1) === '"' && substr($value, -1) === '"') {
                    $value = substr($value, 1, -1); // Remove surrounding quotes
                }
            }
        }
        return $this->model->insert_batch($selectedTable, $records);
    }

    /**
     * Clears data from selected tables.
     * This function expects a JSON payload containing selected table names to be cleared.
     * It deletes data from the selected tables and provides a report on the operation status.
     */
    public function clearData(): void
    {
        // Retrieve raw POST data from the request body
        $rawPostData = file_get_contents('php://input');

        // Decode the JSON data into an associative array
        $postData = json_decode($rawPostData, true);
        //var_dump($postData);
        // Extract relevant data from the decoded JSON
        $selectedTables = $postData['selectedTables'];

        if ($selectedTables != null && $selectedTables != "") {
            $responseText = '';
            $deletedTables = [];
            $failedTables = [];

            try {
                foreach ($selectedTables as $key => $selectedTable) {

                    // Create our SQL statement here
                    $sql = 'DELETE FROM ' . $selectedTable;
                    switch ($selectedTable) {
                        case 'trongate_users':
                        case 'trongate_user_levels':
                        case 'trongate_administrators':
                            $sql .= ' Where id > 1';
                            break;
                        default:
                            break;
                    }
                    try {
                        // Enclose the query method in a try-catch block
                        $this->model->query($sql, '');

                        // If the query was successful, add the table to the list of deleted tables
                        $deletedTables[] = $selectedTable;
                    } catch (Exception $e) {
                        // Handle the exception here, you can log it, display an error message, or take any other appropriate action
                        // In this example, we're just logging the error message
                        echo 'Error: ' . $e->getMessage();
                        // Add the table to the list of failed tables
                        $failedTables[] = $selectedTable;
                    }
                }

                // If no exception was thrown, it means all queries were successful
                $responseText .= 'Operation completed successfully.';
            } catch (Exception $e) {
                // If an exception was thrown outside of the foreach loop, handle it here
                echo 'Error: ' . $e->getMessage();
                $responseText .= 'Operation failed.'.$e;
            }

            // Append the list of deleted tables to the response text
            if (!empty($deletedTables)) {
                $responseText .= "Deleted Tables:\n";
                foreach ($deletedTables as $table) {
                    $responseText .= "- $table\n";
                }
            }

            // Append the list of failed tables to the response text
            if (!empty($failedTables)) {
                $responseText .= "Failed Tables:\n";
                foreach ($failedTables as $failedTable) {
                    $responseText .= "- $failedTable\n";
                }
            }

            // Now $responseText contains the report for the whole operation
            echo $responseText;
        }
        else{ echo 'No Tables were selected';}

    }

    /**
     * Function to create indexes for selected rows in a specified table.
     *
     * This function receives JSON data containing information about the selected table, rows, and index type.
     * It processes the data, creates indexes based on the provided parameters, and provides feedback on the success
     * or failure of each index creation operation.
     */
    public function createIndex(): void
    {
        $rawPostData = file_get_contents('php://input');
        $postData = json_decode($rawPostData, true);

        // Extract relevant data from the decoded JSON
        $selectedTable = $postData['selectedTable'];
        $selectedRows = $postData['selectedRows'];
        $indexType = $postData['indexType'];
        if ($selectedRows != null ) {
            $responseText = '';
            $indexesCreated = [];
            $failedIndexes = [];
            try {
                foreach ($selectedRows as  $selectedRow) {
                    $indexName = 'idx_'.$selectedTable.'_'.$selectedRow['field'];
                    try{

                        // Check if index already exists
                        $existingIndexQuery = "SHOW INDEX FROM $selectedTable WHERE Key_name = '$indexName';";
                        $existingIndexResult = $this->model->query($existingIndexQuery);
                        if ($existingIndexResult && $existingIndexResult->num_rows > 0) {
                            echo "Index $indexName already exists.\n";
                            continue; // Skip creating index if it already exists
                        }
                        $sql = '';
                        switch($indexType){
                            case 'Standard':
                                $sql = 'CREATE INDEX '.$indexName.' ON '.$selectedTable. ' ('.$selectedRow['field'].');';
                                break;
                            case 'Unique':
                                $sql = 'CREATE UNIQUE INDEX '.$indexName.' ON '.$selectedTable. ' ('.$selectedRow['field'].');';
                                break;
                            default:
                                break;
                        }

                            $this->model->query($sql);
                            $indexesCreated[] = $indexName;
                    }
                    catch (Exception $ex){
                        echo 'Error: ' . $ex->getMessage();
                        // Add the table to the list of failed tables
                        $failedIndexes[] = $indexName;
                    }
                }
            }
            catch (Exception $e) {
                // If an exception was thrown outside of the foreach loop, handle it here
                echo 'Error: ' . $e->getMessage();
                $responseText .= 'Operation failed.'.$e;
            }

            // Append the list of created indexes to the response text
            if (!empty($indexesCreated)) {
                $responseText .= "Created Indexes:\n";
                foreach ($indexesCreated as $index) {
                    $responseText .= "- $index\n";
                }
            }

            // Append the list of failed indexes to the response text
            if (!empty($failedIndexes)) {
                $responseText .= "Failed Indexes:\n";
                foreach ($failedIndexes as $failedIndex) {
                    $responseText .= "- $failedIndex\n";
                }
            }


            // Now $responseText contains the report for the whole operation
            echo $responseText;
        }
        else{
            echo 'No Rows were selected';
        }

    }

    /**
     * Delete indexes from the specified table.
     */
    public function deleteIndex(): void
    {
        $rawPostData = file_get_contents('php://input');
        $postData = json_decode($rawPostData, true);
        // Extract relevant data from the decoded JSON
        $selectedTable = $postData['selectedTable'];
        $selectedRows = $postData['selectedRows'];
        if ($selectedRows != null) {
            $responseText = '';
            $indexesDeleted = [];
            $failedDeletions = [];
            try {
                foreach ($selectedRows as $selectedRow) {
                    $indexName = $selectedRow['keyName'];
                    try {
                        $sql = 'ALTER TABLE ' . $selectedTable . ' DROP INDEX ' . $indexName . ';';
                        $this->model->query($sql);
                        $indexesDeleted[] = $indexName;
                    } catch (Exception $ex) {
                        echo 'Error: ' . $ex->getMessage();
                        // Add the table to the list of failed tables
                        $failedDeletions[] = $indexName;
                    }
                }
            } catch (Exception $e) {
                // If an exception was thrown outside of the foreach loop, handle it here
                echo 'Error: ' . $e->getMessage();
                $responseText .= 'Operation failed.' . $e;
            }

            // Append the list of deleted indexes to the response text
            if (!empty($indexesDeleted)) {
                $responseText .= "Deleted Indexes:\n";
                foreach ($indexesDeleted as $index) {
                    $responseText .= "- $index\n";
                }
            }

            // Append the list of failed deletions to the response text
            if (!empty($failedDeletions)) {
                $responseText .= "Failed Deletions:\n";
                foreach ($failedDeletions as $failedDeletion) {
                    $responseText .= "- $failedDeletion\n";
                }
            }

            // Now $responseText contains the report for the whole operation
            echo $responseText;
        }
        else{
            echo 'No Rows were selected';
        }
    }

    /**
     * Export database tables with specified settings.
     * Retrieves post data containing information about tables to export and their settings.
     * Exports the specified tables' structure and optionally skips exporting data for certain tables.
     * Utilizes mysqldump-php library to perform the database export.
     *
     * @throws \Exception When there's an error during the export process.
     *
     * @return void
     */
        public function exportDatabase(): void
        {
            $rawPostData = file_get_contents('php://input');
            $postData = json_decode($rawPostData, true);
            // Extract tables to export from post data
            $tablesToExport = $postData['tablesToExport'];

            // Extract tables with data to export from post data
            $tablesWithDataToExport = $postData['tablesWithDataToExport'];

            // Array to store tables that should not have data exported
            $tablesToSkipDataExport = [];

            // Loop through tables to export
            foreach ($tablesToExport as $table) {
                // If the table is in tables with data to export, skip it
                if (in_array($table, $tablesWithDataToExport)) {
                    continue;
                }
                // Otherwise, add it to the tables to skip data export
                $tablesToSkipDataExport[] = $table;

            }

            // Now $tablesToSkipDataExport contains tables whose data should not be exported
            // and can be added to the dump settings.
            $dumpSettings = array(
                'include-tables' => $tablesToExport,
                'no-data' => $tablesToSkipDataExport,
                'add-drop-database' => true,
                'no-create-db' => false,
                'add-drop-table' => true,
                'single-transaction' => true,
                'reset-auto-increment' => true
            );
            $pdoSettings = array(
                PDO::ATTR_PERSISTENT => true,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            );

            try {

                $dump = new IMysqldump\Mysqldump('mysql:host='.$this->host.';dbname='.$this->dbname, $this->user, $this->pass, $dumpSettings, $pdoSettings);
                $dateSuffix = date('Ymd_His'); // Current date and time format: YYYYMMDD_HHmmss
                $backupFilename = __DIR__ . '/../assets/backups/backup_' . $dateSuffix . '.sql';
                $dump->start($backupFilename);
                echo 'Success, your database script ( backup'.$dateSuffix.'.sql )is in the folder modules/vtl_gen/vtl_faker/assets/backups';
            } catch (\Exception $e) {
                echo 'mysqldump-php error: ' . $e->getMessage();
            }
        }
        function __destruct()
        {
            $this->parent_module = '';
            $this->child_module = '';
        }

}