<?php 
//Athon Sports Mailchimp 3.0 Class by Tristan Gordon

//campaignSegmentTest doesnt translate
//check out the failsafe for the updateCampaign function...is it thorough enough?
//make sure campaigns() function still fits in, may have to rewrite other files...doesnt properly translate
//added a curl request in case the get functions don't return a $response object that is COMPLETE...
//once campaigns() returns proper array, check with E to ensure that this will fit the previous api setup
//do i need to figure out how to make sure that some sort of pagination happens with the campaigns??
//campaign and campaign stats...do they even require that i create the proper arrays or are they fine the way they return
//might have to find a way to paginate the campaignMembers function****
//campaignHardBounces is deprecated and doesnt seem to have been replaced 
//same with campaignSoftBounces
//not sure what campaignUnsubscribes translates to as far as the 3.0 endpoints
//campaignAnalytics doesnt seem to translate, must find proper end point
//geo opens functions dont seem to function properly, must find proper end points
//campaignBounceMessage and campaignBounceMessages endpoints need to be found and added 
//campaignEcommOrders doesnt seem to translate, must find out which endpoint is used to acces this data
//campaignShareReports must be found in the current endpoints 
//campaignTemplateContent must figure out if this can proeprly transfer, or if it is jus part of '/campaigns/{campaign-id}/content'
//campaignOpenedAim and campaignNotOpenedAim no longer valid, find out which endpoint this has been consolidated into
//campaignGeoOpens find proper endpoint 
//campaignGeoOpensForCountry find proper endpoint 
//need to come up with logic that throws an error to $errorMessage and $errorCode if something goes wrong just in case 
//make sure all functions return proper things (specified in the comments above each function)
//make sure all functions that require pagination are properly accomodated wit hthe new count and offset parameters

//11/9/2016 PHASE 2 - Correct broken functions and polish API

class MCAPI {
	//api version 
	var $version = '3.0';
	var $errorMessage;
    var $errorCode;

    var $apiUrl;
    var $apiHost;

	//specific data center for our account
	var $dataCenter;

	//default to a 300 second timeout on all server calls
	var $timeout = 300;

	//default to a 8k chunk size 
	var $chunkSize = 8192;

	//cache apikey so we only have to log in once per each instantiation
	var $api_key;

	function __construct ($apikey, $secure=false) {
		$this->secure = $secure;
        $this->dataCenter = substr($apikey,strpos($apikey,'-')+1);
		$this->apiHost = 'https://' .$this->dataCenter. '.api.mailchimp.com/' . $this->version;
		$this->api_key = $apikey;
	}

	function setTimeout($seconds){
        if (is_int($seconds)){
            $this->timeout = $seconds;
            return true;
        }
    }

    function getTimeout(){
        return $this->timeout;
    }

    function useSecure($val){
        if ($val===true){
            $this->secure = true;
        } else {
            $this->secure = false;
        }
    }

    /**
     * Unschedule a campaign that is scheduled to be sent in the future
     *
     * @section Campaign  Related
     * @example mcapi_campaignUnschedule.php
     * @example xml-rpc_campaignUnschedule.php
     *
     * @param string $cid the id of the campaign to unschedule
     * @return boolean true on success
     */
	function campaignUnschedule($cid) {
        $method = 'POST';

        $json = json_encode(array());

		$endpoint = '/campaign/' . $cid . '/actions/unschedule';
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        $response = $this->callServer($json, $method);

        $response = json_decode($response);

        print_r($response);

        if ($response->errors || substr($response->status,0,1) === '4') {
            $this->errorMessage = ($response->errors) ? $response->errors[0]->message : $response->detail;
            $this->errorCode = $response->status; 

            echo "************************************************************************************\n\n";
            echo $this->errorCode . ': (' . $this->errorMessage .")\n\n";
            echo "************************************************************************************\n\n";

            return false;
        }

        if ($response->errors || substr($response->status,0,1) === '5') {
            $this->errorMessage = ($response->errors) ? $response->errors[0]->message : $response->detail;
            $this->errorCode = $response->status;

            echo "************************************************************************************\n\n";
            echo "There is an error on mailchimp's side. Contact apihelp@mailchimp.com \n\n";
            echo "************************************************************************************\n\n";
            
            return false;
        }

        if (!$response->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";
            return true;
        }
	}

    function campaignSchedule($cid, $schedule_time, $schedule_time_b=NULL) {
        $method = 'POST';

        //scheduletime_b is not mentioned in the 3.0 documentation. Ask eric for Clarification.
        $json = json_encode(array(
            'schedule_time' => $schedule_time,
            'timewarp'      => false
        ));

        $endpoint = '/campaign/' .$cid. '/actions/schedule';
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        print_r($url);

        $response = $this->callServer($json, $method);

        $response = json_decode($response);

        print_r($response);

        if ($response->errors || substr($response->status,0,1) === '4') {
            $this->errorMessage = ($response->errors) ? $response->errors[0]->message : $response->detail;
            $this->errorCode = $response->status; 

            echo "************************************************************************************\n\n";
            echo $this->errorCode . ': (' . $this->errorMessage .")\n\n";
            echo "************************************************************************************\n\n";

            return false;
        }

        if ($response->errors || substr($response->status,0,1) === '5') {
            $this->errorMessage = ($response->errors) ? $response->errors[0]->message : $response->detail;
            $this->errorCode = $response->status;

            echo "************************************************************************************\n\n";
            echo "There is an error on mailchimp's side. Contact apihelp@mailchimp.com \n\n";
            echo "************************************************************************************\n\n";
            
            return false;
        }

        if (!$response->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";
            return true;
        }
    }

    /**
     * Schedule a campaign to be sent in batches sometime in the future. Only valid for "regular" campaigns
     *
     * @section Campaign  Related
     *
     * @param string $cid the id of the campaign to schedule
     * @param string $schedule_time the time to schedule the campaign.
     * @param int $num_batches optional - the number of batches between 2 and 26 to send. defaults to 2
     * @param int $stagger_mins optional - the number of minutes between each batch - 5, 10, 15, 20, 25, 30, or 60. defaults to 5
     * @return boolean true on success
     */

    function campaignScheduleBatch($cid, $schedule_time, $num_batches=2, $stagger_mins=5) {
        $method = 'POST';

        $endpoint = '/campaign/' . $cid . '/actions/schedule';
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);
        $this->apiUrl = $url;

        $json = json_encode([
            'schedule_time'  => $schedule_time,
            'timewarp'       => false,
            'batch_delivery' => array(
                'batch_delay' => $stagger_mins,
                'batch_count' => $num_batches
            )
        ]);  
        
        $response = $this->callServer($json, $method);

        $response = json_decode($response);

        print_r($response);

        if ($response->errors || substr($response->status,0,1) === '4') {
            $this->errorMessage = ($response->errors) ? $response->errors[0]->message : $response->detail;
            $this->errorCode = $response->status; 

            echo "************************************************************************************\n\n";
            echo $this->errorCode . ': (' . $this->errorMessage .")\n\n";
            echo "************************************************************************************\n\n";

            return false;
        }

        if ($response->errors || substr($response->status,0,1) === '5') {
            $this->errorMessage = ($response->errors) ? $response->errors[0]->message : $response->detail;
            $this->errorCode = $response->status;

            echo "************************************************************************************\n\n";
            echo "There is an error on mailchimp's side. Contact apihelp@mailchimp.com \n\n";
            echo "************************************************************************************\n\n";
            
            return false;
        }

