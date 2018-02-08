<?php
#####################################################################################################
# Loxberry Plugin to change the HTTP-Authentication of a Trendnet TV-IP310PI Surveillance IP-Cam
# from Digest to none to be used in the Loxone Door-Control-Object.
# Version: 2018.02.03
#####################################################################################################

// Error Reporting off
error_reporting(~E_ALL & ~E_STRICT);     // Alle Fehler reporten (Außer E_STRICT)
ini_set("display_errors", false);        // Fehler nicht direkt via PHP ausgeben
require_once "loxberry_system.php";
require_once "loxberry_log.php";
$L = LBSystem::readlanguage("language.ini");
ini_set("log_errors", 1);
ini_set("error_log", LBPLOGDIR."/cam_connect.log");

function debug($message = "", $loglevel, $raw = 0)
{
	global $plugin_cfg;
	if ( intval($plugin_cfg["LOGLEVEL"]) >= intval($loglevel) )
	{
		($raw == 1)?$message="<br>".$message:$message=htmlentities($message);
		switch ($loglevel)
		{
		    case 2:
		        error_log( "<CRITICAL> PHP: ".$message );
		        break;
		    case 3:
		        error_log( "<ERROR> PHP: ".$message );
		        break;
		    case 4:
		        error_log( "<WARNING> PHP: ".$message );
		        break;
		    case 7:
		    default:
		        error_log( " PHP: ".$message );
		        break;
		}
		if ( $loglevel < 4 ) 
		{
			notify ( LBPPLUGINDIR, $L['CC.MY_NAME'], $message);
		}

	}
	return;
}

$datetime    = new DateTime;
debug("Entering plugin for ".$_SERVER['REMOTE_ADDR']." ".$_SERVER['REMOTE_HOST'],7);

debug("Check Logfile size: ".LBPLOGDIR."/cam_connect.log",7);
$logsize = filesize(LBPLOGDIR."/cam_connect.log");
if ( $logsize > 5242880 )
{
	debug("Logfile size is above 5 MB threshold: ".$logsize." Bytes",4);
    debug("Set Logfile notification: ".LBPPLUGINDIR." ".$L['CC.MY_NAME']." => ".$L['ERRORS.ERROR_LOGFILE_TOO_BIG'],4);
    notify ( LBPPLUGINDIR, $L['CC.MY_NAME'], $L['ERRORS.ERROR_LOGFILE_TOO_BIG']);
    system("echo '' > ".LBPLOGDIR."/cam_connect.log");
    debug($L["ERROR_LOGFILE_TOO_BIG"],4);
}
else
{
	debug("Logfile size is ok: ".$logsize,7);
}

