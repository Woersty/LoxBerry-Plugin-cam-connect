<?php
#####################################################################################################
# Loxberry Plugin to change the HTTP-Authentication of a Trendnet TV-IP310PI Surveillance IP-Cam
# from Digest to none to be used in the Loxone Door-Control-Object.
# Version: 04.10.2016 19:47:45
#####################################################################################################

// Error Reporting off
error_reporting(E_ALL & ~E_STRICT);     // Alle Fehler reporten (Außer E_STRICT)
ini_set("display_errors", false);       // Fehler nicht direkt via PHP ausgeben

// Read LoxBerry Basic configuration file to get the used language
$config_file          = dirname(__FILE__)."/../../../../config/system/general.cfg";
$config_file_handle   = fopen($config_file, "r");
if ($config_file_handle)
{
  while (!feof($config_file_handle))
  {
    $line_of_text = fgets($config_file_handle);
    if (strlen($line_of_text) > 3)
    {
      $config_line = explode('=', $line_of_text);
      if ($config_line[0] == "LANG")
      {
        $cfg[$config_line[0]]=preg_replace('/\r?\n|\r/','', $config_line[1]);
        $lang = $cfg["LANG"];
        break;
      }
    }
  }
  fclose($config_file_handle);
}
else
{
  error_image(array('FATAL1: Error reading general.cfg'),0);
}

$plugin_config_file = dirname(__FILE__)."/../../../../config/plugins/".basename(dirname(__FILE__))."/cam-connect.cfg";
$camera_models_file = dirname(__FILE__)."/../../../../config/plugins/".basename(dirname(__FILE__))."/camera_models.dat";
$plugin_phrase_file = dirname(__FILE__)."/../../../../templates/plugins/".basename(dirname(__FILE__))."/$lang/language.dat";
$plugin_cfg_handle    = fopen($plugin_config_file, "r");
$cam_models_handle    = fopen($camera_models_file, "r");
$plugin_phrase_handle = fopen($plugin_phrase_file, "r");

// Read language file to get the strings for the right language
if ($plugin_phrase_handle)
{
  while (!feof($plugin_phrase_handle))
  {
    $line_of_text = fgets($plugin_phrase_handle);
    if (strlen($line_of_text) > 3)
    {
      $config_line = explode('=', $line_of_text);
      if ( isset($config_line[1]))
      {
        $phrases[$config_line[0]]=preg_replace('/\r?\n|\r/','', $config_line[1]);
      }
    }
  }
  fclose($plugin_phrase_handle);
}
else
{
  error_image(array('FATAL2: Error reading language.dat'),0);
}

// Read config into $plugin_cfg array
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
        $plugin_cfg[$config_line[0]]=preg_replace('/\r?\n|\r/','', $config_line[1]);
      }
    }
  }
  fclose($plugin_cfg_handle);
}
else
{
  error_image($phrases,"ERROR03");
}

// Read camera-config
if ($cam_models_handle)
{
  (isset($_GET['cam-model']))?$cam_model=intval($_GET['cam-model']):$cam_model=1;
  while (!feof($cam_models_handle))
  {
    $line_of_text = fgets($cam_models_handle);
    $config_line = explode('|', $line_of_text);
    if (count($config_line) == 4)
    {
      if (intval($config_line[0]) == $cam_model)
      {
        $plugin_cfg['imagepath'] = $config_line[3];
        break;
      }
    }
  }
  fclose($cam_models_handle);
}
else
{
  // cannot read cam models
  error_image($phrases,"ERROR04");
}
// Override $plugin_cfg['EMAIL_USED'] in config with URL if exists
if (isset($_GET['email']))
{
  $plugin_cfg['EMAIL_USED'] = intval($_GET['email']);
}

// Read LoxBerry Mail config
$mail_config_file   = dirname(__FILE__)."/../../../../config/system/mail.cfg";
if (($plugin_cfg['EMAIL_USED'] == 1))
{
  $mail_cfg    = parse_ini_file($mail_config_file,true);
  if ( !isset($mail_cfg) )
  {
     error_image($phrases,"ERROR05");
  }
  else
  {
    if ( $mail_cfg['SMTP']['ISCONFIGURED'] == "0" )
    {
     error_image($phrases,"ERROR06");
    }
  }
}
// Set camera name if provided in URL via &cam-name=xxxx
(isset($_GET['cam-name']))?$cam_name="[".addslashes($_GET['cam-name'])."] ":$cam_name="";

// Wait a half second to be sure the image is available.
sleep(.5);

// Read IP-CAM connection details from URL
$plugin_cfg['url']  = "http://".trim(addslashes($_GET['kamera'].":".$_GET['port'].$plugin_cfg['imagepath']));
$plugin_cfg['user'] = addslashes($_GET['user']);
$plugin_cfg['pass'] = addslashes($_GET['pass']);

// Init and config cURL
$curl = curl_init();
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
curl_setopt($curl, CURLOPT_USERPWD, $plugin_cfg['user'].":".$plugin_cfg['pass']);
curl_setopt($curl, CURLOPT_URL, $plugin_cfg['url']);

