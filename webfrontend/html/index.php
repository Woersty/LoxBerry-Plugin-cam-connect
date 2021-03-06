<?php
#####################################################################################################
# Loxberry Plugin to change the HTTP-Authentication of a Trendnet TV-IP310PI Surveillance IP-Cam
# from Digest to none to be used in the Loxone Door-Control-Object.
#####################################################################################################

// Error Reporting off
error_reporting(~E_ALL & ~E_STRICT);     // Keine Fehler reporten (auch nicht E_STRICT)
ini_set("display_errors", false);        // Fehler nicht direkt via PHP ausgeben
require_once "loxberry_system.php";
require_once "loxberry_log.php";
$L = LBSystem::readlanguage("language.ini");
$plugindata = LBSystem::plugindata();
ini_set("log_errors", 1);
ini_set("error_log", $lbplogdir."/cam_connect.log");

$datetime    = new DateTime;
if (isset($_GET['cam']))
{
	$cam=intval($_GET['cam']);
}
else
{
	unset($cam);
}
	
function debug($message = "", $loglevel = 7, $raw = 0)
{
	global $L,$plugindata,$cam;
	if ( $plugindata['PLUGINDB_LOGLEVEL'] >= intval($loglevel)  || $loglevel == 8 )
	{
		if (isset($cam))
		{
			$camprefix="Cam #".$cam.": ";
		}
		else
		{
			$camprefix="";
		}
		if ( $raw != 1 ) $message = $camprefix.$message;
		switch ($loglevel)
		{
		    case 0:
		        // OFF
		        break;
		    case 1:
		        error_log( strftime("%A") ." <ALERT> PHP: ".$message );
		        break;
		    case 2:
		    case 8:
		        error_log( strftime("%A") ." <CRITICAL> PHP: ".$message );
		        break;
		    case 3:
		        error_log( strftime("%A") ." <ERROR> PHP: ".$message );
		        break;
		    case 4:
		        error_log( strftime("%A") ." <WARNING> PHP: ".$message );
		        break;
		    case 5:
		        error_log( strftime("%A") ." <OK> PHP: ".$message );
		        break;
		    case 6:
		        error_log( strftime("%A") ." <INFO> PHP: ".$message );
		        break;
		    case 7:
		    default:
		        error_log( strftime("%A") ." PHP: ".$message );
		        break;
		}
		if ( $loglevel < 4 ) 
		{
		  if ( isset($message) && $message != "" ) notify ( LBPPLUGINDIR, $L['CC.MY_NAME'], $message);
		}
	}
	return;
}

// Check for GD Library
if ( !function_exists (@ImageCreate) ) 
{
	debug($L["ERRORS.ERROR_IMAGE_FUNCTION_MISSING"],8);
	die($L["ERRORS.ERROR_IMAGE_FUNCTION_MISSING"]);
}

$plugin_config_file = LBPCONFIGDIR."/cam-connect.cfg";
$plugin_cfg_handle    = fopen($plugin_config_file, "r") or debug($L["ERRORS.ERROR_READING_CFG"],4); ;
if ($plugin_cfg_handle)
{
  while (!feof($plugin_cfg_handle))
  {
    $line_of_text = fgets($plugin_cfg_handle);
    if (strlen($line_of_text) > 3)
    {
      $config_line = explode('=', $line_of_text);
      if ($config_line[0])
      {
      	if (!isset($config_line[1])) $config_line[1] = "";
        $plugin_cfg[$config_line[0]]=preg_replace('/\r?\n|\r/','', str_ireplace('"','',$config_line[1]));
      }
    }
  }
  fclose($plugin_cfg_handle);
}
else
{
  debug("No plugin config file handle found.",7);
  error_image($L["ERRORS.ERROR_READING_CFG"]);
  exit;
}
debug($L["ERRORS.ERROR_ENTER_PLUGIN"]." ".$_SERVER['REMOTE_ADDR']."\n+ + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + ",5);
debug("Check Logfile size: ".LBPLOGDIR."/cam_connect.log",7);
$logsize = filesize(LBPLOGDIR."/cam_connect.log");
if ( $logsize > 5242880 )
{
    debug($L["ERRORS.ERROR_LOGFILE_TOO_BIG"]." (".$logsize." Bytes)",4);
    debug("Set Logfile notification: ".LBPPLUGINDIR." ".$L['CC.MY_NAME']." => ".$L['ERRORS.ERROR_LOGFILE_TOO_BIG'],7);
    notify ( LBPPLUGINDIR, $L['CC.MY_NAME'], $L["ERRORS.ERROR_LOGFILE_TOO_BIG"]." (".$logsize." Bytes)");
	system("echo '' > ".LBPLOGDIR."/cam_connect.log");
}
else
{
	debug("Logfile size is ok: ".$logsize,7);
}

if (isset($_GET['alarm'])) 
{
	$alarms=explode(",",$_GET['alarm']);
}
else
{
	$alarms=array();
}
alarm_loop:
if ( count($alarms) >= 1 )
{
	$cam = array_shift($alarms);
	debug("Got Alarm for Cam ".$cam,7);
}

if (isset($cam))
{
    debug("Camera $cam requested.",7);
}
else
{
	error_image($L["ERRORS.ERROR_NO_CAM_PARAMETER"]);
	exit;
}