$plugin_config_file = LBPCONFIGDIR."/cam-connect.cfg";
debug("Read plugin config from ".$plugin_config_file,7);
$plugin_cfg_handle    = fopen($plugin_config_file, "r");
if ($plugin_cfg_handle)
{
  while (!feof($plugin_cfg_handle))
  {
    $line_of_text = fgets($plugin_cfg_handle);
    debug("Read plugin config line: ".$line_of_text,7);
    if (strlen($line_of_text) > 3)
    {
      $config_line = explode('=', $line_of_text);
      if ($config_line[0])
      {
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
}

if (isset($_GET['cam']))
{
	$cam=intval($_GET['cam']);
    debug("Camera $cam requested.",7);
}
else
{
	error_image($L["ERRORS.ERROR_NO_CAM_PARAMETER"]);
}

$camera_models_file = LBPCONFIGDIR."/camera_models.dat";
debug("Read cameras from ".$camera_models_file,7);
$lines_of_text = file ( $camera_models_file );

  
  (isset($plugin_cfg['CAM_MODEL'.$cam]))?$cam_model=intval($plugin_cfg['CAM_MODEL'.$cam]):error_image($L["ERRORS.ERROR_READING_CAM_MODEL"]);
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
		debug("Found the camera we're looking for at line " . $line_num + 1 .": ".$line_of_text,7);
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
		debug("Invalid format in line $line_num: ".$line_of_text,4);
    }
  }
  if ( $plugin_cfg['model'] == "" || $plugin_cfg['httpauth'] == "" || $plugin_cfg['imagepath'] == "" )
  {      
  	error_image($L["ERRORS.ERROR_READING_CAMS"]);
  }

if (isset($_GET['email']))
{
  debug("Deprecated: GET parameter 'email' is not longer available.",4);
}
if (isset($_GET['cam-name']))
{
  debug("Deprecated: GET parameter 'cam-name' is not longer available.",4);
}
if (isset($_GET['image_resize']))
{
  debug("Deprecated: GET parameter 'image_resize' is not longer available.",4);
}

debug("Read LoxBerry global eMail config",7);
if ($plugin_cfg['CAM_EMAIL_USED_CB'.$cam] == 1) 
{
  $mail_config_file   = LBSCONFIGDIR."/mail.cfg";
  debug("Parameter CAM_EMAIL_USED_CB is set for camera $cam, read eMail config from ".$mail_config_file,7);
  $mail_cfg    = parse_ini_file($mail_config_file,true);
  if ( !isset($mail_cfg) )
  {
     debug("Can't read eMail config",7);
     error_image($L["ERRORS.ERROR_READING_EMAIL_CFG"]);
  }
  else
  {
    debug("eMail config found, assuming it's okay. Using Server: ".$mail_cfg['SMTP']['SMTPSERVER']." on port: ".$mail_cfg['SMTP']['PORT'],7);
    if ( $mail_cfg['SMTP']['ISCONFIGURED'] == "0" )
    {
     debug("eMail ist not configured: SMTP.ISCONFIGURED is 0",7);
     error_image($L["ERRORS.ERROR_INVALID_EMAIL_CFG"]);
    }
  }
}
else
{
  debug("Parameter CAM_EMAIL_USED_CB is not set for camera $cam, ignoring eMail config file.",7);
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
}
else
{
	exec ( "/bin/ping -c 1 ".$plugin_cfg['CAM_HOST_OR_IP'.$cam], $output , $return_var );
	if ( $return_var != 0 )
	{
		error_image($L["ERRORS.ERROR_PING_ERR_HOST_OR_IP"]." ".$plugin_cfg['CAM_HOST_OR_IP'.$cam]);
	}
}

$plugin_cfg['url']  = "http://".trim(addslashes($plugin_cfg['CAM_HOST_OR_IP'.$cam].":".$plugin_cfg['CAM_PORT'.$cam].$plugin_cfg['imagepath']));
debug("Using url: ".$plugin_cfg['url'],7);
$plugin_cfg['user'] = addslashes($plugin_cfg['CAM_USER'.$cam]);
debug("Using user: ".$plugin_cfg['user'],7);
$plugin_cfg['pass'] = addslashes($plugin_cfg['CAM_PASS'.$cam]);
debug("Using pass: ".$plugin_cfg['pass'],7);

function get_image()
{
	global $plugin_cfg, $curl, $lbpplugindir, $L, $cam;
	debug("Function get_image called for camera $cam with hostname/IP: ".$plugin_cfg['CAM_HOST_OR_IP'.$cam],7);
    $curl = curl_init() or error_image($L["ERRORS.ERROR_INIT_CURL"]);
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
	  $picture = curl_exec($curl) or debug("Problem to exec curl on: ".$plugin_cfg['url'],3);
	  if ($curl) { curl_close($curl); }

		if($picture === false)
		{
		  debug("Something went wrong. Try again to get the image from the camera...",4);
		  $picture = get_image();
		}
		else
		{
		  debug("Image successfully read from the camera.",7);
		  if ( $plugin_cfg["LOGLEVEL"] == 7 )
		  {
		  	$finfo 	= 	new finfo(FILEINFO_MIME);
		  	$type 	= 	explode(';',$finfo->buffer($picture),2)[0];
		  	debug("Type: ".$type." Size: ".strlen($picture)." Bytes",7);
			if (!isset($_GET['stream']))
			{
			  	 debug("<img src='data:".$type.";base64,".base64_encode($picture)."'></>",7);
			  	 debug("<img src='data:".$type.";base64,".base64_encode($picture)."'></>",7,1);
			}
			else
			{
			  	debug("Picture:\n[not shown in stream mode]",7);
			}
		  }
		}
	}
	return $picture;
}

