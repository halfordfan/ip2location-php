<?php

//
// This is the base class.  It is not intended for direct use.
//
class IP2Location {
  // Define variables to be set by the constructor.
  protected $token;
  protected $dbconn;

  // This is for reference.
  protected $pkgname;
  protected $csvfile;

  // A debug flag
  protected $debug = 0;

  // The download URL base component.
  protected $download_url='https://www.ip2location.com/download/?token=';

  public function __construct($token, $dbconn) {
    $this->token = $token;
    $this->dbconn = $dbconn;
    $pkg2csv=array("DB5LITE"      => "IP2LOCATION-LITE-DB5.CSV",
                   "DB5LITEIPV6"  => "IP2LOCATION-LITE-DB5.IPV6.CSV",
                   "PX11LITE"     => "IP2PROXY-LITE-PX11.CSV",
                   "PX11LITEIPV6" => "IP2PROXY-LITE-PX11.IPV6.CSV",
                   "DBASNLITE"     => "IP2LOCATION-LITE-ASN.CSV",
                   "DBASNLITEIPV6" => "IP2LOCATION-LITE-ASN.IPV6.CSV");
    $this->csvfile=$pkg2csv[$this->pkgname];
    // Warn of artifacts.
    if ( file_exists("/tmp/" . $this->pkgname . ".ZIP") ) {
      print "NOTICE: ZIP download for package " . $this->pkgname . " already exists in /tmp." . PHP_EOL;
    }
    if ( file_exists("/tmp/" . $this->csvfile) ) {
      print "NOTICE: CSV file " . $this->csvfile . " already exists in /tmp." . PHP_EOL;
    }
  }

  // A method to set the debug level.
  public function setDebugLevel($level) {
    $this->debug=$level;
  }

  // This should be called by the subclass after creating the table
  // This must be called with a db connection that who can create users and grants.
  public function createDBUser($username) {
    if ( $username ) {
      $this->dbconn->query("CREATE USER IF NOT EXISTS " . $username);
      if ( $error = $this->dbconn->error ) {
        throw new Exception ($error);
      }
      $this->dbconn->query("GRANT FILE ON *.* TO " . $username);
      if ( $error = $this->dbconn->error ) {
        throw new Exception ($error);
      }
      foreach ( array("_temp", "_stage", "_current", "_backup") as $suffix ) {
        $this->dbconn->query("GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER ON `" . $this->pkgname . $suffix . "` TO " . $username);
        if ( $error = $this->dbconn->error ) {
          throw new Exception ($error);
        }
      }
      $this->dbconn->query("GRANT SELECT, INSERT, UPDATE, DELETE ON `releasedates` TO " . $username);
        if ( $error = $this->dbconn->error ) {
          throw new Exception ($error);
        }
      $this->debug && print "DEBUG: Database user $username created and granted appropriate permissions." . PHP_EOL;
    } else {
      throw new Exception ("ERROR: A username must be specified when creating and granting a database user permissions.");
    }
  }

  public function createTableSet() {
    // This should be overridden with the proper table creation code.
  }

