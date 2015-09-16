<?php
namespace script;

use Composer\Script\Event;
use Composer\Installer\PackageEvent;


class installer
{
    public static function postUpdate(Event $event)
    {

        $composer = $event->getComposer();
        runTasks();

    }


    public static function postPackageUpdate(Event $event)
    {
    $packageName = $event->getOperation()
        ->getPackage()
        ->getName();
    echo "$packageName\n";
    // do stuff
    }

    public static function postPackageInstall(PackageEvent $event)
    {
        $installedPackage = $event->getOperation()->getPackage();
        echo "Script callback - Just installed " . $installedPackage . "\n";

        if (isWordpress($installedPackage)) {
		    runTasks();
		}

    }

    public static function warmCache(Event $event)
    {
    // make cache toasty
    }
}

function isWordpress($installedPackage) {
	if (strpos($installedPackage, 'wordpress/wordpress') !== false) {
	    return true;
	} else {
		return false;
	}
}

function runTasks() {

	$newline = "\n\n";
    echo $newline;

    DEFINE("WP_FOLDER", "core");
    DEFINE("WP_CONTENT", "content");
    DEFINE("WP_CONTENT_PATH", WP_FOLDER . "/" . WP_CONTENT);
    DEFINE("WP_CONFIG_PATH", WP_FOLDER . "/wp-config.php");


    $old_content = "./" . WP_FOLDER . "/" . "wp-content";
    $new_content = "./" . WP_CONTENT_PATH;


    $user_input = true;


    /* MOVE WP-CONFIG FILE */
    if (!file_exists("./" . WP_FOLDER . "/wp-config.php")) {

    	/* WP-CONFIG DOESNT EXIST IN CORE */
    	echo "wp-config.php COULD NOT BE FOUND IN CORE\n";

    	if (file_exists("./wp-config.php")) {

    		/* FILE EXISTS IN ROOT OF PROJECT */

    		echo "FOUND wp-config.php IN ROOT\n";
        	rename("./wp-config.php", "./" . WP_FOLDER . "/wp-config.php");
        	echo "MOVED WP-CONFIG FILE TO CORE FOLDER\n"; 

    	} else {

    		/* NO WP-CONFIG EXISTS ANYWHERE */
    		echo "NO INSTANCE OF wp-config.php COULD BE FOUND ANYWHERE. EXITING\n";

    		if ($user_input) {

    			echo "WILL ATTEMPT TO GERNERATE wp-config.php BASED ON SAMPLE FILE AND USER INPUT\n";
    			generateUserWPConfig();	

    		} else {

    			/* USER INPUT HAS BEEN SET TO FALSE SO SCRIPT WILL NOT PROMPT FOR CUSTOM WP-CONFIG VALUES. EXITING... */
    			echo "USER INPUT HAS BEEN SET TO FALSE SO SCRIPT WILL NOT PROMPT FOR CUSTOM WP-CONFIG VALUES. EXITING...";
    			return;

    		}

    	}

    } else {

    	/* WP-CONFIG ALREADY EXISTS IN CORE */
    	echo "SKIP: wp-config.php FILE ALREADY EXISTS IN CORE\n";

    }

    echo $newline;

    /* MERGE core/wp-content WITH core/content */
    $sourcePath = "core/wp-content";
    $targetPath = "core/content";

    /* IF wp-content FOLDER EXISTS, COPY TO TARGET PATH (THIS HAPPENS BEFORE DELETION BELOW) */
    if (file_exists($sourcePath)) {
    	echo "wp-content FOUND IN " . $sourcePath . "\n";
    	Helper::copy($sourcePath, $targetPath);
    	echo "COPIED CONTENTS OF " . $sourcePath . " TO " . $targetPath . "\n";
    	echo $newline;
    }
	
	/* DELETE UNWANTED FILES (INCLUDING wp-content FOLDER IN CORE) */
    $files = array(
    	"wp-admin",
    	"wp-includes",
    	"wp-content",
    	"license.txt",
    	"readme.html",
    	"wp-activate.php",
    	"wp-blog-header.php",
    	"wp-comments-post.php",
    	"wp-config-sample.php",
    	"wp-cron.php",
    	"wp-links-opml.php",
    	"wp-load.php",
    	"wp-login.php",
    	"wp-mail.php",
    	"wp-settings.php",
    	"wp-signup.php",
    	"wp-trackback.php",
    	"xmlrpc.php",
    	"core/wp-content"
	);

    foreach ($files as $file) {

    	$filename = "./" . $file;
    	if (file_exists($filename)) {
    		echo "REMOVING - " . $filename . $newline;
    		if (is_dir($filename)) {

    			rrmdir($filename);

    		} else {
    			unlink($filename);
    		}
    		
    	}
    }

    if (file_exists("./" . WP_CONFIG_PATH)) {

	    $db_credentials = returnDBCredentials("./". WP_FOLDER . "/wp-config.php");

	    if ($db_credentials != null) {
	    	
		    $homeurl = returnURL($db_credentials["DB_HOST"], $db_credentials["DB_NAME"], $db_credentials["DB_USER"], $db_credentials["DB_PASSWORD"]);

		    if ($homeurl != null) {
		    	DEFINE("HOME_URL", $homeurl);
		    	DEFINE("WP_PATH", HOME_URL . "/" . WP_FOLDER);
		    }

		    if (HOME_URL) {

			    /* APPEND WP-CONTENT RENAME TO WP-CONFIG */
				$content_string = <<<END


// ========================
// Custom Content Directory
// ========================
define ('WP_CONTENT_FOLDERNAME', '
END;
$content_string .= WP_CONTENT;
<<<END
END;
$content_string .= <<<END
');
define ('WP_CONTENT_DIR', ABSPATH . WP_CONTENT_FOLDERNAME);
define ('WP_CONTENT_URL', '
END;
$content_string .= WP_PATH;
<<<END
END;
$content_string .= <<<END
/'.WP_CONTENT_FOLDERNAME);
END;


				/* TODO: THIS NEEDS TO BE UPDATED SO THAT THE WP-CONTENT RENAME APPEND HAPPENS ABOVE DATABASE STUFF */
				appendContentFolder("./" . WP_FOLDER . "/wp-config.php", $content_string);

			} else {

				echo "WARNING: COULD NOT UPDATE wp-config.php WITH SETTINGS FOR NEW wp-content LOCATION (HOME_URL) IS NULL\n";

			}

		} else {

			echo "ERROR: COULD NOT GET DB CREDENTIALS FROM wp-config.php. EXITING...\n";
	    	return;

	    }

	} else {

		echo "ERROR: COULD NOT FIND wp-config.php TO UPDATE wp-content LOCATION\n";

	}


    


    /* UPDATE INDEX PATH */
    $indexfile_path = "./index.php";
    $indexfile_core_path = "./" . WP_FOLDER . "/index.php";

    if (!file_exists($indexfile_path)) {

    	echo "COULD NOT FIND index.php IN ROOT\n";
    	$indexfile_core_path;
    	if (file_exists($indexfile_core_path))
    	{
    		echo "FOUND index.php IN " . WP_FOLDER . "\n";
    		copy($indexfile_core_path, $indexfile_path);
    		echo "COPIED " . $indexfile_path . " TO " . $indexfile_core_path . "\n";
    	} else {

    		echo "ERROR: COULD NOT FIND ANY EXISTANCE OF index.php. SITE STRUCTURE WILL BE BROKEN\n";
    		return;

    	}

    }

    if (file_exists($indexfile_path)) {

    	echo "FOUND index.php IN ROOT\n";

    	$path = returnBlogHeaderPath($indexfile_path);

    	if ($path == "/". WP_FOLDER) {
    		echo "SKIP: BLOG HEADER PATH IN index.php ALREADY RENAMED\n";
    	} else {
    		updateBlogHeaderPath($indexfile_path, "/". WP_FOLDER); 
    		echo "UPDATED index.php BLOG HEADER PATH" . $newline;
    	}
    	//echo "PRECEEDING TEXT IS!!! " . returnBlogHeaderPath($indexfile_path) . $newline . $newline;
    }


    /* UPDATE WP-CONTENT FOLDER NAME */
    /* EDIT: THIS IS OBSELETE AS wp-content IS DELETED AFTER IT'S CONTENTS ARE TRANSFERRED REMOVED */
    // if(file_exists($old_content)) {

    // 	if(file_exists($new_content)) {

	   //  	if (rename($old_content, $new_content)) {
	   //  		echo "RENAMED " . $old_content . " TO " . $new_content . $newline;
	   //  	} else {
	   //  		echo "ERROR RENAMING!" . $newline;
	   //  	}

    // 	} else {
    // 		echo "ERROR: WP-CONTENT AND NEW/RENAMED WP-CONTENT FOLDER ALREADY EXISTS. THEREFORE WP-CONTENT CANNOT BE RENAMED TO NEW" . $newline;
    // 	}
    	
    // } else {
    // 	echo "SKIP: WP-CONTENT FOLDER HAS ALREADY BEEN RENAMED" . $newline;
    // }


    /* UPDATE DATABASE */
    
	if ($db_credentials) {
		if (WP_PATH) {
			updateDatabase($db_credentials["DB_HOST"], $db_credentials["DB_NAME"], $db_credentials["DB_USER"], $db_credentials["DB_PASSWORD"], WP_PATH);
		} else {
			echo "WARNING : SKIPPING DATABASE UPDATE AS WP_PATH DOES NOT CONTAIN A VALID VALUE";
		}
	}
}

function generateUserWPConfig() {

	$sample_file = "./" . WP_FOLDER . "/wp-config-sample.php";

	if (file_exists($sample_file)) {

		copy($sample_file, "./" . WP_CONFIG_PATH);
		echo "COPIED SAMPLE FILE TO " . WP_CONFIG_PATH . "\n";

		echo "\n";

		echo "PLEASE ENTER YOUR HOST ADDRESS: ";
		$db_host_custom = trim(fgets(STDIN));

		echo "\n";

		echo "PLEASE ENTER YOUR DATABASE NAME: ";
		$db_name_custom = trim(fgets(STDIN));

		echo "\n";
		
		echo "PLEASE ENTER YOUR DB USERNAME: ";
		$db_user_custom = trim(fgets(STDIN));

		echo "\n";

		echo "PLEASE ENTER YOUR DB PASSWORD: ";
		$db_password_custom = trim(fgets(STDIN));

		echo "\n";


		$config_contents = file_get_contents($sample_file);

		if ($config_contents != null && $config_contents != "") {

			$config_contents = str_replace("localhost", $db_host_custom, $config_contents);
			$config_contents = str_replace("database_name_here", $db_name_custom, $config_contents);
			$config_contents = str_replace("username_here", $db_user_custom, $config_contents);
			$config_contents = str_replace("password_here", $db_password_custom, $config_contents);

			//echo $db_host_custom;

			file_put_contents("./" . WP_CONFIG_PATH, $config_contents);
		}	

	}
}

function returnURL($servername, $dbname, $username, $password) {

	$newline = "\n\n";

    echo $newline . "CONNECTING TO DATABASE\n";

	if (!function_exists('mysqli_init') && !extension_loaded('mysqli')) {

	    echo 'MYSQL IS NOT INSTALLED - EXITING..' . $newline;

	} else {

	    echo 'MYSQL IS INSTALLED' . "\n";
	    $conn = mysqli_connect($servername, $username, $password, $dbname);
		// Check connection
		if (!$conn) {
		    die("CONNECTION FAILED: " . mysqli_connect_error());
		}
		echo 'CONNECTED TO WORDPRESS DATABASE - ' . $servername . " : " . $dbname . "\n";


		$sqlread = "SELECT option_value FROM wp_options WHERE option_name = 'home'";
		
		$result = $conn->query($sqlread)->fetch_row()[0];
		

		if ($result != null || $result != "") {

			echo "CURRENT HOME URL : " . $result . "\n";
			mysqli_close($conn);
			return $result;

		} else {

			echo "WARNING: CURRENT HOME URL IS NULL\n";
			mysqli_close($conn);
			return null;

		}

	}	
}

/* =========== HELP! =========== */
/* THIS FUNCTION TAKES THE CONTENTS OF THE WP-CONFIG FILE AND APPENDS THE DECLARATION OF THE RENAME OF WP-CONTENT (this code is passed in $appendtext variable) */
/* I AM ABLE TO APPEND THE TEXT TO THE BOTTOM OF THE DOCUMENT HOWEVER IT NEEDS TO BE CLOSER TO THE TOP OF THE WP-CONFIG FILE IN ORDER FOR IT TO WORK */
/* I AM TRYING TO USE REGEX TO SPLIT THE DOCUMENT INTO 2 ARRAYS. THE FIRST ARRAY SHOULD CONTAIN THE <?php DECLARATION ALONG WITH THE MULTILINE COMMENT BLOCK */
/* THE SECOND ARRAY SHOULD CONTAIN EVERYTHING AFTER THAT BUT IN BETWEEN I WANT TO PLACE THE $appendtext IN BETWEEN */

function appendContentFolder($wpconfig_file, $appendtext) {

	if (file_exists($wpconfig_file)) {
		$contents = file_get_contents($wpconfig_file);

		if (!preg_match("/.WP_CONTENT_DIR./", $contents)) {

			/* HAD TO DECLARE MY PATTERN LIKE THIS AS PHP WAS COMPLAINING ABOUT CERTAIN CHARS */
			$regex_string  = <<<END
/<\?php\n\/\*+([^\*][^*]*\*?)*\*\//
END;

			/* SPLIT THE CONTENTS OF THE WP_CONFIG FILE BASED ON THE REGEX PATTERN */
			//$split = preg_split($regex_string, $contents);

			/* I'M ABLE TO GET THE SECOND ($split[1]) VALUE BUT $split[0] IS NULL */
			//echo "CAN GET FIRST HALF ==== " . $split[1] . "\n";


			//$contents = preg_replace("/" . $split[1] . "/", $appendtext . $split[1], $contents);

			//$contents = $split[0] . $appendtext . $split[1];

			/* FOR NOW LET'S JUST APPEND THE TEXT TO THE BOTTOM OF THE DOCUMENT */
			$contents .= $appendtext;
			file_put_contents($wpconfig_file, $contents);
			echo 'UPDATED WP-CONFIG WITH RENAMED WP-CONTENT PATH';

		} else {
			echo "SKIP: WP-CONFIG FILE ALREADY CONTAINS RENAMED WP-CONTENT\n";
		}

		
	} else {
		echo "ERROR: COULD NOT FIND WP-CONFIG WHEN ATTEMPTING TO APPEND TEXT";
	}
}


function returnBlogHeaderPath($index_file) {

	$contents = file_get_contents($index_file);
	$pattern = "/(?<=')(.*?)(?=\/wp-blog-header.php)/";

	if(preg_match_all($pattern, $contents, $matches)) {
		$match = implode("\n", $matches[0]);
		return $match;
	}

}

function updateBlogHeaderPath($index_file, $path) {

	$contents = file_get_contents($index_file);
	$pattern = "/(?<=')(.*?)(?=\/wp-blog-header.php)/";

	$contents_new = preg_replace($pattern, $path, $contents);

	file_put_contents($index_file, $contents_new);
	

}

function returnDBCredentials($wpconfig_file) {

	$credentials = array();
	$wp_db_host = null;
	$wp_db_name = null;
	$wp_db_user = null;
	$wp_db_password = null;

	if (file_exists($wpconfig_file)) {

		echo "FOUND WP-CONFIG FILE AT : " . $wpconfig_file  . "\n";

		$contents = file_get_contents($wpconfig_file);

		/* GET DB_HOST FROM WP-CONFIG */
		$pattern = "/(?<=DB_HOST', ')(.*?)(?='\);)/";
		if(preg_match_all($pattern, $contents, $matches)) {
			$match = implode("\n", $matches[0]);
			echo "FOUND DB_HOST - " . $match . "\n";
			$wp_db_host = $match;
		}
		else{
		   echo "ERROR: COULDN'T FIND 'DB_HOST' IN WP-CONFIG\n";
		}

		/* GET DB_NAME FROM WP-CONFIG */
		$pattern = "/(?<=DB_NAME', ')(.*?)(?='\);)/";
		if(preg_match_all($pattern, $contents, $matches)) {
			$match = implode("\n", $matches[0]);
			echo "FOUND DB_NAME - " . $match . "\n";
			$wp_db_name = $match;
		}
		else{
		   echo "ERROR: COULDN'T FIND 'DB_NAME' IN WP-CONFIG\n";
		}

		/* GET DB_USER FROM WP-CONFIG */
		$pattern = "/(?<=DB_USER', ')(.*?)(?='\);)/";
		if(preg_match_all($pattern, $contents, $matches)) {
			$match = implode("\n", $matches[0]);
			echo "FOUND DB_USER - " . $match . "\n";
			$wp_db_user = $match;
		}
		else{
		   echo "ERROR: COULDN'T FIND 'DB_USER' IN WP-CONFIG\n";
		}

		/* GET DB_PASSWORD FROM WP-CONFIG */
		$pattern = "/(?<=DB_PASSWORD', ')(.*?)(?='\);)/";
		if(preg_match_all($pattern, $contents, $matches)) {
			$match = implode("\n", $matches[0]);
			echo "FOUND DB_PASSWORD - " . $match . "\n";
			$wp_db_password = $match;
		}
		else{
		   echo "ERROR: COULDN'T FIND 'DB_PASSWORD' IN WP-CONFIG\n";
		}

		if ($wp_db_host != null && $wp_db_name != null && $wp_db_user != null && $wp_db_password != null) {
			$credentials = array(
				"DB_HOST"		=> $wp_db_host,
				"DB_NAME"		=> $wp_db_name,
				"DB_USER"		=> $wp_db_user,
				"DB_PASSWORD"	=> $wp_db_password
			);
			return $credentials;
		} else {
			echo "ERROR: COULD NOT FIND ALL DB CONNECTION VALUES IN wp-config.php" . "\n" . "\n";
			return null;
		}
	} else {
		echo "ERROR: COULD NOT FIND WP-CONFIG FILE" . "\n" . "\n";
		return null;
	}

}

function updateDatabase($servername, $dbname, $username, $password, $siteurl_new) {

	$newline = "\n\n";

    echo $newline . "CONNECTING TO DATABASE\n";

	if (!function_exists('mysqli_init') && !extension_loaded('mysqli')) {

	    echo 'MYSQL IS NOT INSTALLED - EXITING..' . $newline;

	} else {

	    echo 'MYSQL IS INSTALLED' . "\n";
	    $conn = mysqli_connect($servername, $username, $password, $dbname);
		// Check connection
		if (!$conn) {
		    die("CONNECTION FAILED: " . mysqli_connect_error());
		}
		echo 'CONNECTED TO WORDPRESS DATABASE - ' . $servername . " : " . $dbname . "\n";


		$sqlread = "SELECT option_value FROM wp_options WHERE option_name = 'siteurl'";
		$sqlwrite = "UPDATE wp_options SET option_value = '" . $siteurl_new . "' WHERE option_name = 'siteurl'";
		
		$result = $conn->query($sqlread)->fetch_row()[0];
		echo "CURRENT SITEURL : " . $result . "\n";


		if ($result != $siteurl_new) {

			if (mysqli_query($conn, $sqlwrite)) {
		    	echo "SITE URL CHANGE TO " . $siteurl_new . " SUCCESSFULLY\n";
			} else {
			    echo "ERROR UPDATING SITE URL : " . mysqli_error($conn) . "\n";
			}

		} else {
			echo "SITEURL CHANGE NOT REQUIRED\n";
		}
		
		mysqli_close($conn);
	}
}

function rrmdir($dir) {
   if (is_dir($dir)) {
     $objects = scandir($dir);
     foreach ($objects as $object) {
       if ($object != "." && $object != "..") {
         if (filetype($dir."/".$object) == "dir"){
            rrmdir($dir."/".$object);
         }else{ 
            unlink($dir."/".$object);
         }
       }
     }
     reset($objects);
     rmdir($dir);
  }
}

class Helper {

    static function copy($source, $target) {
        if (!is_dir($source)) {//it is a file, do a normal copy
            copy($source, $target);
            return;
        }

        //it is a folder, copy its files & sub-folders
        @mkdir($target);
        $d = dir($source);
        $navFolders = array('.', '..');
        while (false !== ($fileEntry=$d->read() )) {//copy one by one
            //skip if it is navigation folder . or ..
            if (in_array($fileEntry, $navFolders) ) {
                continue;
            }

            //do copy
            $s = "$source/$fileEntry";
            $t = "$target/$fileEntry";
            self::copy($s, $t);
        }
        $d->close();
    }

}