        if (!$response->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";
            return true;
        }
    }

    /**
     * Resume sending an AutoResponder or RSS campaign
     *
     * @section Campaign  Related
     *
     * @param string $cid the id of the campaign to pause
     * @return boolean true on success
     */
    function campaignResume($cid) {
        $method = 'POST';

        $json = json_encode(array());

        $endpoint = '/campaigns/' .$cid. '/actions/resume';
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);
        $this->apiUrl = $url;

        $info = $this->callServer($json, $method);

        $info = json_decode($info);

        print_r($info);

        if ($response->errors || substr($response->status,0,1) === '4') {
            $this->errorMessage = ($response->errors) ? $response->errors[0]->message : $response->detail;
            $this->errorCode = $response->status; 

            echo "************************************************************************************\n\n";
            echo $this->errorCode . ': (' . $this->errorMessage .")\n\n";
            echo "************************************************************************************\n\n";

            return false;
        }

        if ($response->errors || substr($response->status,0,1) === '5') {
            $this->errorMessage = ($response->errors) ? $response->errors[0]->message : $response->detail;
            $this->errorCode = $response->status;

            echo "************************************************************************************\n\n";
            echo "There is an error on mailchimp's side. Contact apihelp@mailchimp.com \n\n";
            echo "************************************************************************************\n\n";
            
            return false;
        }

        if (!$response->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";
            return true;
        } 
    }


    /**
     * Pause an AutoResponder or RSS campaign from sending
     *
     * @section Campaign  Related
     *
     * @param string $cid the id of the campaign to pause
     * @return boolean true on success
     */
    function campaignPause($cid) {
        $method = 'POST';

        $json = json_encode(array());

        $endpoint = '/campaigns/' .$cid. '/actions/pause';
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);
        $this->apiUrl = $url;

        $response = $this->callServer($json, $method);

        $response = json_decode($response);

        print_r($response);

        if ($response->errors || substr($response->status,0,1) === '4') {
            $this->errorMessage = ($response->errors) ? $response->errors[0]->message : $response->detail;
            $this->errorCode = $response->status; 

            echo "************************************************************************************\n\n";
            echo $this->errorCode . ': (' . $this->errorMessage .")\n\n";
            echo "************************************************************************************\n\n";

            return false;
        }

        if ($response->errors || substr($response->status,0,1) === '5') {
            $this->errorMessage = ($response->errors) ? $response->errors[0]->message : $response->detail;
            $this->errorCode = $response->status;

            echo "************************************************************************************\n\n";
            echo "There is an error on mailchimp's side. Contact apihelp@mailchimp.com \n\n";
            echo "************************************************************************************\n\n";
            
            return false;
        }

        if (!$response->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";
            return true;
        }  
    }

    /**
     * Send a given campaign immediately. For RSS campaigns, this will "start" them.
     *
     * @section Campaign  Related
     *
     * @example mcapi_campaignSendNow.php
     * @example xml-rpc_campaignSendNow.php
     *
     * @param string $cid the id of the campaign to send
     * @return boolean true on success
     */
    function campaignSendNow($cid) {
        $method = 'POST';

        $json = json_encode(array());

        $endpoint = '/campaigns/' . $cid . '/actions/send';
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);
        $this->apiUrl = $url;

        $response = $this->callServer($json, $method);

        $response = json_decode($response);

        print_r($response);

        if ($response->errors || substr($response->status,0,1) === '4') {
            $this->errorMessage = ($response->errors) ? $response->errors[0]->message : $response->detail;
            $this->errorCode = $response->status; 

            echo "************************************************************************************\n\n";
            echo $this->errorCode . ': (' . $this->errorMessage .")\n\n";
            echo "************************************************************************************\n\n";

            return false;
        }

        if ($response->errors || substr($response->status,0,1) === '5') {
            $this->errorMessage = ($response->errors) ? $response->errors[0]->message : $response->detail;
            $this->errorCode = $response->status;

            echo "************************************************************************************\n\n";
            echo "There is an error on mailchimp's side. Contact apihelp@mailchimp.com \n\n";
            echo "************************************************************************************\n\n";
            
            return false;
        }

        if (!$response->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";

            return true;
        }  
    }

    /**
     * Send a test of this campaign to the provided email address
     *
     * @section Campaign  Related
     *
     * @example mcapi_campaignSendTest.php
     * @example xml-rpc_campaignSendTest.php
     *
     * @param string $cid the id of the campaign to test
     * @param array $test_emails an array of email address to receive the test message
     * @param string $send_type optional by default (null) both formats are sent - "html" or "text" send just that format
     * @return boolean true on success
     */
    function campaignSendTest($cid, $test_emails=array (
    ), $send_type="html") {
        $method = 'POST';

        $json = json_encode([
            'test_emails' => $test_emails,
            'send_type'   => $send_type
        ]);

        $endpoint = '/campaigns/' .$cid. '/actions/test';
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);
        $this->apiUrl = $url;

        
        $response = $this->callServer($json, $method);

        $response = json_decode($response);

        print_r($response);

        if ($response->errors || substr($response->status,0,1) === '4') {
            $this->errorMessage = ($response->errors) ? $response->errors[0]->message : $response->detail;
            $this->errorCode = $response->status; 

            echo "************************************************************************************\n\n";
            echo $this->errorCode . ': (' . $this->errorMessage .")\n\n";
            echo "************************************************************************************\n\n";

            return false;
        }

        if ($response->errors || substr($response->status,0,1) === '5') {
            $this->errorMessage = ($response->errors) ? $response->errors[0]->message : $response->detail;
            $this->errorCode = $response->status;

            echo "************************************************************************************\n\n";
            echo "There is an error on mailchimp's side. Contact apihelp@mailchimp.com \n\n";
            echo "************************************************************************************\n\n";
            
            return false;
        }

        if (!$response->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";

            return true;
        } 
    }

    /**
     * Allows one to test their segmentation rules before creating a campaign using them
     *
     *  
    **/
    /*****************************************************this function does not translate to 3.0******************************************************/
    function campaignSegmentTest($list_id, $options) {
        //cant find a match...this used to return a count of all members that match a certain segment criterea...maybe try to find a function that does something similar/ 
        $this->errorMessage = "This function does not translate to an endpoint in the 3.0 api.";
        $this->errorCode = 400; 

        echo "************************************************************************************\n\n";
        echo $this->errorCode . ": (" . $this->errorMessage . ")\n\n";
        echo "************************************************************************************\n\n";
        return false;
    }

    function campaignCreate($type, $options, $content, $segment_opts=NULL, $type_opts=NULL) {
        $method = 'POST';

        //auto is no longer an option. Accepted Values: regular, plaintext, absplit, rss, variate
        ($type == 'auto') ? $type = 'regular' : '';

        $json = json_encode( array(
            'recipients' => array( 
                'list_id'     => $options['list_id'],
                'segment_opts' => array(
                    'match'      => 'any',
                    'conditions' => array( 
                        array(
                            'condition_type' => 'StaticSegment',
                            'op'             => ($segment_opts['conditions'][0]['op'] == 'eq') ? 'static_is' : 'static_not',
                            'field'          => $segment_opts['conditions'][0]['field'],
                            'value'          => $segment_opts['conditions'][0]['value']
                        )
                    )
                )
            ), 
        
            'type'       => $type,
            'settings'   => array(
                'subject_line' => $options['subject'],
                'reply_to'     => $options['from_email'],
                'from_name'    => $options['from_name'],
                'title'        => $options['title']
            )
        ));

        $endpoint = '/campaigns';
        
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;
        //get info pertaining to the segment opts section of last previous campaignCreate function 

        $response = $this->callServer($json, $method);

        $response = json_decode($response);

        print_r($response);

        $this->setCampaignContent($content, $response->id);

        echo ($response->id) ? "this campaigns id is: " . $response->id . "\n\n" : '';

        if ($response->errors || substr($response->status,0,1) === '4') {
            $this->errorMessage = ($response->errors) ? $response->errors[0]->message : $response->detail;
            $this->errorCode = $response->status; 

            echo "************************************************************************************\n\n";
            echo $this->errorCode . ': (' . $this->errorMessage .")\n\n";
            echo "************************************************************************************\n\n";

            return false;
        }

        if ($response->errors || substr($response->status,0,1) === '5') {
            $this->errorMessage = ($response->errors) ? $response->errors[0]->message : $response->detail;
            $this->errorCode = $response->status;

            echo "************************************************************************************\n\n";
            echo "There is an error on mailchimp's side. Contact apihelp@mailchimp.com \n\n";
            echo "************************************************************************************\n\n";
            
            return false;
        }

        if (!$response->errors && $response->id) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";

            return $response->id;
        } 

    }

    /*
    *@param array $content an array containing the html and plain text for a specific campaign 
    *@param string $cid ID for the campaign being created
    *@param return response object for use in the campaignCreate function
    */
    function setCampaignContent($content, $cid) {
        $method = 'PUT';

        $json = json_encode( array(
            'html' => $content['html'],
            'plain_text' => $content['text']
        ));

        $endpoint = '/campaigns/' . $cid . '/content';
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        $response = $this->callServer($json, $method);

        $response = json_decode($response);

        print_r($response);

        if ($response->errors || substr($response->status,0,1) === '4') {
            $this->errorMessage = ($response->errors) ? $response->errors[0]->message : $response->detail;
            $this->errorCode = $response->status; 

            echo "************************************************************************************\n\n";
            echo $this->errorCode . ': (' . $this->errorMessage .")\n\n";
            echo "************************************************************************************\n\n";

            return false;
        }

        if ($response->errors || substr($response->status,0,1) === '5') {
            $this->errorMessage = ($response->errors) ? $response->errors[0]->message : $response->detail;
            $this->errorCode = $response->status;

            echo "************************************************************************************\n\n";
            echo "There is an error on mailchimp's side. Contact apihelp@mailchimp.com \n\n";
            echo "************************************************************************************\n\n";
            
            return false;
        }

        if (!$response->errors && $response) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";

            return $content;
        } 
    }

    /** Update just about any setting for a campaign that has <em>not</em> been sent. See campaignCreate() for details.
     *
     *
     *  Caveats:<br/><ul class='simplelist square'>
     *        <li>If you set list_id, all segmentation options will be deleted and must be re-added.</li>
     *        <li>If you set template_id, you need to follow that up by setting it's 'content'</li>
     *        <li>If you set segment_opts, you should have tested your options against campaignSegmentTest() as campaignUpdate() will not allow you to set a segment that includes no members.</li>
     *        <li>To clear/unset segment_opts, pass an empty string or array as the value. Various wrappers may require one or the other.</li>
     * </ul>
     * @section Campaign  Related
     *
     * @example mcapi_campaignUpdate.php
     * @example mcapi_campaignUpdateAB.php
     * @example xml-rpc_campaignUpdate.php
     * @example xml-rpc_campaignUpdateAB.php
     *
     * @param string $cid the Campaign Id to update
     * @param string $name the parameter name ( see campaignCreate() ). For items in the <strong>options</strong> array, this will be that parameter's name (subject, from_email, etc.). Additional parameters will be that option name  (content, segment_opts). "type_opts" will be the name of the type - rss, auto, etc.
     * @param mixed  $value an appropriate value for the parameter ( see campaignCreate() ). For items in the <strong>options</strong> array, this will be that parameter's value. For additional parameters, this is the same value passed to them.
     * @return boolean true if the update succeeds, otherwise an error will be thrown
     */
    function campaignUpdate($cid, $name, $value) {
        $method = 'PATCH';

        if ($name == 'content') {
            $response = json_decode($this->setCampaignContent($value, $cid));
            print_r($response);
            if ($response->errors) {
                echo "************************************************************************************\n\n";
                echo "you have an error. Check the response object.\n\n";
                echo "************************************************************************************\n\n";

                return false;
            }

            if (!$response->errors) {
                echo "************************************************************************************\n\n";
                echo "Successfully completed.\n\n";
                echo "************************************************************************************\n\n";
                return true;

                print_r($response);
            }

        }

        else {
            $endpoint = '/campaigns/' . $cid;
            $url = $this->apiHost . $endpoint;
            $url = parse_url($url);

            $this->apiUrl = $url;

            ($name == 'subject') ? $name = 'subject_line' : '';
            ($name == 'from_email') ? $name = 'reply_to' : '';

            $json = json_encode(array(
                'settings'   => array(
                    $name => $value
                )
            ));

            $response = json_decode($this->callServer($json, $method));
            print_r($response);
            if ($response->errors) {
                echo "************************************************************************************\n\n";
                echo "you have an error. Check the response object.\n\n";
                echo $response->errors;
                echo "************************************************************************************\n\n";

                return false;
            }

            if (!$response->errors) {
                echo "************************************************************************************\n\n";
                echo "Successfully completed.\n\n";
                echo "************************************************************************************\n\n";
                return true;

                print_r($response);
            }

        }
    }

    /** Replicate a campaign.
     *
     * @section Campaign  Related
     *
     * @example mcapi_campaignReplicate.php
     *
     * @param string $cid the Campaign Id to replicate
     * @return string the id of the replicated Campaign created, otherwise an error will be thrown
     */
    function campaignReplicate($cid) {
        $method = 'POST';
        $endpoint = '/campaigns/' . $cid . '/actions/replicate';
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        $info = $this->callServer($json, $method);

        $info = json_decode($info);

        print_r($info);

        return $info->id;
    }

    /** Delete a campaign. Seriously, "poof, gone!" - be careful!
     *
     * @section Campaign  Related
     *
     * @example mcapi_campaignDelete.php
     *
     * @param string $cid the Campaign Id to delete
     * @return boolean true if the delete succeeds, otherwise an error will be thrown
     */
    function campaignDelete($cid) {
        $method = 'DELETE';
        $endpoint = '/campaigns/' . $cid;
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        $response = $this->callServer($json, $method);

        $response = json_decode($response);

        print_r($response);

        if ($response->errors || substr($response->status,0,1) === '4') {
            $this->errorMessage = ($response->errors) ? $response->errors[0]->message : $response->detail;
            $this->errorCode = $response->status; 

            echo "************************************************************************************\n\n";
            echo $this->errorCode . ': (' . $this->errorMessage .")\n\n";
            echo "************************************************************************************\n\n";

            return false;
        }

        if ($response->errors || substr($response->status,0,1) === '5') {
            $this->errorMessage = ($response->errors) ? $response->errors[0]->message : $response->detail;
            $this->errorCode = $response->status;

            echo "************************************************************************************\n\n";
            echo "There is an error on mailchimp's side. Contact apihelp@mailchimp.com \n\n";
            echo "************************************************************************************\n\n";
            
            return false;
        }

        if (!$response->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";
            return true;
        }
    }

    /**
     * Get the list of campaigns and their details matching the specified filters
     *
     * @section Campaign  Related
     * @example mcapi_campaigns.php
     * @example xml-rpc_campaigns.php
     *
     * @param array $filters a hash of filters to apply to this query - all are optional:
    string campaign_id optional - return the campaign using a know campaign_id.  Accepts multiples separated by commas when not using exact matching.
    string parent_id optional - return the child campaigns using a known parent campaign_id.  Accepts multiples separated by commas when not using exact matching.
    string list_id optional - the list to send this campaign to - get lists using lists(). Accepts multiples separated by commas when not using exact matching.
    int folder_id optional - only show campaigns from this folder id - get folders using campaignFolders(). Accepts multiples separated by commas when not using exact matching.
    int template_id optional - only show campaigns using this template id - get templates using templates(). Accepts multiples separated by commas when not using exact matching.
    string  status optional - return campaigns of a specific status - one of "sent", "save", "paused", "schedule", "sending". Accepts multiples separated by commas when not using exact matching.
    string  type optional - return campaigns of a specific type - one of "regular", "plaintext", "absplit", "rss", "auto". Accepts multiples separated by commas when not using exact matching.
    string  from_name optional - only show campaigns that have this "From Name"
    string  from_email optional - only show campaigns that have this "Reply-to Email"
    string  title optional - only show campaigns that have this title
    string  subject optional - only show campaigns that have this subject
    string  sendtime_start optional - only show campaigns that have been sent since this date/time (in GMT) -  - 24 hour format in <strong>GMT</strong>, eg "2013-12-30 20:30:00"
    string  sendtime_end optional - only show campaigns that have been sent before this date/time (in GMT) -  - 24 hour format in <strong>GMT</strong>, eg "2013-12-30 20:30:00"
    boolean uses_segment - whether to return just campaigns with or without segments
    boolean exact optional - flag for whether to filter on exact values when filtering, or search within content for filter values - defaults to true. Using this disables the use of any filters that accept multiples.
    * @param int $start optional - control paging of campaigns, start results at this campaign #, defaults to 1st page of data  (page 0)
    * @param int $limit optional - control paging of campaigns, number of campaigns to return with each call, defaults to 25 (max=1000)
    * @param string sort_field optional - one of "create_time", "send_time", "title", "subject" . Invalid values will fall back on "create_time" - case insensitive.
    * @param string sort_dir optional - "DESC" for descending (default), "ASC" for Ascending.  Invalid values will fall back on "DESC" - case insensitive.
    * @return array an array containing a count of all matching campaigns and the specific ones for the current page (see Returned Fields for description)
    int total the total number of campaigns matching the filters passed in
    array data the data for each campaign being returned
    string id Campaign Id (used for all other campaign functions)
    int web_id The Campaign id used in our web app, allows you to create a link directly to it
    string list_id The List used for this campaign
    int folder_id The Folder this campaign is in
    int template_id The Template this campaign uses
    string content_type How the campaign's content is put together - one of 'template', 'html', 'url'
    string title Title of the campaign
    string type The type of campaign this is (regular,plaintext,absplit,rss,inspection,auto)
    string create_time Creation time for the campaign
    string send_time Send time for the campaign - also the scheduled time for scheduled campaigns.
    int emails_sent Number of emails email was sent to
    string status Status of the given campaign (save,paused,schedule,sending,sent)
    string from_name From name of the given campaign
    string from_email Reply-to email of the given campaign
    string subject Subject of the given campaign
    string to_name Custom "To:" email string using merge variables
    string archive_url Archive link for the given campaign
    boolean inline_css Whether or not the campaign content's css was auto-inlined
    string analytics Either "google" if enabled or "N" if disabled
    string analytics_tag The name/tag the campaign's links were tagged with if analytics were enabled.
    boolean authenticate Whether or not the campaign was authenticated
    boolean ecomm360 Whether or not ecomm360 tracking was appended to links
    boolean auto_tweet Whether or not the campaign was auto tweeted after sending
    string auto_fb_post A comma delimited list of Facebook Profile/Page Ids the campaign was posted to after sending. If not used, blank.
    boolean auto_footer Whether or not the auto_footer was manually turned on
    boolean timewarp Whether or not the campaign used Timewarp
    string timewarp_schedule The time, in GMT, that the Timewarp campaign is being sent. For A/B Split campaigns, this is blank and is instead in their schedule_a and schedule_b in the type_opts array
    string parent_id the unique id of the parent campaign (currently only valid for rss children)
    array tracking the various tracking options used
    boolean html_clicks whether or not tracking for html clicks was enabled.
    boolean text_clicks whether or not tracking for text clicks was enabled.
    boolean opens whether or not opens tracking was enabled.
    string segment_text a string marked-up with HTML explaining the segment used for the campaign in plain English
    array segment_opts the segment used for the campaign - can be passed to campaignSegmentTest() or campaignCreate()
    array type_opts the type-specific options for the campaign - can be passed to campaignCreate()
    */
    function campaigns($filters=array (
    ), $start=0, $limit=25, $sort_field='create_time', $sort_dir='DESC') {
        //start will eventually equal offset and limit will be count 
        $method = 'GET';
        $endpoint = '/campaigns';

        $url = $this->apiHost . $endpoint;
        $url = $url . '?count=' . $limit . '&offset=' . $start;
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        $response = $this->callServer($json, $method);

        $response = json_decode($response, true);

        print_r($response);
    }

    /**
     * Given a list and a campaign, get all the relevant campaign statistics (opens, bounces, clicks, etc.)
     *
     * @section Campaign  Stats
     *
     * @example mcapi_campaignStats.php
     * @example xml-rpc_campaignStats.php
     *
     * @param string $cid the campaign id to pull stats for (can be gathered using campaigns())
     * @return array struct of the statistics for this campaign
    int syntax_errors Number of email addresses in campaign that had syntactical errors.
    int hard_bounces Number of email addresses in campaign that hard bounced.
    int soft_bounces Number of email addresses in campaign that soft bounced.
    int unsubscribes Number of email addresses in campaign that unsubscribed.
    int abuse_reports Number of email addresses in campaign that reported campaign for abuse.
    int forwards Number of times email was forwarded to a friend.
    int forwards_opens Number of times a forwarded email was opened.
    int opens Number of times the campaign was opened.
    string last_open Date of the last time the email was opened.
    int unique_opens Number of people who opened the campaign.
    int clicks Number of times a link in the campaign was clicked.
    int unique_clicks Number of unique recipient/click pairs for the campaign.
    string last_click Date of the last time a link in the email was clicked.
    int users_who_clicked Number of unique recipients who clicked on a link in the campaign.
    int emails_sent Number of email addresses campaign was sent to.
    int unique_likes total number of unique likes (Facebook)
    int recipient_likes total number of recipients who liked (Facebook) the campaign
    int facebook_likes total number of likes (Facebook) that came from Facebook
    array absplit If this was an absplit campaign, stats for the A and B groups will be returned
    int bounces_a bounces for the A group
    int bounces_b bounces for the B group
    int forwards_a forwards for the A group
    int forwards_b forwards for the B group
    int abuse_reports_a abuse reports for the A group
    int abuse_reports_b abuse reports for the B group
    int unsubs_a unsubs for the A group
    int unsubs_b unsubs for the B group
    int recipients_click_a clicks for the A group
    int recipients_click_b clicks for the B group
    int forwards_opens_a opened forwards for the A group
    int forwards_opens_b opened forwards for the B group
    int opens_a total opens for the A group
    int opens_b total opens for the B group
    string last_open_a date/time of last open for the A group
    string last_open_b date/time of last open for the BG group
    int unique_opens_a unique opens for the A group
    int unique_opens_b unique opens for the B group
    array timewarp If this campaign was a Timewarp campaign, an array of stats from each timezone for it, with the GMT offset as they key. Each timezone will contain:
    int opens opens for this timezone
    string last_open the date/time of the last open for this timezone
    int unique_opens the unique opens for this timezone
    int clicks the total clicks for this timezone
    string last_click the date/time of the last click for this timezone
    int unique_opens the unique clicks for this timezone
    int bounces the total bounces for this timezone
    int total the total number of members sent to in this timezone
    int sent the total number of members delivered to in this timezone
    array timeseries For the first 24 hours of the campaign, per-hour stats:
    string timestamp The timestemp in Y-m-d H:00:00 format
    int emails_sent the total emails sent during the hour
    int unique_opens unique opens seen during the hour
    int recipients_click unique clicks seen during the hour

     */
    function campaignStats($cid) {
        //does this require uniformity or is the array that is naturally returned enough?
        $method = 'GET';
        $endpoint = '/reports/' . $cid;
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        $response = $this->callServer($json, $method);

        $response = json_decode($response, true);

        print_r($response);
    }

    /**
     * Get an array of the urls being tracked, and their click counts for a given campaign
     *
     * @section Campaign  Stats
     *
     * @example mcapi_campaignClickStats.php
     * @example xml-rpc_campaignClickStats.php
     *
     * @param string $cid the campaign id to pull stats for (can be gathered using campaigns())
     * @return array urls will be keys and contain their associated statistics:
    int clicks Number of times the specific link was clicked
    int unique Number of unique people who clicked on the specific link
     */
    function campaignClickStats($cid) {
        $method = 'GET';
        $endpoint = '/reports/' . $cid . '/click-details'; 
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        $response = $this->callServer($json, $method);

        $response = json_decode($response, true);

        print_r($response);
    }

    /**
     * Get the top 5 performing email domains for this campaign. Users want more than 5 should use campaign campaignEmailStatsAIM()
     * or campaignEmailStatsAIMAll() and generate any additional stats they require.
     *
     * @section Campaign  Stats
     *
     * @example mcapi_campaignEmailDomainPerformance.php
     *
     * @param string $cid the campaign id to pull email domain performance for (can be gathered using campaigns())
     * @return array domains email domains and their associated stats
    string domain Domain name or special "Other" to roll-up stats past 5 domains
    int total_sent Total Email across all domains - this will be the same in every row
    int emails Number of emails sent to this domain
    int bounces Number of bounces
    int opens Number of opens
    int clicks Number of clicks
    int unsubs Number of unsubs
    int delivered Number of deliveries
    int emails_pct Percentage of emails that went to this domain (whole number)
    int bounces_pct Percentage of bounces from this domain (whole number)
    int opens_pct Percentage of opens from this domain (whole number)
    int clicks_pct Percentage of clicks from this domain (whole number)
    int unsubs_pct Percentage of unsubs from this domain (whole number)
     */
    function campaignEmailDomainPerformance($cid) {
        $method = 'GET';
        $endpoint = '/reports/' . $cid . '/domain-performance'; 
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        $response = $this->callServer($json, $method);

        $response = json_decode($response, true);

        print_r($response);
    }

    /**
     * Get all email addresses the campaign was sent to
     *
     * @section Campaign  Stats
     *
     * @param string $cid the campaign id to pull members for (can be gathered using campaigns())
     * @param string $status optional the status to pull - one of 'sent', 'hard' (bounce), or 'soft' (bounce). By default, all records are returned
     * @param int    $start optional for large data sets, the page number to start at - defaults to 1st page of data (page 0)
     * @param int    $limit optional for large data sets, the number of results to return - defaults to 1000, upper limit set at 15000
     * @return array a total of all matching emails and the specific emails for this page
    int total   the total number of members for the campaign and status
    array data  the full campaign member records
    string email the email address sent to
    string status the status of the send - one of 'sent', 'hard', 'soft'
    string absplit_group if this was an absplit campaign, one of 'a','b', or 'winner'
    string tz_group if this was an timewarp campaign the timezone GMT offset the member was included in
     */
    function getCampaignListId($cid) {
        $method = 'GET';
        $endpoint = '/campaigns/' . $cid;
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        $response = $this->callServer($json, $method, true);

        $response = json_decode($response, true);

        echo $response['recipients']['list_id'] . "\n\n";

        return $response['recipients']['list_id'];
    }

    function campaignMembers($cid, $status=NULL, $start=0, $limit=1000) {
        //might have to find a way to paginate this*******
        $listId = $this->getCampaignListId($cid);

        $method = 'GET';
        $endpoint = '/lists/' . $listId . '/members'; 
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        $response = $this->callServer($json, $method);

        $response = json_decode($response, true);

        print_r($response);

        return $response;
    }

    /**
     * <strong>DEPRECATED</strong> Get all email addresses with Hard Bounces for a given campaign
     *
     * @deprecated See campaignMembers() for a replacement
     *
     * @section Campaign  Stats
     *
     * @param string $cid the campaign id to pull bounces for (can be gathered using campaigns())
     * @param int    $start optional for large data sets, the page number to start at - defaults to 1st page of data (page 0)
     * @param int    $limit optional for large data sets, the number of results to return - defaults to 1000, upper limit set at 15000
     * @return array a total of all hard bounced emails and the specific emails for this page
    int total   the total number of hard bounces for the campaign
    array data array of the full email addresses that bounced
     */
    function campaignHardBounces($cid, $start=0, $limit=1000) {
        //deprecated
        $params = array();
        $params["cid"] = $cid;
        $params["start"] = $start;
        $params["limit"] = $limit;
        return $this->callServer("campaignHardBounces", $params);
    }

    /**
     * <strong>DEPRECATED</strong> Get all email addresses with Soft Bounces for a given campaign
     *
     * @deprecated See campaignMembers() for a replacement
     *
     * @section Campaign  Stats
     *
     * @param string $cid the campaign id to pull bounces for (can be gathered using campaigns())
     * @param int    $start optional for large data sets, the page number to start at - defaults to 1st page of data (page 0)
     * @param int    $limit optional for large data sets, the number of results to return - defaults to 1000, upper limit set at 15000
     * @return array a total of all soft bounced emails and the specific emails for this page
    int total   the total number of soft bounces for the campaign
    array data array of the full email addresses that bounced
     */
    function campaignSoftBounces($cid, $start=0, $limit=1000) {
        //deprecated
        $params = array();
        $params["cid"] = $cid;
        $params["start"] = $start;
        $params["limit"] = $limit;
        return $this->callServer("campaignSoftBounces", $params);
    }

    /**
     * Get all unsubscribed email addresses for a given campaign
     *
     * @section Campaign  Stats
     *
     * @param string $cid the campaign id to pull bounces for (can be gathered using campaigns())
     * @param int    $start optional for large data sets, the page number to start at - defaults to 1st page of data  (page 0)
     * @param int    $limit optional for large data sets, the number of results to return - defaults to 1000, upper limit set at 15000
     * @return array a total of all unsubscribed emails and the specific emails for this page
    int total   the total number of unsubscribes for the campaign
    array data  the full email addresses that unsubscribed
    string email the email address that unsubscribed
    string reason For unsubscribes only - the reason collected for the unsubscribe. If populated, one of 'NORMAL','NOSIGNUP','INAPPROPRIATE','SPAM','OTHER'
    string reason_text For unsubscribes only - if the reason is OTHER, the text entered.
     */
    function campaignUnsubscribes($cid, $start=0, $limit=1000) {
        //not exactly sure what this translates to
        $method = 'GET';
        $endpoint = '/reports/' . $cid . '/unsubscribed'; 
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        $response = $this->callServer($json, $method);

        $response = json_decode($response, true);

        print_r($response);

        return $response;
    }

    /**
     * Get all email addresses that complained about a given campaign
     *
     * @section Campaign  Stats
     *
     * @example mcapi_campaignAbuseReports.php
     *
     * @param string $cid the campaign id to pull abuse reports for (can be gathered using campaigns())
     * @param int $start optional for large data sets, the page number to start at - defaults to 1st page of data  (page 0)
     * @param int $limit optional for large data sets, the number of results to return - defaults to 500, upper limit set at 1000
     * @param string $since optional pull only messages since this time - 24 hour format in <strong>GMT</strong>, eg "2013-12-30 20:30:00"
     * @return array reports the abuse reports for this campaign
    int total the total reports matched
    array data the report data for each, including:
    string date date/time the abuse report was received and processed
    string email the email address that reported abuse
    string type an internal type generally specifying the orginating mail provider - may not be useful outside of filling report views
     */
    function campaignAbuseReports($cid, $since=NULL, $start=0, $limit=500) {
        //assure that this lines up properly and that the necessary parameters are utilized
        $method = 'GET';
        $endpoint = '/reports/' . $cid; 
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        $response = $this->callServer($json, $method);

        $response = json_decode($response, true);

        print_r($response);

        print_r($url);
    }

    /**
     * Retrieve the text presented in our app for how a campaign performed and any advice we may have for you - best
     * suited for display in customized reports pages. Note: some messages will contain HTML - clean tags as necessary
     *
     * @section Campaign  Stats
     *
     * @example mcapi_campaignAdvice.php
     *
     * @param string $cid the campaign id to pull advice text for (can be gathered using campaigns())
     * @return array advice on the campaign's performance, each containing:
    msg the advice message
    type the "type" of the message. one of: negative, positive, or neutral
     */
    function campaignAdvice($cid) {
        $method = 'GET';
        $endpoint = '/reports/' . $cid . '/advice'; 
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        $response = $this->callServer($json, $method);

        $response = json_decode($response, true);

        print_r($response);

        print_r($url);
    }

    /**
     * Retrieve the Google Analytics data we've collected for this campaign. Note, requires Google Analytics Add-on to be installed and configured.
     *
     * @section Campaign  Stats
     *
     * @example mcapi_campaignAnalytics.php
     *
     * @param string $cid the campaign id to pull bounces for (can be gathered using campaigns())
     * @return array analytics we've collected for the passed campaign.
    int visits number of visits
    int pages number of page views
    int new_visits new visits recorded
    int bounces vistors who "bounced" from your site
    double time_on_site the total time visitors spent on your sites
    int goal_conversions number of goals converted
    double goal_value value of conversion in dollars
    double revenue revenue generated by campaign
    int transactions number of transactions tracked
    int ecomm_conversions number Ecommerce transactions tracked
    array goals an array containing goal names and number of conversions
    string name the name of the goal
    int conversions the number of conversions for the goal
     */
    function campaignAnalytics($cid) {
        //this function doesnt seem to translate at all
        //need to find proper end point

    }

    /**
     * Retrieve the countries and number of opens tracked for each. Email address are not returned.
     *
     * @section Campaign  Stats
     *
     *
     * @param string $cid the campaign id to pull bounces for (can be gathered using campaigns())
     * @return array countries an array of countries where opens occurred
    string code The ISO3166 2 digit country code
    string name A version of the country name, if we have it
    int opens The total number of opens that occurred in the country
    boolean region_detail Whether or not a subsequent call to campaignGeoOpensByCountry() will return anything
     */
    function campaignGeoOpens($cid) {

    }

    /**
     * Retrieve the regions and number of opens tracked for a certain country. Email address are not returned.
     *
     * @section Campaign  Stats
     *
     *
     * @param string $cid the campaign id to pull bounces for (can be gathered using campaigns())
     * @param string $code An ISO3166 2 digit country code
     * @return array regions an array of regions within the provided country where opens occurred.
    string code An internal code for the region. When this is blank, it indicates we know the country, but not the region
    string name The name of the region, if we have one. For blank "code" values, this will be "Rest of Country"
    int opens The total number of opens that occurred in the country
     */
    function campaignGeoOpensForCountry($cid, $code) {
        //find proper endpoint
    }

    /** 
     * Retrieve the tracked eepurl mentions on Twitter
     *
     * @section Campaign  Stats
     *
     *
     * @param string $cid the campaign id to pull bounces for (can be gathered using campaigns())
     * @return array stats an array containing tweets, retweets, clicks, and referrer related to using the campaign's eepurl
    array twitter various Twitter related stats
    int tweets Total number of tweets seen
    string first_tweet date and time of the first tweet seen
    string last_tweet date and time of the last tweet seen
    int retweets Total number of retweets seen
    string first_retweet date and time of the first retweet seen
    string last_retweet date and time of the last retweet seen
    array statuses an array of statuses recorded inclduing:
    string status the text of the tweet/update
    string screen_name the screen name as recorded when first seen
    string status_id the status id of the tweet (they are really unsigned 64 bit ints)
    string datetime the date/time of the tweet
    bool is_retweet whether or not this was a retweet
    array clicks stats related to click-throughs on the eepurl
    int clicks Total number of clicks seen
    string first_click date and time of the first click seen
    string last_click date and time of the first click seen
    array locations an array of geographic locations including:
    string country the country name the click was tracked to
    string region the region in the country the click was tracked to (if available)
    int total clicks total clicks occuring in this country+region pair
    array referrers an array of arrays, each containing
    string referrer the referrer, truncated to 100 bytes
    int clicks Total number of clicks seen from this referrer
    string first_click date and time of the first click seen from this referrer
    string last_click date and time of the first click seen from this referrer
     */
    function campaignEepUrlStats($cid) {
        $method = 'GET';
        $endpoint = '/reports/' . $cid .'/eepurl';
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        $response = $this->callServer($json, $method);

        $response = json_decode($response, true);

        print_r($response);

        print_r($url);
    }

    /**
     * Retrieve the most recent full bounce message for a specific email address on the given campaign.
     * Messages over 30 days old are subject to being removed
     *
     *
     * @section Campaign  Stats
     *
     * @param string $cid the campaign id to pull bounces for (can be gathered using campaigns())
     * @param string $email the email address or unique id of the member to pull a bounce message for.
     * @return array the full bounce message for this email+campaign along with some extra data.
    string date date/time the bounce was received and processed
    string email the email address that bounced
    string message the entire bounce message received
     */
    function campaignBounceMessage($cid, $email) {
        //probably associated with another end point and has a different name, find it - DEPRECATED.
        $method = 'GET';
        //$endpoint = '/reports/' . $cid .'/eepurl';
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        $response = $this->callServer($json, $method);

        $response = json_decode($response, true);

        print_r($response);

        print_r($url);
    }

    /**
     * Retrieve the full bounce messages for the given campaign. Note that this can return very large amounts
     * of data depending on how large the campaign was and how much cruft the bounce provider returned. Also,
     * message over 30 days old are subject to being removed
     *
     * @section Campaign  Stats
     *
     * @example mcapi_campaignBounceMessages.php
     *
     * @param string $cid the campaign id to pull bounces for (can be gathered using campaigns())
     * @param int $start optional for large data sets, the page number to start at - defaults to 1st page of data  (page 0)
     * @param int $limit optional for large data sets, the number of results to return - defaults to 25, upper limit set at 50
     * @param string $since optional pull only messages since this time - use YYYY-MM-DD format in <strong>GMT</strong> (we only store the date, not the time)
     * @return array bounces the full bounce messages for this campaign
    int total that total number of bounce messages for the campaign
    array data an array containing the data for this page
    string date date/time the bounce was received and processed
    string email the email address that bounced
    string message the entire bounce message received
     */
    function campaignBounceMessages($cid, $start=0, $limit=25, $since=NULL) {
        //same as above 
    }

    /**
     * Retrieve the Ecommerce Orders tracked by campaignEcommOrderAdd()
     *
     * @section Campaign  Stats
     *
     * @param string $cid the campaign id to pull bounces for (can be gathered using campaigns())
     * @param int $start optional for large data sets, the page number to start at - defaults to 1st page of data  (page 0)
     * @param int $limit optional for large data sets, the number of results to return - defaults to 100, upper limit set at 500
     * @param string $since optional pull only messages since this time - 24 hour format in <strong>GMT</strong>, eg "2013-12-30 20:30:00"
     * @return array the total matching orders and the specific orders for the requested page
    int total the total matching orders
    array data the actual data for each order being returned
    string store_id the store id generated by the plugin used to uniquely identify a store
    string store_name the store name collected by the plugin - often the domain name
    string order_id the internal order id the store tracked this order by
    string email  the email address that received this campaign and is associated with this order
    double order_total the order total
    double tax_total the total tax for the order (if collected)
    double ship_total the shipping total for the order (if collected)
    string order_date the date the order was tracked - from the store if possible, otherwise the GMT time we received it
    array lines containing detail of the order:
    int line_num the line number assigned to this line
    int product_id the product id assigned to this item
    string product_name the product name
    string product_sku the sku for the product
    int product_category_id the id for the product category
    string product_category_name the product category name
    double qty optional the quantity of the item ordered - defaults to 1
    double cost optional the cost of a single item (ie, not the extended cost of the line) - defaults to 0
     */
    function campaignEcommOrders($cid, $start=0, $limit=100, $since=NULL) {
        //as dj khaled would say, 'another one'. Must figure out proper endpoint
        $params = array();
        $params["cid"] = $cid;
        $params["start"] = $start;
        $params["limit"] = $limit;
        $params["since"] = $since;
        return $this->callServer("campaignEcommOrders", $params);
    }

    /**
     * Get the URL to a customized <a href="http://eepurl.com/gKmL" target="_blank">VIP Report</a> for the specified campaign and optionally send an email to someone with links to it. Note subsequent calls will overwrite anything already set for the same campign (eg, the password)
     *
     * @section Campaign  Related
     *
     * @param string $cid the campaign id to share a report for (can be gathered using campaigns())
     * @param array  $opts optional various parameters which can be used to configure the shared report
    string to_email optional - optional, comma delimited list of email addresses to share the report with - no value means an email will not be sent
    string company optional - a company name to be displayed (use of a theme may hide this) - max 255 bytes
    int theme_id optional - either a global or a user-specific theme id. Currently this needs to be pulled out of either the Share Report or Cobranding web views by grabbing the "theme" attribute from the list presented.
    string  css_url    optional - a link to an external CSS file to be included after our default CSS (http://vip-reports.net/css/vip.css) <strong>only if</strong> loaded via the "secure_url" - max 255 bytes
     * @return array Array containing details for the shared report
    string title The Title of the Campaign being shared
    string url The URL to the shared report
    string secure_url The URL to the shared report, including the password (good for loading in an IFRAME). For non-secure reports, this will not be returned
    string password If secured, the password for the report, otherwise this field will not be returned
     */
    function campaignShareReport($cid, $opts=array (
    )) {
        //doesnt exist  
    }

    /**
     * Get the content (both html and text) for a campaign either as it would appear in the campaign archive or as the raw, original content
     *
     * @section Campaign  Related
     *
     * @param string $cid the campaign id to get content for (can be gathered using campaigns())
     * @param bool   $for_archive optional controls whether we return the Archive version (true) or the Raw version (false), defaults to true
     * @return array Array containing all content for the campaign
    string html The HTML content used for the campaign with merge tags intact
    string text The Text content used for the campaign with merge tags intact
     */
    function campaignContent($cid, $for_archive=true) {
        $method = 'GET';

        $endpoint = '/campaigns/' . $cid . '/content';
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        $response = $this->callServer($json, $method);

        $response = json_decode($response, true);

        print_r($response);

        return $response;
    }

    function singleCampaign($cid) {
        $method = 'GET';

        $endpoint = '/campaigns/' . $cid;
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        $response = $this->callServer($json, $method);

        $response = json_decode($response, true);

        print_r($response);

        return $response;
    }

    /**
     * Get the HTML template content sections for a campaign. Note that this <strong>will</strong> return very jagged, non-standard results based on the template
     * a campaign is using. You only want to use this if you want to allow editing template sections in your applicaton.
     *
     * @section Campaign  Related
     *
     * @param string $cid the campaign id to get content for (can be gathered using campaigns())
     * @return array array containing all content section for the campaign - section name are dependent upon the template used and thus can't be documented
     */
    function campaignTemplateContent($cid) {
        $singleCampaign = $this->singleCampaign($cid);
        $templateId = $singleCampaign['settings']['template_id'];

        $method = 'GET';

        $endpoint = '/templates/' . $templateId . '/default-content';
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        $response = $this->callServer($json, $method);

        $response = json_decode($response, true);

        print_r($response);

        return $response;
    }

    /**
     * Retrieve the list of email addresses that opened a given campaign with how many times they opened
     *
     * @section Campaign Report Data
     *
     * @param string $cid the campaign id to get opens for (can be gathered using campaigns())
     * @param int    $start optional for large data sets, the page number to start at - defaults to 1st page of data  (page 0)
     * @param int    $limit optional for large data sets, the number of results to return - defaults to 1000, upper limit set at 15000
     * @return array array containing the total records matched and the specific records for this page
    int total the total number of records matched
    array data the actual opens data, including:
    string email Email address that opened the campaign
    int open_count Total number of times the campaign was opened by this email address
     */
    function campaignOpenedAIM($cid, $start=0, $limit=1000) {
        //no longer valid, find out which endpoint this has been consolidated into
        //this is now the reports/campaign_id/email-activity endpoint
    }

    /**
     * Retrieve the list of email addresses that did not open a given campaign
     *
     * @section Campaign Report Data
     *
     * @param string $cid the campaign id to get no opens for (can be gathered using campaigns())
     * @param int    $start optional for large data sets, the page number to start at - defaults to 1st page of data  (page 0)
     * @param int    $limit optional for large data sets, the number of results to return - defaults to 1000, upper limit set at 15000
     * @return array array containing the total records matched and the specific records for this page
    int total the total number of records matched
    array data the email addresses that did not open the campaign
     */
    function campaignNotOpenedAIM($cid, $start=0, $limit=1000) {
        //no longer valid, find out which endpoint this has been consolidated into
    }

    /**
     * Return the list of email addresses that clicked on a given url, and how many times they clicked
     *
     * @section Campaign Report Data
     *
     * @param string $cid the campaign id to get click stats for (can be gathered using campaigns())
     * @param string $url the URL of the link that was clicked on
     * @param int    $start optional for large data sets, the page number to start at - defaults to 1st page of data (page 0)
     * @param int    $limit optional for large data sets, the number of results to return - defaults to 1000, upper limit set at 15000
     * @return array array containing the total records matched and the specific records for this page
    int total the total number of records matched
    array data the email addresses that did not open the campaign
    string email Email address that opened the campaign
    int clicks Total number of times the URL was clicked on by this email address
     */
    function campaignClickDetailAIM($cid, $url, $start=0, $limit=1000) {
        //no longer valid, find out which endpoint this has been consolidated into
    }

    /**
     * Given a campaign and email address, return the entire click and open history with timestamps, ordered by time
     *
     * @section Campaign Report Data
     *
     * @param string $cid the campaign id to get stats for (can be gathered using campaigns())
     * @param array $email_address an array of up to 50 email addresses to check OR the email "id" returned from listMemberInfo, Webhooks, and Campaigns. For backwards compatibility, if a string is passed, it will be treated as an array with a single element (will not work with XML-RPC).
     * @return array an array with the keys listed in Returned Fields below
    int success the number of email address records found
    int error the number of email address records which could not be found
    array data arrays containing the actions (opens and clicks) that the email took, with timestamps
    string action The action taken (open or click)
    string timestamp Time the action occurred
    string url For clicks, the URL that was clicked
     */
    function campaignEmailStatsAIM($cid, $email_address) {
        //no longer valid, find out which endpoint this has been consolidated into
    }

    /**
     * Attach Ecommerce Order Information to a Campaign. This will generally be used by ecommerce package plugins
     * <a href="http://connect.mailchimp.com/category/ecommerce" target="_blank">provided by us or by 3rd part system developers</a>.
     * @section Campaign  Related
     *
     * @param array $order an array of information pertaining to the order that has completed. Use the following keys:
    string id the Order Id
    string campaign_id the Campaign Id to track this order with (see the "mc_cid" query string variable a campaign passes)
    string email_id the Email Id of the subscriber we should attach this order to (see the "mc_eid" query string variable a campaign passes)
    double total The Order Total (ie, the full amount the customer ends up paying)
    string order_date optional the date of the order - if this is not provided, we will default the date to now
    double shipping optional the total paid for Shipping Fees
    double tax optional the total tax paid
    string store_id a unique id for the store sending the order in (20 bytes max)
    string store_name optional a "nice" name for the store - typically the base web address (ie, "store.mailchimp.com"). We will automatically update this if it changes (based on store_id)
    array items the individual line items for an order using these keys:
    <div style="padding-left:30px"><table>
    int line_num optional the line number of the item on the order. We will generate these if they are not passed
    int product_id the store's internal Id for the product. Lines that do no contain this will be skipped
    string sku optional the store's internal SKU for the product. (max 30 bytes)
    string product_name the product name for the product_id associated with this item. We will auto update these as they change (based on product_id)
    int category_id the store's internal Id for the (main) category associated with this product. Our testing has found this to be a "best guess" scenario
    string category_name the category name for the category_id this product is in. Our testing has found this to be a "best guess" scenario. Our plugins walk the category heirarchy up and send "Root - SubCat1 - SubCat4", etc.
    double qty the quantity of the item ordered
    double cost the cost of a single item (ie, not the extended cost of the line)
    </table></div>
     * @return bool true if the data is saved, otherwise an error is thrown.
     */
    function campaignEcommOrderAdd($order) {
        //use $order array to create json object for request
        //continue to translate the parameters above into something that works with 3.0 api
        $method = 'POST';

        $endpoint = '/ecommerce/stores/' . $store_id . '/orders';
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array(
            'id'       => '',
            'customer' => array(
                'id' => ''
            ),
            'campaign_id' => '',
            'order_total' => ''
        ));

        $json = json_encode( array(
            'recipients' => array( 
                'list_id'     => $options['list_id'],
                'segment_opts' => array(
                    'match'      => 'any',
                    'conditions' => array( 
                         array(
                            'condition_type' => 'StaticSegment',
                            'op'             => ($segment_opts['conditions'][0]['op'] == 'eq') ? 'static_is' : 'static_not',
                            'field'          => $segment_opts['conditions'][0]['field'],
                            'value'          => $segment_opts['conditions'][0]['value']
                        )
                    )
                )
            ), 
        
            'type'       => $type,
            'settings'   => array(
                'subject_line' => $options['subject'],
                'reply_to'     => $options['from_email'],
                'from_name'    => $options['from_name'],
                'title'        => $options['title']
            )
        ));

        $response = $this->callServer($json, $method);

        $response = json_decode($response, true);

        print_r($response);

        print_r($url);
    }

    /**
     * Retrieve all of the lists defined for your user account
     *
     * @section List Related
     * @example mcapi_lists.php
     * @example xml-rpc_lists.php
     *
     * @param array $filters a hash of filters to apply to this query - all are optional:
    string list_id optional - return a single list using a known list_id. Accepts multiples separated by commas when not using exact matching
    string list_name optional - only lists that match this name
    string from_name optional - only lists that have a default from name matching this
    string from_email optional - only lists that have a default from email matching this
    string from_subject optional - only lists that have a default from email matching this
    string created_before optional - only show lists that were created before this date/time  - 24 hour format in <strong>GMT</strong>, eg "2013-12-30 20:30:00"
    string created_after optional - only show lists that were created since this date/time  - 24 hour format in <strong>GMT</strong>, eg "2013-12-30 20:30:00"
    boolean exact optional - flag for whether to filter on exact values when filtering, or search within content for filter values - defaults to true
     * @param int $start optional - control paging of lists, start results at this list #, defaults to 1st page of data  (page 0)
     * @param int $limit optional - control paging of lists, number of lists to return with each call, defaults to 25 (max=100)
     * @param string sort_field optional - "created" (the created date, default) or "web" (the display order in the web app). Invalid values will fall back on "created" - case insensitive.
     * @param string sort_dir optional - "DESC" for descending (default), "ASC" for Ascending.  Invalid values will fall back on "created" - case insensitive. Note: to get the exact display order as the web app you'd use "web" and "ASC"
     * @return array an array with keys listed in Returned Fields below
    int total the total number of lists which matched the provided filters
    array data the lists which matched the provided filters, including the following for
    string id The list id for this list. This will be used for all other list management functions.
    int web_id The list id used in our web app, allows you to create a link directly to it
    string name The name of the list.
    string date_created The date that this list was created.
    boolean email_type_option Whether or not the List supports multiple formats for emails or just HTML
    boolean use_awesomebar Whether or not campaigns for this list use the Awesome Bar in archives by default
    string default_from_name Default From Name for campaigns using this list
    string default_from_email Default From Email for campaigns using this list
    string default_subject Default Subject Line for campaigns using this list
    string default_language Default Language for this list's forms
    double list_rating An auto-generated activity score for the list (0 - 5)
    string subscribe_url_short Our eepurl shortened version of this list's subscribe form (will not change)
    string subscribe_url_long The full version of this list's subscribe form (host will vary)
    string beamer_address The email address to use for this list's <a href="http://kb.mailchimp.com/article/how-do-i-import-a-campaign-via-email-email-beamer/">Email Beamer</a>
    string visibility Whether this list is Public (pub) or Private (prv). Used internally for projects like <a href="http://blog.mailchimp.com/introducing-wavelength/" target="_blank">Wavelength</a>
    array stats various stats and counts for the list - many of these are cached for at least 5 minutes
    double member_count The number of active members in the given list.
    double unsubscribe_count The number of members who have unsubscribed from the given list.
    double cleaned_count The number of members cleaned from the given list.
    double member_count_since_send The number of active members in the given list since the last campaign was sent
    double unsubscribe_count_since_send The number of members who have unsubscribed from the given list since the last campaign was sent
    double cleaned_count_since_send The number of members cleaned from the given list since the last campaign was sent
    double campaign_count The number of campaigns in any status that use this list
    double grouping_count The number of Interest Groupings for this list
    double group_count The number of Interest Groups (regardless of grouping) for this list
    double merge_var_count The number of merge vars for this list (not including the required EMAIL one)
    double avg_sub_rate the average number of subscribe per month for the list (empty value if we haven't calculated this yet)
    double avg_unsub_rate the average number of unsubscribe per month for the list (empty value if we haven't calculated this yet)
    double target_sub_rate the target subscription rate for the list to keep it growing (empty value if we haven't calculated this yet)
    double open_rate the average open rate per campaign for the list  (empty value if we haven't calculated this yet)
    double click_rate the average click rate per campaign for the list  (empty value if we haven't calculated this yet)
    array modules Any list specific modules installed for this list (example is SocialPro)
     */
    function lists($filters=array (
    ), $start=0, $limit=25, $sort_field='created', $sort_dir='DESC') {
        $method = 'GET';

        $endpoint = '/lists';
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        $response = $this->callServer($json, $method);

        $response = json_decode($response, true);

        print_r($response);

        print_r($url);
    }

    /**
     * Get the list of merge tags for a given list, including their name, tag, and required setting
     *
     * @section List Related
     * @example xml-rpc_listMergeVars.php
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @return array list of merge tags for the list
    string name Name of the merge field
    bool req Denotes whether the field is required (true) or not (false)
    string field_type The "data type" of this merge var. One of: email, text, number, radio, dropdown, date, address, phone, url, imageurl
    bool public Whether or not this field is visible to list subscribers
    bool show Whether the list owner has this field displayed on their list dashboard
    string order The order the list owner has set this field to display in
    string default The default value the list owner has set for this field
    string size The width of the field to be used
    string tag The merge tag that's used for forms and listSubscribe() and listUpdateMember()
    array choices For radio and dropdown field types, an array of the options available
    int id an unchanging id for the merge var
     */
    function listMergeVars($id) {
        $method = 'GET';

        $endpoint = '/lists/' . $id . '/merge-fields';
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        $response = $this->callServer($json, $method, true);

        $response = json_decode($response, true);

        print_r($response);

        print_r($url);
    }

    /**
     * Add a new merge tag to a given list
     *
     * @section List Related
     * @example xml-rpc_listMergeVarAdd.php
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @param string $tag The merge tag to add, e.g. FNAME. 10 bytes max, valid characters: "A-Z 0-9 _" no spaces, dashes, etc.
     * @param string $name The long description of the tag being added, used for user displays
     * @param array $options optional Various options for this merge var. <em>note:</em> for historical purposes this can also take a "boolean"
    string field_type optional one of: text, number, radio, dropdown, date, address, phone, url, imageurl, zip, birthday - defaults to text
    boolean req optional indicates whether the field is required - defaults to false
    boolean public optional indicates whether the field is displayed in public - defaults to true
    boolean show optional indicates whether the field is displayed in the app's list member view - defaults to true
    int order The order this merge tag should be displayed in - this will cause existing values to be reset so this fits
    string default_value optional the default value for the field. See listSubscribe() for formatting info. Defaults to blank
    array choices optional kind of - an array of strings to use as the choices for radio and dropdown type fields
    string dateformat optional only valid for birthday and date fields. For birthday type, must be "MM/DD" (default) or "DD/MM". For date type, must be "MM/DD/YYYY" (default) or "DD/MM/YYYY". Any other values will be converted to the default.
    string phoneformat optional "US" is the default - any other value will cause them to be unformatted (international)
    string defaultcountry optional the <a href="http://www.iso.org/iso/english_country_names_and_code_elements" target="_blank">ISO 3166 2 digit character code</a> for the default country. Defaults to "US". Anything unrecognized will be converted to the default.

     * @return bool true if the request succeeds, otherwise an error will be thrown
     */
    function listMergeVarAdd($id, $tag, $name, $options=array (
    )) {

        //find out how the options array works with this one, and use it to obtain the 'type' parameter value
        $method = 'POST';

        $endpoint = '/lists/' . $id . '/merge-fields';
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array(
            'tag'  => $tag,
            'name' => $name,
            'type' => strtolower($tag)
        ));

        $response = $this->callServer($json, $method, true);

        $response = json_decode($response);

        print_r($response);

        if ($response->errors || substr($response->status,0,1) === '4') {
            $this->errorMessage = ($response->errors) ? $response->errors[0]->message : $response->detail;
            $this->errorCode = $response->status; 

            echo "************************************************************************************\n\n";
            echo $this->errorCode . ': (' . $this->errorMessage .")\n\n";
            echo "************************************************************************************\n\n";

            return false;
        }

        if ($response->errors || substr($response->status,0,1) === '5') {
            $this->errorMessage = ($response->errors) ? $response->errors[0]->message : $response->detail;
            $this->errorCode = $response->status;

            echo "************************************************************************************\n\n";
            echo "There is an error on mailchimp's side. Contact apihelp@mailchimp.com \n\n";
            echo "************************************************************************************\n\n";
            
            return false;
        }

        if (!$response->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";
            return true;
        }
    }

    /**
     * Update most parameters for a merge tag on a given list. You cannot currently change the merge type
     *
     * @section List Related
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @param string $tag The merge tag to update
     * @param array $options The options to change for a merge var. See listMergeVarAdd() for valid options. "tag" and "name" may also be used here.
     * @return bool true if the request succeeds, otherwise an error will be thrown
     */
    function listMergeVarUpdate($id, $tag, $options) {
        //find out how to use listMergeVarAdd to grab the merge-id necessary, this endpoint uses
        //two url parameters POST /lists/{list_id}/merge-fields/{merge_id}
        //maybe call listMergeVars and loop through the response object for a match with the 
        //$tag parameter and upon match, use the object to grab the merge field id?
    }

    /**
     * Delete a merge tag from a given list and all its members. Seriously - the data is removed from all members as well!
     * Note that on large lists this method may seem a bit slower than calls you typically make.
     *
     * @section List Related
     * @example xml-rpc_listMergeVarDel.php
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @param string $tag The merge tag to delete
     * @return bool true if the request succeeds, otherwise an error will be thrown
     */
    function listMergeVarDel($id, $tag) {
        //find out how to use listMergeVarAdd to grab the merge-id necessary, this endpoint uses
        //two url parameters DELETE /lists/{list_id}/merge-fields/{merge_id}
        //maybe call listMergeVars and loop through the response object for a match with the 
        //$tag parameter and upon match, use the object to grab the merge field id?
    }

    /**
     * Completely resets all data stored in a merge var on a list. All data is removed and this action can not be undone.
     *
     * @section List Related
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @param string $tag The merge tag to reset
     * @return bool true if the request succeeds, otherwise an error will be thrown
     */
    function listMergeVarReset($id, $tag) {
        //this does not translate to any of the current endpoints
    }

    /**
     * Get the list of interest groupings for a given list, including the label, form information, and included groups for each
     *
     * @section List Related
     * @example xml-rpc_listInterestGroupings.php
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @return struct list of interest groups for the list
    int id The id for the Grouping
    string name Name for the Interest groups
    string form_field Gives the type of interest group: checkbox,radio,select
    array groups Array of the grouping options including:
    string bit the bit value - not really anything to be done with this
    string name the name of the group
    string display_order the display order of the group, if set
    int subscribers total number of subscribers who have this group
     */
    function listInterestGroupings($id) {
        //looks like this is now the interest categories? or its an interest inside of an interest category. What a cocophony of spiraling fecal wonder.
        $method = 'GET';

        $endpoint = '/lists/' . $id . '/interest-categories';
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        $response = $this->callServer($json, $method, true);

        $response = json_decode($response, true);

        print_r($response);

        print_r($url);
    }

    /** Add a single Interest Group - if interest groups for the List are not yet enabled, adding the first
     *  group will automatically turn them on.
     *
     * @section List Related
     * @example xml-rpc_listInterestGroupAdd.php
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @param string $group_name the interest group to add - group names must be unique within a grouping
     * @param int $grouping_id optional The grouping to add the new group to - get using listInterestGrouping() . If not supplied, the first grouping on the list is used.
     * @return bool true if the request succeeds, otherwise an error will be thrown
     */
    function listInterestGroupAdd($id, $group_name, $grouping_id=NULL) {
        $method = 'POST';

        $endpoint = '/lists/' . $id . '/interest-categories';
        $url = $this->apiHost . $endpoint;  
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array(
            'title' => $group_name,
            'type'  => 'hidden'
        ));

        $response = $this->callServer($json, $method, true);

        $response = json_decode($response, true);

        print_r($response);

        print_r($url);
    }

    /** Delete a single Interest Group - if the last group for a list is deleted, this will also turn groups for the list off.
     *
     * @section List Related
     * @example xml-rpc_listInterestGroupDel.php
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @param string $group_name the interest group to delete
     * @param int $grouping_id The grouping to delete the group from - get using listInterestGrouping() . If not supplied, the first grouping on the list is used.
     * @return bool true if the request succeeds, otherwise an error will be thrown
     */
    function listInterestGroupDel($id, $group_name, $grouping_id=NULL) {
        $method = 'DELETE';

        $icd = NULL; //this will use the listInterestsGroup to grab the proper interest category id for use as a url parameter

        $endpoint = '/lists/' . $id . '/interest-categories/' . $icd;
        $url = $this->apiHost . $endpoint;  
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array(
            'title' => $group_name,
            'type'  => 'hidden'
        ));

        $response = $this->callServer($json, $method, true);

        $response = json_decode($response, true);

        print_r($response);

        print_r($url);
    }

    /** Change the name of an Interest Group
     *
     * @section List Related
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @param string $old_name the interest group name to be changed
     * @param string $new_name the new interest group name to be set
     * @param int $grouping_id optional The grouping to delete the group from - get using listInterestGrouping() . If not supplied, the first grouping on the list is used.
     * @return bool true if the request succeeds, otherwise an error will be thrown
     */
    function listInterestGroupUpdate($id, $old_name, $new_name, $grouping_id=NULL) {
        $method = 'PATCH';

        $icd = NULL; //this will use the listInterestsGroup to grab the proper interest category id for use as a url parameter

        $endpoint = '/lists/' . $id . '/interest-categories/' . $icd;
        $url = $this->apiHost . $endpoint;  
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array(
            'title' => $group_name,
            'type'  => 'hidden'
        ));

        $response = $this->callServer($json, $method, true);

        $response = json_decode($response, true);

        print_r($response);

        print_r($url);
    }

    /** Add a new Interest Grouping - if interest groups for the List are not yet enabled, adding the first
     *  grouping will automatically turn them on.
     *
     * @section List Related
     * @example xml-rpc_listInterestGroupingAdd.php
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @param string $name the interest grouping to add - grouping names must be unique
     * @param string $type The type of the grouping to add - one of "checkboxes", "hidden", "dropdown", "radio"
     * @param array $groups The lists of initial group names to be added - at least 1 is required and the names must be unique within a grouping. If the number takes you over the 60 group limit, an error will be thrown.
     * @return int the new grouping id if the request succeeds, otherwise an error will be thrown
     */
    function listInterestGroupingAdd($id, $name, $type, $groups) {
        //find out if 'groupings' is the same as /lists/{list_id}/interest-categories/{interest_category_id}/interests
    }

    /** Update an existing Interest Grouping
     *
     * @section List Related
     * @example xml-rpc_listInterestGroupingUpdate.php
     *
     * @param int $grouping_id the interest grouping id - get from listInterestGroupings()
     * @param string $name The name of the field to update - either "name" or "type". Groups with in the grouping should be manipulated using the standard listInterestGroup* methods
     * @param string $value The new value of the field. Grouping names must be unique - only "hidden" and "checkboxes" grouping types can be converted between each other.
     * @return bool true if the request succeeds, otherwise an error will be thrown
     */
    function listInterestGroupingUpdate($grouping_id, $name, $value) {
        //find out if 'groupings' is the same as /lists/{list_id}/interest-categories/{interest_category_id}/interests
    }

    /** Delete an existing Interest Grouping - this will permanently delete all contained interest groups and will remove those selections from all list members
     *
     * @section List Related
     * @example xml-rpc_listInterestGroupingDel.php
     *
     * @param int $grouping_id the interest grouping id - get from listInterestGroupings()
     * @return bool true if the request succeeds, otherwise an error will be thrown
     */
    function listInterestGroupingDel($grouping_id) {
        //find out if 'groupings' is the same as /lists/{list_id}/interest-categories/{interest_category_id}/interests
    }

    /** Return the Webhooks configured for the given list
     *
     * @section List Related
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @return array list of webhooks
    string url the URL for this Webhook
    array actions the possible actions and whether they are enabled
    bool subscribe triggered when subscribes happen
    bool unsubscribe triggered when unsubscribes happen
    bool profile triggered when profile updates happen
    bool cleaned triggered when a subscriber is cleaned (bounced) from a list
    bool upemail triggered when a subscriber's email address is changed
    bool campaign triggered when a campaign is sent or canceled
    array sources the possible sources and whether they are enabled
    bool user whether user/subscriber triggered actions are returned
    bool admin whether admin (manual, in-app) triggered actions are returned
    bool api  whether api triggered actions are returned
     */
    function listWebhooks($id) {
        $method = 'GET';

        $endpoint = '/lists/' . $id . '/webhooks';
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        $response = $this->callServer($json, $method, true);

        $response = json_decode($response, true);

        print_r($response);

        print_r($url);
    }

    /** Add a new Webhook URL for the given list
     *
     * @section List Related
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @param string $url a valid URL for the Webhook - it will be validated. note that a url may only exist on a list once.
     * @param array $actions optional a hash of actions to fire this Webhook for
    bool subscribe optional as subscribes occur, defaults to true
    bool unsubscribe optional as subscribes occur, defaults to true
    bool profile optional as profile updates occur, defaults to true
    bool cleaned optional as emails are cleaned from the list, defaults to true
    bool upemail optional when  subscribers change their email address, defaults to true
    bool campaign option when a campaign is sent or canceled, defaults to true
     * @param array $sources optional a hash of sources to fire this Webhook for
    bool user optional user/subscriber initiated actions, defaults to true
    bool admin optional admin actions in our web app, defaults to true
    bool api optional actions that happen via API calls, defaults to false
     * @return bool true if the call succeeds, otherwise an exception will be thrown
     */
    function listWebhookAdd($id, $url, $actions=array (), $sources=array ()) {
        $method = 'POST';

        $endpoint = '/lists/' . $id . '/webhooks';
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array(
            'url' => $url
        ));

        $response = $this->callServer($json, $method, true);

        $response = json_decode($response, true);

        print_r($response);

        print_r($url);
    }

    /** Delete an existing Webhook URL from a given list
     *
     * @section List Related
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @param string $url the URL of a Webhook on this list
     * @return boolean true if the call succeeds, otherwise an exception will be thrown
     */
    function listWebhookDel($id, $url) {
        $method = 'DELETE';

        $wid = NULL; //this will be the id found by using the $url parameter to loop through the list webhooks object and grab the webhook id for use in the endpoint
        $endpoint = '/lists/' . $id . '/webhooks' . $wid;
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array(
            'url' => 'https://athlon-social.herokuapp.com/'
        ));

        $response = $this->callServer($json, $method, true);

        $response = json_decode($response, true);

        print_r($response);

        print_r($url);
    }

    /** Retrieve all of the Static Segments for a list.
     *
     * @section List Related
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @return array an array of parameters for each static segment
    int id the id of the segment
    string name the name for the segment
    int member_count the total number of subscribed members currently in a segment
    string created_date the date/time the segment was created
    string last_update the date/time the segment was last updated (add or del)
    string last_reset the date/time the segment was last reset (ie had all members cleared from it)
     */
    function listStaticSegments($id) {
        $method = 'GET';

        $endpoint = '/lists/' . $id . '/segments';
        $url = $this->apiHost . $endpoint;
        $url = $url . '?count=20';
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        $response = $this->callServer($json, $method, true);

        $response = json_decode($response, true);

        print_r($response);

        print_r($url);
    }

    /** Save a segment against a list for later use. There is no limit to the number of segments which can be saved. Static Segments <strong>are not</strong> tied
     *  to any merge data, interest groups, etc. They essentially allow you to configure an unlimited number of custom segments which will have standard performance.
     *  When using proper segments, Static Segments are one of the available options for segmentation just as if you used a merge var (and they can be used with other segmentation
     *  options), though performance may degrade at that point.
     *
     * @section List Related
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @param string $name a unique name per list for the segment - 50 byte maximum length, anything longer will throw an error
     * @return int the id of the new segment, otherwise an error will be thrown.
     */
    function listStaticSegmentAdd($id, $name) {
        $method = 'POST';

        $endpoint = '/lists/' . $id . '/segments';
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array(
            'name'           => $name,
            'static_segment' => array()
        ));

        $response = $this->callServer($json, $method, true);

        $response = json_decode($response, true);

        print_r($response);

        print_r($url);
    }

    /** Resets a static segment - removes <strong>all</strong> members from the static segment. Note: does not actually affect list member data
     *
     * @section List Related
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @param int $seg_id the id of the static segment to reset  - get from listStaticSegments()
     * @return bool true if it worked, otherwise an error is thrown.
     */
    function listStaticSegmentReset($id, $seg_id) {
        //this does not transfer, there is no way to erase all emails
    }

    /** Delete a static segment. Note that this will, of course, remove any member affiliations with the segment
     *
     * @section List Related
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @param int $seg_id the id of the static segment to delete - get from listStaticSegments()
     * @return bool true if it worked, otherwise an error is thrown.
     */
    function listStaticSegmentDel($id, $seg_id) {
        $method = 'DELETE';

        $endpoint = '/lists/' . $id . '/segments/' . $seg_id;
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        $response = $this->callServer($json, $method, true);

        $response = json_decode($response, true);

        print_r($response);

        print_r($url);
    }

    /** Add list members to a static segment. It is suggested that you limit batch size to no more than 10,000 addresses per call. Email addresses must exist on the list
     *  in order to be included - this <strong>will not</strong> subscribe them to the list!
     *
     * @section List Related
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @param int $seg_id the id of the static segment to modify - get from listStaticSegments()
     * @param array $batch an array of email addresses and/or unique_ids to add to the segment
     * @return array an array with the results of the operation
    int success the total number of successful updates (will include members already in the segment)
    array errors error data including:
    string email address the email address in question
    string code the error code
    string msg  the full error message
     */
    function listStaticSegmentMembersAdd($id, $seg_id, $batch) {
        $method = 'POST';

        $endpoint = '/lists/' . $id . '/segments/' . $seg_id;
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array(
            'members_to_add' => $batch
        ));

        $response = $this->callServer($json, $method, true);

        $response = json_decode($response, true);

        print_r($response);

        print_r($url);
    }

    /** Remove list members from a static segment. It is suggested that you limit batch size to no more than 10,000 addresses per call. Email addresses must exist on the list
     *  in order to be removed - this <strong>will not</strong> unsubscribe them from the list!
     *
     * @section List Related
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @param int $seg_id the id of the static segment to delete - get from listStaticSegments()
     * @param array $batch an array of email addresses and/or unique_ids to remove from the segment
     * @return array an array with the results of the operation
    int success the total number of succesful removals
    array errors error data including:
    string email address the email address in question
    string code the error code
    string msg  the full error message
     */
    function listStaticSegmentMembersDel($id, $seg_id, $batch) {
        $method = 'POST';

        $endpoint = '/lists/' . $id . '/segments/' . $seg_id;
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array(
            'members_to_remove' => $batch
        ));

        $response = $this->callServer($json, $method, true);

        $response = json_decode($response, true);

        print_r($response);

        print_r($url);
    }

    /**
     * Subscribe the provided email to a list. By default this sends a confirmation email - you will not see new members until the link contained in it is clicked!
     *
     * @section List Related
     *
     * @example mcapi_listSubscribe.php
     * @example json_listSubscribe.php
     * @example xml-rpc_listSubscribe.php
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @param string $email_address the email address to subscribe
     * @param array $merge_vars optional merges for the email (FNAME, LNAME, etc.) (see examples below for handling "blank" arrays). Note that a merge field can only hold up to 255 bytes. Also, there are a few "special" keys:
    string EMAIL set this to change the email address. This is only respected on calls using update_existing or when passed to listUpdateMember()
    string NEW-EMAIL set this to change the email address. This is only respected on calls using update_existing or when passed to listUpdateMember(). Required to change via listBatchSubscribe() - EMAIL takes precedence on other calls, though either will work.
    array GROUPINGS Set Interest Groups by Grouping. Each element in this array should be an array containing the "groups" parameter which contains a comma delimited list of Interest Groups to add. Commas in Interest Group names should be escaped with a backslash. ie, "," =&gt; "\," and either an "id" or "name" parameter to specify the Grouping - get from listInterestGroupings()
    string OPTIN_IP Set the Opt-in IP field. <em>Abusing this may cause your account to be suspended.</em> We do validate this and it must not be a private IP address.
    string OPTIN_TIME Set the Opt-in Time field. <em>Abusing this may cause your account to be suspended.</em> We do validate this and it must be a valid date. Use  - 24 hour format in <strong>GMT</strong>, eg "2013-12-30 20:30:00" to be safe. Generally, though, anything strtotime() understands we'll understand - <a href="http://us2.php.net/strtotime" target="_blank">http://us2.php.net/strtotime</a>
    array MC_LOCATION Set the member's geographic location. By default if this merge field exists, we'll update using the optin_ip if it exists. If the array contains LATITUDE and LONGITUDE keys, they will be used. NOTE - this will slow down each subscribe call a bit, especially for lat/lng pairs in sparsely populated areas. Currently our automated background processes can and will overwrite this based on opens and clicks.
    string MC_LANGUAGE Set the member's language preference. Supported codes are fully case-sensitive and can be found <a href="http://kb.mailchimp.com/article/can-i-see-what-languages-my-subscribers-use#code" target="_new">here</a>.
    array MC_NOTES Add, update, or delete notes associated with a member. The array must contain either a "note" key (the note to set) or an "id" key (the note id to modify). If the "id" key exists and is valid, an "update" key may be set to "append" (default), "prepend", "replace", or "delete" to handle how we should update existing notes. If a "note" key is passed and the "id" key is not passed or is not valid, a new note will be added. "delete", obviously, will only work with a valid "id" - passing that along with "note" and an invalid "id" is wrong and will be ignored. If this is not an array, it will silently be ignored.

    <strong>Handling Field Data Types</strong> - most fields you can just pass a string and all is well. For some, though, that is not the case...
    Field values should be formatted as follows:
    string address For the string version of an Address, the fields should be delimited by <strong>2</strong> spaces. Address 2 can be skipped. The Country should be a 2 character ISO-3166-1 code and will default to your default country if not set
    array address For the array version of an Address, the requirements for Address 2 and Country are the same as with the string version. Then simply pass us an array with the keys <strong>addr1</strong>, <strong>addr2</strong>, <strong>city</strong>, <strong>state</strong>, <strong>zip</strong>, <strong>country</strong> and appropriate values for each

    string birthday the month and day of birth, passed as MM/DD
    array birthday the month and day of birth, passed in an array using the keys <strong>month</strong> and <strong>day</strong>

    string date use YYYY-MM-DD to be safe. Generally, though, anything strtotime() understands we'll understand - <a href="http://us2.php.net/strtotime" target="_blank">http://us2.php.net/strtotime</a>
    string dropdown can be a normal string - we <em>will</em> validate that the value is a valid option
    string image must be a valid, existing url. we <em>will</em> check its existence
    string multi_choice can be a normal string - we <em>will</em> validate that the value is a valid option
    double number pass in a valid number - anything else will turn in to zero (0). Note, this will be rounded to 2 decimal places
    string phone If your account has the US Phone numbers option set, this <em>must</em> be in the form of NPA-NXX-LINE (404-555-1212). If not, we assume an International number and will simply set the field with what ever number is passed in.
    string website This is a standard string, but we <em>will</em> verify that it looks like a valid URL
    string zip A U.S. zip code. We'll validate this is a 4 or 5 digit number.

     * @param string $email_type optional email type preference for the email (html or text - defaults to html)
     * @param bool $double_optin optional flag to control whether a double opt-in confirmation message is sent, defaults to true. <em>Abusing this may cause your account to be suspended.</em>
     * @param bool $update_existing optional flag to control whether existing subscribers should be updated instead of throwing an error, defaults to false
     * @param bool $replace_interests optional flag to determine whether we replace the interest groups with the groups provided or we add the provided groups to the member's interest groups (optional, defaults to true)
     * @param bool $send_welcome optional if your double_optin is false and this is true, we will send your lists Welcome Email if this subscribe succeeds - this will *not* fire if we end up updating an existing subscriber. If double_optin is true, this has no effect. defaults to false.
     * @return boolean true on success, false on failure. When using MCAPI.class.php, the value can be tested and error messages pulled from the MCAPI object (see below)
     */
    function listSubscribe($id, $email_address, $merge_vars=NULL, $email_type='html', $double_optin=true, $update_existing=false, $replace_interests=true, $send_welcome=false) {
        $method = "PUT";

        $json = json_encode( array(
            'email_type'    => $email_type,
            'email_address' => $email_address,
            'status'        => 'subscribed', // "subscribed","unsubscribed","cleaned","pending"
            'merge_fields'  => $merge_vars,
            //'status_if_new' => 'subscribed'
        ));

        //'merge_fields'  => array(
        //    'FNAME'     => $data['firstname'],
        //    'LNAME'     => $data['lastname']
        //)
        
        $memberId = md5(strtolower($email_address));

        $endpoint = '/lists/' . $id . '/members/' . $memberId;
        //$endpoint = '/lists/' . $listid . '/members';
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        print_r($url);

        print_r($json);

        $info = $this->callServer($json, $method);

        $info = json_decode($info);

        print_r($info);

        if ($info->errors || $info->status == 404) {
            echo "************************************************************************************\n\n";
            echo  (($info->detail) ? $info->detail : "you have an error. Check the response object.") . "\n\n";
            echo "************************************************************************************\n\n";
            return false;
        }

        if (!$info->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";
            return true;
        }
    }

    /**
     * Unsubscribe the given email address from the list
     *
     * @section List Related
     * @example mcapi_listUnsubscribe.php
     * @example xml-rpc_listUnsubscribe.php
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @param string $email_address the email address to unsubscribe  OR the email "id" returned from listMemberInfo, Webhooks, and Campaigns
     * @param boolean $delete_member flag to completely delete the member from your list instead of just unsubscribing, default to false
     * @param boolean $send_goodbye flag to send the goodbye email to the email address, defaults to true
     * @param boolean $send_notify flag to send the unsubscribe notification email to the address defined in the list email notification settings, defaults to true
     * @return boolean true on success, false on failure. When using MCAPI.class.php, the value can be tested and error messages pulled from the MCAPI object (see below)
     */
    function listUnsubscribe($id, $email_address, $delete_member=false, $send_goodbye=true, $send_notify=true) {
        //make sure merge_vars array fits properly with new api. Specifically the update Email part ex. (merge_vars['NEW-EMAIL']) ? merge_vars['NEW-EMAIL'] : $email_address;
        $method = "PUT";

        $json = json_encode( array(
            'email_type'    => $email_type,
            'email_address' => $email_address,
            'status'        => 'unsubscribed', // "subscribed","unsubscribed","cleaned","pending"
            'merge_fields'  => $merge_vars
        ));
        
        $memberId = md5(strtolower($email_address));

        $endpoint = '/lists/' . $id . '/members/' . $memberId;
        //$endpoint = '/lists/' . $listid . '/members';
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        print_r($url);

        print_r($json);

        $info = $this->callServer($json, $method);

        $info = json_decode($info);

        print_r($info);

        if ($info->errors || $info->status == 404) {
            echo "************************************************************************************\n\n";
            echo  (($info->detail) ? $info->detail : "you have an error. Check the response object.") . "\n\n";
            echo "************************************************************************************\n\n";
            return false;
        }

        if (!$info->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";
            return true;
        }
    }

    /**
     * Edit the email address, merge fields, and interest groups for a list member. If you are doing a batch update on lots of users,
     * consider using listBatchSubscribe() with the update_existing and possible replace_interests parameter.
     *
     * @section List Related
     * @example mcapi_listUpdateMember.php
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @param string $email_address the current email address of the member to update OR the "id" for the member returned from listMemberInfo, Webhooks, and Campaigns
     * @param array $merge_vars array of new field values to update the member with.  See merge_vars in listSubscribe() for details.
     * @param string $email_type change the email type preference for the member ("html" or "text").  Leave blank to keep the existing preference (optional)
     * @param boolean $replace_interests flag to determine whether we replace the interest groups with the updated groups provided, or we add the provided groups to the member's interest groups (optional, defaults to true)
     * @return boolean true on success, false on failure. When using MCAPI.class.php, the value can be tested and error messages pulled from the MCAPI object
     */
    function listUpdateMember($id, $email_address, $merge_vars, $email_type='', $replace_interests=true) {

        $method = "PUT";

        $json = json_encode( array(
            'email_type'    => $email_type,
            'email_address' => $email_address,
            'status'        => 'unsubscribed', // "subscribed","unsubscribed","cleaned","pending"
            'merge_fields'  => $merge_vars
        ));
        
        $memberId = md5(strtolower($email_address));

        $endpoint = '/lists/' . $id . '/members/' . $memberId;
        
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        print_r($url);

        print_r($json);

        $info = $this->callServer($json, $method);

        $info = json_decode($info);

        print_r($info);

        if ($info->errors || $info->status == 404) {
            echo "************************************************************************************\n\n";
            echo  (($info->detail) ? $info->detail : "you have an error. Check the response object.") . "\n\n";
            echo "************************************************************************************\n\n";
            return false;
        }

        if (!$info->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";
            return true;
        }
    }

    /**
     * Subscribe a batch of email addresses to a list at once. If you are using a serialized version of the API, we strongly suggest that you
     * only run this method as a POST request, and <em>not</em> a GET request. Maximum batch sizes vary based on the amount of data in each record,
     * though you should cap them at 5k - 10k records, depending on your experience. These calls are also long, so be sure you increase your timeout values.
     *
     * @section List Related
     *
     * @example mcapi_listBatchSubscribe.php
     * @example xml-rpc_listBatchSubscribe.php
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @param array $batch an array of structs for each address to import with two special keys: "EMAIL" for the email address, and "EMAIL_TYPE" for the email type option (html or text). Aside from those, see listSubscribe() for other merge var options
     * @param boolean $double_optin flag to control whether to send an opt-in confirmation email - defaults to true
     * @param boolean $update_existing flag to control whether to update members that are already subscribed to the list or to return an error, defaults to false (return error)
     * @param boolean $replace_interests flag to determine whether we replace the interest groups with the updated groups provided, or we add the provided groups to the member's interest groups (optional, defaults to true)
     * @return array Array of result counts and any errors that occurred
    int add_count Number of email addresses that were succesfully added
    int update_count Number of email addresses that were succesfully updated
    int error_count Number of email addresses that failed during addition/updating
    array errors error data including:
    string email address the email address in question
    int code the error code
    string message the full error message
     */
    function listBatchSubscribe($id, $batch, $double_optin=true, $update_existing=false, $replace_interests=true) {
        //placing the batch parameter should be enough, as it is supposed to include objects that hold the 'email' and 'email_type' request params
        //must figure out how to differentiate bewteen subscribe and unsubscribe. Must add 'status' parameter somehow
        //find out if the array passed as $batch in mc_syncusers.pl is just emails or emails and email_type
        $method = 'POST';

        $endpoint = '/lists/' . $id;
        
        $url = $this->apiHost . $endpoint;
        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array(
            'members' => $batch
        ));

        print_r($json);

        $info = $this->callServer($json, $method);

        $info = json_decode($info);

        print_r($info);

        if ($info->errors || $info->status == 404) {
            echo "************************************************************************************\n\n";
            echo  (($info->detail) ? $info->detail : "you have an error. Check the response object.") . "\n\n";
            echo "************************************************************************************\n\n";
            return false;
        }

        if (!$info->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";
            return true;
        }
    }

    /**
     * Unsubscribe a batch of email addresses to a list
     *
     * @section List Related
     * @example mcapi_listBatchUnsubscribe.php
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @param array $emails array of email addresses to unsubscribe
     * @param boolean $delete_member flag to completely delete the member from your list instead of just unsubscribing, default to false
     * @param boolean $send_goodbye flag to send the goodbye email to the email addresses, defaults to true
     * @param boolean $send_notify flag to send the unsubscribe notification email to the address defined in the list email notification settings, defaults to false
     * @return array Array of result counts and any errors that occurred
    int success_count Number of email addresses that were succesfully added/updated
    int error_count Number of email addresses that failed during addition/updating
    array errors error data including:
    string email address the email address in question
    int code the error code
    string message  the full error message

     */
    function listBatchUnsubscribe($id, $emails, $delete_member=false, $send_goodbye=true, $send_notify=false) {
        //placing the batch parameter should be enough, as it is supposed to include objects that hold the 'email' and 'email_type' request params
        //must figure out how to differentiate bewteen subscribe and unsubscribe. Must add 'status' parameter somehow
        //find out if the array passed as $batch in mc_syncusers.pl is just emails or emails and email_type
        $method = 'POST';

        $endpoint = '/lists/' . $id;
        
        $url = $this->apiHost . $endpoint;

        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array(
            'members' => $batch
        ));

        print_r($json);

        $info = $this->callServer($json, $method);

        $info = json_decode($info);

        print_r($info);

        if ($info->errors || $info->status == 404) {
            echo "************************************************************************************\n\n";
            echo  (($info->detail) ? $info->detail : "you have an error. Check the response object.") . "\n\n";
            echo "************************************************************************************\n\n";
            return false;
        }

        if (!$info->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";
            return true;
        }
    }

    /**
     * Get all of the list members for a list that are of a particular status. Are you trying to get a dump including lots of merge
     * data or specific members of a list? If so, checkout the <a href="/export">Export API</a>
     *
     * @section List Related
     * @example mcapi_listMembers.php
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @param string $status the status to get members for - one of(subscribed, unsubscribed, <a target="_blank" href="http://eepurl.com/gWOO">cleaned</a>, updated), defaults to subscribed
     * @param string $since optional pull all members whose status (subscribed/unsubscribed/cleaned) has changed or whose profile (updated) has changed since this date/time - 24 hour format in <strong>GMT</strong>, eg "2013-12-30 20:30:00"
     * @param int $start optional for large data sets, the page number to start at - defaults to 1st page of data (page 0)
     * @param int $limit optional for large data sets, the number of results to return - defaults to 100, upper limit set at 15000
     * @param string $sort_dir optional ASC for ascending, DESC for descending. defaults to ASC even if an invalid value is encountered.
     * @return array Array of all the total records match and matching list member data for this page (see Returned Fields for details)
    int total the total matching records
    array data the data for each member, including:
    string email Member email address
    date timestamp timestamp of their associated status date (subscribed, unsubscribed, cleaned, or updated) in GMT
    string reason For unsubscribes only - the reason collected for the unsubscribe. If populated, one of 'NORMAL','NOSIGNUP','INAPPROPRIATE','SPAM','OTHER'
    string reason_text For unsubscribes only - if the reason is OTHER, the text entered.
     */
    function listMembers($id, $status='subscribed', $since=NULL, $start=0, $limit=100, $sort_dir='ASC') {
        //make sure the returned array is fashioned properly, as stated in the return part of the documentation above

        $method = 'GET';

        $endpoint = '/lists/' . $id . '/members';
        
        $url = $this->apiHost . $endpoint;

        $queryString = '?status=' . $status;

        $url = $url . $queryString;

        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        print_r($url);

        $info = $this->callServer($json, $method);

        $info = json_decode($info);

        print_r($info);

        if ($info->errors || $info->status == 404) {
            echo "************************************************************************************\n\n";
            echo  (($info->detail) ? $info->detail : "you have an error. Check the response object.") . "\n\n";
            echo "************************************************************************************\n\n";
            return false;
        }

        if (!$info->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";
            return true;
        }
    }

    /**
     * Get all the information for particular members of a list
     *
     * @section List Related
     * @example mcapi_listMemberInfo.php
     * @example xml-rpc_listMemberInfo.php
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @param array $email_address an array of up to 50 email addresses to get information for OR the "id"(s) for the member returned from listMembers, Webhooks, and Campaigns. For backwards compatibility, if a string is passed, it will be treated as an array with a single element (will not work with XML-RPC).
     * @return array array of list members with their info in an array (see Returned Fields for details)
    int success the number of subscribers successfully found on the list
    int errors the number of subscribers who were not found on the list
    array data an array of arrays where each one has member info:
    string id The unique id for this email address on an account
    string email The email address associated with this record
    string email_type The type of emails this customer asked to get: html or tex
    array merges An associative array of all the merge tags and the data for those tags for this email address. <em>Note</em>: Interest Groups are returned as comma delimited strings - if a group name contains a comma, it will be escaped with a backslash. ie, "," =&gt; "\,". Groupings will be returned with their "id" and "name" as well as a "groups" field formatted just like Interest Groups
    string status The subscription status for this email address, either pending, subscribed, unsubscribed, or cleaned
    string ip_signup IP Address this address signed up from. This may be blank if single optin is used.
    string timestamp_signup The date/time the double optin was initiated. This may be blank if single optin is used.
    string ip_opt IP Address this address opted in from.
    string timestamp_opt The date/time the optin completed
    int member_rating the rating of the subscriber. This will be 1 - 5 as described <a href="http://eepurl.com/f-2P" target="_blank">here</a>
    string campaign_id If the user is unsubscribed and they unsubscribed from a specific campaign, that campaign_id will be listed, otherwise this is not returned.
    array lists An associative array of the other lists this member belongs to - the key is the list id and the value is their status in that list.
    string timestamp The date/time this email address entered it's current status
    string info_changed The last time this record was changed. If the record is old enough, this may be blank.
    int web_id The Member id used in our web app, allows you to create a link directly to it
    string list_id The list id the for the member record being returned
    string language if set/detected, a language code from <a href="http://kb.mailchimp.com/article/can-i-see-what-languages-my-subscribers-use#code" target="_blank">here</a>
    bool is_gmonkey Whether the member is a <a href="http://mailchimp.com/features/golden-monkeys/" target="_blank">Golden Monkey</a> or not.
    array geo the geographic information if we have it. including:
    string latitude the latitude
    string longitude the longitude
    string gmtoff GMT offset
    string dstoff GMT offset during daylight savings (if DST not observered, will be same as gmtoff
    string timezone the timezone we've place them in
    string cc 2 digit ISO-3166 country code
    string region generally state, province, or similar
    array clients the client we've tracked the address as using with two keys:
    string name the common name of the client
    string icon_url a url representing a path to an icon representing this client
    array static_segments static segments the member is a part of including:
    int id the segment id
    string name the name given to the segment
    string added the date the member was added
    array notes notes entered for this member. For each note:
    int id the note id
    string note the text entered
    string created the date the note was created
    string updated the date the note was last updated
    string created_by_name the name of the user who created the note. This can change as users update their profile.
     */
    function listMemberInfo($id, $email_address) {
        //maybe a batch request to /lists/{id}/members/{sub_hash} ?
    }

    /**
     * Get the most recent 100 activities for particular list members (open, click, bounce, unsub, abuse, sent to)
     *
     * @section List Related
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @param array $email_address an array of up to 50 email addresses to get information for OR the "id"(s) for the member returned from listMembers, Webhooks, and Campaigns.
     * @return array array of data and success/error counts
    int success the number of subscribers successfully found on the list
    int errors the number of subscribers who were not found on the list
    array data an array of arrays where each activity record has:
    string action The action name, one of: open, click, bounce, unsub, abuse, sent, queued, ecomm, mandrill_send, mandrill_hard_bounce, mandrill_soft_bounce, mandrill_open, mandrill_click, mandrill_spam, mandrill_unsub, mandrill_reject
    string timestamp The date/time of the action
    string url For click actions, the url clicked, otherwise this is empty
    string type If there's extra bounce, unsub, etc data it will show up here.
    string bounce_type For backwards compat, this will exist and be the same data as "type"
    string campaign_id The campaign id the action was related to, if it exists - otherwise empty (ie, direct unsub from list)
     */
    function listMemberActivity($id, $email_address) {
        //batch call to /lists/{list_id}/members/{subscriber_hash}/activity ?

    }

    /**
     * Get all email addresses that complained about a given campaign
     *
     * @section List Related
     *
     * @example mcapi_listAbuseReports.php
     *
     * @param string $id the list id to pull abuse reports for (can be gathered using lists())
     * @param int $start optional for large data sets, the page number to start at - defaults to 1st page of data  (page 0)
     * @param int $limit optional for large data sets, the number of results to return - defaults to 500, upper limit set at 1000
     * @param string $since optional pull only messages since this time - 24 hour format in <strong>GMT</strong>, eg "2013-12-30 20:30:00"
     * @return array the total of all reports and the specific reports reports this page
    int total the total number of matching abuse reports
    array data the actual data for each reports, including:
    string date date/time the abuse report was received and processed
    string email the email address that reported abuse
    string campaign_id the unique id for the campaign that report was made against
    string type an internal type generally specifying the orginating mail provider - may not be useful outside of filling report views
     */
    function listAbuseReports($id, $start=0, $limit=500, $since=NULL) {
        $method = "GET";

        $endpoint = '/lists/' . $id . '/abuse-reports';
        
        $url = $this->apiHost . $endpoint;

        $url = $url . $queryString;

        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        print_r($url);

        $info = $this->callServer($json, $method);

        $info = json_decode($info);

        print_r($info);

        if ($info->errors || $info->status == 404) {
            echo "************************************************************************************\n\n";
            echo  (($info->detail) ? $info->detail : "you have an error. Check the response object.") . "\n\n";
            echo "************************************************************************************\n\n";
            return false;
        }

        if (!$info->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";
            return true;
        }
    }

    /**
     * Access the Growth History by Month for a given list.
     *
     * @section List Related
     *
     * @example mcapi_listGrowthHistory.php
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @return array array of months and growth
    string month The Year and Month in question using YYYY-MM format
    int existing number of existing subscribers to start the month
    int imports number of subscribers imported during the month
    int optins number of subscribers who opted-in during the month
     */
    function listGrowthHistory($id) {
        $method = "GET";

        $endpoint = '/lists/' . $id . '/growth-history';
        
        $url = $this->apiHost . $endpoint;

        $url = $url . $queryString;

        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        print_r($url);

        $info = $this->callServer($json, $method);

        $info = json_decode($info);

        print_r($info);

        if ($info->errors || $info->status == 404) {
            echo "************************************************************************************\n\n";
            echo  (($info->detail) ? $info->detail : "you have an error. Check the response object.") . "\n\n";
            echo "************************************************************************************\n\n";
            return false;
        }

        if (!$info->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";
            return true;
        }
    }

    /**
     * Access up to the previous 180 days of daily detailed aggregated activity stats for a given list
     *
     * @section List Related
     *
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @return array array of array of daily values, each containing:
    string day The day in YYYY-MM-DD
    int emails_sent number of emails sent to the list
    int unique_opens number of unique opens for the list
    int recipient_clicks number of clicks for the list
    int hard_bounce number of hard bounces for the list
    int soft_bounce number of soft bounces for the list
    int abuse_reports number of abuse reports for the list
    int subs number of double optin subscribes for the list
    int unsubs number of manual unsubscribes for the list
    int other_adds number of non-double optin subscribes for the list (manual, API, or import)
    int other_removes number of non-manual unsubscribes for the list (deletions, empties, soft-bounce removals)
     */
    function listActivity($id) {
        $method = "GET";

        $endpoint = '/lists/' . $id . '/activity';
        
        $url = $this->apiHost . $endpoint;

        $url = $url . $queryString;

        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        print_r($url);

        $info = $this->callServer($json, $method);

        $info = json_decode($info);

        print_r($info);

        if ($info->errors || $info->status == 404) {
            echo "************************************************************************************\n\n";
            echo  (($info->detail) ? $info->detail : "you have an error. Check the response object.") . "\n\n";
            echo "************************************************************************************\n\n";
        }

        if (!$info->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";
        }

        return $info;
    }

    /**
     * Retrieve the locations (countries) that the list's subscribers have been tagged to based on geocoding their IP address
     *
     * @section List Related
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @return array array of locations
    string country the country name
    string cc the 2 digit country code
    double percent the percent of subscribers in the country
    double total the total number of subscribers in the country
     */
    function listLocations($id) {
        //i dont believe this function translates in any way....cant be found under any endpoints
    }

    /**
     * Retrieve the clients that the list's subscribers have been tagged as being used based on user agents seen. Made possible by <a href="http://user-agent-string.info" target="_blank">user-agent-string.info</a>
     *
     * @section List Related
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @return array the desktop and mobile user agents in use on the list
    array desktop desktop user agents and percentages
    double penetration the percent of desktop clients in use
    array clients a record for each containing:
    string client the common name for the client
    string icon a url to an image representing this client
    string percent percent of list using the client
    string members total members using the client
    array mobile mobile user agents and percentages
    double penetration the percent of mobile clients in use
    array clients a record for each containing:
    string client the common name for the client
    string icon a url to an image representing this client
    string percent percent of list using the client
    string members total members using the client
     */
    function listClients($id) {
        //send campaign and open so that we can see how this returns data, or use the id of another list to find out
        $method = "GET";

        $endpoint = '/lists/' . $id . '/clients';
        
        $url = $this->apiHost . $endpoint;

        $url = $url . $queryString;

        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        print_r($url);

        $info = $this->callServer($json, $method);

        $info = json_decode($info);

        print_r($info);

        if ($info->errors || $info->status == 404) {
            echo "************************************************************************************\n\n";
            echo  (($info->detail) ? $info->detail : "you have an error. Check the response object.") . "\n\n";
            echo "************************************************************************************\n\n";
        }

        if (!$info->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";
        }

        return $info;
    }

    /**
     * Retrieve various templates available in the system, allowing some thing similar to our template gallery to be created.
     *
     * @section Template  Related
     * @example mcapi_templates.php
     * @example xml-rpc_templates.php
     *
     * @param array $types optional the types of templates to return
     * boolean user    Custom templates for this user account. Defaults to true.
     * boolean gallery Templates from our Gallery. Note that some templates that require extra configuration are withheld. (eg, the Etsy template). Defaults to false.
     * boolean base    Our "start from scratch" extremely basic templates. Defaults to false.
     * @param string $category optional for Gallery templates only, limit to a specific template category
     * @param array $inactives optional options to control how inactive templates are returned, if at all
     * boolean include user templates are not deleted, only set inactive. defaults to false.
     * boolean only    only include inactive templates. defaults to false.
     * @return array An array of arrays, one for each template
    int id Id of the template
    string name Name of the template
    string layout Layout of the template - "basic", "left_column", "right_column", or "postcard"
    string preview_image If we've generated it, the url of the preview image for the template. We do out best to keep these up to date, but Preview image urls are not guaranteed to be available
    string date_created The date/time the template was created
    boolean edit_source Whether or not you are able to edit the source of a template.
     */
    function templates($types=array (
    ), $category=NULL, $inactives=array (
    )) {
        //make sure info is returned the way it is written above

        $method = "GET";

        $endpoint = '/templates';
        
        $url = $this->apiHost . $endpoint;

        $url = $url . $queryString;

        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        print_r($url);

        $info = $this->callServer($json, $method);

        $info = json_decode($info);

        print_r($info);

        if ($info->errors || $info->status == 404) {
            echo "************************************************************************************\n\n";
            echo  (($info->detail) ? $info->detail : "you have an error. Check the response object.") . "\n\n";
            echo "************************************************************************************\n\n";
        }

        if (!$info->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";
        }

        return $info;
    }

    /**
     * Pull details for a specific template to help support editing
     *
     * @section Template  Related
     *
     * @param int $tid the template id - get from templates()
     * @param string $type optional the template type to load - one of 'user', 'gallery', 'base', defaults to user.
     * @return array an array of info to be used when editing
    array default_content the default content broken down into the named editable sections for the template - dependant upon template, so not documented
    array sections the valid editable section names - dependant upon template, so not documented
    string source the full source of the template as if you exported it via our template editor
    string preview similar to the source, but the rendered version of the source from our popup preview
     */
    function templateInfo($tid, $type='user') {
        //make sure info is returned the way it is written above/ might have to write another function to get the default content endpoint /templates/{template_id}/default-content to get default content and sections

        $method = "GET";

        $endpoint = '/templates/' . $tid;
        
        $url = $this->apiHost . $endpoint;

        $url = $url . $queryString;

        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        print_r($url);

        $info = $this->callServer($json, $method);

        $info = json_decode($info);

        print_r($info);

        if ($info->errors || $info->status == 404) {
            echo "************************************************************************************\n\n";
            echo  (($info->detail) ? $info->detail : "you have an error. Check the response object.") . "\n\n";
            echo "************************************************************************************\n\n";
        }

        if (!$info->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";
        }

        return $info;
    }

    /**
     * Create a new user template, <strong>NOT</strong> campaign content. These templates can then be applied while creating campaigns.
     *
     * @section Template  Related
     * @example mcapi_create_template.php
     * @example xml-rpc_create_template.php
     *
     * @param string $name the name for the template - names must be unique and a max of 50 bytes
     * @param string $html a string specifying the entire template to be created. This is <strong>NOT</strong> campaign content. They are intended to utilize our <a href="http://www.mailchimp.com/resources/email-template-language/" target="_blank">template language</a>.
     * @return int the new template id, otherwise an error is thrown.
     */
    function templateAdd($name, $html) {
        $method = "POST";

        $endpoint = '/templates';
        
        $url = $this->apiHost . $endpoint;

        $url = $url . $queryString;

        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array(
            'name' => $name,
            'html' => $html
        ));

        print_r($url);

        $info = $this->callServer($json, $method);

        $info = json_decode($info);

        print_r($info);

        print_r($info->id);

        if ($info->errors || $info->status == 404) {
            echo "************************************************************************************\n\n";
            echo  (($info->detail) ? $info->detail : "you have an error. Check the response object.") . "\n\n";
            echo "************************************************************************************\n\n";
        }

        if (!$info->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";
        }

        return $info->id;
    }

    /**
     * Replace the content of a user template, <strong>NOT</strong> campaign content.
     *
     * @section Template  Related
     *
     * @param int $id the id of the user template to update
     * @param array  $values the values to updates - while both are optional, at least one should be provided. Both can be updated at the same time.
    string name optional the name for the template - names must be unique and a max of 50 bytes
    string html optional a string specifying the entire template to be created. This is <strong>NOT</strong> campaign content. They are intended to utilize our <a href="http://www.mailchimp.com/resources/email-template-language/" target="_blank">template language</a>.

     * @return boolean true if the template was updated, otherwise an error will be thrown
     */
    function templateUpdate($id, $values) {
        $method = "PATCH";

        $endpoint = '/templates/' . $id;
        
        $url = $this->apiHost . $endpoint;

        $url = $url . $queryString;

        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array(
            'name' => ($values['name']) ? $values['name'] : '',
            'html' => ($values['html']) ? $values['html'] : ''
        ));

        print_r($url);

        $info = $this->callServer($json, $method);

        $info = json_decode($info);

        print_r($info);

        if ($info->errors || $info->status == 404) {
            echo "************************************************************************************\n\n";
            echo  (($info->detail) ? $info->detail : "you have an error. Check the response object.") . "\n\n";
            echo "************************************************************************************\n\n";
            return false;
        }

        if (!$info->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";
            return true;
        }
    }

    /**
     * Delete (deactivate) a user template
     *
     * @section Template  Related
     *
     * @param int $id the id of the user template to delete
     * @return boolean true if the template was deleted, otherwise an error will be thrown
     */
    function templateDel($id) {
        $method = "DELETE";

        $endpoint = '/templates/' . $id;
        
        $url = $this->apiHost . $endpoint;

        $url = $url . $queryString;

        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        print_r($url);

        $info = $this->callServer($json, $method);

        $info = json_decode($info);

        print_r($info);

        if ($info->errors || $info->status == 404) {
            echo "************************************************************************************\n\n";
            echo  (($info->detail) ? $info->detail : "you have an error. Check the response object.") . "\n\n";
            echo "************************************************************************************\n\n";
            return false;
        }

        if (!$info->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";
            return true;
        }
    }

    /**
     * Undelete (reactivate) a user template
     *
     * @section Template  Related
     *
     * @param int $id the id of the user template to reactivate
     * @return boolean true if the template was deleted, otherwise an error will be thrown
     */
    function templateUndel($id) {
        //this does not translate at all...
    }

    /**
     * Retrieve lots of account information including payments made, plan info, some account stats, installed modules,
     * contact info, and more. No private information like Credit Card numbers is available.
     *
     * @section Helper
     *
     * @param array $exclude optional defaults to nothing for backwards compatibility. Allows controlling which extra arrays are returned since they can slow down calls. Valid keys are "modules", "orders", "rewards-credits", "rewards-inspections", "rewards-referrals", and "rewards-applied". Hint: "rewards-referrals" is typically the culprit. To avoid confusion, if data is excluded, the corresponding key <strong>will not be returned at all</strong>.
     * @return array containing the details for the account tied to this API Key
    string username The Account username
    string user_id The Account user unique id (for building some links)
    bool is_trial Whether the Account is in Trial mode (can only send campaigns to less than 100 emails)
    bool is_approved Whether the Account has been approved for purchases
    bool has_activated Whether the Account has been activated
    string timezone The timezone for the Account - default is "US/Eastern"
    string plan_type Plan Type - "monthly", "payasyougo", or "free"
    int plan_low <em>only for Monthly plans</em> - the lower tier for list size
    int plan_high <em>only for Monthly plans</em> - the upper tier for list size
    string plan_start_date <em>only for Monthly plans</em> - the start date for a monthly plan
    int emails_left <em>only for Free and Pay-as-you-go plans</em> emails credits left for the account
    bool pending_monthly Whether the account is finishing Pay As You Go credits before switching to a Monthly plan
    string first_payment date of first payment
    string last_payment date of most recent payment
    int times_logged_in total number of times the account has been logged into via the web
    string last_login date/time of last login via the web
    string affiliate_link Monkey Rewards link for our Affiliate program
    array contact Contact details for the account
    string fname First Name
    string lname Last Name
    string email Email Address
    string company Company Name
    string address1 Address Line 1
    string address2 Address Line 2
    string city City
    string state State or Province
    string zip Zip or Postal Code
    string country Country name
    string url Website URL
    string phone Phone number
    string fax Fax number
    array modules Addons installed in the account
    string id An internal module id
    string name The module name
    string added The date the module was added
    array data Any extra data associated with this module as key=>value pairs
    array orders Order details for the account
    int order_id The order id
    string type The order type - either "monthly" or "credits"
    double amount The order amount
    string date The order date
    double credits_used The total credits used
    array rewards Rewards details for the account including credits & inspections earned, number of referals, referal details, and rewards used
    int referrals_this_month the total number of referrals this month
    string notify_on whether or not we notify the user when rewards are earned
    string notify_email the email address address used for rewards notifications
    array credits Email credits earned:
    int this_month credits earned this month
    int total_earned credits earned all time
    int remaining credits remaining
    array inspections Inbox Inspections earned:
    int this_month credits earned this month
    int total_earned credits earned all time
    int remaining credits remaining
    array referrals All referrals, including:
    string name the name of the account
    string email the email address associated with the account
    string signup_date the signup date for the account
    string type the source for the referral
    array applied Applied rewards, including:
    int value the number of credits user
    string date the date appplied
    int order_id the order number credits were applied to
    string order_desc the order description
     */
    function getAccountDetails($exclude=array (
    )) {
        $method = "GET";

        $endpoint = '/';
        
        $url = $this->apiHost . $endpoint;

        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        print_r($url);

        $info = $this->callServer($json, $method);

        $info = json_decode($info);

        print_r($info);

        if ($info->errors || $info->status == 404) {
            echo "************************************************************************************\n\n";
            echo  (($info->detail) ? $info->detail : "you have an error. Check the response object.") . "\n\n";
            echo "************************************************************************************\n\n";
            return false;
        }

        if (!$info->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";
            return true;
        }
    }

    /**
     * Retrieve all domains verification records for an account
     *
     * @section Helper
     *
     * @return array records of domains verification has been attempted for
    string domain the verified domain
    string status the status of the verification - either "verified" or "pending"
    string email the email address used for verification
     */
    function getVerifiedDomains() {
        //does not transfer 
    }

    /**
     * Have HTML content auto-converted to a text-only format. You can send: plain HTML, an array of Template content, an existing Campaign Id, or an existing Template Id. Note that this will <strong>not</strong> save anything to or update any of your lists, campaigns, or templates.
     *
     * @section Helper
     * @example xml-rpc_generateText.php
     *
     * @param string $type The type of content to parse. Must be one of: "html", "template", "url", "cid" (Campaign Id), or "tid" (Template Id)
     * @param mixed $content The content to use. For "html" expects  a single string value, "template" expects an array like you send to campaignCreate, "url" expects a valid & public URL to pull from, "cid" expects a valid Campaign Id, and "tid" expects a valid Template Id on your account.
     * @return string the content pass in converted to text.
     */
    function generateText($type, $content) {
        //function does not translate 
    }

    /**
     * Send your HTML content to have the CSS inlined and optionally remove the original styles.
     *
     * @section Helper
     * @example xml-rpc_inlineCss.php
     *
     * @param string $html Your HTML content
     * @param bool $strip_css optional Whether you want the CSS &lt;style&gt; tags stripped from the returned document. Defaults to false.
     * @return string Your HTML content with all CSS inlined, just like if we sent it.
     */
    function inlineCss($html, $strip_css=false) {
        //function does not translate
    }

    /**
     * List all the folders for a user account
     *
     * @section Folder  Related
     * @example mcapi_folders.php
     * @example xml-rpc_folders.php
     *
     * @param string $type optional the type of folders to return - either "campaign" or "autoresponder". Defaults to "campaign"
     * @return array Array of folder structs (see Returned Fields for details)
    int folder_id Folder Id for the given folder, this can be used in the campaigns() function to filter on.
    string name Name of the given folder
    string date_created The date/time the folder was created
    string type The type of the folders being returned, just to make sure you know.
     */
    function folders($type='campaign') {
        $method = "GET";

        $endpoint = '/campaign-folders';
        
        $url = $this->apiHost . $endpoint;

        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        print_r($url);

        $info = $this->callServer($json, $method);

        $info = json_decode($info);

        print_r($info);

        if ($info->errors || $info->status == 404) {
            echo "************************************************************************************\n\n";
            echo  (($info->detail) ? $info->detail : "you have an error. Check the response object.") . "\n\n";
            echo "************************************************************************************\n\n";
            return false;
        }

        if (!$info->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";
            return true;
        }
    }

    /**
     * Add a new folder to file campaigns or autoresponders in
     *
     * @section Folder  Related
     * @example mcapi_folderAdd.php
     * @example xml-rpc_folderAdd.php
     *
     * @param string $name a unique name for a folder (max 100 bytes)
     * @param string $type optional the type of folder to create - either "campaign" or "autoresponder". Defaults to "campaign"
     * @return int the folder_id of the newly created folder.
     */
    function folderAdd($name, $type='campaign') {
        $method = "POST";

        $endpoint = '/campaign-folders';
        
        $url = $this->apiHost . $endpoint;

        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array(
            'name' => $name
        ));

        print_r($url);

        $info = $this->callServer($json, $method);

        $info = json_decode($info);

        print_r($info);

        if ($info->errors || $info->status == 404) {
            echo "************************************************************************************\n\n";
            echo  (($info->detail) ? $info->detail : "you have an error. Check the response object.") . "\n\n";
            echo "************************************************************************************\n\n";
            return false;
        }

        if (!$info->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";
            return true;
        }

    }

    /**
     * Update the name of a folder for campaigns or autoresponders
     *
     * @section Folder  Related
     *
     * @param int $fid the folder id to update - retrieve from folders()
     * @param string $name a new, unique name for the folder (max 100 bytes)
     * @param string $type optional the type of folder to create - either "campaign" or "autoresponder". Defaults to "campaign"
     * @return bool true if the update worked, otherwise an exception is thrown
     */
    function folderUpdate($fid, $name, $type='campaign') {
        $method = "PATCH";

        $endpoint = '/campaign-folders/' . $fid;
        
        $url = $this->apiHost . $endpoint;

        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array(
            'name' => $name
        ));

        print_r($url);

        $info = $this->callServer($json, $method);

        $info = json_decode($info);

        print_r($info);

        if ($info->errors || $info->status == 404) {
            echo "************************************************************************************\n\n";
            echo  (($info->detail) ? $info->detail : "you have an error. Check the response object.") . "\n\n";
            echo "************************************************************************************\n\n";
            return false;
        }

        if (!$info->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";
            return true;
        }
    }

    /**
     * Delete a campaign or autoresponder folder. Note that this will simply make campaigns in the folder appear unfiled, they are not removed.
     *
     * @section Folder  Related
     *
     * @param int $fid the folder id to update - retrieve from folders()
     * @param string $type optional the type of folder to create - either "campaign" or "autoresponder". Defaults to "campaign"
     * @return bool true if the delete worked, otherwise an exception is thrown
     */
    function folderDel($fid, $type='campaign') {
        $method = "DELETE";

        $endpoint = '/campaign-folders/' . $fid;
        
        $url = $this->apiHost . $endpoint;

        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        print_r($url);

        $info = $this->callServer($json, $method);

        $info = json_decode($info);

        print_r($info);

        if ($info->errors || $info->status == 404) {
            echo "************************************************************************************\n\n";
            echo  (($info->detail) ? $info->detail : "you have an error. Check the response object.") . "\n\n";
            echo "************************************************************************************\n\n";
            return false;
        }

        if (!$info->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";
            return true;
        }
    }

    /**
     * Retrieve the Ecommerce Orders for an account
     *
     * @section Ecommerce
     *
     * @param int $start optional for large data sets, the page number to start at - defaults to 1st page of data  (page 0)
     * @param int $limit optional for large data sets, the number of results to return - defaults to 100, upper limit set at 500
     * @param string $since optional pull only messages since this time - 24 hour format in <strong>GMT</strong>, eg "2013-12-30 20:30:00"
     * @return array the total matching orders and the specific orders for the requested page
    int total the total matching orders
    array data the actual data for each order being returned
    string store_id the store id generated by the plugin used to uniquely identify a store
    string store_name the store name collected by the plugin - often the domain name
    string order_id the internal order id the store tracked this order by
    string email  the email address that received this campaign and is associated with this order
    double order_total the order total
    double tax_total the total tax for the order (if collected)
    double ship_total the shipping total for the order (if collected)
    string order_date the date the order was tracked - from the store if possible, otherwise the GMT time we received it
    array lines containing the detail of line of the order:
    int line_num the line number
    int product_id the product id
    string product_name the product name
    string product_sku the sku for the product
    int product_category_id the category id for the product
    string product_category_name the category name for the product
    int qty the quantity ordered
    double cost the cost of the item
     */
    function ecommOrders($start=0, $limit=100, $since=NULL) {
        //must have store id etc. investigate later
    }

    /**
     * Delete Ecommerce Order Information used for segmentation. This will generally be used by ecommerce package plugins
     * <a href="/plugins/ecomm360.phtml">that we provide</a> or by 3rd part system developers.
     * @section Ecommerce
     *
     * @param string $store_id the store id the order belongs to
     * @param string $order_id the order id (generated by the store) to delete
     * @return bool true if an order is deleted, otherwise an error is thrown.
     */
    function ecommOrderDel($store_id, $order_id) {
        //same as ecommOrders function 
    }

    /**
     * Retrieve all List Ids a member is subscribed to.
     *
     * @section Helper
     *
     * @param string $email_address the email address to check OR the email "id" returned from listMemberInfo, Webhooks, and Campaigns
     * @return array An array of list_ids the member is subscribed to.
     */
    function listsForEmail($email_address) {
        $method = "GET";

        $endpoint = '/lists';
        
        $url = $this->apiHost . $endpoint;

        $url = $url . '?email=' . $email_address;

        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        print_r($url);

        $info = $this->callServer($json, $method);

        $info = json_decode($info);

        print_r($info);

        if ($info->errors || $info->status == 404) {
            echo "************************************************************************************\n\n";
            echo  (($info->detail) ? $info->detail : "you have an error. Check the response object.") . "\n\n";
            echo "************************************************************************************\n\n";
            return false;
        }

        if (!$info->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";
            return true;
        }
    }

    /**
     * Retrieve all Campaigns Ids a member was sent
     *
     * @section Helper
     *
     * @param string $email_address the email address to unsubscribe  OR the email "id" returned from listMemberInfo, Webhooks, and Campaigns
     * @param array $options optional extra options to modify the returned data.
    string list_id optional A list_id to limit the campaigns to
    bool   verbose optional Whether or not to return verbose data (beta - this will change the return format into something undocumented, but consistent). defaults to false
     * @return array An array of campaign_ids the member received
     */
    function campaignsForEmail($email_address, $options=NULL) {
        //doesn't appear to translate
    }

    /**
     * Return the current Chimp Chatter messages for an account.
     *
     * @section Helper
     *
     * @return array An array of chatter messages and properties
    string message The chatter message
    string type The type of the message - one of lists:new-subscriber, lists:unsubscribes, lists:profile-updates, campaigns:facebook-likes, campaigns:facebook-comments, campaigns:forward-to-friend, lists:imports, or campaigns:inbox-inspections
    string url a url into the web app that the message could link to
    string list_id the list_id a message relates to, if applicable
    string campaign_id the list_id a message relates to, if applicable
    string update_time The date/time the message was last updated
     */
    function chimpChatter() {
        //doesn't appear to exist anymore/does not translate
    }

    /**
     * Search account wide or on a specific list using the specified query terms
     *
     * @section Helper
     *
     * @param string $query terms to search on, <a href="http://kb.mailchimp.com/article/i-cant-find-a-recipient-on-my-list" target="_blank">just like you do in the app</a>
     * @param string $id optional the list id to limit the search to. Get by calling lists()
     * @param int offset optional the paging offset to use if more than 100 records match
     * @return array An array of both exact matches and partial matches over a full search
    array exact_matches
    int total total members matching
    array members each entry will match the data format for a single member as returned by listMemberInfo()
    array full_search
    int total total members matching
    array members each entry will match the data format for a single member as returned by listMemberInfo()
     */
    function searchMembers($query, $id=NULL, $offset=0) {
        $method = "GET";

        $endpoint = '/search-members';
        
        $url = $this->apiHost . $endpoint;

        $url = $url . '?query=' . $query;

        if (!$id == NULL) {
            $url = $url . '&list_id=' . $id;
        }

        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        print_r($url);

        $info = $this->callServer($json, $method);

        $info = json_decode($info);

        print_r($info);

        if ($info->errors || $info->status == 404) {
            echo "************************************************************************************\n\n";
            echo  (($info->detail) ? $info->detail : "you have an error. Check the response object.") . "\n\n";
            echo "************************************************************************************\n\n";
            return false;
        }

        if (!$info->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";
            return true;
        }
    }

    /**
     * Search all campaigns for the specified query terms
     *
     * @section Helper
     *
     * @param string $query terms to search on
     * @param int offset optional the paging offset to use if more than 100 records match
     * @param string snip_start optional by default clear text is returned. To have the match highlighted with something (like a strong HTML tag), <strong>both</strong> this and "snip_end" must be passed. You're on your own to not break the tags - 25 character max.
     * @param string snip_end optional see "snip_start" above.
     * @return array An array containing the total matches and current results
    int total total campaigns matching
    array results matching campaigns and snippets
    string snippet the matching snippet for the campaign
    array campaign the matching campaign's details - will return same data as single campaign from campaigns()
     */
    function searchCampaigns($query, $offset=0, $snip_start=NULL, $snip_end=NULL) {
        $method = "GET";

        $endpoint = '/search-campaigns';
        
        $url = $this->apiHost . $endpoint;

        $url = $url . '?query=' . $query;

        $url = parse_url($url);

        $this->apiUrl = $url;

        $json = json_encode(array());

        print_r($url);

        $info = $this->callServer($json, $method);

        $info = json_decode($info);

        print_r($info);

        if ($info->errors || $info->status == 404) {
            echo "************************************************************************************\n\n";
            echo  (($info->detail) ? $info->detail : "you have an error. Check the response object.") . "\n\n";
            echo "************************************************************************************\n\n";
            return false;
        }

        if (!$info->errors) {
            echo "************************************************************************************\n\n";
            echo "Successfully completed.\n\n";
            echo "************************************************************************************\n\n";
            return true;
        }
    }

    /**
     * Retrieve a list of all MailChimp API Keys for this User
     *
     * @section Security Related
     * @example xml-rpc_apikeyAdd.php
     * @example mcapi_apikeyAdd.php
     *
     * @param string $username Your MailChimp user name
     * @param string $password Your MailChimp password
     * @param boolean $expired optional - whether or not to include expired keys, defaults to false
     * @return array an array of API keys including:
    string apikey The api key that can be used
    string created_at The date the key was created
    string expired_at The date the key was expired
     */
    function apikeys($username, $password, $expired=false) {
        //does not translate
    }

    /**
     * Add an API Key to your account. We will generate a new key for you and return it.
     *
     * @section Security Related
     * @example xml-rpc_apikeyAdd.php
     *
     * @param string $username Your MailChimp user name
     * @param string $password Your MailChimp password
     * @return string a new API Key that can be immediately used.
     */
    function apikeyAdd($username, $password) {
        //doesnt transfer
    }

    /**
     * Expire a Specific API Key. Note that if you expire all of your keys, just visit <a href="http://admin.mailchimp.com/account/api" target="_blank">your API dashboard</a>
     * to create a new one. If you are trying to shut off access to your account for an old developer, change your
     * MailChimp password, then expire all of the keys they had access to. Note that this takes effect immediately, so make
     * sure you replace the keys in any working application before expiring them! Consider yourself warned...
     *
     * @section Security Related
     * @example mcapi_apikeyExpire.php
     * @example xml-rpc_apikeyExpire.php
     *
     * @param string $username Your MailChimp user name
     * @param string $password Your MailChimp password
     * @return boolean true if it worked, otherwise an error is thrown.
     */
    function apikeyExpire($username, $password) {
        //doesnt transfer
    }

    /**
     * "Ping" the MailChimp API - a simple method you can call that will return a constant value as long as everything is good. Note
     * than unlike most all of our methods, we don't throw an Exception if we are having issues. You will simply receive a different
     * string back that will explain our view on what is going on.
     *
     * @section Helper
     * @example xml-rpc_ping.php
     *
     * @return string returns "Everything's Chimpy!" if everything is chimpy, otherwise returns an error message
     */
    function ping() {
        //doesnt transfer
    }

    /**
     * Register a mobile device
     *
     * @section Mobile
     *
     * @param string $mobile_key a valid key identifying your mobile application.
     * @param array $details the details for the device registration
     * @return array the method completion status
    string status The status (success) of the call if it completed. Otherwise an error is thrown.
     */
    function deviceRegister($mobile_key, $details) {
        //doesnt transfer 
    }

    /**
     * Unregister a mobile device
     *
     * @section Mobile
     *
     * @param string $mobile_key a valid key identifying your mobile application.
     * @param string $device_id the device id used for the device registration
     * @return array the method completion status
    string status The status (success) of the call if it completed. Otherwise an error is thrown.
     */
    function deviceUnregister($mobile_key, $device_id) {
        //same as device transfer
    }

    /**
     * Add Golden Monkey(s)
     *
     * @section Golden Monkeys
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @param array $email_address an array of email addresses (max 50) to attempt to flag as Golden Monkeys
     * @return array an array of API keys including:
    int success The number of successful adds
    int errors The number of unsuccessful adds
    array data details on the errors which occurred
    string email_address the email address which errored out
    string error an error message explaining the error
     */
    function gmonkeyAdd($id, $email_address) {
        //doesnt translate
    }

    /**
     * Remove Golden Monkey(s)
     *
     * @section Golden Monkeys
     *
     * @param string $id the list id to connect to. Get by calling lists()
     * @param array $email_address an array of email addresses (max 50) to attempt to remove Golden Monkey status from.
     * @return array an array of API keys including:
    int success The number of successful removals
    int errors The number of unsuccessful removals
    array data details on the errors which occurred
    string email_address the email address which errored out
    string error an error message explaining the error
     */
    function gmonkeyDel($id, $email_address) {
        //doesnt transfer
    }

    /**
     * Retrieve all Golden Monkey(s) for an account
     *
     * @section Golden Monkeys
     *
     * @return array an array for each Golden Monkey, including:
    string list_id   The id of the List the Member appears on
    string list_name The name of the List the Member appears on
    string email     The email address of the member
    string fname IF a FNAME merge field exists on the list, that value for the member
    string lname IF a LNAME merge field exists on the list, that value for the member
    int    member_rating the rating of the subscriber. This will be 1 - 5 as described <a href="http://eepurl.com/f-2P" target="_blank">here</a>
    string member_since the datetime the member was added and/or confirmed
     */
    function gmonkeyMembers() {
        //doesnt transfer
    }

    /**
     * Retrieve all Activity (opens/clicks) for Golden Monkeys over the past 10 days
     *
     * @section Golden Monkeys
     *
     * @return array an array for each Golden Monkey, including:
    string action    The action taken - either "open" or "click"
    string timestamp The datetime the action occurred
    string url       IF the action is a click, the url that was clicked
    string unique_id The campaign_id of the List the Member appears on
    string title     The campaign title
    string list_name The name of the List the Member appears on
    string email     The email address of the member
    string fname IF a FNAME merge field exists on the list, that value for the member
    string lname IF a LNAME merge field exists on the list, that value for the member
    int    member_rating the rating of the subscriber. This will be 1 - 5 as described <a href="http://eepurl.com/f-2P" target="_blank">here</a>
    string member_since the datetime the member was added and/or confirmed
    array geo the geographic information if we have it. including:
    string latitude the latitude
    string longitude the longitude
    string gmtoff GMT offset
    string dstoff GMT offset during daylight savings (if DST not observered, will be same as gmtoff
    string timezone the timezone we've place them in
    string cc 2 digit ISO-3166 country code
    string region generally state, province, or similar
     */
    function gmonkeyActivity() {
        //doesnt transfer 
    }

    /**
     * Internal function - proxy method for certain XML-RPC calls | DO NOT CALL
     * @param mixed Method to call, with any parameters to pass along
     * @return mixed the result of the call
     */
    function callMethod() {
        //doesnt transfer or make sense 
    }

	function callServer($params, $method) {
        $this->errorMessage = "";
        $this->errorCode = "";

        $host = $this->apiUrl["host"];
        //$params["apikey"] = $this->api_key;
        //include cURL request since websocket doesnt return entire object for GET requests
        if (strtolower($method) == 'get') {

            $thispath = 'https://' . $host . $this->apiUrl["path"] . (($this->apiUrl["query"]) ? '?' . $this->apiUrl["query"] : '');

            echo $thispath;

            echo "\n\n|||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||\n\n";
            echo "GET request using cURL";
            echo "\n\n|||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||\n\n";

            $ch = curl_init();

            curl_setopt($ch,CURLOPT_URL,'https://' . $host . $this->apiUrl["path"] . (($this->apiUrl["query"]) ? '?' . $this->apiUrl["query"] : '') );
            curl_setopt($ch, CURLOPT_USERPWD, 'user:' . $this->api_key);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            //curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);                                                                                                                 

            $result = curl_exec($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);

            //print_r($info);

            //var_dump(json_decode($result, true));

            return $result;
        }

        $sep_changed = false;
        //sigh, apparently some distribs change this to &amp; by default
        if (ini_get("arg_separator.output")!="&"){
            $sep_changed = true;
            $orig_sep = ini_get("arg_separator.output");
            ini_set("arg_separator.output", "&");
        }
        $post_vars = $params;
        if ($sep_changed){
            ini_set("arg_separator.output", $orig_sep);
        }
        $auth = base64_encode("user:" . $this->api_key);


        $payload = $method . " " . $this->apiUrl["path"] . " HTTP/1.0\r\n";
        $payload .= "Host: " . $host . "\r\n";
        //$payload .= "User-Agent: MCAPI/" . $this->version ."\r\n";
        $payload .= "Content-Type: application/json\r\n";
        $payload .= "Authorization: Basic " . $auth . "\r\n";
        $payload .= "Content-Length: " . strlen($post_vars) . "\r\n";
        $payload .= "Connection: close\r\n\r\n";
        $payload .= $post_vars . "\r\n\r\n";
        
        //remove this line when development is finished
        echo "\r\n\r\n" . $payload . "\r\n\r\n";

        ob_start();
        //should this always use the secure version since the api list 'https' as the preffered protocol?
        if ($this->secure){
            $sock = fsockopen("ssl://".$host, 443, $errno, $errstr, 300);
            fwrite($sock, $payload);
        } else {
            $sock = fsockopen($host, 80, $errno, $errstr, 300);
            fwrite($sock, $payload);
        }
        if(!$sock) {
            $this->errorMessage = "Could not connect (ERR $errno: $errstr)";
            $this->errorCode = "-99";
            ob_end_clean();
            return false;
        }

        $response = "";
        fwrite($sock, $payload);
        stream_set_timeout($sock, $this->timeout);
        $info = stream_get_meta_data($sock);
        while ((!feof($sock)) && (!$info["timed_out"])) {
            $response .= fread($sock, $this->chunkSize);
            $info = stream_get_meta_data($sock);
        }
        fclose($sock);
        ob_end_clean();
        if ($info["timed_out"]) {
            $this->errorMessage = "Could not read response (timed out)";
            $this->errorCode = -98;
            return false;
        }

        list($headers, $response) = explode("\r\n\r\n", $response, 2);
        $headers = explode("\r\n", $headers);
        $errored = false;
        foreach($headers as $h){
            if (substr($h,0,26)==="X-MailChimp-API-Error-Code"){
                $errored = true;
                $error_code = trim(substr($h,27));
                break;
            }
        }

        if(ini_get("magic_quotes_runtime")) $response = stripslashes($response);

        $serial = $response;
        if($response && $serial === false) {
            $response = array("error" => "Bad Response.  Got This: " . $response, "code" => "-99");
        } else {
            $response = $serial;
        }
        if($errored && is_array($response) && isset($response["error"])) {
            $this->errorMessage = $response["error"];
            $this->errorCode = $response["code"];
            return false;
        } elseif($errored){
            $this->errorMessage = "No error message was found";
            $this->errorCode = $error_code;
            return false;
        }

        echo "\n\n";

        return $response;
    }
}

