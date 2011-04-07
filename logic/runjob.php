<?php
/**
 * runjob.php - add synchronous running cpability to Testswarm
 * 
 * this script adds the capacity for testswarm to run in a pseudo-synchronous
 * fashion for currently connected clients. The job is submitted by calling the
 * addjob 'state' on the same testwswarm server. Completion is  monitored by 
 * polling the database for the number of uncompleted runs for the currently
 * connected clients. As runs are completed the output from the jobs is 
 * returned. Currently only xUnit style output from QUnit tests is supported. 
 * 
 * 
 *
 * @author Gordon McMullan <gordon.mcmullan@bbc.co.uk>
 * 
 * 
 */

    $title = "Run New Job";

    /**
     * 
     * return the number of waiting test runs for the currently connected 
     * clients that form part of a test job
     * 
     * @author Gordon McMullan <gordon.mcmullan@bbc.co.uk>
     * 
     * @return int
     * @param int $job_id
     */
    function runs_queued ($job_id){
         $result = mysql_queryf("SELECT COUNT(*) FROM run_useragent ru
                                 JOIN runs r on ru.run_id=r.id
                                 WHERE ru.useragent_id IN
                                       (SELECT useragent_id FROM clients c
                                        WHERE DATE_ADD(c.updated, INTERVAL 1 minute) > NOW())
                                 AND ru.status < 2
                                 AND r.job_id=%s;",$job_id );
        while ( $row = mysql_fetch_array($result) ) {
            $count = $row[0];
        }
        flush();
        return $count;
    }

    /**
     * 
     * return a Gzip compressed representation of the html stored by Testswarm 
     * in its database
     * 
     * @return string 
     * @param int $job_id
     */
    function get_completed_runs($job_id) {
        $result = mysql_queryf("SELECT r.id AS run,
                                       c.id AS client
                                  FROM run_client rc
                                  JOIN runs r ON rc.run_id=r.id
                                  JOIN clients c ON rc.client_id=c.id
                                  WHERE rc.status=2 AND r.job_id=$job_id;");

        while ( $row = mysql_fetch_array($result) ) {
            $completed_runs[]=$row;
        }
        return $completed_runs;
    }

    if ( $_REQUEST['state'] == "runjob" ) {
        $username = preg_replace("/[^a-zA-Z0-9_ -]/", "", $_REQUEST['user']);
        $auth = preg_replace("/[^a-z0-9]/", "", $_REQUEST['auth']);

        $result = mysql_queryf("SELECT id FROM users WHERE name=%s AND auth=%s;", $username, $auth);

        if ( $row = mysql_fetch_array($result) ) {
            $user_id = intval($row[0]);

        # TODO: Improve error message quality.
        } else {
        	error_log($username . ":" . $auth . " - Incorrect username or auth token.");
            exit();
        }
        
        
        $addjob_url = "http://" . $_SERVER['SERVER_NAME'] . ":" . $_SERVER['SERVER_PORT'];
        $addjob_url = $addjob_url . $_SERVER['REQUEST_URI'] . "?";
        $addjob_url = $addjob_url . http_build_query($_POST);
		$addjob_url = preg_replace('/state=runjob/', 'state=addjob', $addjob_url);
		$addjob_url = preg_replace('/output=xml/', 'output=dump', $addjob_url);
        
		
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $addjob_url);
    	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    	curl_setopt($curl, CURLOPT_USERAGENT, "PHP5.3");
    	$addjob_result = curl_exec($curl);
    			
    	$job_id = substr($addjob_result, 5, -1);
    	
    	#ToDo: check whether this could be moved to the corresponding 'view' script
    	#      in the content directory.
    	 
		#ToDo: Support plaintext return properly rather than simply reporting the job number
        if ( $_REQUEST['output'] == "dump" ) {
            header("Content-Type: text/plain");

            echo "job_id = " .$job_id;
            echo "\n";
            
            
        } elseif ( $_REQUEST['output'] == "xml" ) {
        	header("Content-Type: application/xml");
        	$reports_output = array();

        	echo '<?xml version="1.0" encoding="utf-8"?>';

			echo "<testsuites>\n";
            while (runs_queued($job_id)){
				sleep(10);
				$completed_runs = get_completed_runs($job_id);
				foreach ($completed_runs as $run) {
					if (! in_array($run, $reports_output)){
						
						#$xunit_url = "http://localhost:8999/?state=xunit";
						$xunit_url = "http://" . $_SERVER['SERVER_NAME'] . ":" . $_SERVER['SERVER_PORT'];
						$xunit_url = $xunit_url . "/index.php?state=xunit";
						$xunit_url = $xunit_url . "&run_id=" . $run['run'];
						$xunit_url = $xunit_url . "&client_id=" . $run['client'];

        				$curl = curl_init();
        				curl_setopt($curl, CURLOPT_URL, $xunit_url);
    					curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			        	$xunit = curl_exec($curl);
			        	
			        	#ToDo: The following line is a bit of a kludge, probably better ask for
			        	#      the xunit script to return a version without the xml declaration
						$xunit = substr($xunit, strlen('<?xml version="1.0" encoding="utf-8"?>'));
			        	echo $xunit;
			        	flush();
			        	curl_close($curl);
			        $reports_output[] = $run;
					}
				}
            }
        	$completed_runs = get_completed_runs($job_id);
        	if (completed_runs) {
				foreach ($completed_runs as $run) {
					if (! in_array($run, $reports_output)){
						$xunit_url = "http://localhost:8999/?state=xunit";
						$xunit_url = $xunit_url . "&run_id=" . $run['run'];
						$xunit_url = $xunit_url . "&client_id=" . $run['client'];

        				$curl = curl_init();
        				curl_setopt($curl, CURLOPT_URL, $xunit_url);
    					curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			        	$xunit = curl_exec($curl);
						$xunit = substr($xunit, strlen('<?xml version="1.0" encoding="utf-8"?>'));
			        	echo $xunit;
			        	flush();
			        	curl_close($curl);
			        $reports_output[] = $run;
					}
				}
			echo "</testsuites>\n";
        	}
        } else {
            header("Location: $url");
        }

        exit();

    }




?>
