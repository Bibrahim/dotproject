<?php  // $Id$
class Cinstall {

//  @var bool Did we create the db successfully?
var $dbConfigured = false;

//  @var bool Did we create the db successfully?
var $dbCreated = false;

//  @var bool Did we populate the db successfully?
var $dbPopulated = false;

//  @var bool Did we create the cfg file successfully?
var $cfgFileCreated = false;

//  @var bool Is config.php writable?
var $cfgFileWritable = false;

//  @var array Dynamic Container for array keys of booleans in config array
var $boolcfg = array();

//  @var array Dynamic Container for config data
var $cfg = array();

//  @var array List of available ADODB db drivers
var $dbDrivers = array();

//  @var array Dynamic Container for file parser config data
var $ft = array();

//  @var array Persistent Container for original configuration data
var $dPcfg = array();

//  @var array Container for various informations needed in install process
var $various = array();

//	@var Object permissions container for providing dummy permissions with installer
var $_acl = null;

	// Constructor: populate config arrays
	function Cinstall() {

                if ($GLOBALS["dPrunLevel"] == 0) {             // config.php is not available, nor $dPconfig

                        /* we load config values from config-dist.php
                        ** and override some wrong values with guessed information.
                        ** We also have to set $dPconfig.
                        */

                        // initialize class config array
                        $this->cfg = $this->getCfgFile();

                        // register config vars of boolean type
                        $this->registerBools();

                        // initialize file parser info
                        $this->ft = $this->getFTFile();

                        // keep a copy of original config data here
                        $this->dPcfg = $this->cfg;
                        $this->dPft = $this->ft;

                        // guess and override some run critical cfg values
                        $this->guessImportantCfg();

                        // initialize global config values
                        $this->updateDPcfg( $this->cfg );
                        $this->updateDPft( $this->ft );

                } else {                                        // config.php available, hence we have $dPconfig

                        // we load config values from config.php
                        $this->dPcfg = $this->cfg = $GLOBALS["dPconfig"];
                        $this->dPft = $this->ft = $GLOBALS["ft"];

                        // config.php is already there
                        $this->cfgFileCreated = true;

                        // DB is there if dPrunLevel > 0
                        if ($GLOBALS["dPrunLevel"] > 1) {
                                $this->dbCreated = true;
                        }
                // override the host style (only the default style is tweaked to run without database)
                $GLOBALS["dPconfig"]['host_style'] = "default";

                }

		// discover and determine writability of config.php
		$this->cfgFileWritable = $this->isCfgFileWritable();

                // create a list of available database Driver Names
                $this->dbDrivers = array ( "access" => "access", "ado"=> "ado", "ado_access"=> "ado_access", "ado_mssql"=> "ado_mssql",
                                "db2" => "db2", "vfp" => "vfp", "fbsql" => "fbsql", "ibase" =>"ibase", "firebird" => "firebird",
                                "borland_ibase" => "borland_ibase", "informix" => "informix", "informix72" => "informix72",
                                "ldap" => "ldap", "mssql" =>"mssql", "mssqlpro" =>"mssqlpro", "mysql" => "mysql", "mysqlt" => "mysqlt",
                                "maxsql" => "maxsql", "oci8" => "oci8", "oci805" => "oci805", "oci8po" => "oci8po", "odbc" => "odbc",
                                "odbc_mssql" => "odbc_mssql", "odbc_oracle" => "odbc_oracle", "odbt" => "odbt", "odbt_unicode" => "odbt_unicode",
                                "oracle" => "oracle", "netezza" => "netezza", "postgres" => "postgres", "postgres64" => "postgres64",
                                "postgres7" => "postgres7", "sapdb" => "sapdb", "sqlanywhere" => "sqlanywhere", "sqlite" => "sqlite",
                                "sqlitepo" => "sqlitepo", "sybase" => "sybase" );

	}

