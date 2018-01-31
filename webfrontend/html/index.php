<?php
#####################################################################################################
# Loxberry Plugin to change the HTTP-Authentication of a Trendnet TV-IP310PI Surveillance IP-Cam
# from Digest to none to be used in the Loxone Door-Control-Object.
# Version: 03.05.2017 22:14:23
#####################################################################################################

// Error Reporting off
error_reporting(~E_ALL & ~E_STRICT);     // Alle Fehler reporten (Außer E_STRICT)
ini_set("display_errors", false);        // Fehler nicht direkt via PHP ausgeben
require_once "loxberry_system.php";
require_once "loxberry_log.php";
$L = LBSystem::readlanguage("language.ini");
ini_set("log_errors", 1);
ini_set("error_log", LBPLOGDIR."/cam_connect.log");

function debug($message = "", $loglevel)
{
	global $plugin_cfg;
	if ( intval($plugin_cfg["LOGLEVEL"]) >= intval($loglevel) )
	{
		switch ($loglevel)
		{
		    case 2:
		        Error_Log( "<CRITICAL> PHP: ".$message );
		        break;
		    case 3:
		        Error_Log( "<ERROR> PHP: ".$message );
		        break;
		    case 4:
		        Error_Log( "<WARNING> PHP: ".$message );
		        break;
		    case 7:
		    default:
		        Error_Log( " PHP: ".$message );
		        break;
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

$camera_models_file = LBPCONFIGDIR."/camera_models.dat";
debug("Read cameras from ".$camera_models_file,7);
$cam_models_handle    = fopen($camera_models_file, "r");
if ($cam_models_handle)
{
  (isset($_GET['cam-model']))?$cam_model=intval($_GET['cam-model']):$cam_model=1;
  while (!feof($cam_models_handle))
  {
    $line_of_text = fgets($cam_models_handle);
	debug("Read cameras line: ".$line_of_text,7);
    $line_of_text = preg_replace('/\r?\n|\r/','', $line_of_text);
    $config_line = explode('|', $line_of_text);
    if (count($config_line) == 5)
    {
      if (intval($config_line[0]) == $cam_model)
      {
        $plugin_cfg['httpauth'] = $config_line[4];
        $plugin_cfg['imagepath'] = $config_line[3];
        $plugin_cfg['model']     = $config_line[2];
        break;
      }
    }
  }
  fclose($cam_models_handle);
}
else
{
  debug("No camera file handle found.",7);
  error_image($L["ERRORS.ERROR_READING_CAMS"]);
}

debug("Check for GET parameter 'email' (can override EMAIL_USED in config)",7);
if (isset($_GET['email']))
{
  debug("Found. Override EMAIL_USED in config (".$plugin_cfg['EMAIL_USED'].") with integer of ".$_GET['email']." => ".intval($_GET['email']),7);
  $plugin_cfg['EMAIL_USED'] = intval($_GET['email']);
}
else
{
  debug("Not found. Use EMAIL_USED from config: ".$plugin_cfg['EMAIL_USED'],7);
}

debug("Check for GET parameter 'stream' (must override EMAIL_USED in config in this case)",7);
if (isset($_GET['stream']))
{
	$plugin_cfg['EMAIL_USED']=0;
	debug("Found. Override EMAIL_USED with: ".$plugin_cfg['EMAIL_USED'],7);
}
else
{
	debug("Not found. Keep EMAIL_USED: ".$plugin_cfg['EMAIL_USED'],7);
}

debug("Read LoxBerry global eMail config",7);
if (($plugin_cfg['EMAIL_USED'] == 1))
{
  $mail_config_file   = LBSCONFIGDIR."/mail.cfg";
  debug("Parameter EMAIL_USED is set, read eMail config from ".$mail_config_file,7);
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
  debug("Parameter EMAIL_USED is not set, ignoring eMail config file.",7);
}

debug("Set camera name if provided in URL via &cam-name=xxxx",7);
if (isset($_GET['cam-name']))
{
	$cam_name="[".addslashes($_GET['cam-name'])."] ";
	debug("Parameter 'cam_name' (for eMail) is set, take value into account: ".$cam_name,7);
}
else
{
	debug("Parameter 'cam_name' (for eMail) is not set, ignoring it.",7);
	$cam_name="";
}

debug("Read IP-CAM connection details from URL",7);
$plugin_cfg['url']  = "http://".trim(addslashes($_GET['kamera'].":".$_GET['port'].$plugin_cfg['imagepath']));
$plugin_cfg['user'] = addslashes($_GET['user']);
$plugin_cfg['pass'] = addslashes($_GET['pass']);

function get_image()
{
	debug("Function get_image called",7);
	global $plugin_cfg, $curl, $lbpplugindir;
	debug("Wait a half second to be sure the image is available.",7);
	sleep(.5);
    $curl = curl_init() or debug("Problem to init curl.",3);
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
	      $plugin_cfg['url']  = "http://".trim(addslashes($_GET['kamera'].":".$_GET['port'].substr($html_zeile,$anfang,$ende)));
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
	  $picture = curl_exec($curl) or debug("Problem to esec curl on: ".$plugin_cfg['url'],3) or debug("Cannot execute the curl command on: ".$plugin_cfg['url'],7);
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
  error_image($L["ERRORS.ERROR_ACCESS_CAM"]);
}
else
{
  debug("Image seems to be ok, continue",7);
  if ($plugin_cfg["WATERMARK"] == 1)
  {
    debug("WATERMARK = 1 so I have to put the overlay LoxBerry on it",7);
	$watermarkfile = LBPHTMLDIR."/watermark.png";
    debug("The overlay file will be: ".$watermarkfile,7);
    $watermarked_picture = imagecreatefromstring($picture) or debug("Function imagecreatefromstring failed.",3);
    list($ix, $iy, $type, $attr) = getimagesizefromstring($picture);
    if ($type <> 2) error_image($L["ERRORS.ERROR02"]);
    debug("Reading watermark.png into variable and applying overlay to camera image.",7);
    $stamp = imagecreatefrompng($watermarkfile) or debug("Function imagecreatefrompng failed.",3);
    $sx    = imagesx($stamp);
    $sy    = imagesy($stamp);
    debug("Target image width/height: ".$sx."/".$sy,7);
    $logo_width  = 120;
    $logo_height = 86;
    debug("Logo width/height: ".$logo_width."/".$logo_height,7);
    $margin_right  = $ix - $logo_width - 20;
    $margin_bottom = 20;
    debug("Margin right/bottom: ".$margin_right."/".$margin_bottom,7);
    ImageCopyResized($watermarked_picture, $stamp, $ix - $logo_width - $margin_right, $iy - $logo_height - $margin_bottom, 0, 0, $logo_width, $logo_height, $sx, $sy);
    ImageDestroy($stamp);
    ob_start();
    ImageJPEG($watermarked_picture);
    $picture = ob_get_contents();
	if ( $plugin_cfg["LOGLEVEL"] == 7 )
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
	debug("WATERMARK = 0 so I don't have to put the overlay LoxBerry on it",7);
  }

  debug("Check if resize image parameter '&image_resize=xxx' from URL was provided otherwise use IMAGE_RESIZE read from config file",7);
  if ( isset($_GET["image_resize"]) && $_GET["image_resize"] <> 0 )
  {
    debug("Yes, found URL parameter 'image_resize' with: ".$_GET["image_resize"],7);
    if ( (intval($_GET["image_resize"]) >= 200) &&  ( intval($_GET["image_resize"]) <= 1920 ) )
    {
	  debug("URL parameter 'image_resize' is in valid range because ".$_GET["image_resize"]." is >= 200 and <= 1920.",7);
      $newwidth = intval($_GET["image_resize"]);
	  debug("Resizing picture to ".$newwidth,7);
      $resized_picture = resize_cam_image($picture,$newwidth);
    }
    else
    {
	  debug("URL parameter 'image_resize' is not within a valid range because ".$_GET["image_resize"]." is not >= 200 and <= 1920.",7);
	  debug("No resizing here, keep picture as it is.",7);
      $resized_picture = $picture;
    }
  }
  elseif ( ( isset( $plugin_cfg['IMAGE_RESIZE'] ) && $plugin_cfg['IMAGE_RESIZE'] <> 0 ) && !isset($_GET["image_resize"]) )
  {
  	debug("Resize image to parameter IMAGE_RESIZE read from config file: ".$plugin_cfg['IMAGE_RESIZE'],7);
    if ( (intval($plugin_cfg['IMAGE_RESIZE']) >= 200) &&  ( intval($plugin_cfg['IMAGE_RESIZE']) <= 1920 ) )
    {
	  debug("CFG parameter 'IMAGE_RESIZE' is in valid range because ".intval($plugin_cfg['IMAGE_RESIZE'])." is >= 200 and <= 1920.",7);
      $newwidth = intval($plugin_cfg['IMAGE_RESIZE']);
      debug("Resizing picture to ".$newwidth,7);
	  $resized_picture = resize_cam_image($picture,$newwidth);
    }
    else
    {
	  debug("CFG parameter 'IMAGE_RESIZE' is not within a valid range because ".intval($plugin_cfg['IMAGE_RESIZE'])." is not >= 200 and <= 1920.",7);
	  debug("No resizing here, keep picture as it is.",7);
      $resized_picture = $picture;
    }
  }
  else
  {
    debug("No resizing wanted. Neither in config nor via URL request. Keep picture size.",7);
    $resized_picture = $picture;
  }

    debug("Check if stream parameter '&stream' from URL was provided",7);
	if (isset($_GET['stream']))
	{
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
		$maxloops=180;
        debug("Start looping stream now, max $maxloops times",7);
		while ($maxloops > 0)
		{
			$maxloops = $maxloops - 1;
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
		}
        debug("Exit normally after stream mode reached max loop count: ".$maxloops,7);
		exit;
	}
	else
	{
      debug("No, streaming mode is not wanted, so continue normally.",7);
	  if ( ($_GET["image_resize"] == 0 && isset($_GET["image_resize"])) || ( !isset($_GET["image_resize"]) && $plugin_cfg['IMAGE_RESIZE'] == 0 ) )
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
	    header ('Content-Disposition: inline; filename="'.$plugin_cfg['EMAIL_FILENAME']."_".$datetime->format("Y-m-d_i\hh\mH\s").'.jpg');
        debug("Picture wanted, display it now.",7);
		if ( $plugin_cfg["LOGLEVEL"] == 7 )
		{
			$finfo 	= 	new finfo(FILEINFO_MIME);
			$type 	= 	explode(';',$finfo->buffer($resized_picture),2)[0];
			debug("Type: ".$type." Size: ".strlen($resized_picture)." Bytes",7);
			if (!isset($_GET['stream']))
			{
			  	 debug("<img src='data:".$type.";base64,".base64_encode($resized_picture)."'></>",7);
			}
			else
			{
			  	debug("Picture:\n[not shown in stream mode]",7);
			}
		}
	    echo $resized_picture;
	  }
	}

	debug("eMail Part reached",7);
    debug("Check for parameter &email_resize=xxx in URL or EMAIL_RESIZE in config ",7);
	if ( isset($_GET["email_resize"]) )
	{
		debug("Resize image parameter &email_resize=xxx found in URL: ".$_GET["email_resize"],7);
		if ( (intval($_GET["email_resize"]) >= 200) &&  ( intval($_GET["email_resize"]) <= 1920 ) )
		{
		  $newwidth = intval($_GET["email_resize"]);
		  debug("URL parameter 'email_resize' is in valid range because ".$newwidth." is >= 200 and <= 1920.",7);
		  debug("Resizing picture to ".$newwidth,7);
		  $resized_picture = resize_cam_image($picture,$newwidth);
		}
		else
		{
		  debug("URL parameter 'email_resize' is not within a valid range because ".$_GET["email_resize"]." is not >= 200 and <= 1920.",7);
		  debug("No resizing here, keep picture as it is.",7);
		  $resized_picture = $picture;
		}
	}
	elseif ( isset( $plugin_cfg['EMAIL_RESIZE'] ) )
	{
		debug("Resize image parameter EMAIL_RESIZE found in configuration: ".$plugin_cfg['EMAIL_RESIZE'],7);
		if ( (intval($plugin_cfg['EMAIL_RESIZE']) >= 200) &&  ( intval($plugin_cfg['EMAIL_RESIZE']) <= 1920 ) )
		{
		  $newwidth = intval($plugin_cfg['EMAIL_RESIZE']);
		  debug("CFG parameter 'EMAIL_RESIZE' is in valid range because ".$newwidth." is >= 200 and <= 1920.",7);
		  debug("Resizing picture to ".$newwidth,7);
		  $resized_picture = resize_cam_image($picture,$newwidth);
		}
		else
		{
		  debug("CFG parameter 'EMAIL_RESIZE' is not within a valid range because ".$plugin_cfg['EMAIL_RESIZE']." is not >= 200 and <= 1920.",7);
		  debug("No resizing here, keep picture as it is.",7);
		  $resized_picture = $picture;
		}
	}
	else
	{
		debug("No resizing of eMail picture requested in URL or configured in plugin settings.",7);
		$resized_picture = $picture;
	}

	debug("Check if sending eMail is enabled",7);
	if (($plugin_cfg['EMAIL_USED'] == 1) && ($mail_cfg['SMTP']['ISCONFIGURED'] == 1) && !isset($_GET["no_email"]))
	{
		debug("Sending email because 'EMAIL_USED' is set in config and SMTP server is configured and 'no_email' URL parameter is not set.",7);
		if (isset($plugin_cfg['EMAIL_USED'])) debug("CFG parameter 'EMAIL_USED' is: ".$plugin_cfg['EMAIL_USED'],7);
		if (isset($mail_cfg['SMTP']['ISCONFIGURED'])) debug("CFG parameter 'SMTP.ISCONFIGURED' is: ".$mail_cfg['SMTP']['ISCONFIGURED'],7);
		if (isset($_GET["no_email"])) debug("URL parameter 'no_email' is: ".$_GET["no_email"],7);
		$sent = send_mail_pic($resized_picture);
	}
	else
	{
		debug("Do not send email because 'EMAIL_USED' is not set in config or SMTP server is not configured or 'no_email' URL parameter is set.",7);
		if (isset($plugin_cfg['EMAIL_USED'])) debug("CFG parameter 'EMAIL_USED' is: ".$plugin_cfg['EMAIL_USED'],7);
		if (isset($mail_cfg['SMTP']['ISCONFIGURED'])) debug("CFG parameter 'SMTP.ISCONFIGURED' is: ".$mail_cfg['SMTP']['ISCONFIGURED'],7);
		if (isset($_GET["no_email"])) debug("URL parameter 'no_email' is: ".$_GET["no_email"],7);
	}

#	if ( ($_GET["image_resize"] == 0 && isset($_GET["image_resize"])) || ( !isset($_GET["image_resize"]) && $plugin_cfg['IMAGE_RESIZE'] == 0 ))
#	{
#		debug("Just text mode for email.",7);
#		echo $sent;
#	}
#	else
#	{
#		debug("Not just text mode. But we're done",7);
#		echo "Don't know what to do here.";
#	}
}
debug("Exit plugin normally now.",7);
exit;

function error_image ($error_msg)
{
  global $L;
  (strlen($error_msg) > 0)?$error_msg=$error_msg:$error_msg=$L["ERRORS.ERROR_UNKNOWN"];
  debug($error_msg,3);
  // Display an Error-Picture
  header ("Content-type: image/jpeg");
  header ("Cache-Control: no-cache, no-store, must-revalidate");
  header ("Pragma: no-cache");
  header ("Expires: 0");
  $error_image      = @ImageCreate (320, 240) or die ($error_msg);
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
  global $datetime, $plugin_cfg, $cam_name, $mail_cfg, $L;
  if ( isset($plugin_cfg["EMAIL_FROM_NAME"]) )
  {
      $mailFromName   = '"'.utf8_decode($plugin_cfg["EMAIL_FROM_NAME"]). '" <'.$mail_cfg['SMTP']['EMAIL'].'>';  // Sender name
      debug("Config value EMAIL_FROM_NAME found - using it: ".$mailFromName,7);
  }
  else
  {
      $mailFromName   = '"LoxBerry" <'.$mail_cfg['SMTP']['EMAIL'].'>';  // Default Sender name
      debug("Config value EMAIL_FROM_NAME not found - using default: ".$mailFromName,4);
  }
  $at_least_one_valid_email=0;
  if ( $plugin_cfg['EMAIL_TO'] == 1 )
  {
    debug("Config value EMAIL_TO found with value: 1 -> start recipients manipulation.",7);
    if ( isset($_GET["email_to"]) )
    {
      debug("URL parameter 'email_to' found. Using it: ".$_GET["email_to"],7);
      debug("Removing original eMail recipient",7);
      $mailTo = ""; 
      foreach (explode(";",rawurldecode($_GET["email_to"]) ) as $recipients_data)
      {
        $recipients_data = str_ireplace("(at)","@",$recipients_data);
        $recipients_data = trim(str_ireplace("\"","",$recipients_data));
        debug("Recipient(s): ".$recipients_data,7);
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
    }
    else
    {
      debug("URL parameter 'email_to' not found. Abort recipients manipulation.",7);
    }
  }
else
	{
	 debug("Config value EMAIL_TO has not value 1. No recipients manipulation.",7);
	}
  if ( $at_least_one_valid_email == 0 )
  {
  	debug("URL parameter 'email_to' had no valid recipients. Using default from config file.",7);
   	debug("Adding recipients from config file: ".$plugin_cfg['EMAIL_RECIPIENTS'],7);

      foreach (explode(";",$plugin_cfg['EMAIL_RECIPIENTS']) as $recipients_data)
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
   }
	else
	{
    	debug("Using recipient taken from URL before.",7);
	}
  $emailSubject = utf8_decode($cam_name.$plugin_cfg["EMAIL_SUBJECT1"]." ".$datetime->format($plugin_cfg["EMAIL_DATE_FORMAT"])." ".$plugin_cfg["EMAIL_SUBJECT2"]." ".$datetime->format($plugin_cfg["EMAIL_TIME_FORMAT"])." ".$plugin_cfg["EMAIL_SUBJECT3"]);
  debug("Building eMail subject: ".$emailSubject,7);
  if ($plugin_cfg["EMAIL_INLINE"] == 1)
  {
    debug("Place image inline",7);
    $inline  =  'inline';
    $email_image_part =  '<img src="cid:'.$plugin_cfg['EMAIL_FILENAME']."_".$datetime->format("Y-m-d_i\hh\mH\s").'" alt="'.$plugin_cfg['EMAIL_FILENAME']."_".$datetime->format("Y-m-d_i\hh\mH\s").'.jpg'.'" />';
  }
  else
  {
    debug("Place image as attachment",7);
    $inline  =  'attachment';
    $email_image_part ="";
  }

  debug("Resize image to parameter &email_resize=xxx from URL if provided",7);
  if ( isset($_GET["email_resize"]) )
  {
    debug("Found. Try to override EMAIL_RESIZE from config with 'email_resize' from URL: ".$plugin_cfg['EMAIL_RESIZE']."=>".$_GET["email_resize"],7);
    if ( (intval($_GET["email_resize"]) >= 200) && (intval($_GET["email_resize"]) <= 1920) )
    {
      $plugin_cfg['EMAIL_RESIZE'] = intval($_GET["email_resize"]);
      debug("Override EMAIL_RESIZE ok: ".$plugin_cfg['EMAIL_RESIZE'],7);
    }
    else
    {
      debug("Override EMAIL_RESIZE failed, keep value from config: ".$plugin_cfg['EMAIL_RESIZE'],7);
    }
  }
  else
  {
  	debug("Not found. No resize override by URL",7);
  }

  $newwidth = $plugin_cfg['EMAIL_RESIZE'];
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

$mailTo = substr($mailTo,0,-1);
$html = "From: ".$mailFromName."
To: ".$mailTo."
Subject: ".$emailSubject." 
MIME-Version: 1.0
Content-Type: multipart/alternative;
	boundary=\"b1_7eb9272345eb191ab133eafc6fca47e1\"
Content-Transfer-Encoding: 8bit

--b1_7eb9272345eb191ab133eafc6fca47e1
Content-Type: text/plain; charset=us-ascii

".utf8_decode($plugin_cfg["EMAIL_BODY"])."


--b1_7eb9272345eb191ab133eafc6fca47e1
Content-Type: multipart/related;
	boundary=\"b2_7eb9272345eb191ab133eafc6fca47e1\"

--b2_7eb9272345eb191ab133eafc6fca47e1
Content-Type: text/html; charset=iso-8859-1
Content-Transfer-Encoding: 8bit

<html><body>".utf8_decode($plugin_cfg["EMAIL_BODY"])."<br/>".$email_image_part."
<br>".utf8_decode($plugin_cfg["EMAIL_SIGNATURE"])." 
</body></html>


--b2_7eb9272345eb191ab133eafc6fca47e1
Content-Type: image/jpeg; name=\"".$plugin_cfg['EMAIL_FILENAME']."_".$datetime->format("Y-m-d_i\hh\mH\s").".jpg\"
Content-Transfer-Encoding: base64
Content-ID: <".$plugin_cfg['EMAIL_FILENAME']."_".$datetime->format("Y-m-d_i\hh\mH\s").">
Content-Disposition: ".$inline."; filename=".$plugin_cfg['EMAIL_FILENAME']."_".$datetime->format("Y-m-d_i\hh\mH\s").".jpg

".base64_encode($picture)."

--b2_7eb9272345eb191ab133eafc6fca47e1--


--b1_7eb9272345eb191ab133eafc6fca47e1--\n\n";

  debug("eMail-Body will be:\n".$html,7);
  $last_line = system("echo '$html' | /usr/sbin/sendmail -t 2>&1 ",$retval);
  debug("Sendmail Ausgabe: ".$last_line,7);
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
