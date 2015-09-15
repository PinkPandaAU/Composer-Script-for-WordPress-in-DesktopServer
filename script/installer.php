<?php
namespace script;

use Composer\Script\Event;


class installer
{
    public static function postUpdate(Event $event)
    {

        $composer = $event->getComposer();

        $newline = "\n\n";
        echo $newline;

        DEFINE("WP_FOLDER", "core");
        DEFINE("WP_CONTENT", "content");


        $old_content = "./" . WP_FOLDER . "/" . "wp-content";
        $new_content = "./" . WP_FOLDER . "/" . WP_CONTENT;


        /* MOVE WP-CONFIG FILE */
        if (!file_exists("./" . WP_FOLDER . "/wp-config.php")) {
        	if (file_exists("./wp-config.php")) {
	        	rename("./wp-config.php", "./" . WP_FOLDER . "/wp-config.php");
	        	echo "MOVED WP-CONFIG FILE TO WORDPRES FOLDER" . $newline; 
        	}
        	echo "SKIP: WP-CONFIG FILE ALREADY MOVED\n";
        }


        $db_credentials = returnDBCredentials("./". WP_FOLDER . "/wp-config.php");
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

		}


        /* DELETE UNWANTED FILES */
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
        	"xmlrpc.php"
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


        /* UPDATE INDEX PATH */
        $indexfile_path = "./index.php";
        if (file_exists($indexfile_path)) {

        	$path = returnBlogHeaderPath($indexfile_path);

        	if ($path == "/". WP_FOLDER) {
        		echo "SKIP: BLOG HEADER PATH IN INDEX.PHP ALREADY RENAMED\n";
        	} else {
        		updateBlogHeaderPath($indexfile_path, "/". WP_FOLDER); 
        		echo "UPDATED INDEX.PHP BLOG HEADER PATH" . $newline;
        	}
        	//echo "PRECEEDING TEXT IS!!! " . returnBlogHeaderPath($indexfile_path) . $newline . $newline;
        }


        /* UPDATE WP-CONTENT FOLDER NAME */
        if(file_exists($old_content)) {
        	rename($old_content, $new_content);
        	echo "RENAMED " . $old_content . " TO " . $new_content . $newline;
        } else {
        	echo "SKIP: WP-CONTENT FOLDER HAS ALREADY BEEN RENAMED" . $newline;
        }


        /* UPDATE DATABASE */
        
		if ($db_credentials) {
			if (WP_PATH) {
				updateDatabase($db_credentials["DB_HOST"], $db_credentials["DB_NAME"], $db_credentials["DB_USER"], $db_credentials["DB_PASSWORD"], WP_PATH);
			} else {
				echo "WARNING : SKIPPING DATABASE UPDATE AS WP_PATH DOES NOT CONTAIN A VALID VALUE";
			}
		}

    }

    

    public static function postPackageUpdate(Event $event)
    {
    $packageName = $event->getOperation()
        ->getPackage()
        ->getName();
    echo "$packageName\n";
    // do stuff
    }

    public static function warmCache(Event $event)
    {
    // make cache toasty
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
			return $result;

		} else {

			echo "WARNING: CURRENT HOME URL IS NULL\n";
			return null;

		}
		
		mysqli_close($conn);
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
			$split = preg_split($regex_string, $contents);

			/* I'M ABLE TO GET THE SECOND ($split[1]) VALUE BUT $split[0] IS NULL */
			echo "CAN GET FIRST HALF ==== " . $split[1] . "\n";


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
	$wp_db_host;
	$wp_db_name;
	$wp_db_user;
	$wp_db_password;

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
			echo "ERROR: RETURNING NULL FROM WP-CONFIG. COULD NOT FIND ALL VALUES IN FILE" . "\n" . "\n";
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