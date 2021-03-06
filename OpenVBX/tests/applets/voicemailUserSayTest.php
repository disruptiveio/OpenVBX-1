<?php

include_once dirname(__FILE__).'/../CIUnit.php';

class voicemailUserSayTest extends OpenVBX_Applet_TestCase
{
	private $user_id = 1;
	
	public function setUp()
	{
		parent::setUp();
		
		$this->setFlow(array(
			'id' => 1,
			'user_id' => 1,
			'created' => NULL,
			'updated' => NULL,
			'data' => '{"start":{"name":"Call Start","data":{"next":"start/f274cd"},"id":"start","type":"standard---start"},"f274cd":{"name":"Voicemail","data":{"prompt_say":"","prompt_play":"","prompt_mode":"say","prompt_tag":"global","number":"","library":"","permissions_id":"'.$this->user_id.'","permissions_type":"user"},"id":"f274cd","type":"standard---voicemail"}}',
			'sms_data' => NULL,
			'tenant_id' => 1
		));
		
		// set up our request
		$this->setPath('/twiml/applet/voice/1/f1e974');
		$this->setRequestMethod('POST');
		
		// prepare the header token for request validation
		$this->setRequestToken();
	}
	
	public function tearDown() 
	{	
		$this->CI->db->truncate('user_messages');
		$this->CI->db->truncate('messages');
		parent::tearDown();
	}

	public function testVoicemailUserSay()
	{	
		ob_start();
		$this->CI->voice(1, 'f274cd');
		$out = ob_get_clean();
		
		$xml = simplexml_load_string($out);
		$this->assertInstanceOf('SimpleXMLElement', $xml);
		
		$user = VBX_User::get($this->user_id);
		
		// this regex match is cheap, need better reg-fu to match possible
		// language and voice attributes that could appear in any order
		$this->assertRegExp('|(<Say(.*?)>'.$user->voicemail.'</Say>)|', $out);
		$this->assertRegExp('|(<Record transcribeCallback="(.*?)"/>)|', $out);
	}
	
	public function testVoicemailUserMessage()
	{
		$message_data = array(
			'CallSid' => 'CA123',
			'From' => '+15148675309',
			'To' => '+13038675309',
			'RecordingUrl' => 'http://foo.com/my/user_recording.wav',
			'RecordingDuration' => 10
		);
		$this->setRequest($message_data);

		ob_start();
		$this->CI->voice(1, 'f274cd');
		$out = ob_get_clean();
				
		$result = $this->CI->db->get_where('messages', array('content_url' => $message_data['RecordingUrl']))->result();
		$this->assertEquals(1, count($result));
		
		$message = current($result);
		$this->assertEquals($message_data['CallSid'], $message->call_sid);
		$this->assertEquals($message_data['From'], $message->caller);
		$this->assertEquals($message_data['To'], $message->called);
		$this->assertEquals($message_data['RecordingUrl'], $message->content_url);
		$this->assertEquals($message_data['RecordingDuration'], $message->size);
	}
}