$camera_models_file = LBPDATADIR."/camera_models.dat";
debug("Read cameras from ".$camera_models_file,7);
$lines_of_text = file ( $camera_models_file );

  
  if (isset($plugin_cfg['CAM_MODEL'.$cam]))
  {
	  $cam_model=intval($plugin_cfg['CAM_MODEL'.$cam]);
	  
  }
  else
  {
	  error_image($L["ERRORS.ERROR_READING_CAM_MODEL"]);
	  goto alarm_loop;
  }
  debug("Camera model for camera $cam is ".$cam_model,7);

  foreach ($lines_of_text as $line_num => $line_of_text) 
  {
	debug("Read cameras line $line_num: ".$line_of_text,7);
    $line_of_text = preg_replace('/\r?\n|\r/','', $line_of_text);
    $config_line = explode('|', $line_of_text);
    if (count($config_line) == 5)
    {
      if (intval($config_line[0]) == $cam_model)
      {
        $plugin_cfg['httpauth'] = $config_line[4];
        $plugin_cfg['imagepath'] = $config_line[3];
        $plugin_cfg['model']     = $config_line[2];
		debug( $L["ERRORS.ERROR_CAM_FOUND"] . " ". ( $line_num + 1 ) . "\n" . $line_of_text ,5);
		debug("Stop reading camera file",7);
        break;
      }
      else
      {
		debug("Did not found the camera we're looking for at line $line_num: ".$line_of_text,7);
	  }
    }
    else
    {
    	debug($L["ERRORS.ERROR_CAMERA_LIST_LINE"]." ".$line_num." => ".$line_of_text,4);
    }
  }
  if ( $plugin_cfg['model'] == "" || $plugin_cfg['httpauth'] == "" || $plugin_cfg['imagepath'] == "" )
  {      
  	error_image($L["ERRORS.ERROR_READING_CAMS"]);
	goto alarm_loop;
  }

# Check for deprecated values
if (isset($_GET['email'])) 			{ debug($L["ERRORS.ERROR_DEPRECATED"]." email",4); 			}
if (isset($_GET['cam-name']))		{ debug($L["ERRORS.ERROR_DEPRECATED"]." cam-name",4);		}
if (isset($_GET['image_resize'])) 	{ debug($L["ERRORS.ERROR_DEPRECATED"]." image_resize",4);	}
if (isset($_GET['email_resize']))	{ debug($L["ERRORS.ERROR_DEPRECATED"]." image_resize",4);	}
if (isset($_GET['user']))			{ debug($L["ERRORS.ERROR_DEPRECATED"]." user",4);			}
if (isset($_GET['pass']))			{ debug($L["ERRORS.ERROR_DEPRECATED"]." pass",4);			}
if (isset($_GET['kamera']))			{ debug($L["ERRORS.ERROR_DEPRECATED"]." kamera",4);			}
if (isset($_GET['port']))			{ debug($L["ERRORS.ERROR_DEPRECATED"]." port",4);			}
if (isset($_GET['cam-model']))		{ debug($L["ERRORS.ERROR_DEPRECATED"]." cam-model",4);		}
if (isset($_GET['email_to']))		{ debug($L["ERRORS.ERROR_DEPRECATED"]." email_to",4);		}
if (isset($_GET['no_email']))		{ debug($L["ERRORS.ERROR_DEPRECATED"]." no_email",4);		}
# Check for values causing trouble
if (isset($_GET['stream']))			{ debug($L["ERRORS.ERROR_GET_PARAM_STREAM"],4);				}

debug("Read LoxBerry global eMail config",7);
if ($plugin_cfg['CAM_EMAIL_USED_CB'.$cam] == 1) 
{
	$mail_config_file   = LBSCONFIGDIR."/mail.json";
	if (is_readable($mail_config_file)) 
	{
		debug("Parameter CAM_EMAIL_USED_CB is set for camera $cam, read eMail config from ".$mail_config_file,5);
		$mail_cfg  = json_decode(file_get_contents($mail_config_file), true);
	}
	else
	{
		debug("Cannot read eMail config from ".$mail_config_file,7);
		$mail_config_file   = LBSCONFIGDIR."/mail.cfg";
		debug("Try to find deprecated config prior LoxBerry v1.4.x in ".$mail_config_file,7);
		if (is_readable($mail_config_file)) 
		{
			debug("Parameter CAM_EMAIL_USED_CB is set for camera $cam, read eMail config from ".$mail_config_file,5);
			$mail_cfg    = parse_ini_file($mail_config_file,true);
		}
		else
		{
			debug("Cannot read eMail config from ".$mail_config_file,7);
		}
	}

  if ( !isset($mail_cfg) )
  {
     debug("Can't read eMail config",7);
     error_image($L["ERRORS.ERROR_READING_EMAIL_CFG"]);
	 exit;
  }
  else
  {
    debug($L["ERRORS.ERROR_EMAIL_CONFIG_OK"]." [".$mail_cfg['SMTP']['SMTPSERVER'].":".$mail_cfg['SMTP']['PORT']."]",5);
    if ( $mail_cfg['SMTP']['ACTIVATE_MAIL'] == "0" )
    {
     debug("eMail ist not activated: SMTP.ACTIVATE_MAIL is 0",7);
     error_image($L["ERRORS.ERROR_INVALID_EMAIL_CFG"]);
	 exit;
    }
  }
}
else
{
debug($L["ERRORS.ERROR_EMAIL_NOT_USED"],5);
}

if (isset($plugin_cfg['CAM_NAME'.$cam]) && $plugin_cfg['CAM_NAME'.$cam] != "")
{
	$cam_name="[".addslashes($plugin_cfg['CAM_NAME'.$cam])."] ";
	debug("Parameter 'CAM_NAME' (for eMail) is set, take value into account: ".$cam_name,7);
}
else
{
	debug("Parameter 'CAM_NAME' (for eMail) is not set, ignoring it.",7);
	$cam_name="";
}

if (!isset($plugin_cfg['CAM_HOST_OR_IP'.$cam]))
{
     error_image($L["ERRORS.ERROR_INVALID_HOST_OR_IP_CFG"]);
	 goto alarm_loop;
}
else
{
	exec ( "/bin/ping -c 1 ".$plugin_cfg['CAM_HOST_OR_IP'.$cam], $output , $return_var );
	if ( $return_var != 0 )
	{
		error_image($L["ERRORS.ERROR_PING_ERR_HOST_OR_IP"]." ".$plugin_cfg['CAM_HOST_OR_IP'.$cam]);
		goto alarm_loop;
	}
}

