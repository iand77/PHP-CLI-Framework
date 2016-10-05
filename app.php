<?php

/**
 * Description of index
 *
 * @author iantr
 */


namespace CLI;

/**
 * A CLI application Parameter
 */
class Param {
   
   protected $_value;
   protected $_name;
   protected $_abbv;
   protected $_long;
   protected $_desc;
   protected $_validation;
   protected $_cli_app;
   
   function __construct($cli_app) {
      $this->_cli_app = $cli_app;
   }
   
   /**
    * Set the name of the parameter
    * @param type $name Name of the parameter
    */
   function setName($name) {
      $this->_name = $name;
   }
   
   /**
    * Returns the name of the parameter
    * @return string
    */
   function getName() {
      return $this->_name;
   }
   
   /**
    * Returns the value of the input parameter
    * @return string
    */
   function getValue() {
      return $this->_value;
   }
   
   /**
    * Sets a value for the input parameter
    * @param type $value Value of the input parameter
    */
   function setValue($value) {
      $this->_value = $value;
   }
   
   /**
    * Sets the abbreviated name of the parameter
    * @param string $abbv  
    */
   function setAbbreviatedArgName($abbv) {
      $this->_abbv = $abbv;
   }
   
   /**
    * Returns the abbreviated parameter name
    * @return string Abbreviated paramter name
    */
   function getAbbreviatedArgName() {
      return $this->_abbv;
   }
   
   /**
    * Sets the long argument name of the parameter
    * @param type $long
    */
   function setLongArgName($long) {
      $this->_long = $long;
   }
   
   /**
    * Returns the long argument name of the parameter
    * @return string Long argument name
    */
   function getLongArgName() {
      return $this->_long;
   }
   
   /**
    * Sets a description for the input parameter
    * @param string $desc Input parameter description
    */
   function setDescription($desc) {
      $this->_desc = $desc;
   }
   
   /**
    * Returns a description for the input parameter
    * @return string Description of input parameter
    */
   function getDescription() {
      return $this->_desc;
   }
   
   /**
    * Assigns validation rules to the parameter
    * @param type $validation The validation rules as a pile-deliminated string
    */
   function setValidation($validation) {
      $this->_validation = $validation;
   }
   
   /**
    * Returns the validation rules
    * @return string Validation rules
    */
   function getValidation() {
      return $this->_validation;
   }
   
   /**
    * Validate the input parameter against the validation rules assigned in setValidation function
    * @param int $err_no Error number set by reference
    * @param string $err_msg Error message set by reference
    * @return boolean Validation passed or failed. TRUE=pass, FALSE=fail
    */
   function validate(& $err_no, & $err_msg) {
      $validation = $this->_validation;
      $rules = explode('|', $validation);
      
      foreach($rules as $rule) {
         if ($rule === 'file') {
            if (!is_file($this->_value)) {
               $err_no = 1;
               $err_msg = 'Not a valid file';
               return FALSE;
            }
         } else if ($rule === 'required') {
            if (is_null($this->_value)) {
               $err_no = 2;
               $err_msg = 'Cannot be null';
               return FALSE;
            }
         } else if ($rule === 'numeric') {
            if (!ctype_digit($this->_value)) {
               $err_no = 3;
               $err_msg = 'Must be a number';
               return FALSE;
            }
         }
         else if ($rule === 'alpha') {
            if (!ctype_alpha($this->_value)) {
               $err_no = 4;
               $err_msg = 'Alphabetical chars only';
               return FALSE;
            }
         } else if ($rule === 'alphanum') {
            if (!ctype_alnum($this->_value)) {
               $err_no = 5;
               $err_msg = 'Alphanumeric chars only';
               return FALSE;
            }
         } else if (substr($rule, 0, 8) === 'callback') {
            $func = 'callback_'.substr($rule, 9, -1);
            if (method_exists($this->_cli_app, $func)) {
               list($status, $msg) = call_user_func_array(array($this->_cli_app, $func), array($this->_value));
               if (!$status) {
                  $err_no = 6;
                  $err_msg = $msg;
                  return FALSE;
               }
            } else {
               trigger_error(sprintf('Callback function "%s" undefined in CLI Application main class.', $func), E_USER_ERROR);
            }
         }
      }
      return TRUE;
   }
   
}

class ApplicationTitle {
   
   protected $_title;
   protected $_description;
   protected $_version;
   
   
   function __construct($title, $description, $version) {
      $this->_title = $title;
      $this->_description = $description;
      $this->_version = $version;
   }
   
   function getTitle() {
      return $this->_title;
   }
   
   function getDescription() {
      return $this->_description;
   }
   
   function getVersion() {
      return $this->_version;
   }
   
}

class TitleRenderer {
   
   protected $_application_title;
   
   function setApplicationTitle($application_title) {
      $this->_application_title = $application_title;   
   }
   
   function render() {
      print $this->_application_title->getTitle()."\n";
      print $this->_application_title->getDescription()."\n";
      print "Version: ".$this->_application_title->getVersion()."\n\n";
   }
   
}

class FlashyTitleRenderer extends TitleRenderer {
   
   function render() {
      parent::render();
   }
   
   function setStyle() {
      
   }
   
}

abstract class Application {
   
   protected $_params, $_abbv_to_long = array();
   
   protected $_default_cmd;
   