// Read picture from IP-Cam and close connection to Cam
$picture = curl_exec($curl);
curl_close($curl);


// If the result has less than 500 byte, it's no picture.
if(mb_strlen($picture) < 500)
{
  // Image too small, raise error 01
  error_image($phrases,"ERROR01");
}
else
{
  // Seems to be ok - Display the picture
  header('Content-type: image/jpeg');
  header('Content-Disposition: inline; filename="snapshot.jpeg"');
  if ($plugin_cfg["WATERMARK"] == 1)
  {
    // Create Cam Image Object
    $watermarked_picture = imagecreatefromstring($picture);
    list($ix, $iy, $type, $attr) = getimagesizefromstring($picture);
    if ($type <> 2) error_image($phrases,"ERROR02");

    // Create Watermark Image
    $stamp = imagecreatefrompng(dirname(__FILE__)."/watermark.png");
    $sx    = imagesx($stamp);
    $sy    = imagesy($stamp);

    // Wanted Logo Size
    $logo_width  = 120;
    $logo_height = 86;

    // Borders for Watermark
    $margin_right  = $ix - $logo_width - 20;
    $margin_bottom = 20;

    // Mix the images together
    ImageCopyResized($watermarked_picture, $stamp, $ix - $logo_width - $margin_right, $iy - $logo_height - $margin_bottom, 0, 0, $logo_width, $logo_height, $sx, $sy);
    ImageDestroy($stamp);
    ob_start();
    ImageJPEG($watermarked_picture);
    $picture = ob_get_contents();
    ob_end_clean();
    ImageDestroy($watermarked_picture);
  }

  // Resize image to parameter &image_resize=xxx from URL if provided - must be >= 240 or <= 1920
  if ( isset($_GET["image_resize"]) )
	{
		// Resize
		if ( (intval($_GET["image_resize"]) >= 200) &&  ( intval($_GET["image_resize"]) <= 1920 ) )
		{
			$newwidth = intval($_GET["image_resize"]);
			$resized_picture = resize_cam_image($picture,$newwidth);
		}
		else
		{
			// Invalid, no resize
			$resized_picture = $picture;
		}
	}
	// Resize image to parameter IMAGE_RESIZE read from cam-connect.cfg - must be >= 240 or <= 1920
	elseif ( isset( $plugin_cfg['IMAGE_RESIZE'] ) )
	{
		// Resize as configured in cam-connect.cfg
		if ( (intval($plugin_cfg['IMAGE_RESIZE']) >= 200) &&  ( intval($plugin_cfg['IMAGE_RESIZE']) <= 1920 ) )
		{
			$newwidth = intval($plugin_cfg['IMAGE_RESIZE']);
			$resized_picture = resize_cam_image($picture,$newwidth);
		}
		else
		{
			// Invalid, no resize
			$resized_picture = $picture;
		}
	}
	else
	{
		// No resize
		$resized_picture = $picture;
	}
  echo $resized_picture;

  // eMail Part
  // Resize image to parameter &email_resize=xxx from URL if provided - must be >= 240 or <= 1920
  if ( isset($_GET["email_resize"]) )
	{
		// Resize
		if ( (intval($_GET["email_resize"]) >= 200) &&  ( intval($_GET["email_resize"]) <= 1920 ) )
		{
			$newwidth = intval($_GET["email_resize"]);
			$resized_picture = resize_cam_image($picture,$newwidth);
		}
		else
		{
			// Invalid, no resize
			$resized_picture = $picture;
		}
	}
	// Resize image to parameter EMAIL_RESIZE read from cam-connect.cfg - must be >= 240 or <= 1920
	elseif ( isset( $plugin_cfg['EMAIL_RESIZE'] ) )
	{
		// Resize as configured in cam-connect.cfg
		if ( (intval($plugin_cfg['EMAIL_RESIZE']) >= 200) &&  ( intval($plugin_cfg['EMAIL_RESIZE']) <= 1920 ) )
		{
			$newwidth = intval($plugin_cfg['EMAIL_RESIZE']);
			$resized_picture = resize_cam_image($picture,$newwidth);
		}
		else
		{
			// Invalid, no resize
			$resized_picture = $picture;
		}
	}
	else
	{
		// No resize
		$resized_picture = $picture;
	}
	
  // If wanted, send eMail
  if (($plugin_cfg['EMAIL_USED'] == 1) && ($mail_cfg['SMTP']['ISCONFIGURED'] == 1) && !isset($_GET["no_email"])) send_mail_pic($resized_picture );
}
exit;

function error_image ($phrases,$error_code)
{
  // Read error string
  $error_msg   = $phrases[$error_code];
  (strlen($error_msg) > 0)?$error_msg=$error_msg:$error_msg="Plugin-Error: [$error_code]";

  // Display an Error-Picture
  header ("Content-type: image/jpeg");
  $error_image      = @ImageCreate (320, 240) or die ($error_msg);
  $background_color = ImageColorAllocate ($error_image, 255, 240, 240);
  $text_color       = ImageColorAllocate ($error_image, 255, 64, 64);
  ImageString ($error_image, 20, 10, 110, $error_msg, $text_color);
  ImageJPEG ($error_image);
  ImageDestroy($error_image);
  exit;
}