$plugin_cfg['url']  = "http://".trim(addslashes($plugin_cfg['CAM_HOST_OR_IP'.$cam].":".$plugin_cfg['CAM_PORT'.$cam].$plugin_cfg['imagepath']));
debug("Using url: ".$plugin_cfg['url'],7);
$plugin_cfg['user'] = addslashes($plugin_cfg['CAM_USER'.$cam]);
debug("Using user: ".$plugin_cfg['user'],7);
$plugin_cfg['pass'] = addslashes($plugin_cfg['CAM_PASS'.$cam]);
debug("Using pass: ".$plugin_cfg['pass'],7);

function get_image($retry=0)
{
	global $plugin_cfg, $curl, $lbpplugindir, $L, $cam, $plugindata;
	$retry=intval($retry);
	debug("Function get_image called ($retry) for camera $cam with hostname/IP: ".$plugin_cfg['CAM_HOST_OR_IP'.$cam],7);
    $curl = curl_init() or error_image($L["ERRORS.ERROR_INIT_CURL"]);
	if ($curl === FALSE ) return;
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_HTTPAUTH, constant($plugin_cfg['httpauth']));
	curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
	curl_setopt($curl, CURLOPT_USERPWD, $plugin_cfg['user'].":".$plugin_cfg['pass']);
	curl_setopt($curl, CURLOPT_URL, $plugin_cfg['url']);
	debug("Check for 'Digitus DN-16049' camera model",7);
	if ( $plugin_cfg['model'] == "DN-16049" )
	{
	  debug("It's a 'Digitus DN-16049' camera - do some exceptional things to get real image path",7);
	  debug("Read webpage from 'Digitus DN-16049' camera to cut out the image path",7);
	  foreach(split("\n",curl_exec($curl)) as $k=>$html_zeile)
	  {
	    if(preg_match("/\b.jpg\b/i", $html_zeile))
	    {
	      $anfang             = stripos($html_zeile, '"../../..')  +9;
	      $ende               = strrpos($html_zeile, '.jpg"')       -5 -9;
	      $plugin_cfg['url']  = "http://".trim(addslashes($plugin_cfg['CAM_HOST_OR_IP'.$cam].":".$plugin_cfg['CAM_PORT'.$cam].substr($html_zeile,$anfang,$ende)));
		  debug("Line with '.jpg' found. Resulting URL is:".$plugin_cfg['url'],7);
	      break;
	    }
	    else
	    {
		  debug("No line with '.jpg' found. Keep going until a line is found or no lines are left.",7);
	    }
	  }
	}
	else
	{
	  debug("It's no 'Digitus DN-16049' but a ". $plugin_cfg['model'] ." camera - continue normally",7);
	  debug("Get the image from the camera: ".$plugin_cfg['url'],7);
	  debug("User is ".$plugin_cfg['user'],7);
	  debug("Password is ".$plugin_cfg['pass'],7);
	  $picture = curl_exec($curl) or debug($L["ERRORS.ERROR_CURL"]." ".$plugin_cfg['url'],3);
	    if ($curl) { curl_close($curl); }

		if( mb_strlen($picture) < 2000 && $retry <= 1) 
		{
		  debug($L["ERRORS.ERROR_IMAGE_NOT_OK_RETRY"]."\n".$picture,7);
		  sleep (.25);
		  $picture = get_image($retry + 1);
		}
		if( mb_strlen($picture) < 2000 && $retry <= 2) 
		{
		  debug($L["ERRORS.ERROR_IMAGE_NOT_OK_LAST_RETRY"],4);
		  debug($L["ERRORS.ERROR_IMAGE_NOT_OK_LAST_RETRY_DEBUG"]."\n".$picture,7);
		  sleep (.25);
		  $picture = get_image($retry + 1);
		}
		else
		{
		  debug("Image successfully read from the camera.",7);
		  if ( $plugindata['PLUGINDB_LOGLEVEL'] == 7 )
		  {
		  	$finfo 	= 	new finfo(FILEINFO_MIME);
		  	$type 	= 	explode(';',$finfo->buffer($picture),2)[0];
		  	debug("Type: ".$type." Size: ".strlen($picture)." Bytes",7);
			if (!isset($_GET['stream']))
			{
			  	 debug("\n<img src='data:".$type.";base64,".base64_encode($picture)."'></>",7);
			}
			else
			{
			  	debug("Picture:\n[not shown in stream mode]",7);
			}
		  }
		}
	}
	debug("Check, if the picture has less than 2000 bytes - then it's no picture.",7);
	if(mb_strlen($picture) < 2000)
	{
	  debug($L["ERRORS.ERROR_IMAGE_TOO_SMALL"]."\n".htmlentities($picture),5);
	  error_image($L["ERRORS.ERROR_IMAGE_TOO_SMALL"]."\n".$picture);
	  return false;
	}
	else
	{
	  debug($L["ERRORS.ERROR_PIC_OK"]." [".mb_strlen($picture)." Bytes]",5);
	  return $picture;
	}
}