        /*
        * Prepare Config Values from config array for File Output
        * @return $config string Code output for dP-like config file
        */
	function cfgFilePrepare() {
                $config = "<?php \n";
		$config .= "### Copyright (c) 2004, The dotProject Development Team dotproject.net and sf.net/projects/dotproject ###\n";
		$config .= "### All rights reserved. Released under BSD License. For further Information see ./includes/config-dist.php ###\n";
		$config .= "\n";
		$config .= "### CONFIGURATION FILE AUTOMATICALLY GENERATED BY THE DOTPROJECT INSTALLER ###\n";
		$config .= "### FOR INFORMATION ON MANUAL CONFIGURATION AND FOR DOCUMENTATION SEE ./includes/config-dist.php ###\n";
		$config .= "\n";
		$keys = array_keys($this->cfg);
		foreach ($keys as $k) {
                        // convert empty booleans to false (vars for unchecked checkboxes are empty)
                        if ( in_array( "$k", $this->boolcfg ) ) {
                                if ($this->cfg["$k"] > null) {
                                         $config .= "\$dPconfig['{$k}'] = true;\n";
                                }
                                else {
                                         $config .= "\$dPconfig['{$k}'] = false;\n";
                                }
                        } else {
			        $config .= "\$dPconfig['{$k}'] = \"{$this->cfg["$k"]}\";\n";
                        }
		}
		$keys = array_keys($this->ft);
		foreach ($keys as $k) {
			$config .= "\$ft['{$k}'] = \"{$this->ft["$k"]}\";\n";
		}
                $config .= "?>";

		return trim($config);

	}

        /*
        * write config data to config file, use therefore config parameter or values prepared by Cinstall::cfgFilePrepare()
        * @param $file string filename
        * @param $config array Config values to write
        * @return bool File written successfully?
        */
	function cfgFileStore( $file = "./includes/config.php", $config = null ) {
		if (empty($config)) {
			$config = $this->cfgFilePrepare();
		}

		if ($this->isCfgFileWritable($file) && ($fp = fopen($file, "w"))) {
			fputs( $fp, $config, strlen( $config ) );
			fclose( $fp );
			$this->cfgFileCreated = true;
		} else {
			$this->cfgFileWritable = false;
			$this->cfgFileCreated = false;
		}
		return $this->cfgFileCreated;
	}

        /*
        * check if config file is writable
        * @return bool
        */
	function isCfgFileWritable( $file = "./includes/config.php" ) {
		return is_writable($file);
	}

         /*
        * discover and save of boolean values in config array
        */
        function registerBools() {
		$keys = array_keys($this->cfg);
		foreach ($keys as $k) {
                        if (is_bool($this->cfg["$k"]))  {
                                $this->boolcfg[] = "$k";
                        }
		}
	}

        /*
        * bind post data to various infos array necessary to be transported by post and stored in session data
        * @param $data array Array of postdata (of the form various[key])
        */
	function bindToVarious( $data = array() ) {
		$keys = array_keys($data);
		foreach ($keys as $k) {
			$this->various["$k"] = trim($data["$k"]);
		}
	}

         /*
        * bind post data to ft array
        * @param $data array Array of postdata (of the form ft[key])
        */
	function bindToFT( $data = array() ) {
		$keys = array_keys($data);
		foreach ($keys as $k) {
			$this->ft["$k"] = trim($data["$k"]);
		}
	}

        /*
        * bind post data to config array
        * @param $data array Array of postdata (of the form pd[key])
        */
	function bindPost( $data = array() ) {
                // get array keys for boolean config vars
                $bc = $this->boolcfg;
		$keys = array_keys($data);
		foreach ($keys as $k) {
			$this->cfg["$k"] = trim($data["$k"]);
		}
                // create a list of unsent boolean config vars (estimated to be false)
                $bn = array_unique(array_diff($bc, $keys));
                // set unsent/false config data to null
                foreach ($bn as $bk) {
                        $this->cfg["$bk"] = null;
                }
	}

