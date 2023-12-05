# ip2location-php
A PHP class library for importing IP2Location data

# Overview
This is a class library that can be used with a simple driver to update a MySQL database with the monthly updates of DB5 from IP2Location's no cost service (https://lite.ip2location.com/).  Once imported, the data can easily be referenced to provide latitude/longitude/city/country data to visualize network activity (like with Smashing).  The library includes functions to allow you to setup the database, download new data, stage it, activate it, and roll it back if necessary.

# Installation
1. Clone or copy the class library and driver from this project.
2. Satisfy PHP dependencies.  At least unzip and curl are required (maybe more, I forget).
3. Enable LOAD DATA INFILE in MySQL through the <code>mysqld.cnf</code> directives.
4. Enable the user of LOAD DATA INFILE in <code>php.ini</code>.
5. Create a database for the IP2Location data (optional)
6. Configure the driver for an administrative user that has at least the <code>CREATE TABLE</code> privilege.
7. Use the createTableSet() functions to create the tables and optionally, a dedicated user for them.
8. Reconfigure the driver for the non-privileged user just created.

# Usage
Use the driver to update the IP2Location tables with the <code>update</code> argument.  If you need to rerun it, use the optional <code>-reimport</code> flag to skip the download.

# Documentation
There is very little except comments in the code.  