function stream()
{
	global $plugin_cfg, $curl, $lbpplugindir, $L, $cam;
   		debug("Check if stream parameter '&stream' from URL was provided",7);
		    debug("Yes, looping the picture as mjpeg_stream.",7);
			$boundary = "mjpeg_stream";
			header("Cache-Control: no-cache");
			header("Cache-Control: private");
			header("Pragma: no-cache");
			header("Content-type: multipart/x-mixed-replace; boundary=$boundary");
			print "--$boundary\n";
	        debug("Set PHP time limit to 0 so it doesn't timeout during a long stream",7);
			set_time_limit(0);
	        debug("Disable output compression",7);
			#@apache_setenv('no-gzip', 1);
			@ini_set('zlib.output_compression', 0);
	        debug("Enable implicit_flush",7);
			@ini_set('implicit_flush', 1);
			for ($i = 0; $i < ob_get_level(); $i++)
	    	ob_end_flush();
			ob_implicit_flush(1);
	        debug("Start looping stream now, max $maxloops times",7);
				if ( substr($_SERVER['SERVER_SOFTWARE'],0,6) == "Apache") 
				{
				    while (true == true)
				    {
						print "Content-type: image/jpeg\n\n";
						$picture = get_image();
						// Try again if last call failed e.g. device busy
						if(mb_strlen($picture) < 2000)
						{
					        debug("Fail, try again",7);
							$picture = get_image();
						}
						// Try again if last call failed - but last time we try it...
						if(mb_strlen($picture) < 2000)
						{
					        debug("Fail again, try last time",7);
							$picture = get_image();
						}
				        debug("Send frame to ".$_SERVER['REMOTE_ADDR'],7);
						echo $picture;
						print "--$boundary\n";
					}
				}
				else
				{
					$maxloops=180;
				    while ($maxloops > 0)
					{
						print "Content-type: image/jpeg\n\n";
						$picture = get_image();
						// Try again if last call failed e.g. device busy
						if(mb_strlen($picture) < 2000)
						{
					        debug("Fail, try again",7);
							$picture = get_image();
						}
						// Try again if last call failed - but last time we try it...
						if(mb_strlen($picture) < 2000)
						{
					        debug("Fail again, try last time",7);
							$picture = get_image();
						}
				        debug("Send frame $maxloops to ".$_SERVER['REMOTE_ADDR'],7);
						echo $picture;
						print "--$boundary\n";
						$maxloops = $maxloops - 1;
					}
				}			
	        debug("Exit normally after stream mode reached max loop count: ".$maxloops,7);
			exit;
}

function main()
{
	global $plugin_cfg, $curl, $lbpplugindir, $L, $cam, $plugindata;
	debug("Function 'main' reached",7);
	debug("Call get_image() fist time",7);
	$picture = get_image();
  if ($plugin_cfg["CAM_WATERMARK_CB".$cam] == 1)
  {
    debug("Parameter CAM_WATERMARK_CB is set to 1 so I have to put the overlay LoxBerry on it",7);
	$watermarkfile = LBPHTMLDIR."/watermark.png";
    debug("The overlay file will be: ".$watermarkfile,7);
    $watermarked_picture = imagecreatefromstring($picture);
	if ( $watermarked_picture === false ) 
	{
		error_image($L["ERRORS.ERROR_CREATE_WATERMARK_UNDERLAY"]);
		return false;
	}
    list($ix, $iy, $type, $attr) = getimagesizefromstring($picture);
    if ($type <> 2) error_image($L["ERRORS.ERROR_BAD_WATERMARK_IMAGETYPE"]);
    debug("Reading watermark.png into variable and applying overlay to camera image.",7);
    $stamp = imagecreatefrompng($watermarkfile) or error_image($L["ERRORS.ERROR_CREATE_WATERMARK_OVERLAY"]);
    $sx    = imagesx($stamp);
    $sy    = imagesy($stamp);
    debug("Target image width/height: ".$sx."/".$sy,7);
    $logo_width  = 120;
    $logo_height = 86;
    debug("Logo width/height: ".$logo_width."/".$logo_height,7);
    $margin_right  = $ix - $logo_width - 20;
    $margin_bottom = 20;
    debug("Margin right/bottom: ".$margin_right."/".$margin_bottom,7);
    ImageCopyResized($watermarked_picture, $stamp, $ix - $logo_width - $margin_right, $iy - $logo_height - $margin_bottom, 0, 0, $logo_width, $logo_height, $sx, $sy) or error_image($L["ERRORS.ERROR_MERGE_WATERMARK_LAYERS"]);
    ImageDestroy($stamp);
    ob_start();
    ImageJPEG($watermarked_picture) or error_image($L["ERRORS.ERROR_BUILD_JPEG"]);
    $picture = ob_get_contents();
	if ( $plugindata['PLUGINDB_LOGLEVEL'] == 7 )
	{
		if (!isset($_GET['stream']))
		{
		  	debug("Converted picture:\n<img src='data:image/jpeg;base64,".base64_encode($picture)."'></>",7);
		}
		else
		{
		  	debug("Converted picture:\n[not shown in stream mode]",7);
		}

	}
    ob_end_clean();
    ImageDestroy($watermarked_picture);
  }
  else
  {
	debug("Parameter CAM_WATERMARK_CB is set to 0 so I don't have to put the overlay LoxBerry on it",7);
  }

  debug("Reading CAM_IMAGE_RESIZE value from config file",7);

  if ( ( isset( $plugin_cfg['CAM_IMAGE_RESIZE'.$cam] ) && $plugin_cfg['CAM_IMAGE_RESIZE'.$cam] <> 0 )  )
  {
  	debug("Resize image to parameter CAM_IMAGE_RESIZE read from config file: ".$plugin_cfg['CAM_IMAGE_RESIZE'.$cam],7);
    if ( (intval($plugin_cfg['CAM_IMAGE_RESIZE'.$cam]) >= 200) &&  ( intval($plugin_cfg['CAM_IMAGE_RESIZE'.$cam]) <= 1920 ) )
    {
	  debug("CFG parameter 'CAM_IMAGE_RESIZE' is in valid range because ".intval($plugin_cfg['CAM_IMAGE_RESIZE'.$cam])." is >= 200 and <= 1920.",7);
      $newwidth = intval($plugin_cfg['CAM_IMAGE_RESIZE'.$cam]);
      debug("Resizing picture to ".$newwidth,7);
	  $resized_picture = resize_cam_image($picture,$newwidth) or error_image($L["ERRORS.ERROR_RESIZE"]);
    }
    else
    {
	  debug("CFG parameter 'CAM_IMAGE_RESIZE' is not within a valid range because ".intval($plugin_cfg['CAM_IMAGE_RESIZE'])." is not >= 200 and <= 1920.",7);
	  debug("No resizing here, keep picture as it is.",7);
      $resized_picture = $picture;
    }
  }
  else
  {
    debug("No resizing wanted in config. Keep picture size.",7);
    $resized_picture = $picture;
  }
return [$picture,$resized_picture];
}