$segments = [];

$segments['conditions'][] = array("field" => "static_segment",
                                    'op' => 'eq',
                                    'value' => '21161');

$mc_options = array(
    "list_id"    => 'ee21077674',
    "subject"    => 'Campaign Creation Test',
    "from_email" => 'dude@athlonsports.com',
    "from_name"  => 'Tristan',
    "title"      => 'Campaign Test'
);

$secure = true;

$apikey = '80da46c29ccd2343b4be09220829b107-us10';

$api = new MCAPI($apikey, $secure);

$listId = 'ee21077674';

$data = [
    'email'     => 'tristan@tdgordon.com',
    'status'    => 'subscribed',
    'firstname' => 'Just',
    'lastname'  => 'Testing'
];

$anArray = array();

$content = array("html" => '<p>Change it up, but still testing</p>', "text" => 'plain text testing');
$contentTwo = array("html" => '<p>still just a test</p>', "text" => 'still plain text testing');
$contentThree = "justtesting@athlonsports.com";
$contentFour = array("html" => '<p>Change it up, but still testing</p>');

$batch = array("athlonexpert1@athlonsports.com", "tristan@tdgordon.com", "tgordon1@athlonsports.com");

$batchTwo =  array(
                array(
                    'email_address' => 'batchtest@athlonsports.com',
                    'email_type'    => 'html',
                    'status'        => 'subscribed'

                ),
                array(
                    'email_address' => 'batchtest1@athlonsports.com',
                    'email_type'    => 'html',
                    'status'        => 'subscribed'

                ),
                array(
                    'email_address' => 'batchtest2@athlonsports.com',
                    'email_type'    => 'html',
                    'status'        => 'subscribed'
                )
            );