        /*
        * read existing config data from config file
        * @param $file string filename
        * @return $dPconfig array Config array
        */
	function getCfgFile( $file = "./includes/config-dist.php" ) {

		is_file( $file )
		or die("$file is not available. It is needed for guessing some config values for installation procedure.
		Therefore you should never delete or modify it.Please restore this file.");

		// include the standard config values
		include_once( $file );

		return $dPconfig;
	}

        /*
        * read existing file parser info from config file
        * @param $file string filename
        * @return $ft array Config array
        */
	function getFTFile( $file = "./includes/config-dist.php" ) {

		is_file( $file )
		or die("$file is not available. It is needed for guessing some config values for installation procedure.
		Therefore you should never delete or modify it.Please restore this file.");

		// include the standard config values
		include( $file );

		return $ft;
	}


	function updateDPcfg ( $data = array() ) {
		global $dPconfig;
		$dPconfig = array_merge($dPconfig, $data);
	}

	function updateDPft ( $data = array() ) {
		global $ft;
		$ft = array_merge($ft, $data);
	}

	function updateDPcfgFromPost ( $data = array() ) {
		global $dPconfig;
		$keys = array_keys($data);
		foreach ($keys as $k) {
			$dPconfig["$k"] = trim($data["$k"]);
		}
	}

	function updateDPftFromPost ( $data = array() ) {
		global $ft;
		$keys = array_keys($data);
		foreach ($keys as $k) {
			$ft["$k"] = trim($data["$k"]);
		}
	}

        /*
        * override some possibly wrong mission critical config values
        */
	function guessImportantCfg() {
		$this->cfg['root_dir'] = realpath("./");
		$this->cfg['base_url'] = "http://".$_SERVER["SERVER_NAME"].str_replace("/index.php", "", $_SERVER["PHP_SELF"]);
	}

        /*
        * check if adodb connection is up
        * @return bool
        */
	function isADODBconnected() {
		global $db;
		return (is_object($db));
	}

        /*
        * check if adodb database connection and selection is up
        * @return bool
        */
	function isDBconnected() {
		global $dbc;
		return $dbc;
	}

        /*
        * encapsulate adodb connection
        * @return bool
        */
	function ADODBconnect( $dbtype = null ) {
		global $db;
		$dPconfig['dbtype'] = empty($dbtype) ? $dbtype : $this->cfg['dbtype'];
		include_once("./includes/db_adodb.php");
		return $db;
	}

        /*
        * encapsulate adodb database selection and connection
        * @return bool
        */
	function DBconnect() {
		global $db, $dbc;
		if(!empty($db)) {
			if ($this->cfg['persist']) {
				$dbc = $db->PConnect($this->cfg['dbost'],$this->cfg['dbuser'],$this->cfg['dbpass'],$this->cfg['dbname']);
			} else {
				$dbc = $db->Connect($this->cfg['dbost'],$this->cfg['dbuser'],$this->cfg['dbpass'],$this->cfg['dbname']);
			}
		} else { $dbc = false; }
		return $dbc;
	}

        /*
        * creates a database
        * @return bool
        */
	function createDB( $dbname = null ) {
		$dbname = !empty($dbname) ? $dbname : $this->cfg['dbname'];
		return db_exec("CREATE DATABASE ".$dbname);
	}

        /*
        * poulates the database with SQL from file
        * @param $sqlfile string Filename
        * @return bool
        */
	function populateDB($sqlfile = "./db/dotproject.sql") {
		if( !$this->isDBconnected() ) {
			return false;
		}
		$mqr = @get_magic_quotes_runtime();
		@set_magic_quotes_runtime(0);
		$query = fread(fopen($sqlfile, "r"), filesize($sqlfile));
		@set_magic_quotes_runtime($mqr);
		$pieces  = $this->splitSql($query);
		$errors = array();
		for ($i=0; $i<count($pieces); $i++) {
			$pieces[$i] = trim($pieces[$i]);
			if(!empty($pieces[$i]) && $pieces[$i] != "#") {
				if (!$result = db_exec($pieces[$i])) {
					$errors[] = array ( db_error(), $pieces[$i] );
				}
			}
		}
		return true;
	}

        /*
        * Utility function to split given SQL-Code
        * @param $sql string SQL-Code
        */
	function splitSql($sql) {
		$sql = trim($sql);
		$sql = ereg_replace("\n#[^\n]*\n", "\n", $sql);

		$buffer = array();
		$ret = array();
		$in_string = false;

		for($i=0; $i<strlen($sql)-1; $i++) {
			if($sql[$i] == ";" && !$in_string) {
				$ret[] = substr($sql, 0, $i);
				$sql = substr($sql, $i + 1);
				$i = 0;
			}

			if($in_string && ($sql[$i] == $in_string) && $buffer[1] != "\\") {
				$in_string = false;
			}
			elseif(!$in_string && ($sql[$i] == '"' || $sql[$i] == "'") && (!isset($buffer[0]) || $buffer[0] != "\\")) {
				$in_string = $sql[$i];
			}
			if(isset($buffer[1])) {
				$buffer[0] = $buffer[1];
			}
			$buffer[1] = $sql[$i];
		}

		if(!empty($sql)) {
			$ret[] = $sql;
		}
		return($ret);
	}

         /*
        * Generate SQL-File with Structure and Content from Database
        * @param $sql string SQL-Code
        */
	function generateBackupSQL( $backupdrop = false ) {
                global $db, $dbc;
                if( !$this->isDBconnected() ) {
			return false;
		} else {

                        $tables = $db->MetaTables();
                        $si = $db->ServerInfo();

                        // generate dbScriptHeader
                        $output  = '';
                        $output .= '# Backup of database \'' . $this->cfg['dbname'] . '\'' . "\r\n";
                        $output .= '# Generated on ' . date('j F Y, H:i:s') . "\r\n";
                        $output .= "# Generator : dotProject Installer \r\n";
                        $output .= '# OS: ' . PHP_OS . "\r\n";
                        $output .= '# PHP version: ' . PHP_VERSION . "\r\n";
                        $output .= '# SQL Server Type: ' . $this->cfg['dbtype'] . "\r\n";
                        $output .= '# SQL Server Version: ' . $si['version'] . "\r\n";
                        $output .= "\r\n";
                        $output .= "\r\n";

                        foreach ($tables as $t) {
                               /* echo $t;
                                $rs = $db->Execute("SELECT * FROM $t");
                                echo $db->GetUpdateSQL($rs, array("id" => "100"));
                                $output .= $db->GetUpdateSQL($rs, array());*/

                        }


                }
                /*
                // fetch all tables one by one
                while ($row = mysql_fetch_row($alltables))
                {
                        // introtext for this table
                        $output .= '# TABLE: ' . $row[0] . "\r\n";
                        $output .= '# --------------------------' . "\r\n";
                        $output .= '#' . "\r\n";
                        $output .= "\r\n";


                        if ($backupdrop == true)
                        {
                                // drop table
                                $output .= 'DROP TABLE IF EXISTS `' . $row[0] . '`;' . "\r\n";
                                $output .= "\r\n";
                        }




                        // structure of the table
                        $table = mysql_query('SHOW CREATE TABLE ' . $row[0]);
                        $create = mysql_fetch_array($table);

                        // replace UNIX enter by Windows Enter for readability in Windows
                        $output .= str_replace("\n","\r\n",$create[1]).';';
                        $output .= "\r\n";
                        $output .= "\r\n";


                        $fields = mysql_list_fields($dbname, $row[0]);
                        $columns = mysql_num_fields($fields);

                        // all data from table
                        $result = mysql_query('SELECT * FROM '.$row[0]);
                        while($tablerow = mysql_fetch_array($result))
                                {
                                $output .= 'INSERT INTO `'.$row[0].'` (';
                                for ($i = 0; $i < $columns; $i++)
                                {
                                        $output .= '`'.mysql_field_name($fields,$i).'`,';
                                }
                                $output = substr($output,0,-1); // remove last comma
                                $output .= ') VALUES (';
                                for ($i = 0; $i < $columns; $i++)
                                {
                                        // remove all enters from the field-string. MySql statement must be on one line
                                        $value = str_replace("\r\n",'\n',$tablerow[$i]);
                                        // replace ' by \'
                                        $value = str_replace('\'',"\'",$value);
                                        $output .= '\''.$value.'\',';
                                }
                                $output = substr($output,0,-1); // remove last comma
                                $output .= ');' . "\r\n";
                                } // while
                        $output .= "\r\n";
                        $output .= "\r\n";

                } //end of while clause

                */
               /* $file = 'backup.sql';
                $mime_type = 'text/sql';
                header('Content-Disposition: inline; filename="' . $file . '"');
                header('Content-Type: ' . $mime_type);
                echo $output;
                        */

                //return $output;
        }

				function & acl() {
					if (! isset($this->_acl))
						$this->_acl =& new InstallerPermissions;
					return $this->_acl;
				}

}

class InstallerPermissions {
	function checkModule($modname, $method, $user_id = null) {
		return true;
	}
}
?>