   function __construct() {
      
      $this->setup();
      $this->printHeader();
      
      $params = $this->_parseInput();
      
      if (isset($params['help'])) {
         $this->printHelp();
      }
      $this->run($params);
      
   }
   
   /**
    * Registers an input parameter for the CLI application
    * @param type $name The name of the input parameter
    * @param type $abbv_param The abbreviated name of the input parameter (accessed via single hyphen)
    * @param type $long_param The long name of the input parameter (accessed via double hyphen)
    * @param type $description A description for the input parameter
    * @param type $default The default value of the input parameter
    * @param type $validation The validation expression for the input parameter
    */
   public function addInputParameter($name, $abbv_param, $long_param, $description, $default, $validation) {
      $param = new Param($this);
      
      if (strlen($abbv_param) > 1 || !ctype_alpha($abbv_param)) {
         $error_msg = '%s: Invalid abbreviated parameter name "%s". Single letter expected...';
         trigger_error(sprintf($error_msg, __FUNCTION__, $abbv_param), E_USER_ERROR);
      }
      if (!ctype_alpha($long_param)) {
         $error_msg = '%s: Invalid long input parameter name "%s"';
         trigger_error(sprintf($error_msg, __FUNCTION__, $long_param), E_USER_ERROR);
      }
      
      $param->setName($name);
      $param->setAbbreviatedArgName($abbv_param);
      $param->setLongArgName($long_param);
      $param->setDescription($description);
      $param->setValidation($validation);
      $param->setValue($default);
      
      $this->_params[$long_param] = $param;
      $this->_abbv_to_long[$abbv_param] = $long_param;
      
   }
   
   /**
    * Parses input arguments from console. Kills program if validation not satisfied 
    * @global type $argv
    * @global type $argc
    * @return array
    */
   private function _parseInput() {
      
      global $argv;
      global $argc;
      $errors = array();
      
      $tmp = NULL;
      
      for($i=1; $i<$argc; $i++) {
         $arg = $argv[$i];
         if (substr($arg, 0, 2) === '--') {
            $arg_name = substr($arg, 2);
            if (isset($this->_params[$arg_name])) {
               $tmp = $arg_name;
            } else {
               $errors[] = sprintf('Invalid argument "%s"', $arg_name);
            }
                    
         } else if (substr($arg, 0, 1) === '-') {
            $arg_name = substr($arg, 1, 1);
            if (isset($this->_abbv_to_long[$arg_name]) &&
                $arg_name = $this->_abbv_to_long[$arg_name]) {
               $tmp = $arg_name;
            }
            else {
               $errors[] = sprintf('Invalid argument "%s"', $arg_name);
            }
            
         } else if ($tmp !== NULL) {
            $this->_params[$tmp]->setValue($argv[$i]);
         }
      }
      
      foreach ($this->_params as $param_long_name=>$param) {
         $ok = $param->validate($err_no, $err_msg);
         if (!$ok) {
            $errors[] = sprintf('Value "%s" invalid for argument "%s" - %s', $param->getValue(), $param_long_name, $err_msg);
         }
      }
            
      if (count($errors)) {
         $this->printErrors($errors);
         exit(1);
      }
      
      return $this->_params;
      
   }
   
   /**
    * Abstract functions to be implemented in child class
    */
   abstract function run($params);
   abstract function setup();
   
   /**
    * Sets the header up by assigning TitleRenderer component
    * @param \CLI\TitleRenderer $title_renderer
    */
   protected function setHeader(TitleRenderer $title_renderer) {
      $this->_title_renderer = $title_renderer;
   }
   
   /**
    * TODO: Default command setup for application 
    * @param type $default_command
    */
   protected function setDefaultCommand($default_command) {
      $this->_default_cmd = $default_command;
   }
   
   /**
    * Prints the application title
    */
   private function printHeader() {
      print $this->_title_renderer->render()."\n";
   }
   
   /**
    * Prints a list of arguments with description and expected values
    */
   private function printHelp() {
      foreach($this->_params as $param) {
         printf("%s", $param->getName());
      }
   }
   
   /**
    * Prints list of errors to console
    * @param type $errors
    */
   private function printErrors($errors) {
      foreach($errors as $error) {
         printf("%s\n", $error);
      }
   }
   
}

/**
 * ReadFile sample application extending abstract Application class
 */
class ReadFile extends Application {
   
   function setup() {
      $application_title = new ApplicationTitle('File Reader Pro', '1.0', 'Utility to open files');
      $ftr = new FlashyTitleRenderer();
      $ftr->setStyle(1);
      $ftr->setApplicationTitle($application_title);
      
      $this->setHeader($ftr);
      $this->addInputParameter('File name', 'f', 'file', 'The file name to read', NULL, 'file|callback[readable]|required');
      $this->addInputParameter('Total lines', 'l', 'lines', 'Total lines to output', '5', 'numeric|required');
      $this->setDefaultCommand('-f -l');
   }
   
   public function callback_readable($input) {
      return array(is_readable($input), 'File could not be read');
   }
   
   function run($params){
      $fp = fopen($params['file']->getValue(), 'r');
      $l = 0;
      while((($line = fgets($fp)) !== FALSE) && ($l < $params['lines']->getValue())) {
         print $line;
         $l++;
      }
      fclose($fp);
      
   }
   
}

$my_app = new ReadFile();