if (isset($_GET['stream']))
{
	stream();
}
list($picture ,$resized_picture ) = main();


// Output to browser
  debug("No, streaming mode is not wanted, so continue normally.",7);
  if ( !isset($plugin_cfg['CAM_IMAGE_RESIZE'.$cam]) || $plugin_cfg['CAM_IMAGE_RESIZE'.$cam] == 0 )
  {
    debug($L["ERRORS.ERROR_NO_PIC_WANTED"]." ".$L['CC.IMAGE_RESIZE_JUST_TEXT_MSG'],5);
	ob_end_clean();
	header("Connection: close");
	ignore_user_abort(true); // just to be safe
	ob_start();
    echo $L['CC.IMAGE_RESIZE_JUST_TEXT_MSG'];
	$size = ob_get_length();
	header("Content-Length: $size");
	ob_end_flush(); // Strange behaviour, will not work
	flush(); // Unless both are called !
	// Do processing here 
  }
  else
  {
    debug($L["ERRORS.ERROR_PIC_WANTED"],5);
    header ('Content-type: image/jpeg');
    header ("Cache-Control: no-cache, no-store, must-revalidate");
    header ("Pragma: no-cache");
    header ("Expires: ".gmdate('D, d M Y H:i:s', time()-3600) . " GMT");
    header ('Content-Disposition: inline; filename="'.$plugin_cfg['EMAIL_FILENAME']."_".$datetime->format("Y-m-d_i\hh\mH\s").'.jpg"');
    debug("Picture wanted, display it now.",7);
	if ( $plugindata['PLUGINDB_LOGLEVEL'] == 7 )
	{
		$finfo 	= 	new finfo(FILEINFO_MIME);
		$type 	= 	explode(';',$finfo->buffer($resized_picture),2)[0];
		debug("Type: ".$type." Size: ".strlen($resized_picture)." Bytes",7);
		if (!isset($_GET['stream']))
		{
		  	 debug("\n<img src='data:".$type.";base64,".base64_encode($resized_picture)."'></>",7);
		}
		else
		{
		  	debug("Picture:\n[not shown in stream mode]",7);
		}
	}
	ob_end_clean();
	ignore_user_abort(true); 
	header("Connection: close");
	ob_start();
	echo $resized_picture;
	$size = ob_get_length();
	header("Content-Length: $size");
	ob_end_flush(); 
	flush(); 
  }

	debug("############# Normal mode done ######################",7);

	debug("############# eMail Part reached ####################",7);

	debug("Check if sending eMail is enabled",7);
	if ( $plugin_cfg['CAM_EMAIL_USED_CB'.$cam] == 1 && $mail_cfg['SMTP']['ACTIVATE_MAIL'] == 1 && $plugin_cfg['CAM_NO_EMAIL_CB'.$cam] == 0 )
	{
		debug($L["ERRORS.ERROR_SEND_EMAIL_INFO"],5);
		$sent = send_mail_pic($picture);
	}
	else
	{
		debug("Do not send email because 'CAM_EMAIL_USED_CB' is not set in config or SMTP server is not configured or 'CAM_NO_EMAIL_CB' parameter is set.",7);
		if (isset($plugin_cfg['CAM_EMAIL_USED_CB'.$cam])) debug("CFG parameter 'CAM_EMAIL_USED_CB' is: ".$plugin_cfg['CAM_EMAIL_USED_CB'.$cam],7);
		if (isset($mail_cfg['SMTP']['ACTIVATE_MAIL'])) debug("CFG parameter 'SMTP.ACTIVATE_MAIL' is: ".$mail_cfg['SMTP']['ACTIVATE_MAIL'],7);
		if (isset($plugin_cfg['CAM_NO_EMAIL_CB'.$cam])) debug("CFG parameter 'CAM_NO_EMAIL_CB' is: ".$plugin_cfg['CAM_NO_EMAIL_CB'.$cam],7);
	}


	debug("############# Archive Part reached ####################",7);

	debug("Check if archiving is enabled",7);
	if ( $plugin_cfg['CAM_SAVE_IMG_USED_CB'.$cam] == 1 )
	{
		debug($L["ERRORS.ERROR_ARCHIVING_INFO"],5);
		if (isset($plugin_cfg['FINALSTORAGE'.$cam])) 
		{
			$default_finalstorage	= $lbpdatadir."/";        # Default localstorage
			$finalstorage           = $plugin_cfg["FINALSTORAGE".$cam];
			$temp_finalstorage 		= $finalstorage;

			#Check if final target is on an external storage like SMB or USB
			if (strpos($finalstorage, '/system/storage/') !== false) 
			{                                       
				#Yes, is on an external storage 
				#Check if subdir must be appended
				if (substr($finalstorage, -1) == "+")
				{
					$temp_finalstorage = substr($finalstorage,0, -1);
					exec("mountpoint '".$temp_finalstorage."' ", $retArr, $retVal);
					if ( $retVal == 0 )
					{
						debug($L["ERRORS.INF_VALID_MOUNTPOINT"]." (".$temp_finalstorage.")",6);
						$finalstorage = $temp_finalstorage."/".$L["CC.STORAGE_PREFIX"].$cam;
					}
					else
					{
						debug($L["ERRORS.ERROR_INVALID_MOUNTPOINT"]." ".$temp_finalstorage,3);
					}
				}
				else if (substr($finalstorage, -1) == "~")
				{
					$temp_finalstorage = substr($finalstorage,0, -1);
					exec("mountpoint '".$temp_finalstorage."' ", $retArr, $retVal);
					if ( $retVal == 0 )
					{
						debug($L["ERRORS.INF_VALID_MOUNTPOINT"]." (".$temp_finalstorage.")",6);
						$finalstorage = $temp_finalstorage."/".$plugin_cfg["SUBDIR".$cam]."/".$L["CC.STORAGE_PREFIX"].$cam;
					}
					else
					{
						debug($L["ERRORS.ERROR_INVALID_MOUNTPOINT"]." ".$temp_finalstorage,3);
					}
				}
				else
				{
					exec("mountpoint '".$finalstorage."' ", $retArr, $retVal);
					if ( $retVal == 0 )
					{
						debug($L["ERRORS.INF_VALID_MOUNTPOINT"]." (".$finalstorage.")",6);
					}
					else
					{
						debug($L["ERRORS.ERROR_INVALID_MOUNTPOINT"]." ".$finalstorage,3);
					}
				} 
			}
			if (!is_dir($finalstorage)) 
			{
				debug($L["ERRORS.ERROR_DIR_NOT_FOUND_TRY_CREATE"],4);
				$resultarray = array();
				@exec("mkdir -v -p '".$finalstorage."' 2>&1",$resultarray,$retval);
				debug(implode("\n",$resultarray));
			}
			if (!is_writable($finalstorage))
			{
				debug($L["ERRORS.ERROR_DIR_NOT_WRITABLE"],3);
			}
			else
			{
				$targetfile = str_ireplace("<datetime>",date($L["CONFIG.DATETIME_FORMAT_PHP"]),str_ireplace("<cam>",$cam,$L["CONFIG.FILENAME_FORMAT_PHP"])).".jpg";
				debug($L["ERRORS.ERROR_DIR_OK"].$targetfile,6);	
				if ( !file_put_contents($finalstorage."/".$targetfile, $picture) )
				{
					debug($L["ERRORS.ERROR_DIR_NOT_WRITABLE"],3);
				}
				else
				{
					debug($L["ERRORS.ERROR_ARCHIVING_OK"]." (".$targetfile.")",5);
				}
			}
			


		}
		else
		{
			debug($L["ERRORS.ERROR_ARCHIVING_TARGET_DIR_CONFIG"],3);	
		}


		
	}
	else
	{
		debug("Do not archiving the image because 'CAM_SAVE_IMG_USED_CB' is not set in config.",7);
		if (isset($plugin_cfg['CAM_SAVE_IMG_USED_CB'.$cam])) debug("CFG parameter 'CAM_SAVE_IMG_USED_CB' is: ".$plugin_cfg['CAM_SAVE_IMG_USED_CB'.$cam],7);
	}