$sendType = null;

//$wonderVar = $api->addSubscriber($listId, $data);

//print_r($segments);
$seg_id = '21157';
$aCampaignId = 'e6001a1958';
$tid = '2000101';
$tempId = '97';
$values = array('name' => "tristanTEST2");

$api->campaignCreate("auto", $mc_options, $content, $segments, $type_opts=NULL);

$newCamp = '4d8fb4b9a5';

//$api->campaignScheduleBatch($newCamp);

//$api->campaignScheduleBatch($newCamp);

//$api->campaignTemplateContent($newCamp);

//$api->campaignHardBounces($newCamp);

//$api->campaignUnsubscribes($newCamp);

//$api->getCampaignListId($newCamp);

//$api->campaignMembers($newCamp);

//$api->campaignStats($newCamp);

//$api->campaignReplicate($newCamp);

//$api->campaignClickStats($newCamp);

//$api->campaignUpdate($newCamp, "content", $contentFour);

//$api->setCampaignContent($content, $newCamp);

//$api->campaignSendNow($newCamp);

//$api->campaignUnschedule($newCamp);

//$api->campaignDelete($extra);

//$api->listStaticSegments($listId);

//$api->campaignUnschedule($newCamp);

//$api->campaignPause($newCamp);

//$api->campaignResume($newCamp);