function stream()
{
	#global $plugin_cfg, $curl, $lbpplugindir, $L, $cam;
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
				        debug("Send frame to ".$_SERVER['REMOTE_ADDR']." ".$_SERVER['REMOTE_HOST'],7);
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
				        debug("Send frame $maxloops to ".$_SERVER['REMOTE_ADDR']." ".$_SERVER['REMOTE_HOST'],7);
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
	global $plugin_cfg, $curl, $lbpplugindir, $L, $cam;
	debug("Function 'main' reached",7);
	debug("Call get_image() fist time",7);
	$picture = get_image();
	
	debug("Check, if the picture has less than 1000 bytes - then it's no picture.",7);
	if(mb_strlen($picture) < 1000)
	{
	  debug("Image too small. Just ".mb_strlen($picture)." Bytes. We got:",7);
	  foreach (explode("\n",$picture ) as $pic_line)
	  {
		  debug("=> $pic_line",7);
	  }
	  error_image($L["ERRORS.ERROR_IMAGE_TOO_SMALL"]);
	}
	else
	{
	  debug("Image seems to be ok, continue",7);
	  if ($plugin_cfg["CAM_WATERMARK_CB".$cam] == 1)
	  {
	    debug("Parameter CAM_WATERMARK_CB is set to 1 so I have to put the overlay LoxBerry on it",7);
		$watermarkfile = LBPHTMLDIR."/watermark.png";
	    debug("The overlay file will be: ".$watermarkfile,7);
	    $watermarked_picture = imagecreatefromstring($picture) or error_image($L["ERRORS.ERROR_CREATE_WATERMARK_UNDERLAY"]);
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
		if ( $plugin_cfg["LOGLEVEL"] == 7 )
		{
			if (!isset($_GET['stream']))
			{
			  	debug("Converted picture:\n<img src='data:image/jpeg;base64,".base64_encode($picture)."'></>",7);
			  	debug("Converted picture:\n<img src='data:image/jpeg;base64,".base64_encode($picture)."'></>",7,1);
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
    debug("No picture wanted, display a text instead:".$L['CC.IMAGE_RESIZE_JUST_TEXT_MSG'],7);
    echo $L['CC.IMAGE_RESIZE_JUST_TEXT_MSG'];
  }
  else
  {
    debug("Picture wanted, display it now.",7);
    header ('Content-type: image/jpeg');
    header ("Cache-Control: no-cache, no-store, must-revalidate");
    header ("Pragma: no-cache");
    header ("Expires: ".gmdate('D, d M Y H:i:s', time()-3600) . " GMT");
    header ('Content-Disposition: inline; filename="'.$plugin_cfg['EMAIL_FILENAME']."_".$datetime->format("Y-m-d_i\hh\mH\s").'.jpg"');
    debug("Picture wanted, display it now.",7);
	if ( $plugin_cfg["LOGLEVEL"] == 7 )
	{
		$finfo 	= 	new finfo(FILEINFO_MIME);
		$type 	= 	explode(';',$finfo->buffer($resized_picture),2)[0];
		debug("Type: ".$type." Size: ".strlen($resized_picture)." Bytes",7);
		if (!isset($_GET['stream']))
		{
		  	 debug("<img src='data:".$type.";base64,".base64_encode($resized_picture)."'></>",7);
		  	 debug("<img src='data:".$type.";base64,".base64_encode($resized_picture)."'></>",7,1);
		}
		else
		{
		  	debug("Picture:\n[not shown in stream mode]",7);
		}
	}
    echo $resized_picture;
  }

	debug("############# Normal mode done ######################",7);

	debug("############# eMail Part reached ####################",7);


	debug("Check if sending eMail is enabled",7);
	if ( $plugin_cfg['CAM_EMAIL_USED_CB'.$cam] == 1 && $mail_cfg['SMTP']['ISCONFIGURED'] == 1 && $plugin_cfg['CAM_NO_EMAIL_CB'.$cam] == 0 )
	{
		debug("Sending email because 'CAM_EMAIL_USED_CB' is set in config and SMTP server is configured and 'CAM_NO_EMAIL_CB' parameter is not set.",7);
		$sent = send_mail_pic($picture);
	}
	else
	{
		debug("Do not send email because 'CAM_EMAIL_USED_CB' is not set in config or SMTP server is not configured or 'CAM_NO_EMAIL_CB' parameter is set.",7);
		if (isset($plugin_cfg['CAM_EMAIL_USED_CB'.$cam])) debug("CFG parameter 'CAM_EMAIL_USED_CB' is: ".$plugin_cfg['CAM_EMAIL_USED_CB'.$cam],7);
		if (isset($mail_cfg['SMTP']['ISCONFIGURED'])) debug("CFG parameter 'SMTP.ISCONFIGURED' is: ".$mail_cfg['SMTP']['ISCONFIGURED'],7);
		if (isset($plugin_cfg['CAM_NO_EMAIL_CB'.$cam])) debug("CFG parameter 'CAM_NO_EMAIL_CB' is: ".$plugin_cfg['CAM_NO_EMAIL_CB'.$cam],7);
	}


debug("Exit plugin normally now.",7);
exit;

function error_image ($error_msg)
{
  global $L;
  if (strlen($error_msg) > 0)
  {
  	$error_msg=$error_msg;
  }
  else
  {
  	$error_msg=$L["ERRORS.ERROR_UNKNOWN"];
  }
  debug($error_msg,3);
  // Display an Error-Picture
  header ("Content-type: image/jpeg");
  header ("Cache-Control: no-cache, no-store, must-revalidate");
  header ("Pragma: no-cache");
  header ("Expires: 0");
  $error_image      = @ImageCreate (640, 480) or die ($error_msg);
  $background_color = ImageColorAllocate ($error_image, 0, 0, 0);
  $text_color       = ImageColorAllocate ($error_image, 255, 0, 0);
  ImageString ($error_image, 20, 10, 110, $error_msg, $text_color);
  ImageJPEG ($error_image);
	if ( $plugin_cfg["LOGLEVEL"] == 7 )
	{
		$finfo 	= 	new finfo(FILEINFO_MIME);
		$type 	= 	explode(';',$finfo->buffer(ImageJPEG ($error_image)),2)[0];
		debug("Type: ".$type." Size: ".strlen(ImageJPEG ($error_image))." Bytes",7);
		if (!isset($_GET['stream']))
		{
		  	 debug("<img src='data:".$type.";base64,".base64_encode(ImageJPEG ($error_image))."'></>",7);
		  	 debug("<img src='data:".$type.";base64,".base64_encode(ImageJPEG ($error_image))."'></>",7,1);
		}
		else
		{
		  	debug("Picture:\n[not shown in stream mode]",7);
		}
	}
  ImageDestroy($error_image);
  debug("Exit plugin in function error_image",7);
  exit;
}

function send_mail_pic($picture)
{
  debug("Function send_mail_pic reached",7);
  global $datetime, $plugin_cfg, $cam_name, $mail_cfg, $L, $cam;
  
  
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
	      debug("Config value CAM_EMAIL_FROM_NAME not found - using default: ".$mailFromName,4);
	  }
  }
  else
  {
      debug("LoxBerry eMail configuration problem. No Sender eMail address found.",3);
      return "Plugin-Error: [No Sender eMail address found]";
  }
   	debug("Adding recipients from config file: ".$plugin_cfg['CAM_RECIPIENTS'.$cam],7);

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
          debug("Invalid recipient(s) in: ".$recipients_data,3);
          debug("Abort recipients manipulation.",7);
    	}
      }
  $emailSubject = utf8_decode($cam_name.$plugin_cfg["CAM_EMAIL_SUBJECT1".$cam]." ".$datetime->format($plugin_cfg["CAM_EMAIL_DATE_FORMAT".$cam])." ".$plugin_cfg["CAM_EMAIL_SUBJECT2".$cam]." ".$datetime->format($plugin_cfg["CAM_EMAIL_TIME_FORMAT".$cam])." ".$plugin_cfg["CAM_EMAIL_SUBJECT3".$cam]);
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
	debug("Parameter CAM_EMAIL_MULTIPICS missing, use default: Send 1 picture.",4);
}