if ( count($alarms) >= 1 ) 
{
		debug("Remaining Alarms: ".count($alarms),7);
		unset($cam);
		goto alarm_loop;
}

debug($L["ERRORS.ERROR_EXIT_PLUGIN_INFO"]."\n+ + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + ",5);
exit;

function error_image ($error_msg)
{
  global $L, $plugin_cfg, $plugindata, $cam;
  if (strlen($error_msg) > 0)
  {
  	$error_msg=$error_msg;
  }
  else
  {
  	$error_msg=$L["ERRORS.ERROR_UNKNOWN"];
  }
  debug(explode("\n",$error_msg)[0],3);
  debug($error_msg,7);
  // Display an Error-Picture
  $error_image      = @ImageCreate (640, 480) or die ($error_msg);
  $background_color = ImageColorAllocate ($error_image, 0, 0, 0);
  $text_color       = ImageColorAllocate ($error_image, 255, 0, 0);
  $line_height		= 20;
  $line_pos         = 50;
  $line_nb			= "";
  foreach (explode("\n",$error_msg ) as $err_line)
  {
  	if ($err_line != "") 
  	{
		$err_line = str_ireplace(array("\r\n","\r","\n",'\r\n','\r','\n'),'', $err_line);
	  	$line_pos = $line_pos + $line_height; 
		ImageString ($error_image, 20, 10, $line_pos, $line_nb.$err_line, $text_color);
	    if ( $line_nb == "" ) $text_color = ImageColorAllocate ($error_image, 128,128,128);
	  	$line_nb++;
	}
  }
  header ("Content-type: image/jpeg");
  header ("Cache-Control: no-cache, no-store, must-revalidate");
  header ("Pragma: no-cache");
  header ("Expires: 0");
  ImageJPEG ($error_image);
	if ( $plugindata['PLUGINDB_LOGLEVEL'] == 7 )
	{
		$finfo 	= 	new finfo(FILEINFO_MIME);
		$type 	= 	explode(';',$finfo->buffer(ImageJPEG ($error_image)),2)[0];
		debug("Type: ".$type." Size: ".strlen(ImageJPEG ($error_image))." Bytes",7);
		if (!isset($_GET['stream']))
		{
		  	 debug("\n<img src='data:".$type.";base64,".base64_encode(ImageJPEG ($error_image))."'></>",7);
		}
		else
		{
		  	debug("Picture:\n[not shown in stream mode]",7);
		}
	}
  ImageDestroy($error_image);
  debug("Exit plugin in function error_image",7);
  unset($cam);
  return;
}

