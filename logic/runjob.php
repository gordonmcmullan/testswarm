<?php
    #@Author: Gordon McMullan <gordon.mcmullan@bbc.co.uk>

    $title = "Run New Job";

    #echo "run job";
    function runs_queued ($job_id){
         $result = mysql_queryf("SELECT COUNT(*) FROM run_useragent ru
                                 JOIN runs r on ru.run_id=r.id
                                 WHERE ru.useragent_id IN
                                       (SELECT useragent_id FROM clients c
                                        WHERE DATE_ADD(c.updated, INTERVAL 1 minute) > NOW())
                                 AND ru.status < 2
                                 AND r.job_id=%s;",$job_id );
        while ( $row = mysql_fetch_array($result) ) {
            #echo "tests ready to run: " . $row[0];
            #echo "\n";
            $count = $row[0];
        }
        flush();
        return $count;
    }

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
            echo "Incorrect username or auth token.";
            exit();
        }

        mysql_queryf("INSERT INTO jobs (user_id,name,created) VALUES(%u,%s,NOW());",
            $user_id, $_REQUEST['job_name']);

        $job_id = mysql_insert_id();
        $suite_count = 0;
        foreach ( $_REQUEST['suites'] as $suite_num => $suite_name ) {
            if ( $suite_name ) {
                $suite_count++;
                #echo "$suite_num " . $_REQUEST['suites'][$suite_num] . " " . $_REQUEST['urls'][$suite_num] . "<br>";
                mysql_queryf("INSERT INTO runs (job_id,name,url,created) VALUES(%u,%s,%s,NOW());",
                    $job_id, $suite_name, $_REQUEST['urls'][$suite_num]);

                $run_id = mysql_insert_id();

                $ua_type = "1 = 1";

                if ( $_REQUEST['browsers'] == "popular" ) {
                    $ua_type = "popular = 1";
                } else if ( $_REQUEST['browsers'] == "current" ) {
                    $ua_type = "current = 1";
                } else if ( $_REQUEST['browsers'] == "gbs" ) {
                    $ua_type = "gbs = 1";
                } else if ( $_REQUEST['browsers'] == "beta" ) {
                    $ua_type = "beta = 1";
                } else if ( $_REQUEST['browsers'] == "mobile" ) {
                    $ua_type = "mobile = 1";
                } else if ( $_REQUEST['browsers'] == "popularbeta" ) {
                    $ua_type = "(popular = 1 OR beta = 1)";
                } else if ( $_REQUEST['browsers'] == "popularbetamobile" ) {
                    $ua_type = "(popular = 1 OR beta = 1 OR mobile = 1)";
                }

                $result = mysql_queryf("SELECT id FROM useragents WHERE active = 1 AND $ua_type;");

                while ( $row = mysql_fetch_array($result) ) {
                    $browser_num = $row[0];
                    mysql_queryf("INSERT INTO run_useragent (run_id,useragent_id,max,created) VALUES(%u,%u,%u,NOW());",
                        $run_id, $browser_num, $_REQUEST['max']);
                }
            }
        }



        $url = "/job/" . $job_id . "/";

        # echo $_REQUEST['output'] . "\n";

        #$result = mysql_queryf("SELECT  clients.id as client_id, clients.ip as ip, users.name as 'user', useragents.engine as engine, useragents.name as name, clients.os as os FROM users, clients, useragents WHERE clients.useragent_id=useragents.id AND DATE_ADD(clients.updated, INTERVAL 1 minute) > NOW() AND clients.user_id=users.id ORDER BY useragents.engine, useragents.name");



        if ( $_REQUEST['output'] == "dump" ) {
            header("Content-Type: text/plain");

            echo $url;
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
            }
        	$completed_runs = get_completed_runs($job_id);
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

        } else {
            header("Location: $url");
        }

        exit();

    }




?>
