<?php
// database.php - this is the database interface
// functions commented with /**/ were not created but modified by me.
// This is the database portion of a nice mvc template called, 
// "jpmaster77's login system" that is a very easy to expand upon system
// This was completely converted over to mysql pdo as of 2013
include("constants.php");
      
class MySQLDB {
   var $connection;         //The MySQL database connection
   var $num_active_users;   //Number of active users viewing site
   var $num_active_guests;  //Number of active guests viewing site
   var $num_members;        //Number of signed-up users

   /* Class constructor */
   function MySQLDB(){
      /* Make connection to database */
    try {
   		# MySQL with PDO_MYSQL
		$this->connection = new PDO('mysql:host='.DB_SERVER.';dbname='.DB_NAME, DB_USER, DB_PASS);
   		$this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
   	} catch(PDOException $e) {  
		echo "Error connecting to database.";   
	}
      
      /**
       * Only query database to find out number of members
       * when getNumMembers() is called for the first time,
       * until then, default value set.
       */
      $this->num_members = -1;
      
      if(TRACK_VISITORS){
         /* Calculate number of users at site */
         $this->calcNumActiveUsers();
      
         /* Calculate number of guests at site */
         $this->calcNumActiveGuests();
      }
   }

    /**
    * confirmUserPass - Checks whether or not the given username is in the database, 
    * if so it checks if the given password is the same password in the database
    * for that user. If the user doesn't exist or if the passwords don't match up, 
    * it returns an error code (1 or 2). On success it returns 0.
    */
   function confirmUserPass($username, $password){
      /* Add slashes if necessary (for query) */
      if(!get_magic_quotes_gpc()) {
	      $username = addslashes($username);
      }

      /* Verify that user is in database */
	  $query = "SELECT password FROM ".TBL_USERS." WHERE username = :username";
	  $stmt = $this->connection->prepare($query);
	  $stmt->execute(array(':username' => $username));
      $count = $stmt->rowCount();
    
	  if(!$stmt || $count < 1){
        return 1; //Indicates username failure
      }

      /* Retrieve password from result, strip slashes */
      $dbarray = $stmt->fetch(PDO::FETCH_ASSOC);

      /* Validate that password is correct */
      if(crypt(sha1($password),$dbarray['password']) == $dbarray['password']){
         return 0; //Success! Username and password confirmed
      }
      else{
         return 2; //Indicates password failure
      }
   }
   
   /**
    * confirmUserID - Checks whether or not the given username is in the database, 
    * if so it checks if the given userid is the same userid in the database
    * for that user. If the user doesn't exist or if the userids don't match up, 
    * it returns an error code (1 or 2). On success it returns 0.
    */
   function confirmUserID($username, $userid){
      /* Add slashes if necessary (for query) */
      if(!get_magic_quotes_gpc()) {
	      $username = addslashes($username);
      }
      
      /* Verify that user is in database */
      $query = "SELECT userid FROM ".TBL_USERS." WHERE username = :username";
      $stmt = $this->connection->prepare($query);
      $stmt->execute(array(':username' => $username));
      $count = $stmt->rowCount();
      
      if(!$stmt || $count < 1){
         return 1; //Indicates username failure
      }

      /* Retrieve userid from result, strip slashes */
      $dbarray = $stmt->fetch(); 
      $dbarray['userid'] = stripslashes($dbarray['userid']);
      $userid = stripslashes($userid);

      /* Validate that userid is correct */
      if($userid == $dbarray['userid']){
         return 0; //Success! Username and userid confirmed
      }
      else{
         return 2; //Indicates userid invalid
      }
   }
   
   /**
    * usernameTaken - Returns true if the username has been taken by another user, false otherwise.
    */
   function usernameTaken($username){
      if(!get_magic_quotes_gpc()){ $username = addslashes($username); }
	  $query = "SELECT username FROM ".TBL_USERS." WHERE username = :username";
	  $stmt = $this->connection->prepare($query);
	  $stmt->execute(array(':username' => $username));
	  $count = $stmt->rowCount();    
      return ($count > 0);
   }
    
   /**
    * usernameBanned - Returns true if the username has been banned by the administrator.
    */
   function usernameBanned($username){
      if(!get_magic_quotes_gpc()){ $username = addslashes($username); }
      $query = "SELECT username FROM ".TBL_BANNED_USERS." WHERE username = :username";
	  $stmt = $this->connection->prepare($query);
	  $stmt->execute(array(':username' => $username));
	  $count = $stmt->rowCount();    
      return ($count > 0);
   }
   