function send_mail_pic($picture)
{
  debug("Function send_mail_pic reached",7);
  global $datetime, $plugin_cfg, $cam_name, $mail_cfg, $L, $cam, $plugindata, $alarms;
  
	// Prevent sending eMails as long as stream is read from Miniserver
	// 10 s delay minimum
	$lockfilename = "/tmp/cam_connect_".$cam;
	debug("Check if lockfile $lockfilename for cam $cam exists",7);
	 
	if (file_exists($lockfilename)) {
	    debug( "The file $lockfilename exists.",7);
	    if (filectime($lockfilename)) 
		{
			if ( ($datetime->getTimestamp() - filectime($lockfilename)) > 10  )
			{
				debug( "Lockfile $lockfilename was changed ". ($datetime->getTimestamp() - filectime($lockfilename)) ." seconds ago. Too old, delete it and send eMail." ,7);
				unlink ($lockfilename) or debug($L["ERRORS.ERROR_DELETE_LOCKFILE_EMAIL"]." ".$lockfilename,3);
			}
			else
			{
				debug( "Lockfile $lockfilename was changed ". ($datetime->getTimestamp() - filectime($lockfilename)) ." seconds ago. Not old enough, keeping it, refresh it, and send no eMail." ,7);
			    $handle = fopen($lockfilename, "w") or debug($L["ERRORS.ERROR_OPEN_LOCKFILE_EMAIL"]." ".$lockfilename,3);
			    fwrite($handle, $datetime->getTimestamp() ) or debug($L["ERRORS.ERROR_WRITE_LOCKFILE_EMAIL"]." ".$lockfilename,3);
				unset($cam);
				return false;
			}
		}
	} 
	else 
	{
	  debug( "The file $lockfilename doesn't exists, create it.",7);
      $handle = fopen($lockfilename, "w") or debug($L["ERRORS.ERROR_OPEN_LOCKFILE_EMAIL"]." ".$lockfilename,3);
	  fwrite($handle, $datetime->getTimestamp() ) or debug($L["ERRORS.ERROR_WRITE_LOCKFILE_EMAIL"]." ".$lockfilename,3);
	  fclose($handle);
	}
  
  if ( isset($mail_cfg['SMTP']['EMAIL']) )
  {
  	 $mailFrom =	trim(str_ireplace('"',"",$mail_cfg['SMTP']['EMAIL']));
      debug("Config value ['SMTP']['EMAIL'] found - using it: ".$mailFrom,7);
	  if ( isset($plugin_cfg["CAM_EMAIL_FROM_NAME".$cam]) )
	  {
	      $mailFromName   = $plugin_cfg["CAM_EMAIL_FROM_NAME".$cam];  // Sender name
	      debug("Config value CAM_EMAIL_FROM_NAME found - using it: ".$mailFromName,7);
	  }
	  else
	  {
	      $mailFromName   = "\"LoxBerry\"";  // Sender name
	      debug("Config value CAM_EMAIL_FROM_NAME not found - using default: ".$mailFromName,7);
	  }
  }
  else
  {
      debug($L["ERRORS.ERROR_EMAIL_NO_SENDER"],3);
      return "Plugin-Error: [No Sender eMail address found]";
  }
   	debug("Adding recipients from config file: ".$plugin_cfg['CAM_RECIPIENTS'.$cam],7);
	  $mailTo="";
      foreach (explode(";",$plugin_cfg['CAM_RECIPIENTS'.$cam]) as $recipients_data)
      {
        debug("Recipient(s): ".$recipients_data,7);
        $recipients_data = trim(str_ireplace("\"","",$recipients_data));
        if (filter_var($recipients_data, FILTER_VALIDATE_EMAIL))
        {
          $mailTo .= $recipients_data.",";  // Add recipient
          $at_least_one_valid_email=1;
          debug("Validated recipient(s): ".$recipients_data,7);
        }
        else
        {
          debug($L["ERRORS.ERROR_EMAIL_INVALID_RECIPIENTS"],3);
          debug("Abort recipients manipulation.",7);
    	}
      }
  
	if ( isset($alarms) && isset($_GET['alarm']) )
	{
		$emailSubject = utf8_decode($cam_name.$datetime->format($plugin_cfg["CAM_EMAIL_DATE_FORMAT".$cam])." ".$datetime->format($plugin_cfg["CAM_EMAIL_TIME_FORMAT".$cam]));
	}
	else
	{
		$emailSubject = utf8_decode($cam_name.$plugin_cfg["CAM_EMAIL_SUBJECT1".$cam]." ".$datetime->format($plugin_cfg["CAM_EMAIL_DATE_FORMAT".$cam])." ".$plugin_cfg["CAM_EMAIL_SUBJECT2".$cam]." ".$datetime->format($plugin_cfg["CAM_EMAIL_TIME_FORMAT".$cam])." ".$plugin_cfg["CAM_EMAIL_SUBJECT3".$cam]);
	}
  debug("Building eMail subject: ".$emailSubject,7);

debug("Check for parameter CAM_EMAIL_MULTIPICS in config: ".$plugin_cfg['CAM_EMAIL_MULTIPICS'.$cam],7);
if ( isset($plugin_cfg['CAM_EMAIL_MULTIPICS'.$cam]))
{
	$pics = substr($plugin_cfg['CAM_EMAIL_MULTIPICS'.$cam],0,1);
	$delay = substr($plugin_cfg['CAM_EMAIL_MULTIPICS'.$cam],1,1);
	debug("Send $pics picture(s) with $delay s delay.",7);
}		
else
{
	debug("Parameter CAM_EMAIL_MULTIPICS missing, use default: Send 1 picture.",7);
}

$outer_boundary= md5("o".$cam.time());
$inner_boundary= md5("i".$cam.time());
$htmlpic="";
$mailTo = substr($mailTo,0,-1);

debug($L["ERROR_SEND_MAIL_INFO"]." From: ".$mailFromName.htmlentities(" <".$mailFrom."> ")." To: ".$mailTo,5);
( isset($alarms) && isset($_GET['alarm']) )?$alarm="=E2=9D=97".$L["CC.ALARM"]."=E2=9D=97":$alarm="";
$html = "From: ".$mailFromName." <".$mailFrom.">
To: ".$mailTo."
Subject: =?utf-8?Q? ".$alarm." ".utf8_encode($emailSubject)." ?= 
MIME-Version: 1.0
Content-Type: multipart/alternative;
 boundary=\"------------".$outer_boundary."\"

This is a multi-part message in MIME format.
--------------".$outer_boundary."
Content-Type: text/plain; charset=utf-8; format=flowed
Content-Transfer-Encoding: 8bit

".strip_tags($plugin_cfg["CAM_EMAIL_BODY".$cam])."
\n--\n".strip_tags($plugin_cfg["CAM_EMAIL_SIGNATURE".$cam])."

--------------".$outer_boundary."
Content-Type: multipart/related;
 boundary=\"------------".$inner_boundary."\"


--------------".$inner_boundary."
Content-Type: text/html; charset=utf-8
Content-Transfer-Encoding: 8bit

<html>
  <head>
    <meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\">
  </head>
  <body text=\"#000000\" bgcolor=\"#FFFFFF\">
  
    <font face=\"Verdana\">".$plugin_cfg["CAM_EMAIL_BODY".$cam]."<br>";
$htmlpicdata="";
for ($i = 1; $i <= $pics; $i++) 
{
	debug("Add picture $i of $pics.",7);
    if ( $i > 1 ) 
    {
	    debug("Wait $delay seconds before getting next picture.",7);
		sleep($delay);
		list($picture ,$resized_picture ) = main();
    }
  if ($plugin_cfg["CAM_EMAIL_INLINE_CB".$cam] == 1)
  {
    debug("Place image inline",7);
    $inline  =  'inline';
    $email_image_part =  "\n<img src=\"cid:".$plugin_cfg['EMAIL_FILENAME']."_".$datetime->format("Y-m-d_i\hh\mH\s")."_".$i."\" alt=\"".$plugin_cfg['EMAIL_FILENAME']."_".$datetime->format("Y-m-d_i\hh\mH\s")."_".$i.".jpg\" />\n<br>";
  }
  else
  {
    debug("Place image as attachment",7);
    $inline  =  'attachment';
    $email_image_part ="\n";
  }
	
     debug("Boundary for picture $i of $pics is: ".$outer_boundary,7);
	 $newwidth = $plugin_cfg['CAM_EMAIL_RESIZE'.$cam];
	 debug("Check if resize value is valid.",7);
	 if ($newwidth >= 240)
	 {
	   debug("Minimum width >= 240 : yes => ".$newwidth,7);
	   if ($newwidth > 1920)
	   {
	       debug("Maximum width > 1920, adapt width from ".$newwidth." to max. 1920",7);
	   	$newwidth=1920;
	   }
	   else
	   {
	   	 debug("Maximum width <= 1920 : yes => ".$newwidth,7);
	   }
	   debug("Resizing to: ".$newwidth,7);
	   $picture = resize_cam_image($picture,$newwidth);
	 }
	 else
	 {
	      debug("Mimimum width < 240, but ok, keep it: ".$newwidth,7);
	 }
	$htmlpic 	 .= $email_image_part;
	$htmlpicdata .= "--------------".$inner_boundary."
Content-Type: image/jpeg; name=\"".$plugin_cfg['EMAIL_FILENAME']."_".$datetime->format("Y-m-d_i\hh\mH\s")."_".$i.".jpg\"
Content-Transfer-Encoding: base64
Content-ID: <".$plugin_cfg['EMAIL_FILENAME']."_".$datetime->format("Y-m-d_i\hh\mH\s")."_".$i.">
Content-Disposition: ".$inline."; filename=\"".$plugin_cfg['EMAIL_FILENAME']."_".$datetime->format("Y-m-d_i\hh\mH\s")."_".$i.".jpg\"

".chunk_split(base64_encode($picture))."\n";

}

$html .= $htmlpic;
		
$html .=" \n--<br>".$plugin_cfg["CAM_EMAIL_SIGNATURE".$cam]." </font></body></html>\n\n";

$html .= $htmlpicdata;
$html .= "--------------".$inner_boundary."--\n\n";
$html .= "--------------".$outer_boundary."--\n\n";

  debug("eMail-Body will be:",7);
  debug($html,7,1);
  $tmpfname = tempnam("/tmp", "cam_connect_");
  debug("Write eMail tempfile $tmpfname",7);
  $handle = fopen($tmpfname, "w") or debug($L["ERRORS.ERROR_OPEN_TEMPFILE_EMAIL"]." ".$tmpfname,3);
  fwrite($handle, $html) or debug($L["ERRORS.ERROR_WRITE_TEMPFILE_EMAIL"]." ".$tmpfname,3);
  fclose($handle);
  debug("Sendng eMail from tempfile $tmpfname",7);
  exec("/usr/sbin/sendmail -t 2>&1 < $tmpfname ",$last_line,$retval);
  debug("Delete tempfile $tmpfname",7);
  unlink($tmpfname) or debug($L["ERRORS.ERROR_DELETE_TEMPFILE_EMAIL"]." ".$tmpfname,3);
  debug("Sendmail Ausgabe: ".join("\n",$last_line),7);
  if($retval)
  {
    debug($L["ERRORS.ERROR_EMAIL_SEND_FAIL"]."Code: ".$retval,3);
    return "Plugin-Error: [".$last_line."]";
  }
  else
  {
    debug("Send eMail ok.",7);
    return "Mail ok.";
  }
}

function resize_cam_image ($picture,$newwidth=720)
{
	debug("Function resize_cam_image",7);
    list($width, $height) = getimagesizefromstring($picture);
    $newheight = $height / ($width/$newwidth);
    $thumb = imagecreatetruecolor($newwidth, $newheight);
    $source = imagecreatefromstring($picture);
    imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
    ob_start();
    ImageJPEG($thumb);
    $picture = ob_get_contents();
    ob_end_clean();
    ImageDestroy($thumb);
    ImageDestroy($source);
    return $picture;
}
