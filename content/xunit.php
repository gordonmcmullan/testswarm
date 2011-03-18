<?php


     #    header("Content-Encoding: gzip");
     #    echo $row[0];
     header('Content-Type: application/xml');

     echo $xunitXMLDOM->saveXML();


?>