   /**
    * addNewUser - Inserts the given (username, password, email)
    * info into the database. Appropriate user level is set.
    * Returns true on success, false otherwise.
    */
   function addNewUser($username, $password, $userid){
      $time = time();
      /* If admin sign up, give admin user level */
      if(strcasecmp($username, ADMIN_NAME) == 0){
         $ulevel = ADMIN_LEVEL;
      }else{
         $ulevel = USER_LEVEL;
      }
      $query = "INSERT INTO ".TBL_USERS." SET username = :username, password = :password, userid = :userid, userlevel = '$ulevel', timestamp = '$time', hash = '0', advanced_mode = '0', shipping_address = NULL";
      $stmt = $this->connection->prepare($query);
      return $stmt->execute(array(':username' => $username, ':password' => $password, ':userid' => $userid));
   }
   
	// addMessage - add a message into the messages table
	function addMessage($subject, $message, $recipient, $sender) {
		$time = time();
		$query = "INSERT INTO ".TBL_MAIL." (UserTo, UserFrom, Subject, Message, SentDate, status) VALUES (:recipient, :sender, :subject, :message, '$time', 'unread')";
		$stmt = $this->connection->prepare($query);
		$stmt->execute(array(':recipient' => $recipient, ':sender' => $sender, ':subject' => $subject, ':message' => $message));
		$result = $this->connection->lastInsertId();
		if(!$result){
			return false; // failure
		} else {
			return $result; // success
		}
	}
   
   /**
    * updateUserField - Updates a field, specified by the field
    * parameter, in the user's row of the database.
    */
   function updateUserField($username, $field, $value){
	   $query = "UPDATE ".TBL_USERS." SET ".$field." = :value WHERE username = :username";
	   $stmt = $this->connection->prepare($query);
	   return $stmt->execute(array(':username' => $username, ':value' => $value));
   }
   
   /**
    * updateMailField - Updates a field, specified by the field
    * parameter, in the mails row of the database.
    */
   function updateMailField($mailid, $field, $value){
	   $query = "UPDATE ".TBL_MAIL." SET ".$field." = :value WHERE mail_id = :mailid";
	   $stmt = $this->connection->prepare($query);
	   return $stmt->execute(array(':mailid' => $mailid, ':value' => $value));
   }
   
   /**
    * getUserInfo - Returns the result array from a mysql
    * query asking for all information stored regarding
    * the given username. If query fails, NULL is returned.
    */
   function getUserInfo($username){
	$query = "SELECT * FROM ".TBL_USERS." WHERE username = :username";
	$stmt = $this->connection->prepare($query);
	$stmt->execute(array(':username' => $username));
	$dbarray = $stmt->fetch();  
      /* Error occurred, return given name by default */
    $count = $stmt->rowCount();
      if(!$dbarray || $count < 1){
         return NULL;
      }
      /* Return result array */
      return $dbarray;
   }
   
   function getUserInfoFromHash($hash){
	$query = "SELECT * FROM ".TBL_USERS." WHERE hash = :hash";
	$stmt = $this->connection->prepare($query);
	$stmt->execute(array(':hash' => $hash));
	$dbarray = $stmt->fetch();  
      /* Error occurred, return given name by default */
    $count = $stmt->rowCount();
      if(!$dbarray || $count < 1){
         return NULL;
      }
      /* Return result array */
      return $dbarray;
   }
   
   /**
    * getNumMembers - Returns the number of signed-up users
    * of the website, banned members not included. The first
    * time the function is called on page load, the database
    * is queried, on subsequent calls, the stored result
    * is returned. This is to improve efficiency, effectively
    * not querying the database when no call is made.
    */
   function getNumMembers(){
      if($this->num_members < 0){
        $result =  $this->connection->query("SELECT username FROM ".TBL_USERS);
        $this->num_members = $result->rowCount(); 
      }
      return $this->num_members;
   }
   
   /**
    * calcNumActiveUsers - Finds out how many active users
    * are viewing site and sets class variable accordingly.
    */
   function calcNumActiveUsers(){
	   /* Calculate number of USERS at site */
	   $sql = $this->connection->query("SELECT * FROM ".TBL_ACTIVE_USERS);
	   $this->num_active_users = $sql->rowCount();
   }
   