  // This function should be called directly, or at the end of each createTableSet call.
  public function createReleaseTable() {
    $this->dbconn->query("CREATE TABLE IF NOT EXISTS `releasedates` (
      `tablename` varchar(32) NOT NULL,
      `releasedate` date NOT NULL,
      PRIMARY KEY (`tablename`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    if ( $error = $this->dbconn->error ) {
      throw new Exception ($error);
    }
  }

  // A download function for the subclasses with a package personality applied.
  public function download() {
    $this->debug && print "DEBUG: Downloading " . $this->pkgname . " from IP2Location...";
    $zipfile=file_get_contents($this->download_url . $this->token . "&file=" . $this->pkgname);
    if ( $zipfile ) {
      file_put_contents("/tmp/" . $this->pkgname . ".ZIP", $zipfile);
    } else {
      throw new Exception("ERROR: failed to download package " . $this->pkgname);
    }
    $this->debug && print strlen($zipfile) . " bytes received." . PHP_EOL;
    return TRUE;
  }

  // A function to extract the CSV using the personality of the subclass.
  public function extract() {
    if ( file_exists("/tmp/" . $this->pkgname . ".ZIP" ) ) {
      $this->debug && print "DEBUG: Extracting ZIP file" . $this->pkgname . ".ZIP...";
      $zip=new ZipArchive();
      $zip->open("/tmp/" . $this->pkgname . ".ZIP");
      $zip->extractTo("/tmp/", $this->csvfile);
      $this->debug > 1 && print("File modification time = " . $zip->statIndex(0)['mtime']) . "...";
      touch("/tmp/" . $this->csvfile, $zip->statIndex(0)['mtime']); // Update the mtime to match the original packaged date.
      $zip->close();
      $this->debug && print "done." . PHP_EOL;
    } else {
      throw new Exception ("ERROR: The zip file for " . $this->pkgname . " has not been downloaded.");
    }
    return TRUE;
  }

  public function loadCSV() {
    // This should be overridden with the proper load sequence for the database.
  }

  // This gets called when the table is in stage.
  public function shortenCountries() {
    $this->dbconn->query("UPDATE `" . $this->pkgname . "_stage` SET countryname='United States' WHERE countryname LIKE 'United States of America'");
    // See if the first update fails.
    if ( $error = $this->dbconn->error ) {
      throw new Exception($error);
    }
    $this->debug && print "DEBUG: shortenCountries() updated United States in " . $this->dbconn->affected_rows . " rows of " . $this->pkgname . "_stage." . PHP_EOL;
    $this->dbconn->query("UPDATE `" . $this->pkgname . "_stage` SET countryname='United Kingdom' WHERE countryname LIKE 'United Kingdom of Great Britain%'");
    $this->debug && print "DEBUG: shortenCountries() updated United Kingdom in " . $this->dbconn->affected_rows . " rows of " . $this->pkgname . "_stage." . PHP_EOL;
    $this->dbconn->query("UPDATE `" . $this->pkgname . "_stage` SET countryname='Russia' WHERE countryname LIKE 'Russian Federation'");
    $this->debug && print "DEBUG: shortenCountries() updated Russia in " . $this->dbconn->affected_rows . " rows of " . $this->pkgname . "_stage." . PHP_EOL;
    $this->dbconn->query("UPDATE `" . $this->pkgname . "_stage` SET countryname='South Korea' WHERE countryname LIKE 'Korea (Republic%'");
    $this->debug && print "DEBUG: shortenCountries() updated South Korea in " . $this->dbconn->affected_rows . " rows of " . $this->pkgname . "_stage." . PHP_EOL;
    $this->dbconn->query("UPDATE `" . $this->pkgname . "_stage` SET countryname='North Korea' WHERE countryname LIKE 'Korea (Democratic%'");
    $this->debug && print "DEBUG: shortenCountries() updated North Korea in " . $this->dbconn->affected_rows . " rows of " . $this->pkgname . "_stage." . PHP_EOL;
    $this->dbconn->query("UPDATE `" . $this->pkgname . "_stage` SET countryname=substring_index(countryname,' (',1) WHERE countryname LIKE '% (%'");
    $this->debug && print "DEBUG: shortenCountries() updated countries with parentheses in " . $this->dbconn->affected_rows . " rows of " . $this->pkgname . "_stage." . PHP_EOL;
    $this->dbconn->query("UPDATE `" . $this->pkgname . "_stage` SET countryname=substring_index(countryname,',',1) WHERE countryname LIKE '%,%'");
    $this->debug && print "DEBUG: shortenCountries() updated countries with commas in " . $this->dbconn->affected_rows . " rows of " . $this->pkgname . "_stage." . PHP_EOL;
    $this->dbconn->query("UPDATE `" . $this->pkgname . "_stage` SET stateprovince='D.C.' WHERE stateprovince LIKE 'District of Columbia'");
    $this->debug && print "DEBUG: shortenCountries() updated D.C. in " . $this->dbconn->affected_rows . " rows of " . $this->pkgname . "_stage." . PHP_EOL;
  }

  public function getRowcounts() {
    foreach ( array("stage", "current", "backup") as $suffix ) {
      $result=$this->dbconn->query("SELECT COUNT(*) FROM `" . $this->pkgname . "_" . $suffix . "`");
      if ( $result !== FALSE ) {
        $row = $result->fetch_row();
        $returnarr[$suffix] = $row[0];
        $this->debug && print "DEBUG: " . $this->pkgname . "_" . $suffix . " contains " . $row[0] . " rows." . PHP_EOL;
      } else {
        throw new Exception("ERROR: failed to get rowcount from table '" . $this->pkgname . "_" . $suffix . "'");
      }
    }
    return $returnarr;
  }

  public function activateStage() {
    $this->debug && print "DEBUG: Activating stage table from " . $this->pkgname . " table set...";
    $result=$this->dbconn->query("SELECT COUNT(*) FROM `" . $this->pkgname . "_stage`");
    if ( $error = $this->dbconn->error ) {
      throw new Exception($error);
    }
    $row=$result->fetch_row();
    if ( $result !== FALSE && $row[0] > 0 ) {
      $this->dbconn->query("ALTER TABLE `" . $this->pkgname . "_backup` RENAME TO `" . $this->pkgname . "_temp`");
      if ( $error = $this->dbconn->error ) {
        throw new Exception($error);
      }
      $this->dbconn->query("UPDATE releasedates SET tablename='" . $this->pkgname . "_temp' WHERE tablename='" . $this->pkgname . "_backup'");
      if ( $error = $this->dbconn->error ) {
        throw new Exception($error);
      }
      $this->dbconn->query("ALTER TABLE `" . $this->pkgname . "_current` RENAME TO `" . $this->pkgname . "_backup`");
      if ( $error = $this->dbconn->error ) {
        throw new Exception($error);
      }
      $this->dbconn->query("UPDATE releasedates SET tablename='" . $this->pkgname . "_backup' WHERE tablename='" . $this->pkgname . "_current'");
      if ( $error = $this->dbconn->error ) {
        throw new Exception($error);
      }
      $this->dbconn->query("ALTER TABLE `" . $this->pkgname . "_stage` RENAME TO `" . $this->pkgname . "_current`");
      if ( $error = $this->dbconn->error ) {
        throw new Exception($error);
      }
      $this->dbconn->query("UPDATE releasedates SET tablename='" . $this->pkgname . "_current' WHERE tablename='" . $this->pkgname . "_stage'");
      if ( $error = $this->dbconn->error ) {
        throw new Exception($error);
      }
      $this->dbconn->query("ALTER TABLE `" . $this->pkgname . "_temp` RENAME TO `" . $this->pkgname . "_stage`");
      if ( $error = $this->dbconn->error ) {
        throw new Exception($error);
      }
      $this->dbconn->query("UPDATE releasedates SET tablename='" . $this->pkgname . "_stage' WHERE tablename='" . $this->pkgname . "_temp'");
      if ( $error = $this->dbconn->error ) {
        throw new Exception($error);
      }
      $this->dbconn->query("TRUNCATE TABLE `" . $this->pkgname . "_stage`");
      if ( $error = $this->dbconn->error ) {
        throw new Exception($error);
      }
      $this->dbconn->query("DELETE FROM releasedates WHERE tablename='" . $this->pkgname . "_stage'");
      if ( $error = $this->dbconn->error ) {
        throw new Exception($error);
      }
      $this->debug && print "done." . PHP_EOL;
      return $row[0];
    } else {
      throw new Exception("ERROR: no rows in " . $this->pkgname . " stage table or other error. Aborting activation.");
    }
  }

  public function restoreBackup() {
    $this->debug && print "DEBUG: Restoring " . $this->pkgname . " table set backup table...";
    $result=$this->dbconn->query("SELECT COUNT(*) FROM `" . $this->pkgname . "_backup`");
    $row=$result->fetch_row();
    if ( $result !== FALSE && $row[0] > 0 ) {
      $this->dbconn->query("ALTER TABLE `" . $this->pkgname . "_backup` RENAME TO `" . $this->pkgname . "_temp`");
      if ( $error = $this->dbconn->error ) {
        throw new Exception($error);
      }
      $this->dbconn->query("UPDATE releasedates SET tablename='" . $this->pkgname . "_temp' WHERE tablename='" . $this->pkgname . "_backup'");
      if ( $error = $this->dbconn->error ) {
        throw new Exception($error);
      }
      $this->dbconn->query("ALTER TABLE `" . $this->pkgname . "_current` RENAME TO `" . $this->pkgname . "_backup`");
      if ( $error = $this->dbconn->error ) {
        throw new Exception($error);
      }
      $this->dbconn->query("UPDATE releasedates SET tablename='" . $this->pkgname . "_backup' WHERE tablename='" . $this->pkgname . "_current'");
      if ( $error = $this->dbconn->error ) {
        throw new Exception($error);
      }
      $this->dbconn->query("ALTER TABLE `" . $this->pkgname . "_temp` RENAME TO `" . $this->pkgname . "_current`");
      if ( $error = $this->dbconn->error ) {
        throw new Exception($error);
      }
      $this->dbconn->query("UPDATE releasedates SET tablename='" . $this->pkgname . "_current' WHERE tablename='" . $this->pkgname . "_temp'");
      if ( $error = $this->dbconn->error ) {
        throw new Exception($error);
      }
      $this->dbconn->query("TRUNCATE TABLE `" . $this->pkgname . "_backup`");
      if ( $error = $this->dbconn->error ) {
        throw new Exception($error);
      }
      $this->dbconn->query("DELETE FROM releasedates WHERE tablename='" . $this->pkgname . "_backup'");
      if ( $error = $this->dbconn->error ) {
        throw new Exception($error);
      }
      $this->debug && print "done." . PHP_EOL;
      return $row[0];
    } else {
      throw new Exception("ERROR: No rows in " . $this->pkgname . " backup table or other error. Aborting restore.");
    }
  }
}

class IP2Location5v4 extends IP2Location {

  public function __construct($token, $db) {
    $this->pkgname="DB5LITE";
    parent::__construct($token, $db);
  }

  // This is the table creation function.
  public function createTableSet() {
    foreach ( array("_stage", "_current", "_backup") as $suffix ) {
      $this->dbconn->query("CREATE TABLE `" . $this->pkgname . $suffix . "` (
        `begin` INT(10) UNSIGNED NOT NULL,
        `end` INT(10) UNSIGNED NOT NULL,
        `countrycode` VARCHAR(2) NOT NULL,
        `countryname` VARCHAR(128) NOT NULL,
        `stateprovince` VARCHAR(128) NOT NULL,
        `city` varchar(80) NOT NULL,
        `latitude` float(9,6) NOT NULL,
        `longitude` float(10,6) NOT NULL,
        `begin_vb` varbinary(4) NOT NULL,
        `end_vb` varbinary(4) NOT NULL
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
      if ( $error = $this->dbconn->error ) {
        throw new Exception ($error);
      }
      $this->dbconn->query("ALTER TABLE `" . $this->pkgname . $suffix . "`
        ADD PRIMARY KEY (`begin`,`end`),
        ADD UNIQUE KEY (`begin_vb`,`end_vb`),
        ADD KEY `countrycode` (`countrycode`)");
      if ( $error = $this->dbconn->error ) {
        throw new Exception ($error);
      }
      $this->dbconn->query("CREATE TRIGGER `trigger_bi_" . $this->pkgname . $suffix . "_calcVB` BEFORE INSERT ON `" . $this->pkgname . $suffix . "` FOR EACH ROW" . "
"                        . "BEGIN" . "
"                        . " SET NEW.begin_vb=UNHEX(LPAD(HEX(NEW.begin), 8, '0'));" . "
"                        . " SET NEW.end_vb=UNHEX(LPAD(HEX(NEW.end), 8, '0'));" . "
"                        . "END");
      if ( $error = $this->dbconn->error ) {
        throw new Exception ($error);
      }
    }
  }

  // This is our import function.
  public function loadCSV() {
    $this->debug && print "DEBUG: Loading CSV " . $this->csvfile . " into database...";
    $this->dbconn->query("TRUNCATE TABLE `" . $this->pkgname . "_stage`");
    if ( $error = $this->dbconn->error ) {
      throw new Exception($error);
    }
    $this->dbconn->query("LOAD DATA LOCAL INFILE '" . '/tmp/' . $this->csvfile . "' INTO TABLE `" . $this->pkgname . "_stage` FIELDS TERMINATED BY ',' ENCLOSED BY '\"' LINES TERMINATED BY '\r\n' (begin, end, countrycode, countryname, stateprovince, city, latitude, longitude)");
    if ( $error = $this->dbconn->error ) {
      throw new Exception($error);
    }
    $importout = $this->dbconn->info;
    $this->debug > 1 && $warnset=$this->dbconn->query("SHOW WARNINGS");
    if ( isset($warnset) ) {
      while ( $row=mysqli_fetch_assoc($warnset) ) {
        print "DEBUG: " . $row['Level'] . ": " . $row['Message'] . PHP_EOL;
      }
    }
    $this->debug && print $importout . PHP_EOL;
    $filedate=date('Y-m-d', filemtime("/tmp/" . $this->csvfile));
    $this->dbconn->query("INSERT INTO releasedates (tablename, releasedate) VALUES ('" . $this->pkgname . "_stage','$filedate') ON DUPLICATE KEY UPDATE releasedate = '$filedate'");
    if ( $error = $this->dbconn->error ) {
      throw new Exception($error);
    }
    $results=explode('  ',strtolower($importout));
    foreach ( $results as $result ) {
      $keyvalue=explode(': ',$result);
      $import[$keyvalue[0]]=$keyvalue[1];
    }
    return $import;
  }
}

class IP2Location5v6 extends IP2Location {

  public function __construct($token, $db) {
    $this->pkgname="DB5LITEIPV6";
    parent::__construct($token, $db);
  }

  // This is the table creation function.
  public function createTableSet() {
    foreach ( array("_stage", "_current", "_backup") as $suffix ) {
      $this->dbconn->query("CREATE TABLE `" . $this->pkgname . $suffix . "` (
        `begin` DECIMAL(60,0) UNSIGNED NOT NULL,
        `end` DECIMAL(60,0) UNSIGNED NOT NULL,
        `countrycode` VARCHAR(2) NOT NULL,
        `countryname` VARCHAR(128) NOT NULL,
        `stateprovince` VARCHAR(128) NOT NULL,
        `city` varchar(80) NOT NULL,
        `latitude` float(9,6) NOT NULL,
        `longitude` float(10,6) NOT NULL,
        `begin_vb` varbinary(16) NOT NULL,
        `end_vb` varbinary(16) NOT NULL
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
      if ( $error = $this->dbconn->error ) {
        throw new Exception ($error);
      }
      $this->dbconn->query("ALTER TABLE `" . $this->pkgname . $suffix . "`
        ADD PRIMARY KEY (`begin`,`end`),
        ADD UNIQUE KEY (`begin_vb`,`end_vb`),
        ADD KEY `countrycode` (`countrycode`)");
      if ( $error = $this->dbconn->error ) {
        throw new Exception ($error);
      }
      $this->dbconn->query("CREATE TRIGGER `trigger_bi_" . $this->pkgname . $suffix . "_calcVB` BEFORE INSERT ON `" . $this->pkgname . $suffix . "` FOR EACH ROW" . "
"                        . "BEGIN" . "
"                        . " DECLARE TwoExp64 DECIMAL(20) UNSIGNED;" . "
"                        . " DECLARE HighPart_b BIGINT UNSIGNED;" . "
"                        . " DECLARE HighPart_e BIGINT UNSIGNED;" . "
"                        . " DECLARE LowPart_b BIGINT UNSIGNED;" . "
"                        . " DECLARE LowPart_e BIGINT UNSIGNED;" . "
"                        . " SET TwoExp64 = 18446744073709551616;" . "
"                        . " SET HighPart_b = NEW.begin DIV TwoExp64;" . "
"                        . " SET LowPart_b = NEW.begin MOD TwoExp64;" . "
"                        . " SET HighPart_e = NEW.end DIV TwoExp64;" . "
"                        . " SET LowPart_e = NEW.end MOD TwoExp64;" . "
"                        . " SET NEW.begin_vb=UNHEX(CONCAT(LPAD(HEX(HighPart_b), 16, '0'), LPAD(HEX(LowPart_b), 16, '0')));" . "
"                        . " SET NEW.end_vb=UNHEX(CONCAT(LPAD(HEX(HighPart_e), 16, '0'), LPAD(HEX(LowPart_e), 16, '0')));" . "
"                        . "END");
      if ( $error = $this->dbconn->error ) {
        throw new Exception ($error);
      }
    }
  }

  // This is our import function.
  public function loadCSV() {
    $this->debug && print "DEBUG: Loading CSV " . $this->csvfile . " into database...";
    $this->dbconn->query("TRUNCATE TABLE `" . $this->pkgname . "_stage`");
    if ( $error = $this->dbconn->error ) {
      throw new Exception($error);
    }
    $this->dbconn->query("LOAD DATA LOCAL INFILE '" . '/tmp/' . $this->csvfile . "' INTO TABLE `" . $this->pkgname . "_stage` FIELDS TERMINATED BY ',' ENCLOSED BY '\"' LINES TERMINATED BY '\r\n' (begin, end, countrycode, countryname, stateprovince, city, latitude, longitude)");
    if ( $error = $this->dbconn->error ) {
      throw new Exception($error);
    }
    $importout = $this->dbconn->info;
    $this->debug > 1 && $warnset=$this->dbconn->query("SHOW WARNINGS");
    if ( isset($warnset) ) {
      while ( $row=mysqli_fetch_assoc($warnset) ) {
        print "DEBUG: " . $row['Level'] . ": " . $row['Message'] . PHP_EOL;
      }
    }
    $this->debug && print $importout . PHP_EOL;
    $filedate=date('Y-m-d', filemtime("/tmp/" . $this->csvfile));
    $this->dbconn->query("INSERT INTO releasedates (tablename, releasedate) VALUES ('" . $this->pkgname . "_stage','$filedate') ON DUPLICATE KEY UPDATE releasedate = '$filedate'");
    if ( $error = $this->dbconn->error ) {
      throw new Exception($error);
    }
    $results=explode('  ',strtolower($importout));
    foreach ( $results as $result ) {
      $keyvalue=explode(': ',$result);
      $import[$keyvalue[0]]=$keyvalue[1];
    }
    return $import;
  }

  public function deleteMappedv4() {
    $this->debug && print "DEBUG: Removing mapped IPv4 rows from " . $this->pkgname . "_stage...";
    $this->dbconn->query("DELETE FROM `" . $this->pkgname . "_stage` WHERE end <= POWER(16,12)");
    if ( $error = $this->dbconn->error ) {
      throw new Exception($error);
    } else {
      $this->debug && print "DEBUG: Deleted " . $this->dbconn->affected_rows . " rows." . PHP_EOL;
      return $this->dbconn->affected_rows;
    }
  }
}

class IP2Proxy11v4 extends IP2Location {

  public function __construct($token, $db) {
    $this->pkgname="PX11LITE";
    parent::__construct($token, $db);
  }

  public function createTableSet() {
    foreach ( array("_stage", "_current", "_backup") as $suffix ) {
      $this->dbconn->query("CREATE TABLE `" . $this->pkgname . $suffix . "` (
        `begin` int unsigned NOT NULL,
        `end` int unsigned NOT NULL,
        `proxytype` varchar(3) NOT NULL,
        `countrycode` varchar(2) NOT NULL,
        `countryname` varchar(64) NOT NULL,
        `stateprovince` varchar(128) NOT NULL,
        `city` varchar(128) NOT NULL,
        `isp` varchar(256) NOT NULL,
        `domain` varchar(128) NOT NULL,
        `usagetype` varchar(11) NOT NULL,
        `asn` VARCHAR(10) NOT NULL,
        `asname` varchar(256) NOT NULL,
        `lastseen` int NOT NULL,
        `threat` varchar(128) NOT NULL,
        `provider` varchar(256) NOT NULL,
        `begin_vb` varbinary(4) NOT NULL,
        `end_vb` varbinary(4) NOT NULL
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
      if ( $error = $this->dbconn->error ) {
        throw new Exception ($error);
      }
      $this->dbconn->query("ALTER TABLE `" . $this->pkgname . $suffix . "`
        ADD PRIMARY KEY (`begin`,`end`),
        ADD UNIQUE KEY (`begin_vb`,`end_vb`),
        ADD KEY `countrycode` (`countrycode`)");
      if ( $error = $this->dbconn->error ) {
        throw new Exception ($error);
      }
            $this->dbconn->query("CREATE TRIGGER `trigger_bi_" . $this->pkgname . $suffix . "_calcVB` BEFORE INSERT ON `" . $this->pkgname . $suffix . "` FOR EACH ROW" . "
"                        . "BEGIN" . "
"                        . " SET NEW.begin_vb=UNHEX(LPAD(HEX(NEW.begin), 8, '0'));" . "
"                        . " SET NEW.end_vb=UNHEX(LPAD(HEX(NEW.end), 8, '0'));" . "
"                        . "END");
      if ( $error = $this->dbconn->error ) {
        throw new Exception ($error);
      }
    }
  }

  public function loadCSV() {
    $this->debug && print "DEBUG: Loading CSV " . $this->csvfile . " into database...";
    $this->dbconn->query("TRUNCATE TABLE `" . $this->pkgname . "_stage`");
    if ( $error = $this->dbconn->error ) {
      throw new Exception($error);
    }
    $this->dbconn->query("LOAD DATA LOCAL INFILE '" . '/tmp/' . $this->csvfile . "' INTO TABLE `" . $this->pkgname . "_stage` FIELDS TERMINATED BY ',' ENCLOSED BY '\"' LINES TERMINATED BY '\n' (begin, end, proxytype, countrycode, countryname, stateprovince, city, isp, domain, usagetype, asn, asname, lastseen, threat, provider)");
    if ( $error = $this->dbconn->error ) {
      throw new Exception($error);
    }
    $importout = $this->dbconn->info;
    $this->debug > 1 && $warnset=$this->dbconn->query("SHOW WARNINGS");
    if ( isset($warnset) ) {
      while ( $row=mysqli_fetch_assoc($warnset) ) {
        print "DEBUG: " . $row['Level'] . ": " . $row['Message'] . PHP_EOL;
      }
    }
    $this->debug && print $importout . PHP_EOL;
    $filedate=date('Y-m-d', filemtime("/tmp/" . $this->csvfile));
    $this->dbconn->query("INSERT INTO releasedates (tablename, releasedate) VALUES ('" . $this->pkgname . "_stage','$filedate') ON DUPLICATE KEY UPDATE releasedate = '$filedate'");
    if ( $error = $this->dbconn->error ) {
      throw new Exception($error);
    }
    $results=explode('  ',strtolower($importout));
    foreach ( $results as $result ) {
      $keyvalue=explode(': ',$result);
      $import[$keyvalue[0]]=$keyvalue[1];
    }
    return $import;
  }
}

class IP2Proxy11v6 extends IP2Location {

  public function __construct($token, $db) {
    $this->pkgname="PX11LITEIPV6";
    parent::__construct($token, $db);
  }

  public function createTableSet() {
    foreach ( array("_stage", "_current", "_backup") as $suffix ) {
      $this->dbconn->query("CREATE TABLE `" . $this->pkgname . $suffix . "` (
        `begin` DECIMAL(60,0) NOT NULL,
        `end` DECIMAL(60,0) NOT NULL,
        `proxytype` VARCHAR(3) NOT NULL,
        `countrycode` VARCHAR(2) NOT NULL,
        `countryname` VARCHAR(64) NOT NULL,
        `stateprovince` VARCHAR(128) NOT NULL,
        `city` VARCHAR(128) NOT NULL,
        `isp` VARCHAR(256) NOT NULL,
        `domain` VARCHAR(128) NOT NULL,
        `usagetype` VARCHAR(11) NOT NULL,
        `asn` VARCHAR(10) NOT NULL,
        `asname` VARCHAR(256) NOT NULL,
        `lastseen` INT NOT NULL,
        `threat` VARCHAR(128) NOT NULL,
        `provider` VARCHAR(256) NOT NULL,
        `begin_vb` VARBINARY(16) NOT NULL,
        `end_vb` VARBINARY(16) NOT NULL
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
      if ( $error = $this->dbconn->error ) {
        throw new Exception ($error);
      }
      $this->dbconn->query("ALTER TABLE `" . $this->pkgname . $suffix . "`
        ADD PRIMARY KEY (`begin`,`end`),
        ADD UNIQUE KEY (`begin_vb`,`end_vb`),
        ADD KEY `countrycode` (`countrycode`)");
      if ( $error = $this->dbconn->error ) {
        throw new Exception ($error);
      }
      $this->dbconn->query("CREATE TRIGGER `trigger_bi_" . $this->pkgname . $suffix . "_calcVB` BEFORE INSERT ON `" . $this->pkgname . $suffix . "` FOR EACH ROW" . "
"                        . "BEGIN" . "
"                        . " DECLARE TwoExp64 DECIMAL(20) UNSIGNED;" . "
"                        . " DECLARE HighPart_b BIGINT UNSIGNED;" . "
"                        . " DECLARE HighPart_e BIGINT UNSIGNED;" . "
"                        . " DECLARE LowPart_b BIGINT UNSIGNED;" . "
"                        . " DECLARE LowPart_e BIGINT UNSIGNED;" . "
"                        . " SET TwoExp64 = 18446744073709551616;" . "
"                        . " SET HighPart_b = NEW.begin DIV TwoExp64;" . "
"                        . " SET LowPart_b = NEW.begin MOD TwoExp64;" . "
"                        . " SET HighPart_e = NEW.end DIV TwoExp64;" . "
"                        . " SET LowPart_e = NEW.end MOD TwoExp64;" . "
"                        . " SET NEW.begin_vb=UNHEX(CONCAT(LPAD(HEX(HighPart_b), 16, '0'), LPAD(HEX(LowPart_b), 16, '0')));" . "
"                        . " SET NEW.end_vb=UNHEX(CONCAT(LPAD(HEX(HighPart_e), 16, '0'), LPAD(HEX(LowPart_e), 16, '0')));" . "
"                        . "END");
      if ( $error = $this->dbconn->error ) {
        throw new Exception ($error);
      }
    }
  }

  public function loadCSV() {
    $this->debug && print "DEBUG: Loading CSV " . $this->csvfile . " into database...";
    $this->dbconn->query("TRUNCATE TABLE `" . $this->pkgname . "_stage`");
    if ( $error = $this->dbconn->error ) {
      throw new Exception($error);
    }
    $this->dbconn->query("LOAD DATA LOCAL INFILE '" . '/tmp/' . $this->csvfile . "' INTO TABLE `" . $this->pkgname . "_stage` FIELDS TERMINATED BY ',' ENCLOSED BY '\"' LINES TERMINATED BY '\n' (begin, end, proxytype, countrycode, countryname, stateprovince, city, isp, domain, usagetype, asn, asname, lastseen, threat, provider)");
    if ( $error = $this->dbconn->error ) {
      throw new Exception($error);
    }
    $importout = $this->dbconn->info;
    $this->debug > 1 && $warnset=$this->dbconn->query("SHOW WARNINGS");
    if ( isset($warnset) ) {
      while ( $row=mysqli_fetch_assoc($warnset) ) {
        print "DEBUG: " . $row['Level'] . ": " . $row['Message'] . PHP_EOL;
      }
    }
    $this->debug && print $importout . PHP_EOL;
    $filedate=date('Y-m-d', filemtime("/tmp/" . $this->csvfile));
    $this->dbconn->query("INSERT INTO releasedates (tablename, releasedate) VALUES ('" . $this->pkgname . "_stage','$filedate') ON DUPLICATE KEY UPDATE releasedate = '$filedate'");
    if ( $error = $this->dbconn->error ) {
      throw new Exception($error);
    }
    $results=explode('  ',strtolower($importout));
    foreach ( $results as $result ) {
      $keyvalue=explode(': ',$result);
      $import[$keyvalue[0]]=$keyvalue[1];
    }
    return $import;
  }

  public function deleteMappedv4() {
    $this->debug && print "DEBUG: Removing mapped IPv4 rows from " . $this->pkgname . "_stage...";
    $this->dbconn->query("DELETE FROM `" . $this->pkgname . "_stage` WHERE end <= POWER(16,12)");
    if ( $error = $this->dbconn->error ) {
      throw new Exception($error);
    } else {
      $this->debug && print "DEBUG: Deleted " . $this->dbconn->affected_rows . " rows." . PHP_EOL;
      return $this->dbconn->affected_rows;
    }
  }
}

class IP2ASNv4 extends IP2Location {

  public function __construct($token, $db) {
    $this->pkgname="DBASNLITE";
    parent::__construct($token, $db);
  }

  public function createTableSet() {
    foreach ( array("_stage", "_current", "_backup") as $suffix ) {
      $this->dbconn->query("CREATE TABLE `" . $this->pkgname . $suffix . "` (
        `begin` int unsigned NOT NULL,
        `end` int unsigned NOT NULL,
        `cidr` VARCHAR(43) NOT NULL,
        `asn` VARCHAR(10) NOT NULL,
        `asname` VARCHAR(256) NOT NULL,
        `begin_vb` VARBINARY(4) NOT NULL,
        `end_vb` VARBINARY(4) NOT NULL
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin");
      if ( $error = $this->dbconn->error ) {
        throw new Exception ($error);
      }
      $this->dbconn->query("ALTER TABLE `" . $this->pkgname . $suffix . "`
        ADD PRIMARY KEY (`begin`,`end`),
        ADD UNIQUE KEY (`begin_vb`,`end_vb`)");
      if ( $error = $this->dbconn->error ) {
        throw new Exception ($error);
      }
      $this->dbconn->query("CREATE TRIGGER `trigger_bi_" . $this->pkgname . $suffix . "_calcVB` BEFORE INSERT ON `" . $this->pkgname . $suffix . "` FOR EACH ROW" . "
"                        . "BEGIN" . "
"                        . " SET NEW.begin_vb=UNHEX(LPAD(HEX(NEW.begin), 8, '0'));" . "
"                        . " SET NEW.end_vb=UNHEX(LPAD(HEX(NEW.end), 8, '0'));" . "
"                        . "END");
      if ( $error = $this->dbconn->error ) {
        throw new Exception ($error);
      }
    }
  }

  // This is our import function.
  public function loadCSV() {
    $this->debug && print "DEBUG: Loading CSV " . $this->csvfile . " into database...";
    $this->dbconn->query("TRUNCATE TABLE `" . $this->pkgname . "_stage`");
    if ( $error = $this->dbconn->error ) {
      throw new Exception($error);
    }
    $this->dbconn->query("LOAD DATA LOCAL INFILE '" . '/tmp/' . $this->csvfile . "' INTO TABLE `" . $this->pkgname . "_stage` FIELDS TERMINATED BY ',' ENCLOSED BY '\"' LINES TERMINATED BY '\r\n' (begin, end, cidr, asn, asname)");
    if ( $error = $this->dbconn->error ) {
      throw new Exception($error);
    }
    $importout = $this->dbconn->info;
    $this->debug > 1 && $warnset=$this->dbconn->query("SHOW WARNINGS");
    if ( isset($warnset) ) {
      while ( $row=mysqli_fetch_assoc($warnset) ) {
        print "DEBUG: " . $row['Level'] . ": " . $row['Message'] . PHP_EOL;
      }
    }
    $this->debug && print $importout . PHP_EOL;
    $filedate=date('Y-m-d', filemtime("/tmp/" . $this->csvfile));
    $this->dbconn->query("INSERT INTO releasedates (tablename, releasedate) VALUES ('" . $this->pkgname . "_stage','$filedate') ON DUPLICATE KEY UPDATE releasedate = '$filedate'");
    if ( $error = $this->dbconn->error ) {
      throw new Exception($error);
    }
    $results=explode('  ',strtolower($importout));
    foreach ( $results as $result ) {
      $keyvalue=explode(': ',$result);
      $import[$keyvalue[0]]=$keyvalue[1];
    }
    return $import;
  }
}

class IP2ASNv6 extends IP2Location {

  public function __construct($token, $db) {
    $this->pkgname="DBASNLITEIPV6";
    parent::__construct($token, $db);
  }

  public function createTableSet() {
    foreach ( array("_stage", "_current", "_backup") as $suffix ) {
      $this->dbconn->query("CREATE TABLE `" . $this->pkgname . $suffix . "` (
        `begin` DECIMAL(60,0) UNSIGNED NOT NULL,
        `end` DECIMAL(60,0) UNSIGNED NOT NULL,
        `cidr` VARCHAR(43) NOT NULL,
        `asn` VARCHAR(10) NOT NULL,
        `asname` VARCHAR(256) NOT NULL,
        `begin_vb` VARBINARY(16) NOT NULL,
        `end_vb` VARBINARY(16) NOT NULL
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin");
      if ( $error = $this->dbconn->error ) {
        throw new Exception ($error);
      }
      $this->dbconn->query("ALTER TABLE `" . $this->pkgname . $suffix . "`
        ADD PRIMARY KEY (`begin`,`end`),
        ADD UNIQUE KEY (`begin_vb`,`end_vb`)");
      if ( $error = $this->dbconn->error ) {
        throw new Exception ($error);
      }
      $this->dbconn->query("CREATE TRIGGER `trigger_bi_" . $this->pkgname . $suffix . "_calcVB` BEFORE INSERT ON `" . $this->pkgname . $suffix . "` FOR EACH ROW" . "
"                        . "BEGIN" . "
"                        . " DECLARE TwoExp64 DECIMAL(20) UNSIGNED;" . "
"                        . " DECLARE HighPart_b BIGINT UNSIGNED;" . "
"                        . " DECLARE HighPart_e BIGINT UNSIGNED;" . "
"                        . " DECLARE LowPart_b BIGINT UNSIGNED;" . "
"                        . " DECLARE LowPart_e BIGINT UNSIGNED;" . "
"                        . " SET TwoExp64 = 18446744073709551616;" . "
"                        . " SET HighPart_b = NEW.begin DIV TwoExp64;" . "
"                        . " SET LowPart_b = NEW.begin MOD TwoExp64;" . "
"                        . " SET HighPart_e = NEW.end DIV TwoExp64;" . "
"                        . " SET LowPart_e = NEW.end MOD TwoExp64;" . "
"                        . " SET NEW.begin_vb=UNHEX(CONCAT(LPAD(HEX(HighPart_b), 16, '0'), LPAD(HEX(LowPart_b), 16, '0')));" . "
"                        . " SET NEW.end_vb=UNHEX(CONCAT(LPAD(HEX(HighPart_e), 16, '0'), LPAD(HEX(LowPart_e), 16, '0')));" . "
"                        . "END");
      if ( $error = $this->dbconn->error ) {
        throw new Exception ($error);
      }
    }
  }

  // This is our import function.
  public function loadCSV() {
    $this->debug && print "DEBUG: Loading CSV " . $this->csvfile . " into database...";
    $this->dbconn->query("TRUNCATE TABLE `" . $this->pkgname . "_stage`");
    if ( $error = $this->dbconn->error ) {
      throw new Exception($error);
    }
    $this->dbconn->query("LOAD DATA LOCAL INFILE '" . '/tmp/' . $this->csvfile . "' INTO TABLE `" . $this->pkgname . "_stage` FIELDS TERMINATED BY ',' ENCLOSED BY '\"' LINES TERMINATED BY '\r\n' (begin, end, cidr, asn, asname)");
    if ( $error = $this->dbconn->error ) {
      throw new Exception($error);
    }
    $importout = $this->dbconn->info;
    $this->debug > 1 && $warnset=$this->dbconn->query("SHOW WARNINGS");
    if ( isset($warnset) ) {
      while ( $row=mysqli_fetch_assoc($warnset) ) {
        print "DEBUG: " . $row['Level'] . ": " . $row['Message'] . PHP_EOL;
      }
    }
    $this->debug && print $importout . PHP_EOL;
    $filedate=date('Y-m-d', filemtime("/tmp/" . $this->csvfile));
    $this->dbconn->query("INSERT INTO releasedates (tablename, releasedate) VALUES ('" . $this->pkgname . "_stage','$filedate') ON DUPLICATE KEY UPDATE releasedate = '$filedate'");
    if ( $error = $this->dbconn->error ) {
      throw new Exception($error);
    }
    $results=explode('  ',strtolower($importout));
    foreach ( $results as $result ) {
      $keyvalue=explode(': ',$result);
      $import[$keyvalue[0]]=$keyvalue[1];
    }
    return $import;
  }
}
?>