function send_mail_pic($picture)
{
  global $plugin_cfg, $cam_name, $mail_cfg, $phrases;
  require dirname($_SERVER["SCRIPT_FILENAME"]).'/PHPMailerAutoload.php';
  $mail = new PHPMailer;
  $mail->isSMTP();                                       // Set mailer to use SMTP
  $mail->isHTML(true);                                   // Set email format to HTML

  if ($mail_cfg['SMTP']['CRYPT'] == "1")
  {
    $mail->SMTPSecure = 'tls';                             // Enable encryption
  }
  $mail->Host       = $mail_cfg['SMTP']['SMTPSERVER'].":".$mail_cfg['SMTP']['PORT'];         // Specify server
  $mail->SMTPAuth   = $mail_cfg['SMTP']['AUTH'];         // Enable SMTP authentication
  $mail->Username   = $mail_cfg['SMTP']['SMTPUSER'];     // SMTP username
  $mail->Password   = $mail_cfg['SMTP']['SMTPPASS'];     // SMTP password
  $mail->From       = $mail_cfg['SMTP']['EMAIL'];        // Sender address
  if ( isset($plugin_cfg["EMAIL_FROM_NAME"]) )
  {
      $mail->FromName   = utf8_decode($plugin_cfg["EMAIL_FROM_NAME"]);    // Sender name
  }
  else
  {
      $mail->FromName   = "LoxBerry";
  }
  //$mail->SMTPDebug = 2;

  // Use recipients from URL if valid and configured
  $at_least_one_valid_email=0;
  if ( $plugin_cfg['EMAIL_TO'] == 1 )
  {
    if ( isset($_GET["email_to"]) )
    {
      foreach (explode(";",rawurldecode($_GET["email_to"]) ) as $recipients_data)
      {
        $recipients_data = str_ireplace("(at)","@",$recipients_data);
        if (filter_var($recipients_data, FILTER_VALIDATE_EMAIL))
        {
          $mail->addAddress($recipients_data);  // Add recipient
          $at_least_one_valid_email=1;
        }
      }
    }
  }

  // Read recipients
  if ( $at_least_one_valid_email == 0 )
  {
    foreach (explode(";",$plugin_cfg['EMAIL_RECIPIENTS']) as $recipients_data)
    {
      $mail->addAddress($recipients_data);  // Add recipient
    }
  }

  // Generate date and time
  $dt    = new DateTime;
  $datum = $dt->format($plugin_cfg['EMAIL_DATE_FORMAT']." ".$plugin_cfg['EMAIL_TIME_FORMAT']);

  // Generate subject
  $mail->Subject = utf8_decode($cam_name.$plugin_cfg["EMAIL_SUBJECT1"].$dt->format($plugin_cfg["EMAIL_DATE_FORMAT"]).$plugin_cfg["EMAIL_SUBJECT2"].$dt->format($plugin_cfg["EMAIL_TIME_FORMAT"]).$plugin_cfg["EMAIL_SUBJECT3"]);

  // Create Body
  $mail->AltBody = $plugin_cfg["EMAIL_BODY"];
  $html  = '<html><body>';                                              // Start of eMail
  $html .= utf8_decode($plugin_cfg["EMAIL_BODY"])."<br/>";

  // Place image inline or as attachment based on config
  if ($plugin_cfg["EMAIL_INLINE"] == 1)
  {
    $inline  =  'inline';
    $html   .=  '<img src="cid:'.$plugin_cfg['EMAIL_FILENAME'].'" alt="'.$plugin_cfg['EMAIL_FILENAME'].'" />';
  }
  else
  {
    $inline  =  'attachment';
  }

  // Resize image to parameter &email_resize=xxx from URL if provided - must be >= 240 or <= 1920
  if ( isset($_GET["email_resize"]) )
	{
		if ( (intval($_GET["email_resize"]) >= 200) && (intval($_GET["email_resize"]) <= 1920) )
		{
			$plugin_cfg['EMAIL_RESIZE'] = intval($_GET["email_resize"]);
		}
	}

  // Resize image if EMAIL_RESIZE is >= 240 - max is 1920
  $newwidth = $plugin_cfg['EMAIL_RESIZE'];
  if ($newwidth >= 240)
  {
    if ($newwidth > 1920) $newwidth=1920;
		$picture = resize_cam_image($picture,$newwidth);
  }

  // Insert image
  $mail->AddStringEmbeddedImage($picture, $plugin_cfg['EMAIL_FILENAME'], $plugin_cfg['EMAIL_FILENAME']."_".$dt->format("Y-m-d_i\hh\mH\s").'.jpg', 'base64', "image/jpeg", "$inline");
  $html .= "<br/>".utf8_decode($plugin_cfg["EMAIL_SIGNATURE"]);
  $html .= '</body></html>';                                            // End of eMail
  $mail->Body    = $html;
  if(!$mail->send())
  {
     return 'Mailer Error: ' . $mail->ErrorInfo;
  }
}

function resize_cam_image ($picture,$newwidth=720)
{
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