   /**
    * calcNumActiveGuests - Finds out how many active guests
    * are viewing site and sets class variable accordingly.
    */
   function calcNumActiveGuests(){
	   /* Calculate number of GUESTS at site */
	   $sql = $this->connection->query("SELECT * FROM ".TBL_ACTIVE_GUESTS);
	   $this->num_active_guests = $sql->rowCount();       
	}
   
   /**
    * addActiveUser - Updates username's last active timestamp
    * in the database, and also adds him to the table of
    * active users, or updates timestamp if already there.
    */
   function addActiveUser($username, $time){  
      $query = "UPDATE ".TBL_USERS." SET timestamp = :time WHERE username = :username";
	  $stmt = $this->connection->prepare($query);
	  $stmt->execute(array(':username' => $username, ':time' => $time));
      
      if(!TRACK_VISITORS) return;
      $query = "REPLACE INTO ".TBL_ACTIVE_USERS." VALUES (:username, :time)";
	  $stmt = $this->connection->prepare($query);
	  $stmt->execute(array(':username' => $username, ':time' => $time));
      $this->calcNumActiveUsers();
   }
   
   /* addActiveGuest - Adds guest to active guests table */
   function addActiveGuest($ip, $time){
      if(!TRACK_VISITORS) return;
      $sql =  $this->connection->prepare("REPLACE INTO ".TBL_ACTIVE_GUESTS." VALUES ('$ip', '$time')");
      $sql->execute();
      $this->calcNumActiveGuests();
   }
   
   /* These functions are self explanatory, no need for comments */
   
   /* removeActiveUser */
   function removeActiveUser($username){
      if(!TRACK_VISITORS) return;
      $sql = $this->connection->prepare("DELETE FROM ".TBL_ACTIVE_USERS." WHERE username = '$username'");
      $sql->execute();
      $this->calcNumActiveUsers();
   }
   
   /* removeActiveGuest */
   function removeActiveGuest($ip){
      if(!TRACK_VISITORS) return;
      $sql = $this->connection->prepare("DELETE FROM ".TBL_ACTIVE_GUESTS." WHERE ip = '$ip'");
      $sql->execute();
      $this->calcNumActiveGuests();
   }
   
   /* removeInactiveUsers */
   function removeInactiveUsers(){
      if(!TRACK_VISITORS) return;
      $timeout = time()-USER_TIMEOUT*60;
      $stmt = $this->connection->prepare("DELETE FROM ".TBL_ACTIVE_USERS." WHERE timestamp < $timeout");
      $stmt->execute();
      $this->calcNumActiveUsers();
   }

   /* removeInactiveGuests */
   function removeInactiveGuests(){
      if(!TRACK_VISITORS) return;
      $timeout = time()-GUEST_TIMEOUT*60;
      $stmt = $this->connection->prepare("DELETE FROM ".TBL_ACTIVE_GUESTS." WHERE timestamp < $timeout");
      $stmt->execute();
      $this->calcNumActiveGuests();
   }

	// listProduct - returns a single products info
    function listProduct($pid) {
		$query = "SELECT * FROM ".TBL_PRODUCTS." WHERE product_id = :pid";
		$stmt = $this->connection->prepare($query);
		$stmt->execute(array(':pid' => $pid));
		$dbarray = $stmt->fetch();
		$count = $stmt->rowCount();
		if(!$dbarray || $count < 1){
			return 1; // failure
		} else {
			return $dbarray; // success
		}
	}
	
	// listProducts - returns an array of products
    function listProducts($pids) {
		$arrCnt = count($pids);
		$cntr   = 1;
		$pidesc = array();
		$query  = "SELECT * FROM ".TBL_PRODUCTS." WHERE product_id IN(";
		foreach($pids as $pid) {
			$pidesc[$cntr] = $pid;
			$query .= "'%s'";
			if($arrCnt != $cntr) {
				$query .= ","; // output commas if its not the last element
			}
			$cntr+=1;
		}
		$query .= ")";
		$q = vsprintf($query,$pidesc);
		$dbarray = $this->connection->query($q);
		$rows = $dbarray->fetchAll(PDO::FETCH_ASSOC);
		if(!$dbarray || $dbarray->rowCount() < 1){
			return 1; // failure
		} else {
			return $rows; // success
		}
	}
	