$boundary= md5(date());
$htmlpic="";
$mailTo = substr($mailTo,0,-1);
$html = "From: ".$mailFromName." <".$mailFrom.">
To: ".$mailTo."
Subject: ".utf8_encode($emailSubject)." 
MIME-Version: 1.0
Content-Type: multipart/alternative;
 boundary=\"------------".$boundary."\"

This is a multi-part message in MIME format.
--------------".$boundary."
Content-Type: text/plain; charset=utf-8; format=flowed
Content-Transfer-Encoding: 8bit

".$plugin_cfg["CAM_EMAIL_BODY".$cam]."
\n--\n".$plugin_cfg["CAM_EMAIL_SIGNATURE".$cam]."

--------------".$boundary."
Content-Type: multipart/related;
 boundary=\"------------8BA5038419D11A90C957A292\"


--------------8BA5038419D11A90C957A292
Content-Type: text/html; charset=utf-8
Content-Transfer-Encoding: 8bit

<html>
  <head>
    <meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\">
  </head>
  <body text=\"#000000\" bgcolor=\"#FFFFFF\">
  
    <font face=\"Verdana\">".$plugin_cfg["CAM_EMAIL_BODY".$cam]."<br>";

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
	
    debug("Booundary for picture $i of $pics is: ".$boundary,4);
	 $newwidth = $plugin_cfg['CAM_EMAIL_RESIZE'.$cam];
	 debug("Check if resize value is valid.",7);
	 if ($newwidth >= 240)
	 {
	   debug("Minimum width >= 240 : yes => ".$newwidth,7);
	   if ($newwidth > 1920)
	   {
	       debug("Maximum width > 1920, adapt width from ".$newwidth." to max. 1920",4);
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
	      debug("Mimimum width < 240, but ok, keep it: ".$newwidth,4);
	 }
	$htmlpic 	 .= $email_image_part;
	$htmlpicdata .= "--------------8BA5038419D11A90C957A292
Content-Type: image/jpeg; name=\"".$plugin_cfg['EMAIL_FILENAME']."_".$datetime->format("Y-m-d_i\hh\mH\s")."_".$i.".jpg\"
Content-Transfer-Encoding: base64
Content-ID: <".$plugin_cfg['EMAIL_FILENAME']."_".$datetime->format("Y-m-d_i\hh\mH\s")."_".$i.">
Content-Disposition: ".$inline."; filename=\"".$plugin_cfg['EMAIL_FILENAME']."_".$datetime->format("Y-m-d_i\hh\mH\s")."_".$i.".jpg\"

".base64_encode($picture)."\n";

}