//$api->campaignSchedule($newCamp, "2016-11-17T19:13:00+00:00");

//$api->campaignScheduleBatch($newCamp, "2016-11-15T19:13:00+00:00");

//$api->campaignSendTest($newCamp, array("tgordon@athlonsports.com"));

//$api->campaignUpdate($aCampaignId, "content", $contentTwo);

//$api->campaignUpdate($newCamp, "from_email", $contentThree);

//$dude = $api->campaignReplicate($aCampaignId);

//$api->campaignDelete($aCampaignId);

//$api->campaigns();

//$api->campaignContent($aCampaignId);

//$api->lists();

//$api->listMergeVars($listId);

//$api->listInterestGroupings($listId);

//$api->listStaticSegments($listId);

$merge = array(
            'FNAME' => 'SoMany',
            'LNAME' => 'Tests'
        );

//$api->listMembers($listId);

//$api->templateInfo($tid);

//$api->templateUpdate($tempId, $values);

//$api->templateDel($tempId);

//$api->getAccountDetails();

//$api->folders();

//$api->listsForEmail('tristan@tdgordon.com');

//$api->searchMembers('tristan@', 'ee21077674');

//$api->searchCampaigns("tristanTEST");

//$api->templateAdd('tristanTEST', '<html><body><h1>Hello</h1></body></html>');

//$api->listUnsubscribe($listId, 'anothertest@athlonsports.com', $merge, 'html', true, false, true, false);

//$api->listBatchSubscribe($listId, $batchTwo, $double_optin=true, $update_existing=false, $replace_interests=true);

//$api->listStaticSegmentMembersDel($listId, $seg_id, $batch);

//$api->listInterestGroupAdd($listId, 'testing');

//$api->listMergeVarAdd($listId);

//$dude = $api->campaignCreate("regular", $mc_options, $content, $segments);

//print_r($dude);

//$api->campaignDelete('1239d9c0');

//$api->campaignPause();

//$api->campaignSendNow();

//$api->campaignSendTest($newCamp);

//$api->campaignSegmentTest();