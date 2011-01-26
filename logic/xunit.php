<?php
function gzdecode($data){
$g=tempnam('/tmp','ff');
@file_put_contents($g,$data);
ob_start();
readgzfile($g);
$d=ob_get_clean();
return $d;
}
$title = ""; //set the title but set to empty string as index.php expects it to exist
$run_id = preg_replace("/[^0-9]/", "", $_REQUEST['run_id']);
$client_id = preg_replace("/[^0-9]/", "", $_REQUEST['client_id']);
$result = mysql_queryf("SELECT results FROM run_client WHERE run_id=%s AND client_id=%s;",
$run_id,
$client_id);
$row = mysql_fetch_array($result);
$htmlData = gzdecode($row['results'],0);
mysql_free_result($result);

$result = mysql_queryf("SELECT c.useragent as ua, c.os as os, u.name as browser FROM run_client rc join clients c on rc.client_id = c.id join useragents u on c.useragent_id = u.id where rc.run_id=%s AND rc.client_id=%s",
$run_id,
$client_id);

$row = mysql_fetch_array($result);

$os = $row['os'];
$browser = $row['browser'];
$userAgent = $row['ua'];



$htmlDoc = new DOMDocument();
$htmlDoc->loadHTML($htmlData);
$nodeList = $htmlDoc->getElementsByTagName('title');
$suiteName = $nodeList->item(0)->firstChild->nodeValue;

#$userAgent = $htmlDoc->getElementById('qunit-userAgent')->nodeValue;

$xpath = new DOMXPath($htmlDoc);
$nodeList = $xpath->query("//ol[@id='qunit-tests']/li");
$testcaseCount = $nodeList->length;

$nodeList = $xpath->query("//ol[@id='qunit-tests']/li[@class='fail']");
$failureCount = $nodeList->length;

$nodeList = $xpath->query("//ol[@id='qunit-tests']/li[@class='pass']");
$passCount = $nodeList->length;
$errorCount = 0;
$skipCount = 0;

$moduleNames = array();
$nodeList = $xpath->query("//span[@class='module-name']");
foreach ($nodeList as $node) {
$moduleNames[] = $node->nodeValue;
}

$testNames = array();
$nodeList = $xpath->query("//span[@class='test-name']");
foreach ($nodeList as $node) {
$testNames[] = $node->nodeValue;
}

$xunitXMLDOM = new DOMdocument('1.0', 'utf-8');
$tsNode = $xunitXMLDOM->createElement('testsuite');
$xunitXMLDOM->appendChild($tsNode);
$att = $xunitXMLDOM->createAttribute('name');
$tsNode->appendChild($att);
$val = $xunitXMLDOM->createTextNode($suiteName);
$att->appendChild($val);

$att = $xunitXMLDOM->createAttribute('id');
$tsNode->appendChild($att);
$val = $xunitXMLDOM->createTextNode("run_id=" . $run_id . "&amp;" .
"client_id=" . $client_id);
$att->appendChild($val);

$att = $xunitXMLDOM->createAttribute('package');
$tsNode->appendChild($att);
$val = $xunitXMLDOM->createTextNode($suiteName);
$att->appendChild($val);
$att = $xunitXMLDOM->createAttribute('tests');
$tsNode->appendChild($att);
$val = $xunitXMLDOM->createTextNode($testcaseCount);
$att->appendChild($val);

$att = $xunitXMLDOM->createAttribute('failures');
$tsNode->appendChild($att);
$val = $xunitXMLDOM->createTextNode($failureCount);
$att->appendChild($val);

$att = $xunitXMLDOM->createAttribute('errors');
$tsNode->appendChild($att);
$val = $xunitXMLDOM->createTextNode($errorCount);
$att->appendChild($val);

$att = $xunitXMLDOM->createAttribute('skip');
$tsNode->appendChild($att);
$val = $xunitXMLDOM->createTextNode($skipCount);
$att->appendChild($val);

$propertiesNode = $xunitXMLDOM->createElement('properties');
$tsNode->appendChild($propertiesNode);

$propertyNode = $xunitXMLDOM->createElement('property');
$propertiesNode->appendChild($propertyNode);
$att = $xunitXMLDOM->createAttribute('name');
$propertyNode->appendChild($att);
$val = $xunitXMLDOM->createTextNode('useragent');
$att->appendChild($val);
$att = $xunitXMLDOM->createAttribute('value');
$propertyNode->appendChild($att);
$val = $xunitXMLDOM->createTextNode($userAgent);
$att->appendChild($val);
$propertyNode = $xunitXMLDOM->createElement('property');
$propertiesNode->appendChild($propertyNode);
$att = $xunitXMLDOM->createAttribute('name');
$propertyNode->appendChild($att);
$val = $xunitXMLDOM->createTextNode('browser');
$att->appendChild($val);
$att = $xunitXMLDOM->createAttribute('value');
$propertyNode->appendChild($att);
$val = $xunitXMLDOM->createTextNode($browser);
$att->appendChild($val);
$propertyNode = $xunitXMLDOM->createElement('property');
$propertiesNode->appendChild($propertyNode);
$att = $xunitXMLDOM->createAttribute('name');
$propertyNode->appendChild($att);
$val = $xunitXMLDOM->createTextNode('platform');
$att->appendChild($val);
$att = $xunitXMLDOM->createAttribute('value');
$propertyNode->appendChild($att);
$val = $xunitXMLDOM->createTextNode($os);
$att->appendChild($val);

for ($i=0; $i<$testcaseCount; $i++){
$testcaseNode = $xunitXMLDOM->createElement('testcase');
$tsNode->appendChild($testcaseNode);
$att = $xunitXMLDOM->createAttribute('name');
$testcaseNode->appendChild($att);
$val = $xunitXMLDOM->createTextNode($testNames[$i]);
$att->appendChild($val);

$att = $xunitXMLDOM->createAttribute('classname');
$testcaseNode->appendChild($att);
$val = $xunitXMLDOM->createTextNode($moduleNames[$i]);
$att->appendChild($val);

$nodeList = $xpath->query("//li[@id='test-output" . $i . "']/ol/li[@class='fail']");
if ($nodeList->length > 0){
$failureNode = $xunitXMLDOM->createElement('failure');
$testcaseNode->appendChild($failureNode);
$att = $xunitXMLDOM->createAttribute('type');
$failureNode->appendChild($att);
$val = $xunitXMLDOM->createTextNode('failure');
$att->appendChild($val);
$att = $xunitXMLDOM->createAttribute('message');
$failureNode->appendChild($att);
$val = $xunitXMLDOM->createTextNode('Put a suitable failure message in here');
$att->appendChild($val);
}

$systemOutNode = $xunitXMLDOM->createElement('system-out');
$testcaseNode->appendChild($systemOutNode);

$nodeList = $xpath->query("//li[@id='test-output" . $i ."']/ol/li");
$systemOut = "";
$j = 0;
foreach ($nodeList as $node) {
$j++;
$systemOut = $systemOut . $j .": " . $node->textContent . "\n";
}

$systemOutData = $xunitXMLDOM->createCDATASection($systemOut);
$systemOutNode->appendChild($systemOutData);


}

mysql_free_result($result);
 
 
 
?>