$html .= $htmlpic;
		
$html .=" \n--<br>".$plugin_cfg["CAM_EMAIL_SIGNATURE".$cam]." </font></body></html>\n\n";

$html .= $htmlpicdata;
$html .= "--------------8BA5038419D11A90C957A292--\n\n";
$html .= "--------------".$boundary."--\n\n";

  debug("eMail-Body will be:",7);
  debug($html,7,1);
  $tmpfname = tempnam("/var/tmp", "cam_connect_");
  debug("Write eMail tempfile $tmpfname",7);
  $handle = fopen($tmpfname, "w") or debug($L["ERRORS.ERROR_OPEN_TEMPFILE_EMAIL"]." ".$tmpfname,3);
  fwrite($handle, $html) or debug($L["ERRORS.ERROR_WRITE_TEMPFILE_EMAIL"]." ".$tmpfname,3);
  fclose($handle);
  debug("Sendng eMail from tempfile $tmpfname",7);
  exec("/usr/sbin/sendmail -t 2>&1 < $tmpfname ",$last_line,$retval);
  debug("Delete tempfile $tmpfname",7) or debug($L["ERRORS.ERROR_DELETE_TEMPFILE_EMAIL"]." ".$tmpfname,3);
  unlink($tmpfname);
  debug("Sendmail Ausgabe: ".join("\n",$last_line),7);
  if($retval)
  {
    debug("Send eMail failed. Code: ".$retval,3);
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
