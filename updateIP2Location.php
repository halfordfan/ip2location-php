#!/usr/bin/php
<?php

require_once 'classes/class.IP2Location.php';

// Config section - use an administrative user when running the setup process
$dbhost='localhost';
$dbname='ip2location';
$dbuser='ip2location';
$dbpass=''; // host-based auth.

// These are specific to setup.
$createdbuser=TRUE;                  // True means we'll create a user named below and assign privs during setup.
$importuserspec="'ip2location'@'localhost'"; // MySQL format username.  Set up your own auth and password outside of this script.

// Defaults.
$debug=0;
$reimport=FALSE;

while ( $arg = array_shift($argv) ) {
  switch ( $arg ) {
    case "-debug":
      $debug++;
    break;
    case "-reimport":
      // pull from an existing downloaded file.
      $reimport=TRUE;
    break;
    case "setup":
      // Connect to the database as an administrative user.
      $db=new mysqli($dbhost, $dbuser, $dbpass, $dbname);

      // No token as we are just creating table sets.
      $ip2lv4=new IP2Location5v4('', $db);
      $ip2lv6=new IP2Location5v6('', $db);
      $ip2pv4=new IP2Proxy11v4('', $db);
      $ip2pv6=new IP2Proxy11v6('', $db);

      // See if we need to create a user and call setup correctly.
      if ( $createdbuser ) {
        $ip2lv4->createDBUser($importuserspec);
        $ip2lv6->createDBUser($importuserspec);
        $ip2pv4->createDBUser($importuserspec);
        $ip2pv6->createDBUser($importuserspec);
      } 

      // Now create the tablesets.
      $ip2lv4->createTableSet();
      $ip2lv6->createTableSet();
      $ip2pv4->createTableSet();
      $ip2pv6->createTableSet();

      // Clean up
      $db->close();
      print "Database setup complete.  Confirm that:" .
"       " . " - new tables exist in '$dbname'," .
"       " . " - if requested, the dedicated user has been created (you must configure credentials and auth methods), and" .
"       " . " - the user id that will perform the imports has privileges on the created tables." .
"       " . "Once confirmed, reconfigure this script to use the proper user id for normal operation." . PHP_EOL;
      exit;
    break;
    default:
      $operation=$arg;
    break;
  }
}

//
// MAIN
//

// connect as the user with privs to do the import.
$db=new mysqli($dbhost, $dbuser, $dbpass, $dbname);
$db->set_charset('utf8');

// Create an IP2Location object with a token and a database connection.
// This is Toby's IP2Location LITE key.
$ip2lv4=new IP2Location5v4('vkct9uxBdoe7p5Qq0K8G3PU1eTRtnQt6SdJSNLgtdXIWXLaLYPJIesROHCnxQmv8',$db);
$ip2lv6=new IP2Location5v6('vkct9uxBdoe7p5Qq0K8G3PU1eTRtnQt6SdJSNLgtdXIWXLaLYPJIesROHCnxQmv8',$db);
$ip2pv4=new IP2Proxy11v4('vkct9uxBdoe7p5Qq0K8G3PU1eTRtnQt6SdJSNLgtdXIWXLaLYPJIesROHCnxQmv8',$db);
$ip2pv6=new IP2Proxy11v6('vkct9uxBdoe7p5Qq0K8G3PU1eTRtnQt6SdJSNLgtdXIWXLaLYPJIesROHCnxQmv8',$db);

// Turn on debug to see output.
$ip2lv4->setDebugLevel($debug);
$ip2lv6->setDebugLevel($debug);
$ip2pv4->setDebugLevel($debug);
$ip2pv6->setDebugLevel($debug);

switch ( $operation ) {
  case "restore":
  //  try {
      $ip2lv4->restoreBackup();
      $ip2lv6->restoreBackup();
      $ip2pv4->restoreBackup();
      $ip2pv6->restoreBackup();
      /*
    } catch (exception $e) {
      print "Unable to restore previous backup table in one or more packages." . PHP_EOL;
      exit(1);
    }
    */
  break;
  case "rowcount":
    $pkg['DB5LITE']=$ip2lv4->getRowcounts();
    $pkg['DB5LITEIPV6']=$ip2lv6->getRowcounts();
    $pkg['PX11LITE']=$ip2pv4->getRowcounts();
    $pkg['PX11LITEIPV6']=$ip2pv6->getRowcounts();
    foreach ( array_keys($pkg) as $pkgname ) {
      foreach ( array("stage", "current", "backup") as $suffix ) {
        print "Table " . $pkgname . "_" . $suffix . " contains " . $pkg[$pkgname][$suffix] . " rows." . PHP_EOL;
      }
    }
  break;
  case "update":
    // Download the new file.
    if ( ! $reimport ) {
      $ip2lv4->download();
      $ip2lv6->download();
      $ip2pv4->download();
      $ip2pv6->download();
    }

    // Extract the CSV
    $ip2lv4->extract();
    $ip2lv6->extract();
    $ip2pv4->extract();
    $ip2pv6->extract();
    
    // Import it into the newip2location5 table
    $loadouts[]=$ip2lv4->loadCSV();
    $loadouts[]=$ip2lv6->loadCSV();
    $loadouts[]=$ip2pv4->loadCSV();
    $loadouts[]=$ip2pv6->loadCSV();

    foreach ( $loadouts as $loadout ) {
      // If the imported rows matches the rows in the table and no warnings, activate.
      if ( $loadout['warnings'] != 0 ) {
        print 'Error during IP2Location database import!' . PHP_EOL;
        exit(1);
      }
    }

    // Trim the mapped v4's from the v6 tables.
    $ip2lv6->deleteMappedv4();
    $ip2pv6->deleteMappedv4();

    // Shorten the countries to more readable names
    $ip2lv4->shortenCountries();
    $ip2lv6->shortenCountries();
    $ip2pv4->shortenCountries();
    $ip2pv6->shortenCountries();
    
    // Activate.
    $ip2lv4->activateStage();
    $ip2lv6->activateStage();
    $ip2pv4->activateStage();
    $ip2pv6->activateStage();
    break;
  default:
    print "One argument of [update|restore|rowcount] required." . PHP_EOL;
}
?>