	// listProductsByCategory - returns an array of products by category
    function listProductsByCategory($category) {
		$query = "SELECT * FROM ".TBL_PRODUCTS." WHERE category = :category AND available = '1' AND stock > 0";
		$stmt = $this->connection->prepare($query);
		$stmt->execute(array(':category' => $category));
		$dbarray = $stmt->fetchAll();
		$count = $stmt->rowCount();
		if(!$dbarray || $count < 1){
			return false; // failure
		} else {
			return $dbarray; // success
		}
	}
	
	// listProductsBySubCategory - returns an array of products by category and subcategory
	function listProductsBySubCategory($category, $subcategory) {
		$query = "SELECT * FROM ".TBL_PRODUCTS." WHERE available = '1' AND stock > 0 AND category = :category AND subcategory = :subcategory";
		$stmt = $this->connection->prepare($query);
		$stmt->execute(array(':category' => $category, ':subcategory' => $subcategory));
		$dbarray = $stmt->fetchAll();
		$count = $stmt->rowCount();
		if(!$dbarray || $count < 1){
			return false; // failure
		} else {
			return $dbarray; // success
		}
	}
	
	// listConversions - returns an array of cryptocoin conversions
	function listConversions() {
		try {
			# MySQL with PDO_MYSQL
			$convConnection = new PDO('mysql:host='.CONV_SERVER.';dbname='.CONV_NAME, CONV_USER, CONV_PASS);
			$convConnection->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION ); 
		} catch(PDOException $e) {  
			echo "Error connecting to database.";
			exit();
		}
		$dbarray = $convConnection->query("SELECT * FROM current_conversion");
		$row = $dbarray->fetch(PDO::FETCH_ASSOC);
		unset($convConnection);
		if(!$dbarray || $dbarray->rowCount() < 1){
			return 1; // failure
		} else {
			return $row; // success
		}		
	}
	
	// listAllUserUsedkeys - returns an array of any used_keys the user may have
	function listAllUserUsedkeys($username) {
		$query = "SELECT * FROM ".TBL_USED_KEYS." WHERE username = :username";
		$stmt = $this->connection->prepare($query);
		$stmt->execute(array(':username' => $username));
		$dbarray = $stmt->fetch(PDO::FETCH_ASSOC);
		$count = $stmt->rowCount();
		if(!$dbarray || $count < 1){
			return 1; // failure
		} else {
			return $dbarray; // success
		}
	}
	
	// listUsedKeyById - returns a used key given by id
	function listUsedKeyById($id) {
		$query = "SELECT * FROM ".TBL_USED_KEYS." WHERE key_id = :key_id";
		$stmt = $this->connection->prepare($query);
		$stmt->execute(array(':key_id' => $id));
		$dbarray = $stmt->fetch(PDO::FETCH_ASSOC);
		$count = $stmt->rowCount();
		if(!$dbarray || $count < 1){
			return 1; // failure
		} else {
			return $dbarray; // success
		}
	}
	
	// listOrderById - returns an order by id
	function listOrderById($id) {		
		$query = "SELECT * FROM ".TBL_ORDERS." WHERE order_id = :order_id";
		$stmt = $this->connection->prepare($query);
		$stmt->execute(array(':order_id' => $id));
		$dbarray = $stmt->fetch(PDO::FETCH_ASSOC);
		$count = $stmt->rowCount();
		if(!$dbarray || $count < 1){
			return 1; // failure
		} else {
			return $dbarray; // success
		}
	}
	
	// listKeyPendingState - return a used_key row
	function listKeyPendingState($username) {
		$query = "SELECT * FROM ".TBL_USED_KEYS." WHERE username = :username AND status = 'pending'";
		$stmt = $this->connection->prepare($query);
		$stmt->execute(array(':username' => $username));
		$dbarray = $stmt->fetch(PDO::FETCH_ASSOC);
		$count = $stmt->rowCount();
		if(!$dbarray || $count < 1){
			return 1; // failure
		} else {
			return $dbarray; // success
		}
	}
	
	// listUserTimedoutUsedkeys - returns an array of any used_keys the user may have that have timedout
	function listUserTimedoutUsedkeys($username) {
		$query = "SELECT * FROM ".TBL_USED_KEYS." WHERE username = :username AND status = 'timedout'";
		$stmt = $this->connection->prepare($query);
		$stmt->execute(array(':username' => $username));
		$dbarray = $stmt->fetch(PDO::FETCH_ASSOC);
		$count = $stmt->rowCount();
		if(!$dbarray || $count < 1){
			return 1; // failure
		} else {
			return $dbarray; // success
		}
	}

	// listUserCompletedNoNotifiedKeys - returns a used key succesfully paid in full but not notified
	function listUserCompletedNoNotifiedKeys() {
		$query = "SELECT * FROM ".TBL_USED_KEYS." WHERE status = 'completed' AND imported = 1";
		$dbarray = $this->connection->query($query);
		$rows = $dbarray->fetch(PDO::FETCH_ASSOC);
		if(!$dbarray || $dbarray->rowCount() < 1){
			return 1; // failure
		} else {
			return $rows; // success
		}
	}
	
	// listUserOrdersByName - list username orders
	function listUserOrdersByName($username) {
		$query = "SELECT ".TBL_ORDERS.".order_id, ".TBL_USED_KEYS.".timestamp, ".TBL_USED_KEYS.".actual_total, ".TBL_USED_KEYS.".status FROM ".TBL_ORDERS." JOIN ".TBL_USED_KEYS." on ".TBL_ORDERS.".order_id=".TBL_USED_KEYS.".order_id AND ".TBL_USED_KEYS.".username = :username";
		$stmt = $this->connection->prepare($query);
		$stmt->execute(array(':username' => $username));
		$dbarray = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$count = $stmt->rowCount();
		if(!$dbarray || $count < 1){
			return 1; // failure
		} else {
			return $dbarray; // success
		}
	}
	
	// listUserOrderById - list order by id and username
	function listUserOrderById($id, $username) {
		$query = "SELECT ".TBL_ORDERS.".order_id, ".TBL_ORDERS.".order_display, ".TBL_USED_KEYS.".timestamp, ".TBL_USED_KEYS.".actual_total, ".TBL_USED_KEYS.".status FROM ".TBL_ORDERS." JOIN ".TBL_USED_KEYS." on ".TBL_ORDERS.".order_id=".TBL_USED_KEYS.".order_id AND ".TBL_ORDERS.".order_id = :id AND ".TBL_ORDERS.".username = :username";
		$stmt = $this->connection->prepare($query);
		$stmt->execute(array(':id' => $id, ':username' => $username));
		$dbarray = $stmt->fetch(PDO::FETCH_ASSOC);
		$count = $stmt->rowCount();
		if(!$dbarray || $count < 1){
			return 1; // failure
		} else {
			return $dbarray; // success
		}
	}
	
	// listRandomProducts - list a certain number of random products
	function listRandomProducts($num) {
		$query = "SELECT * FROM ".TBL_PRODUCTS." WHERE available = '1' AND stock > 0 ORDER BY RAND() LIMIT ".$num;
		$stmt = $this->connection->prepare($query);
		$stmt->execute();
		$dbarray = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$count = $stmt->rowCount();
		if(!$dbarray || $count < 1){
			return 1; // failure
		} else {
			return $dbarray; // success
		}
	}
	
	// removeUsedKeyByid - removes a used key from the used_key table
	function removeUsedKeyByid($id) {
		$query = "DELETE FROM ".TBL_USED_KEYS." WHERE key_id = :key_id";
		$stmt = $this->connection->prepare($query);
		$stmt->execute(array(':key_id' => $id));
	}
	
	// removeOrderByid - removes an order from the order table by id
	function removeOrderByid($id) {
		$query = "DELETE FROM ".TBL_ORDERS." WHERE order_id = :order_id";
		$stmt = $this->connection->prepare($query);
		$stmt->execute(array(':order_id' => $id));
	}
	
	// addkey - add a key into the used_keys table
	function addKey($key, $username, $total, $type, $orderid, $timestamp) {		
		$query = "INSERT INTO ".TBL_USED_KEYS." VALUES (DEFAULT, :key, :username, '0', :total, '0', :type, 'pending', :orderid, :timestamp)";
		$stmt = $this->connection->prepare($query);
		$stmt->execute(array(':key' => $key, ':username' => $username, ':total' => $total, ':type' => $type, ':orderid' => $orderid, ':timestamp' => $timestamp));
		$result = $this->connection->lastInsertId();
		if(!$result){
			return false; // failure
		} else {
			return $result; // success
		}
	}
	
	// addOrder - add an order to the order table, return the order id if success
	function addOrder($order, $username) {
		$query = "INSERT INTO ".TBL_ORDERS." VALUES (DEFAULT, :order, :username)";
		$stmt = $this->connection->prepare($query);
		$stmt->execute(array(':order' => $order, ':username' => $username));
		$result = $this->connection->lastInsertId();
		if(!$result){
			return false; // failure
		} else {
			return $result; // success
		}
	}
	
	// updateOrderField - update order field designated by id
	function updateOrderField($id, $field, $value){
      $query = "UPDATE ".TBL_ORDERS." SET ".$field." = :value WHERE order_id = :id";
	  $stmt = $this->connection->prepare($query);
	  return $stmt->execute(array(":value" => $value, ':id' => $id));
   }
   
   // updateProductField - update product field designated by id
	function updateProductField($id, $field, $value){
      $query = "UPDATE ".TBL_PRODUCTS." SET ".$field." = :value WHERE product_id = :id";
	  $stmt = $this->connection->prepare($query);
	  return $stmt->execute(array(":value" => $value, ':id' => $id));
   }
   
   // updateProducts - update the product table
   function updateProducts($prodtitle, $weight, $total, $imgthumbnail, $imgmedium, $imglarge, $category, $subcategory, $stock, $available, $pid, $desc) {
	  $query = "UPDATE ".TBL_PRODUCTS." SET product_title = :prod_title, weight = :weight, total = :total, 
      image_thumbnail = :imgthumb, image_medium = :imgmedium, image_large = :imglarge, category = :category, 
      subcategory = :subcat, stock = :stock, available = :avail, product_description = :desc WHERE product_id = :pid";
      $stmt = $this->connection->prepare($query);
	  return $stmt->execute(array(':prod_title' => $prodtitle, ':weight' => $weight, ':total' => $total, ':imgthumb' => $imgthumbnail, ':imgmedium' => $imgmedium, ':imglarge' => $imglarge, ':category' => $category, ':subcat' => $subcategory, ':stock' => $stock, ':avail' => $available, ':pid' => $pid, ':desc' => $desc));
   }
   
   // updateKeyField - update the key table
   function updateKeyField($id, $field, $value){
		 $query = "UPDATE ".TBL_USED_KEYS." SET ".$field." = :value WHERE key_id = :id";
		 $stmt = $this->connection->prepare($query);
		 return $stmt->execute(array(':id' => $id, ':value' => $value));
   }
   
   // countUserMessages - return count of user messages
	function countUnreadMessages($username) {
		$query = "SELECT * FROM ".TBL_MAIL." WHERE UserTo = :username AND Deleted = 0 AND status = 'unread' ORDER BY SentDate DESC";
		$stmt = $this->connection->prepare($query);
		$stmt->execute(array(':username' => $username));
		$dbarray = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$count = $stmt->rowCount();
		if(!$dbarray || $count < 1){
			return false; // failure
		} else {
			return $count; // success
		}
	}
   
   // listUnreadMessages - list user messages
	function listUnreadMessages($username) {
		$query = "SELECT * FROM ".TBL_MAIL." WHERE UserTo = :username AND Deleted = 0 AND status = 'unread' ORDER BY SentDate DESC";
		$stmt = $this->connection->prepare($query);
		$stmt->execute(array(':username' => $username));
		$dbarray = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$count = $stmt->rowCount();
		if(!$dbarray || $count < 1){
			return false; // failure
		} else {
			return $dbarray; // success
		}
	}
	
	// listUnreadMessages - list users unread messages
	function listUserMessages($username) {
		$query = "SELECT * FROM ".TBL_MAIL." WHERE UserTo = :username AND Deleted = 0 ORDER BY SentDate DESC";
		$stmt = $this->connection->prepare($query);
		$stmt->execute(array(':username' => $username));
		$dbarray = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$count = $stmt->rowCount();
		if(!$dbarray || $count < 1){
			return false; // failure
		} else {
			return $dbarray; // success
		}
	}
   
   // selectMailMessage - select a single message sent to a user
   function selectMailMessage($username, $mailid) {
		$query = "SELECT * FROM ".TBL_MAIL." WHERE UserTo = :username AND mail_id = :mailid ORDER BY SentDate DESC";
		$stmt = $this->connection->prepare($query);
		$stmt->execute(array(':username' => $username, ':mailid' => $mailid));
		$dbarray = $stmt->fetch(PDO::FETCH_ASSOC);
		$count = $stmt->rowCount();
		if(!$dbarray || $count < 1){
			return false; // failure
		} else {
			return $dbarray; // success
		}
	}
};

/* Create database connection */
$database = new MySQLDB;

?>
